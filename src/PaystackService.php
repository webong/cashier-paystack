<?php
namespace Webong\Cashier;

use Xeviant\LaravelPaystack\Facades\PaystackV1 as Paystack;

class PaystackService 
{
    /**
     * @var \Xeviant\Paystack\Client
     */
    private $paystack;
    /**
     * Paystack constructor.
     */
    public function __construct()
    {
        $this->paystack = app()->make('paystack.connection');
    }

    public static function chargeAuthorization($data)
    {
        return self::$paystack->transactions()->charge($data);
    }

    public static function checkAuthorization($data)
    {
        return (new self)->setHttpResponse('/check_authorization', 'POST', $data)->getResponse();
    }

    public static function deactivateAuthorization($auth_code)
    {
        $data = ['authorization_code' => $auth_code];
        return (new self)->setHttpResponse('/deactivate_authorization', 'POST', $data)->getResponse();
    }

    public static function createRefund($data)
    {
        return self::$paystack->refunds()->create($data);
    }
}