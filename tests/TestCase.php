<?php
namespace Wisdomanthoni\Cashier\Tests;

use Unicodeveloper\Paystack\Facades\Paystack;
use Unicodeveloper\Paystack\PaystackServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
abstract class TestCase extends OrchestraTestCase
{
     /**
     * Load package service provider
     * @param  \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [PaystackServiceProvider::class];
    }
    /**
     * Load package alias
     * @param  \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'laravel-paystack' => Paystack::class,
        ];
    }
}