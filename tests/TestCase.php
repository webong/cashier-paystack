<?php
namespace Webong\Cashier\Tests;

use Xeviant\LaravelPaystack\Facades\Paystack;
use Xeviant\LaravelPaystack\PaystackServiceProvider;
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
}