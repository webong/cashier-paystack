
# Laravel Cashier - Paystack Edition
[![Build Status](https://travis-ci.org/webong/cashier-paystack.svg)](https://travis-ci.org/webong/cashier-paystack)
[![Latest Stable Version](https://poser.pugx.org/wisdomanthoni/cashier-paystack/v/stable)](https://packagist.org/packages/wisdomanthoni/cashier-paystack)
[![Total Downloads](https://poser.pugx.org/wisdomanthoni/cashier-paystack/downloads)](https://packagist.org/packages/wisdomanthoni/cashier-paystack)
[![License](https://poser.pugx.org/wisdomanthoni/cashier-paystack/license)](https://packagist.org/packages/wisdomanthoni/cashier-paystack)

# Introduction
Cashier Paystack provides an expressive, fluent interface to Paystack's subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing.

## Composer
First, add the Cashier package for Paystack to your dependencies:

`composer require wisdomanthoni/cashier-paystack`

## Configuration
You can publish the configuration file using this command:

```shell
php artisan vendor:publish --provider="Unicodeveloper\Paystack\PaystackServiceProvider"
```
A configuration-file named paystack.php with some sensible defaults will be placed in your config directory:
```php
<?php

return [

    /**
     * Public Key From Paystack Dashboard
     *
     */
    'publicKey' => getenv('PAYSTACK_PUBLIC_KEY'),

    /**
     * Secret Key From Paystack Dashboard
     *
     */
    'secretKey' => getenv('PAYSTACK_SECRET_KEY'),

    /**
     * Paystack Payment URL
     *
     */
    'paymentUrl' => getenv('PAYSTACK_PAYMENT_URL'),

    /**
     * Optional email address of the merchant
     *
     */
    'merchantEmail' => getenv('MERCHANT_EMAIL'),

    /**
     * User model for customers
     *
     */
    'model' => getenv('PAYSTACK_MODEL'),

];
```
Update your .env file with the user model
```
PAYSTACK_MODEL='App\Model\User'
```

## Database Migrations
Before using Cashier, we'll also need to prepare the database. We need to add several columns to your  users table and create a new subscriptions table to hold all of our customer's subscriptions:

```php
Schema::table('users', function ($table) {
    $table->string('paystack_id')->nullable();
    $table->string('paystack_code')->nullable();
    $table->string('card_brand')->nullable();
    $table->string('card_last_four', 4)->nullable();
    $table->timestamp('trial_ends_at')->nullable();
});
```
```php
Schema::create('subscriptions', function ($table) {
    $table->increments('id');
    $table->unsignedInteger('user_id');
    $table->string('name');
    $table->string('paystack_id')->nullable();
    $table->string('paystack_code')->nullable();
    $table->string('paystack_plan');
    $table->integer('quantity');
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->timestamps();
});
```
Once the migrations have been created, run the migrate Artisan command.

## Billable Model
Next, add the Billable trait to your model definition. This trait provides various methods to allow you to perform common billing tasks, such as creating subscriptions, applying coupons, and updating credit card information:
```php
use Wisdomanthoni\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```
## Currency Configuration
The default Cashier currency is Nigeria Naira (NGN). You can change the default currency by calling the Cashier::useCurrency method from within the boot method of one of your service providers. The useCurrency method accepts two string parameters: the currency and the currency's symbol:
```php
use Wisdomanthoni\Cashier\Cashier;

Cashier::useCurrency('ngn', '₦');
Cashier::useCurrency('ghs', 'GH₵');
```

## Subscriptions

### Creating Subscriptions
To create a subscription, first retrieve an instance of your billable model, which typically will be an instance of App\Model\User. Once you have retrieved the model instance, you may use the  newSubscription method to create the model's subscription:

```php
$user = User::find(1);
$plan_name = // Paystack plan name e.g default, main, yakata
$plan_code = // Paystack plan code  e.g PLN_gx2wn530m0i3w3m
$auth_token = // Paystack card auth token for customer
// Accepts an card authorization authtoken for the customer
$user->newSubscription($plan_name, $plan_code)->create($auth_token);
// The customer's most recent authorization would be used to charge subscription
$user->newSubscription($plan_name, $plan_code)->create(); 
// Initialize a new charge for a subscription
$user->newSubscription($plan_name, $plan_code)->charge(); 
```
The first argument passed to the newSubscription method should be the name of the subscription. If your application only offers a single subscription, you might call this main or primary. The second argument is the specific Paystack Paystack code the user is subscribing to. This value should correspond to the Paystack's code identifier in Paystack.

The create method, which accepts a Paystack authorization token, will begin the subscription as well as update your database with the customer/user ID and other relevant billing information.

The charge method, initializes a transaction which returns a response containing an authorization url for payment and an access code. 

Additional User Details
If you would like to specify additional customer details, you may do so by passing them as the second argument to the create method:
```php
$user->newSubscription('main', 'PLN_cgumntiwkkda3cw')->create($auth_token, [
    'data' => 'More Customer Data',
],[
    'data' => 'More Subscription Data',
]);
```
To learn more about the additional fields supported by Paystack, check out paystack's documentation on customer creation or the corresponding Paystack documentation.

### Checking Subscription Status
Once a user is subscribed to your application, you may easily check their subscription status using a variety of convenient methods. First, the subscribed method returns true if the user has an active subscription, even if the subscription is currently within its trial period:
```php
// Paystack plan name e.g default, main, yakata
if ($user->subscribed('main')) {
    //
}
```
The subscribed method also makes a great candidate for a route middleware, allowing you to filter access to routes and controllers based on the user's subscription status:
```php
public function handle($request, Closure $next)
{
    if ($request->user() && ! $request->user()->subscribed('main')) {
        // This user is not a paying customer...
        return redirect('billing');
    }

    return $next($request);
}
```

If you would like to determine if a user is still within their trial period, you may use the onTrial method. This method can be useful for displaying a warning to the user that they are still on their trial period:
```php
if ($user->subscription('main')->onTrial()) {
    //
}
```

The subscribedToPaystack method may be used to determine if the user is subscribed to a given Paystack based on a given Paystack Paystack code. In this example, we will determine if the user's main subscription is actively subscribed to the monthly Paystack:
```php
$plan_name = // Paystack plan name e.g default, main, yakata
$plan_code = // Paystack Paystack Code  e.g PLN_gx2wn530m0i3w3m
if ($user->subscribedToPlan($plan_code, $plan_code)) {
    //
}
```
### Cancelled Subscription Status
To determine if the user was once an active subscriber, but has cancelled their subscription, you may use the cancelled method:
```php
if ($user->subscription('main')->cancelled()) {
    //
}
```
You may also determine if a user has cancelled their subscription, but are still on their "grace period" until the subscription fully expires. For example, if a user cancels a subscription on March 5th that was originally scheduled to expire on March 10th, the user is on their "grace period" until March 10th. Note that the subscribed method still returns true during this time:
```php
if ($user->subscription('main')->onGracePeriod()) {
    //
}
```

### Cancelling Subscriptions
To cancel a subscription, call the cancel method on the user's subscription:
```php
$user->subscription('main')->cancel();
```
When a subscription is cancelled, Cashier will automatically set the ends_at column in your database. This column is used to know when the subscribed method should begin returning false. For example, if a customer cancels a subscription on March 1st, but the subscription was not scheduled to end until March 5th, the subscribed method will continue to return true until March 5th.

You may determine if a user has cancelled their subscription but are still on their "grace period" using the  onGracePeriod method:
```php
if ($user->subscription('main')->onGracePeriod()) {
    //
}
```
If you wish to cancel a subscription immediately, call the cancelNow method on the user's subscription:
```php
$user->subscription('main')->cancelNow();
```

### Resuming Subscriptions
If a user has cancelled their subscription and you wish to resume it, use the resume method. The user must still be on their grace period in order to resume a subscription:
```php
$user->subscription('main')->resume();
```
If the user cancels a subscription and then resumes that subscription before the subscription has fully expired, they will not be billed immediately. Instead, their subscription will be re-activated, and they will be billed on the original billing cycle.

## Subscription Trials

### With Billing Up Front
If you would like to offer trial periods to your customers while still collecting payment method information up front, you should use the trialDays method when creating your subscriptions:
```php
$user = User::find(1);

$user->newSubscription('main', 'PLN_gx2wn530m0i3w3m')
            ->trialDays(10)
            ->create($auth_token);
```
This method will set the trial period ending date on the subscription record within the database, as well as instruct Paystack to not begin billing the customer until after this date.

If the customer's subscription is not cancelled before the trial ending date they will be charged as soon as the trial expires, so you should be sure to notify your users of their trial ending date.

You may determine if the user is within their trial period using either the onTrial method of the user instance, or the onTrial method of the subscription instance. The two examples below are identical:
```php
if ($user->onTrial('main')) {
    //
}

if ($user->subscription('main')->onTrial()) {
    //
}
```

### Without Billing Up Front
If you would like to offer trial periods without collecting the user's payment method information up front, you may set the trial_ends_at column on the user record to your desired trial ending date. This is typically done during user registration:
```php
$user = User::create([
    // Populate other user properties...
    'trial_ends_at' => now()->addDays(10),
]);
```
Be sure to add a date mutator for trial_ends_at to your model definition.

Cashier refers to this type of trial as a "generic trial", since it is not attached to any existing subscription. The onTrial method on the User instance will return true if the current date is not past the value of trial_ends_at:
```php
if ($user->onTrial()) {
    // User is within their trial period...
}
```
You may also use the onGenericTrial method if you wish to know specifically that the user is within their "generic" trial period and has not created an actual subscription yet:
```php
if ($user->onGenericTrial()) {
    // User is within their "generic" trial period...
}
```
Once you are ready to create an actual subscription for the user, you may use the newSubscription method as usual:
```php
$user = User::find(1);
$plan_code = // Paystack Paystack Code  e.g PLN_gx2wn530m0i3w3m
// With Paystack card auth token for customer
$user->newSubscription('main', $plan_code)->create($auth_token);
$user->newSubscription('main', $plan_code)->create();
```

## Customers

### Creating Customers
Occasionally, you may wish to create a Paystack customer without beginning a subscription. You may accomplish this using the createAsPaystackCustomer method:
```php
$user->createAsPaystackCustomer();
```
Once the customer has been created in Paystack, you may begin a subscription at a later date.

## Payment Methods 
### Retrieving Authenticated Payment Methods
The cards method on the billable model instance returns a collection of `Wisdomanthoni\Cashier\Card` instances:
```php
$cards = $user->cards();
```
### Deleting Payment Methods
To delete a card, you should first retrieve the customer's authentications with the card method. Then, you may call the delete method on the instance you wish to delete:
```php
foreach ($user->cards() as $card) {
    $card->delete();
}
```
To delete all card payment authentication for a customer
```php
$user->deleteCards();
```

## Handling Paystack Webhooks
Paystack can notify your application of a variety of events via webhooks. To handle Paystack webhooks, define a route that points to Cashier's webhook controller. This controller will handle all incoming webhook requests and dispatch them to the proper controller method:
```php
Route::post(
    'paystack/webhook',
    '\Wisdomanthoni\Cashier\Http\Controllers\WebhookController@handleWebhook'
);
```
Once you have registered your route, be sure to configure the webhook URL in your Paystack dashboard settings.

By default, this controller will automatically handle cancelling subscriptions that have too many failed charges (as defined by your paystack settings), charge success, transfer success or fail, invoice updates and subscription changes; however, as we'll soon discover, you can extend this controller to handle any webhook event you like.

Make sure you protect incoming requests with Cashier's included webhook signature verification middleware.

### Webhooks & CSRF Protection
Since Paystack webhooks need to bypass Laravel's CSRF protection, be sure to list the URI as an exception in your VerifyCsrfToken middleware or list the route outside of the web middleware group:
```php
protected $except = [
    'paystack/*',
];
```

### Defining Webhook Event Handlers
If you have additional Paystack webhook events you would like to handle, extend the Webhook controller. Your method names should correspond to Cashier's expected convention, specifically, methods should be prefixed with handle and the "camel case" name of the Paystack webhook event you wish to handle.

```php
<?php

namespace App\Http\Controllers;

use Wisdomanthoni\Cashier\Http\Controllers\WebhookController as CashierController;

class WebhookController extends CashierController
{
    /**
     * Handle invoice payment succeeded.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleInvoiceUpdate($payload)
    {
        // Handle The Event
    }
}
```
Next, define a route to your Cashier controller within your routes/web.php file:
```php
Route::post(
    'paystack/webhook',
    '\App\Http\Controllers\WebhookController@handleWebhook'
);
```

## Single Charges

### Simple Charge
When using Paystack, the charge method accepts the amount you would like to charge in the lowest denominator of the currency used by your application.

If you would like to make a "one off" charge against a subscribed customer's credit card, you may use the  charge method on a billable model instance.

```php
// Paystack Accepts Charges In Kobo for Naira...
$PaystackCharge = $user->charge(10000);
```
The charge method accepts an array as its second argument, allowing you to pass any options you wish to the underlying Paystack charge creation. Consult the Paystack documentation regarding the options available to you when creating charges:
```php
$user->charge(100, [
    'more_option' => $value,
]);
```
The charge method will throw an exception if the charge fails. If the charge is successful, the full Paystack response will be returned from the method:
```php
try {
    // Paystack Accepts Charges In Kobo for Naira...
    $response = $user->charge(10000);
} catch (Exception $e) {
    //
}
```
## Charge With Invoice
Sometimes you may need to make a one-time charge but also generate an invoice for the charge so that you may offer a PDF receipt to your customer. The invoiceFor method lets you do just that. For example, let's invoice the customer ₦2000.00 for a "One Time Fee":
```php
// Paystack Accepts Charges In Kobo for Naira...
$user->invoiceFor('One Time Fee', 200000);
```
The invoice will be charged immediately against the user's credit card. The invoiceFor method also accepts an array as its third argument. This array contains the billing options for the invoice item. The fourth argument accepted by the method is also an array. This final argument accepts the billing options for the invoice itself:
```php
$user->invoiceFor('Stickers', 50000, [
    'line_items' => [ ],
    'tax' => [{"name":"VAT", "amount":2000}]
]);
```
To learn more about the additional fields supported by Paystack, check out paystack's documentation on customer creation or the corresponding Paystack documentation.

Refunding Charges
If you need to refund a Paystack charge, you may use the refund method. This method accepts the Paystack charge ID as its only argument:
```php
$paystackCharge = $user->charge(100);

$user->refund($paystackCharge->reference);
```
Invoices
You may easily retrieve an array of a billable model's invoices using the invoices method:
```php
$invoices = $user->invoices();

// Include only pending invoices in the results...
$invoices = $user->invoicesOnlyPending();

// Include only paid invoices in the results...
$invoices = $user->invoicesOnlyPaid();
```
When listing the invoices for the customer, you may use the invoice's helper methods to display the relevant invoice information. For example, you may wish to list every invoice in a table, allowing the user to easily download any of them:
```html
<table>
    @foreach ($invoices as $invoice)
        <tr>
            <td>{{ $invoice->date()->toFormattedDateString() }}</td>
            <td>{{ $invoice->total() }}</td>
            <td><a href="/user/invoice/{{ $invoice->id }}">Download</a></td>
        </tr>
    @endforeach
</table>
```
Generating Invoice PDFs
From within a route or controller, use the downloadInvoice method to generate a PDF download of the invoice. This method will automatically generate the proper HTTP response to send the download to the browser:
```php
use Illuminate\Http\Request;

Route::get('user/invoice/{invoice}', function (Request $request, $invoiceId) {
    return $request->user()->downloadInvoice($invoiceId, [
        'vendor'  => 'Your Company',
        'product' => 'Your Product',
    ]);
});
```
