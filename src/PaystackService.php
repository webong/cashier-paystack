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

    public static function createCustomer($data)
    {
        return (new self)->setHttpResponse('/customer', 'POST', $data)->getResponse();
    }

    public static function createSubscription($data)
    {
        return (new self)->setHttpResponse('/subscription', 'POST', $data)->getResponse();
    }

    public static function customerSubscriptions($customer_id)
    {
        $data = ['customer' => $customer_id];
        return (new self)->setHttpResponse('/subscription', 'GET', $data)->getData();
    }

    public static function enableSubscription($data)
    {
        return (new self)->setHttpResponse('/subscription/enable', 'POST', $data)->getResponse();
    }

    public static function disableSubscription($data)
    {
        return (new self)->setHttpResponse('/subscription/disable', 'POST', $data)->getResponse();
    }

    public static function createInvoice($data)
    {
        return self::$paystack->invoices('/paymentrequest', 'POST', $data)->getResponse();
    }

    public static function fetchInvoices($data)
    {
        return (new self)->setHttpResponse('/paymentrequest', 'GET', $data)->getData();
    }
    
    public static function findInvoice($invoice_id)
    {
        return (new self)->setHttpResponse('/paymentrequest'. $invoice_id, 'GET', [])->getData();
    }

    public static function updateInvoice($invoice_id, $data)
    {
        return (new self)->setHttpResponse('/paymentrequest'. $invoice_id, 'PUT', $data)->getResponse();
    }

    public static function verifyInvoice($invoice_code)
    {
        return (new self)->setHttpResponse('/paymentrequest/verify'. $invoice_code, 'GET', [])->getData();
    }

    public static function notifyInvoice($invoice_id)
    {
        return (new self)->setHttpResponse('/paymentrequest/notify'. $invoice_id, 'POST', [])->getResponse();
    }

    public static function finalizeInvoice($invoice_id)
    {
        return (new self)->setHttpResponse('/paymentrequest/finalize'. $invoice_id, 'POST', [])->getResponse();
    }

    public static function archiveInvoice($invoice_id)
    {
        return (new self)->setHttpResponse('/paymentrequest/archive'. $invoice_id, 'POST', [])->getResponse();
    }

    public static function createPlan($data)
    {
        request()->replace($data);
        return Paystack::createPlan($data);
    }
}