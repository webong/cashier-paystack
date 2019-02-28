<?php
namespace Wisdomanthoni\Cashier\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp()
    {
        // $config = [  
        //     'publicKey' => getenv('PAYSTACK_PUBLIC_KEY'),
        //     'secretKey' => getenv('PAYSTACK_SECRET_KEY'),
        //     'paymentUrl' => getenv('PAYSTACK_PAYMENT_URL'),
        //     'merchantEmail' => getenv('MERCHANT_EMAIL'),
        //     'model' => getenv('PAYSTACK_MODEL'),
        // ];
        // $app['config']->set('paystack', $config);
    }
}