<?php
namespace Webong\Cashier\Tests;

use Xeviant\LaravelPaystack\Facades\PaystackV1;
use Xeviant\LaravelPaystack\PaystackServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Xeviant\LaravelPaystack\PaystackFactory;

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
            // 'laravel-paystack' => PaystackV1::class,
            // 'paystack.factory' => PaystackFactory::class,
        ];
    }

}