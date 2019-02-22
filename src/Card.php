<?php
namespace Wisdomanthoni\Cashier;

use Exception;

class Card
{
    /**
     * The Paystack model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;
    /**
     * The Paystack card instance.
     *
     */
    protected $card;
    /**
     * Create a new card instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param   $card
     * @return void
     */
    public function __construct($owner, $card)
    {
        $this->owner = $owner;
        $this->card = (object) $card;
    }
    /**
     * Check the payment Method have funds for the payment you seek.
     *
     * @throws \Exception
     */
    public function check($amount)
    {
        $data = [];
        $data['email'] = $this->owner->email;
        $data['amount'] = $amount;
        $data['authorization_code'] = $this->card->authorization_code;
        if ($this->isReusable) {
            return PaystackService::checkAuthorization($data);
        }
        throw new Exception('Payment Method is no longer reusable.');
    }
    /**
     * Delete the payment Method.
     *
     */
    public function delete()
    {
        return PaystackService::deactivateAuthorization($this->card->authorization_code);
    }
    /**
     * Get the Paystack payment authorization object.
     *
     */
    public function asPaystackAuthorization()
    {
        return $this->card;
    }    
    /**
     * Dynamically get values from the Paystack card.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->card->{$key};
    }
}
