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
