# Contributing

Thank you for considering contributing to the Cashier Paystack.

We accept contributions via Pull Requests on [Github](https://github.com/webong/cashier-paystack).


## Running Tests
You will need to set the Stripe testing secret environment variable in a custom phpunit.xml file in order to run the Cashier tests.

Copy the default file using `cp phpunit.xml.dist phpunit.xml` and update the following line below the PAYSTACK_PAYMENT_URL environment variable in your new phpunit.xml file:

```env
    <env name="PAYSTACK_PUBLIC_KEY" value="Your Paystack Public Key"/>
    <env name="PAYSTACK_SECRET_KEY" value="Your Paystack Secret Key"/>
```
Please note that due to the fact that actual API requests against Paystack are being made, these tests take a few minutes to run.

**Happy coding**!