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
     * Make a "one off" or "recurring" charge on the customer for the given amount or plan respectively
     *
     * @param  $amount
     * @param  array  $options
     * @throws \Exception
     */
    public function charge($amount, array $options = [])
    {
        $options = array_merge([
            'currency' => $this->preferredCurrency(),
            'reference' => Paystack::genTranxRef(),
        ], $options);
        
        $options['email'] = $this->email;
        $options['amount'] = intval($amount);
        if ( array_key_exists('authorization_code', $options) ) {
            $response = PaystackService::chargeAuthorization($options);    
        } elseif (array_key_exists('card', $options) || array_key_exists('bank', $options)) {
            $response = PaystackService::charge($options);   
        } else {
            $response = PaystackService::makePaymentRequest($options);	  
        }

        if (! $response['status']) {
            throw new Exception('Paystack was unable to perform a charge: '.$response->message);
        }
        return $response;
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string  $charge
     * @param  array  $options
     * @return $response
     * @throws \InvalidArgumentException
     */
    public function refund($transaction, array $options = [])
    {
        $options['transaction'] = $transaction;

        $response = PaystackService::refund($options);
        return $response;
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

        $options['due_date'] = Carbon::parse($options['due_date'])->format('c');

        return PaystackService::createInvoice($options);
    }
    /**
     * Invoice the billable entity outside of regular billing cycle.
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
     * @return \Wisdomanthoni\Cashier\SubscriptionBuilder
     */
    public function newSubscription($subscription = 'default', $plan): SubscriptionBuilder
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
               $subscription->paystack_plan === $plan;
    }
    /**
     * Get a subscription instance by name.
     *
     * @param  string  $subscription
     * @return \Wisdomanthoni\Cashier\Subscription|null
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
            $invoice = PaystackService::findInvoice($id);
            if ($invoice['customer']['id'] != $this->paystack_id) {
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
     * @return \Wisdomanthoni\Cashier\Invoice
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
     * @throws \Exception
     */
    public function invoices($options = []): Collection
    {
        if (! $this->hasPaystackId()) {
            throw new InvalidArgumentException(class_basename($this).' is not a Paystack customer. See the createAsPaystackCustomer method.');
        }

        $invoices = [];
        $parameters = array_merge(['customer' => $this->paystack_id], $options);
        $paystackInvoices = PaystackService::fetchInvoices($parameters);
        // Here we will loop through the Paystack invoices and create our own custom Invoice
        // instances that have more helper methods and are generally more convenient to
        // work with than the plain Paystack objects are. Then, we'll return the array.
        if (! is_null($paystackInvoices && ! empty($paystackInvoices))) {
            foreach ($paystackInvoices as $invoice) {
                $invoices[] = new Invoice($this, $invoice);
            }
        }
        return new Collection($invoices);   
    }
    
    /**
     * Get an array of the entity's invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function invoicesOnlyPending(array $parameters = []): Collection
    {
        $parameters['status'] = 'pending';
        return $this->invoices($parameters);
    }
     /**
     * Get an array of the entity's invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function invoicesOnlyPaid(array $parameters = []): Collection
    {
        $parameters['paid'] = true;
        return $this->invoices($parameters);
    }   
    /**
     * Get a collection of the entity's payment methods.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection
     */
    public function cards($parameters = [])
    {
        $cards = [];
        $paystackAuthorizations = $this->asPaystackCustomer()->authorizations;
        if (! is_null($paystackAuthorizations)) {
            foreach ($paystackAuthorizations as $card) {
                if($card['channel'] == 'card')
                    $cards[] = new Card($this, $card);
            }
        }
        return new Collection($cards);
    }
    /**
     * Deletes the entity's payment methods.
     *
     * @return void
     */
    public function deleteCards()
    {
        $this->cards()->each(function ($card) {
            $card->delete();
        });
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
        return ! is_null($this->subscriptions->first(function ($subscription) use ($plan) {
            return $subscription->paystack_plan === $plan;
        }));
    }
    /**
     * Create a Paystack customer for the given model.
     *
     * @param  string  $token
     * @param  array  $options
     * @throws \Exception
     */
    public function createAsPaystackCustomer(array $options = [])
    {
        $options = array_key_exists('email', $options)
        ? $options
        : array_merge($options, ['email' => $this->email]);

        $response = PaystackService::createCustomer($options);

        if (! $response['status']) {
            throw new Exception('Unable to create Paystack customer: '.$response['message']);
        }
        $this->paystack_id = $response['data']['id'];  
        $this->paystack_code = $response['data']['customer_code'];              
        $this->save();

        return $response['data'];   
    }

    /**
     * Get the Paystack customer for the model.
     *
     * @return $customer
     */
    public function asPaystackCustomer()
    {
        $customer = Paystack::fetchCustomer($this->paystack_id)['data'];
        return $customer;
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
     * Get the Paystack supported currency used by the entity.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return Cashier::usesCurrency();
    }

}