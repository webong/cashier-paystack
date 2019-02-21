<?php
namespace Wisdomanthoni\Cashier;

class PaymentMethod
{
    /**
     * The Paystack model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;
    /**
     * The Paystack paymentMethod instance.
     *
     */
    protected $paymentMethod;
    /**
     * Create a new paymentMethod instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param   $paymentMethod
     * @return void
     */
    public function __construct($owner, $paymentMethod)
    {
        $this->owner = $owner;
        $this->paymentMethod = $paymentMethod;
    }
    /**
     * Check the payment Method have funds for the payment you seek.
     *
     */
    public function check($amount)
    {
        $data = [];
        $data['email'] = $this->owner->email;
        $data['amount'] = $amount;
        return PaystackService::checkAuthorization($this->paymentMethod->authorization_code, $data);
    }
    /**
     * Delete the payment Method.
     *
     */
    public function delete()
    {
        return PaystackService::deactivateAuthorization($this->paymentMethod->authorization_code);
    }
    /**
     * Get the Paystack payment authorization object.
     *
     */
    public function asPaystackAuthorization()
    {
        return $this->paymentMethod;
    }    
    /**
     * Dynamically get values from the Paystack paymentMethod.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->paymentMethod->{$key};
    }
}
