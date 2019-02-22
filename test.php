<?php

use Carbon\Carbon;
use Wisdomanthoni\Cashier\PaystackService;

include './vendor/autoload.php';
// $method = 'handle'.studly_case(str_replace('.', '_', 'subscription.create'));
// echo $method;

$carbon = Carbon::now()->addDays(5);
echo $carbon->isFuture();
