<?php
namespace Wisdomanthoni\Cashier;

use Exception;
use Carbon\Carbon;
use Unicodeveloper\Paystack\Facades\Paystack;
class SubscriptionBuilder
{
    /**
     * The model that is subscribing.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;
    /**
     * The name of the subscription.
     *
     * @var string
     */
    protected $name;
    /**
     * The name of the plan being subscribed to.
     *
     * @var string
     */
    protected $plan;
    /**
     * The number of trial days to apply to the subscription.
     *
     * @var int|null
     */
    protected $trialDays;
    /**
     * Indicates that the trial should end immediately.
     *
     * @var bool
     */
    protected $skipTrial = false;
    /**
     * The coupon code being applied to the customer.
     *
     * @var string|null
     */
    protected $coupon;
    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $name
     * @param  string  $plan
     * @return void
     */
    public function __construct($owner, $name, $plan)
    {
        $this->name = $name;
        $this->plan = $plan;
        $this->owner = $owner;
    }
    /**
     * Specify the ending date of the trial.
     *
     * @param  int  $trialDays
     * @return $this
     */
    public function trialDays($trialDays)
    {
        $this->trialDays = $trialDays;
        return $this;
    }
    /**
     * Force the trial to end immediately.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->skipTrial = true;
        return $this;
    }
    /**
     * The coupon to apply to a new subscription.
     *
     * @param  string  $coupon
     * @return $this
     */
    public function withCoupon($coupon)
    {
        $this->coupon = $coupon;
        return $this;
    }
    /**
     * Add a new Paystack subscription to the model.
     *
     * @param  array  $options
     * @return \Laravel\Cashier\Subscription
     * @throws \Exception
     */
    public function add(array $options = [])
    {
        return $this->create(null, $options);
    }
    /**
     * Create a new Paystack subscription.
     *
     * @param  string|null  $token
     * @param  array  $customerOptions
     * @param  array  $subscriptionOptions
     * @return \Laravel\Cashier\Subscription
     * @throws \Exception
     */
    public function create($token = null, array $options = [])
    {
        $customer = $this->getPaystackCustomer($token, $options);

        if ($this->coupon) {
            // TODO: coupon feature
        }
        $response = PaystackService::createSubscription($this->buildPayload($customer));

        if (! $response->success) {
            throw new Exception('Paystack failed to create subscription: '.$response->message);
        }
        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialDays ? Carbon::now()->addDays($this->trialDays) : null;
        }
        return $this->owner->subscriptions()->create([
            'name' => $this->name,
            'paystack_id'   => $response->subscription->id,
            'paystack_plan' => $this->plan,
            'quantity' => 1,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);
    }
    
    /**
     * Get the base subscription payload for Paystack.
     *
     * @param  $customer
     * @param  array  $options
     * @return array
     * @throws \Exception
     */
    protected function buildPayload($customer, array $options = [])
    {
        $plan = Paystack::fetchPlan($this->plan);

        if ($plan) {
            # code...
        }

        if ($this->skipTrial) {
            $startDate = Carbon::now();
        } else {
            $startDate =  $this->trialDays ? Carbon::now()->addDays($this->trialDays) : Carbon::now();
        }

        $data = [
            "customer" => $customer, //Customer email or code
            "plan" => $this->plan,
            "start_date" => $startDate,
        ];
    }
    
    /**
     * Get the Paystack customer instance for the current user and token.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Paystack\Customer
     */
    protected function getPaystackCustomer($token = null, array $options = [])
    {
        if (! $this->owner->paystack_id) {
            $customer = $this->owner->createAsPaystackCustomer($token, $options);
        } else {
            $customer = $this->owner->asPaystackCustomer();
        }
        return $customer->customer_code;
    }
}
