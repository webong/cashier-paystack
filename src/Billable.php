<?php
namespace Wisdomanthoni\Cashier;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Unicodeveloper\Paystack\Facades\Paystack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait Billable
{
    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  array  $options
     * @throws \Exception
     */
    public function charge($amount, array $options = [])
    {
        $options['amount'] = $amount;
        $options['reference'] = $paystack->genTranxRef();

        if (! array_key_exists('email', $options) && $this->email) {
            $options['email'] = $this->email;
        }

        $response = Paystack::makePaymentRequest($options);
        $response->url = $response->getResponse()['data']['authorization_url'];
        return $response; 
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string  $charge
     * @param  array  $options
     * @return \Stripe\Refund
     * @throws \InvalidArgumentException
     */
    public function refund($charge, array $options = [])
    {
        $options['charge'] = $charge;
        return PaystackService::refund($options, ['api_key' => $this->getStripeKey()]);
    }

    /**
     * Invoice the customer for the given amount.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     * @throws \Exception
     */
    public function tab($description, $amount, array $options = [])
    {
        if (! $this->paystack_id) {
            throw new InvalidArgumentException(class_basename($this).' is not a Paystack customer. See the createAsPaystackCustomer method.');
        }

        if (! array_key_exists('due_date', $options)) {
            throw new InvalidArgumentException('No due date provided.');
        }

        $options = array_merge([
            'customer' => $this->paystack_id,
            'amount' => $amount,
            'currency' => $this->preferredCurrency(),
            'description' => $description,
        ], $options);
        return Invoice::create($options);
    }
    /**
     * Invoice the customer for the given amount (alias).
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     * @throws \Exception
     */
    public function invoiceFor($description, $amount, array $options = [])
    {
        return $this->tab($description, $amount, $options);
    }
    /**
     * Begin creating a new subscription.
     *
     * @param  string  $subscription
     * @param  string  $plan
     * @return \Laravel\Cashier\SubscriptionBuilder
     */
    public function newSubscription($subscription, $plan): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $subscription, $plan);
    }
    /**
     * Determine if the model is on trial.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
     * @return bool
     */
    public function onTrial($subscription = 'default', $plan = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }
        $subscription = $this->subscription($subscription);
        if (is_null($plan)) {
            return $subscription && $subscription->onTrial();
        }
        return $subscription && $subscription->onTrial() &&
               $subscription->paystack_plan === $plan;
    }
    /**
     * Determine if the model is on a "generic" trial at the user level.
     *
     * @return bool
     */
    public function onGenericTrial()
    {
        return $this->trial_ends_at && Carbon::now()->lt($this->trial_ends_at);
    }
    /**
     * Determine if the model has a given subscription.
     *
     * @param  string  $subscription
     * @param  string|null  $plan
     * @return bool
     */
    public function subscribed($subscription = 'default', $plan = null)
    {
        $subscription = $this->subscription($subscription);
        if (is_null($subscription)) {
            return false;
        }
        if (is_null($plan)) {
            return $subscription->valid();
        }
        return $subscription->valid() &&
               $subscription->Paystack_plan === $plan;
    }
    /**
     * Get a subscription instance by name.
     *
     * @param  string  $subscription
     * @return \Laravel\Cashier\Subscription|null
     */
    public function subscription($subscription = 'default')
    {
        return $this->subscriptions->sortByDesc(function ($value) {
            return $value->created_at->getTimestamp();
        })->first(function ($value) use ($subscription) {
            return $value->name === $subscription;
        });
    }
    /**
     * Get all of the subscriptions for the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class)->orderBy('created_at', 'desc');
    }
    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Wisdomanthoni\Cashier\Invoice|null
     */
    public function findInvoice($id)
    {
        try {
            $invoice = Invoice::find($id);
            if ($invoice->customerDetails->id != $this->Paystack_id) {
                return;
            }
            return new Invoice($this, $invoice);
        } catch (Exception $e) {
            //
        }
    }
    /**
     * Find an invoice or throw a 404 error.
     *
     * @param  string  $id
     * @return \Laravel\Cashier\Invoice
     */
    public function findInvoiceOrFail($id): Invoice
    {
        $invoice = $this->findInvoice($id);
        if (is_null($invoice)) {
            throw new NotFoundHttpException;
        }
        return $invoice;
    }
    /**
     * Create an invoice download Response.
     *
     * @param  string  $id
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Throwable
     */
    public function downloadInvoice($id, array $data)
    {
        return $this->findInvoiceOrFail($id)->download($data);
    }
    /**
     * Get a collection of the entity's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     * @throws \Paystack\Exception\NotFound
     */
    public function invoices($includePending = false, $parameters = []): Collection
    {
        
    }
    
    /**
     * Get an array of the entity's invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     * @throws \Paystack\Exception\NotFound
     */
    public function invoicesIncludingPending(array $parameters = []): Collection
    {
        return $this->invoices(true, $parameters);
    }

    /**
     * Apply a coupon to the billable entity.
     *
     * @param  string  $coupon
     * @param  string  $subscription
     * @param  bool  $removeOthers
     * @return void
     * @throws \InvalidArgumentException
     */
    public function applyCoupon($coupon, $subscription = 'default', $removeOthers = false)
    {
        $subscription = $this->subscription($subscription);
        if (! $subscription) {
            throw new InvalidArgumentException("Unable to apply coupon. Subscription does not exist.");
        }
        $subscription->applyCoupon($coupon, $removeOthers);
    }
   
    /**
     * Determine if the model is actively subscribed to one of the given plans.
     *
     * @param  array|string  $plans
     * @param  string  $subscription
     * @return bool
     */
    public function subscribedToPlan($plans, $subscription = 'default')
    {
        $subscription = $this->subscription($subscription);
        if (! $subscription || ! $subscription->valid()) {
            return false;
        }
        foreach ((array) $plans as $plan) {
            if ($subscription->paystack_plan === $plan) {
                return true;
            }
        }
        return false;
    }
    /**
     * Determine if the entity is on the given plan.
     *
     * @param  string  $plan
     * @return bool
     */
    public function onPlan($plan)
    {
        return ! is_null($this->subscriptions->first(function ($value) use ($plan) {
            return $value->paystack_plan === $plan;
        }));
    }
    /**
     * Create a Paystack customer for the given model.
     *
     * @param  string  $token
     * @param  array  $options
     * @throws \Exception
     */
    public function createAsPaystackCustomer($token, array $options = [])
    {
        $options = array_key_exists('email', $options)
        ? $options
        : array_merge($options, ['email' => $this->email]);

        $response = PaystackService::createCustomer($options);

        if (! $response->status) {
            throw new Exception('Unable to create Paystack customer: '.$response->message);
        }
        
        $this->paystack_id = $response->data->customer_code;
        $this->save();

        return $response->data;   
    }

    /**
     * Get the Paystack customer for the model.
     *
     * @return \Paystack\Customer
     * @throws \Paystack\Exception\NotFound
     */
    public function asPaystackCustomer()
    {
        return Paystack::fetchCustomer($this->paystack_id);
    }

    /**
     * Determine if the entity has a Paystack customer ID.
     *
     * @return bool
     */
    public function hasPaystackId()
    {
        return ! is_null($this->paystack_id);
    }

    /**
     * Get the tax percentage to apply to the subscription.
     *
     * @return int
     */
    public function taxPercentage()
    {
        return 0;
    }


    /**
     * Get the Paystack supported currency used by the entity.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return Cashier::usesCurrency();
    }

}