<?php
namespace Laravel\Cashier;

use Exception;
use Unicodeveloper\Paystack\Paystack;
use Unicodeveloper\Paystack\TransRef;
class PaystackService
{
    public static function findPlan($id)
    {
        $plans = Paystack::all();
        foreach ($plans as $plan) {
            if ($plan->id === $id) {
                return $plan;
            }
        }
        throw new Exception("Unable to find Paystack plan with ID [{$id}].");
    }

}