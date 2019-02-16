<?php
namespace Laravel\Cashier;

use Exception;
use Wisdomanthoni\Paystack\Plan as PaystackPlan;
class PaystackService
{
    /**
     * Get the Paystack plan that has the given ID.
     *
     * @param  string  $id
     * @return \Paystack\Plan
     * @throws \Exception
     */
    public static function findPlan($id)
    {
        $plans = PaystackPlan::all();
        foreach ($plans as $plan) {
            if ($plan->id === $id) {
                return $plan;
            }
        }
        throw new Exception("Unable to find Paystack plan with ID [{$id}].");
    }
}