<?php
namespace Wisdomanthoni\Cashier\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends BaseTestCase
{
     /**
     * Load package service provider
     * @param  \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
    }
    /**
     * Load package alias
     * @param  \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'Paystack' => Paystack::class,
        ];
    }
}