<?php
namespace Wisdomanthoni\Cashier;

use Exception;
use Carbon\Carbon;
use LogicException;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Wisdomanthoni\Paystack\Subscription as PaystackSubscription;
class Subscription extends Model
{
    /**
     * The attributes that aren't mass assignable.
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
     * Indicates plan changes should be prorated.
     *
     * @var bool
     */
    protected $prorate = true;
    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        return $this->owner();
    }
    /**
     * Get the model related to the subscription.
     */
    public function owner()
    {
        $model = getenv('PAYSTACK_MODEL') ?: config('services.paystack.model', 'App\\User');
        $model = new $model;
        return $this->belongsTo(get_class($model), $model->getForeignKey());
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
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return ! is_null($this->ends_at);
    }
    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        if (! is_null($this->trial_ends_at)) {
            return Carbon::today()->lt($this->trial_ends_at);
        }
        return false;
    }
    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        if (! is_null($endsAt = $this->ends_at)) {
            return Carbon::now()->lt(Carbon::instance($endsAt));
        }
        return false;
    }
    /**
     * Increment the quantity of the subscription.
     *
     * @param  int  $count
     * @return $this
     */
    public function incrementQuantity($count = 1)
    {
        $this->updateQuantity($this->quantity + $count);
        return $this;
    }
    /**
     * Decrement the quantity of the subscription.
     *
     * @param  int  $count
     * @return $this
     */
    public function decrementQuantity($count = 1)
    {
        $this->updateQuantity($this->quantity - $count);
        return $this;
    }
    /**
     * Update the quantity of the subscription.
     *
     * @param  int  $quantity
     * @return $this
     */
    public function updateQuantity($quantity)
    {
        $quantity = max(0, $quantity - 1);
        $addonName = $this->paystack_plan.'-quantity';
        $options = ['remove' => [$addonName]];
        if ($quantity > 0) {
            $options = $this->quantity > 1
                ? ['update' => [['existingId' => $addonName, 'quantity' => $quantity]]]
                : ['add' => [['inheritedFromId' => $addonName, 'quantity' => $quantity]]];
        }
        PaystackSubscription::update($this->Paystack_id, ['addOns' => $options]);
        $this->quantity = $quantity + 1;
        $this->save();
        return $this;
    }
    /**
     * Swap the subscription to a new Paystack plan.
     *
     * @param  string  $plan
     * @return $this|\Laravel\Cashier\Subscription
     * @throws \Exception
     */
    public function swap($plan)
    {
        if ($this->onGracePeriod() && $this->Paystack_plan === $plan) {
            return $this->resume();
        }
        if (! $this->active()) {
            return $this->owner->newSubscription($this->name, $plan)
                                ->skipTrial()->create();
        }
        $plan = PaystackService::findPlan($plan);
        if ($this->wouldChangeBillingFrequency($plan) && $this->prorate) {
            return $this->swapAcrossFrequencies($plan);
        }
        $subscription = $this->asPaystackSubscription();
        $response = PaystackSubscription::update($subscription->id, [
            'planId' => $plan->id,
            'price' => number_format($plan->price * (1 + ($this->owner->taxPercentage() / 100)), 2, '.', ''),
            'neverExpires' => true,
            'numberOfBillingCycles' => null,
            'options' => [
                'prorateCharges' => $this->prorate,
            ],
        ]);
        if ($response->success) {
            $this->fill([
                'paystack_plan' => $plan->id,
                'ends_at' => null,
            ])->save();
        } else {
            throw new Exception('Paystack failed to swap plans: '.$response->message);
        }
        return $this;
    }
    /**
     * Determine if the given plan would alter the billing frequency.
     *
     * @param  string  $plan
     * @return bool
     * @throws \Exception
     */
    protected function wouldChangeBillingFrequency($plan)
    {
        return $plan->billingFrequency !== PaystackService::findPlan($this->Paystack_plan)->billingFrequency;
    }
    /**
     * Swap the subscription to a new Paystack plan with a different frequency.
     *
     * @param  string  $plan
     * @return \Wisdomanthoni\Cashier\Subscription
     * @throws \Exception
     */
    protected function swapAcrossFrequencies($plan): Subscription
    {
        $currentPlan = PaystackService::findPlan($this->Paystack_plan);
        $discount = $this->switchingToMonthlyPlan($currentPlan, $plan)
                                ? $this->getDiscountForSwitchToMonthly($currentPlan, $plan)
                                : $this->getDiscountForSwitchToYearly();
        $options = [];
        if ($discount->amount > 0 && $discount->numberOfBillingCycles > 0) {
            $options = ['discounts' => ['add' => [
                [
                    'inheritedFromId' => 'plan-credit',
                    'amount' => (float) $discount->amount,
                    'numberOfBillingCycles' => $discount->numberOfBillingCycles,
                ],
            ]]];
        }
        $this->cancelNow();
        return $this->owner->newSubscription($this->name, $plan->id)
            ->skipTrial()
            ->create(null, [], $options);
    }
    /**
     * Determine if the user is switching form yearly to monthly billing.
     *
     * @param  \Paystack\Plan  $currentPlan
     * @param  \Paystack\Plan  $plan
     * @return bool
     */
    protected function switchingToMonthlyPlan(Plan $currentPlan, Plan $plan)
    {
        return $currentPlan->billingFrequency == 12 && $plan->billingFrequency == 1;
    }
    /**
     * Get the discount to apply when switching to a monthly plan.
     *
     * @param  \Paystack\Plan  $currentPlan
     * @param  \Paystack\Plan  $plan
     * @return object
     */
    protected function getDiscountForSwitchToMonthly(Plan $currentPlan, Plan $plan)
    {
        return (object) [
            'amount' => $plan->price,
            'numberOfBillingCycles' => floor(
                $this->moneyRemainingOnYearlyPlan($currentPlan) / $plan->price
            ),
        ];
    }
    /**
     * Calculate the amount of discount to apply to a swap to monthly billing.
     *
     * @param  \Paystack\Plan  $plan
     * @return float
     */
    protected function moneyRemainingOnYearlyPlan(Plan $plan)
    {
        return ($plan->price / 365) * Carbon::today()->diffInDays(Carbon::instance(
            $this->asPaystackSubscription()->billingPeriodEndDate
        ), false);
    }
    /**
     * Get the discount to apply when switching to a yearly plan.
     *
     * @return object
     */
    protected function getDiscountForSwitchToYearly()
    {
        $amount = 0;
        foreach ($this->asPaystackSubscription()->discounts as $discount) {
            if ($discount->id == 'plan-credit') {
                $amount += (float) $discount->amount * $discount->numberOfBillingCycles;
            }
        }
        return (object) [
            'amount' => $amount,
            'numberOfBillingCycles' => 1,
        ];
    }
    /**
     * Apply a coupon to the subscription.
     *
     * @param  string  $coupon
     * @param  bool  $removeOthers
     * @return void
     */
    public function applyCoupon($coupon, $removeOthers = false)
    {
        if (! $this->active()) {
            throw new InvalidArgumentException("Unable to apply coupon. Subscription not active.");
        }
        PaystackSubscription::update($this->Paystack_id, [
            'discounts' => [
                'add' => [[
                    'inheritedFromId' => $coupon,
                ]],
                'remove' => $removeOthers ? $this->currentDiscounts() : [],
            ],
        ]);
    }
    
    /**
     * Get the current discounts for the subscription.
     *
     * @return array
     */
    protected function currentDiscounts()
    {
        return collect($this->asPaystackSubscription()->discounts)->map(function ($discount) {
            return $discount->id;
        })->all();
    }
    /**
     * Cancel the subscription.
     *
     * @return $this
     */
    public function cancel()
    {
        $subscription = $this->asPaystackSubscription();
        if ($this->onTrial()) {
            PaystackSubscription::cancel($subscription->id);
            $this->markAsCancelled();
        } else {
            PaystackSubscription::update($subscription->id, [
                'numberOfBillingCycles' => $subscription->currentBillingCycle,
            ]);
            $this->ends_at = $subscription->billingPeriodEndDate;
            $this->save();
        }
        return $this;
    }
    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $subscription = $this->asPaystackSubscription();
        PaystackSubscription::cancel($subscription->id);
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
        if (! $this->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }
        $subscription = $this->asPaystackSubscription();
        PaystackSubscription::update($subscription->id, [
            'neverExpires' => true,
            'numberOfBillingCycles' => null,
        ]);
        $this->fill(['ends_at' => null])->save();
        return $this;
    }
    
    /**
     * Get the subscription as a Paystack subscription object.
     *
     * @return \Paystack\Subscription
     */
    public function asPaystackSubscription(): PaystackSubscription
    {
        return PaystackSubscription::find($this->Paystack_id);
    }
}