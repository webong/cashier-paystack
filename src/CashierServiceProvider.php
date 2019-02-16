<?php
namespace Wisdomanthoni\Cashier;

use Illuminate\Support\ServiceProvider;

class CashierServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier');
        $this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/cashier'),
        ]);
    }
}