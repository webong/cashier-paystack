<?php
namespace Wisdomanthoni\Cashier;

use Carbon\Carbon;
use LogicException;
use Illuminate\Database\Eloquent\Model;
use Unicodeveloper\Paystack\Facades\Paystack;
class Subscription extends Model
{
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'trial_ends_at', 'ends_at',
        'created_at', 'updated_at',
    ];
    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->owner();
    }
    /**
     * Get the model related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        $class = Cashier::paystackModel();
        return $this->belongsTo($class, (new $class)->getForeignKey());
    }
    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid()
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }
    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active()
    {
        return is_null($this->ends_at) || $this->onGracePeriod();
    }
    /**
     * Determine if the subscription is recurring and not on trial.
     *
     * @return bool
     */
    public function recurring()
    {
        return ! $this->onTrial() && ! $this->cancelled();
    }
    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return ! is_null($this->ends_at);
    }
    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended()
    {
        return $this->cancelled() && ! $this->onGracePeriod();
    }
    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }
    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }
    
    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->trial_ends_at = null;
        return $this;
    }
    
    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel()
    {
        $subscription = $this->asPaystackSubscription();

        PaystackService::disableSubscription([
            'token' => $subscription['email_token'],
            'code'  => $subscription['subscription_code'],
        ]);
        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing period
        // period and make that the end of the grace period for this current user.
        if ($this->onTrial()) {
            $this->ends_at = $this->trial_ends_at;
        } else {
            $this->ends_at = Carbon::parse(
                $subscription['next_payment_date']
            );
        }
        $this->save();
        return $this;
    }
    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $this->cancel();
        $this->markAsCancelled();
        return $this;
    }
    /**
     * Mark the subscription as cancelled.
     *
     * @return void
     */
    public function markAsCancelled()
    {
        $this->fill(['ends_at' => Carbon::now()])->save();
    }
    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     * @throws \LogicException
     */
    public function resume()
    {
        $subscription = $this->asPaystackSubscription();
        // To resume the subscription we need to enable the Paystack
        // subscription. Then Paystack will resume this subscription
        // where we left off.
        PaystackService::enableSubscription([
            'token' => $subscription['email_token'],
            'code'  => $subscription['subscription_code'],
        ]);
        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "cancelled". Then we will save this record in the database.
        $this->fill(['ends_at' => null])->save();
        return $this;
    }
    
    /**
     * Get the subscription as a Paystack subscription object.
     *
     * @throws \LogicException
     */
    public function asPaystackSubscription()
    {
        $subscriptions = PaystackService::customerSubscriptions($this->user->paystack_id);

        if (! $subscriptions || empty($subscriptions)) {
            throw new LogicException('The Paystack customer does not have any subscriptions.');
        }
        foreach($subscriptions as $subscription ) {
            if($subscription['id'] == $this->paystack_id ) {
                return $subscription;
            }
        }

        throw new LogicException('The Paystack subscription does not exist for this customer.');
    }
}