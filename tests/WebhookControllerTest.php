<?php
namespace Wisdomanthoni\Cashier\Tests;

use Illuminate\Http\Request;
use Wisdomanthoni\Cashier\Http\Controllers\WebhookController;

class WebhookControllerTest extends TestCase
{
    public function testProperMethodsAreCalledBasedOnPaystackEvent()
    {
        $_SERVER['__received'] = false;
        $request = Request::create(
            '/', 'POST', [], [], [], [], json_encode(['event' => 'subscription.create', 'data' => []])
        );
        (new WebhookControllerTestStub)->handleWebhook($request);
        $this->assertTrue($_SERVER['__received']);
    }
    public function testNormalResponseIsReturnedIfMethodIsMissing()
    {
        $request = Request::create(
            '/', 'POST', [], [], [], [], json_encode(['event' => 'foo.bar', 'data' => []])
        );
        $response = (new WebhookControllerTestStub)->handleWebhook($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}

class WebhookControllerTestStub extends WebhookController
{
    public function __construct()
    {
        // Prevent setting middleware...
    }
    public function handleSubscriptionCreate($payload)
    {
        $_SERVER['__received'] = true;
    }
}