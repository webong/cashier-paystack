<?php
namespace Wisdomanthoni\Cashier;

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
    public function __construct($owner, StripeCard $card)
    {
        $this->owner = $owner;
        $this->card = $card;
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
