<?php
namespace Wisdomanthoni\Cashier;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\View\View as ViewContract;

class Invoice
{
    /**
     * The model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;
    /**
     * The Paystack invoice instance.
     *
     * @var PaystackInvoice
     */
    protected $invoice;
    /**
     * Create a new invoice instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param   $invoice
     * @return void
     */
    public function __construct($owner, $invoice)
    {
        $this->owner = $owner;
        $this->invoice = $invoice;
    }
    /**
     * Get a Carbon date for the invoice.
     *
     * @param  \DateTimeZone|string  $timezone
     * @return \Carbon\Carbon
     */
    public function date($timezone = null): Carbon
    {
        $carbon = Carbon::instance($this->invoice['created_at']);
        return $timezone ? $carbon->setTimezone($timezone) : $carbon;
    }
    /**
     * Get the total amount that was paid (or will be paid).
     *
     * @return string
     */
    public function total()
    {
        return $this->formatAmount($this->rawTotal());
    }
    /**
     * Get the raw total amount that was paid (or will be paid).
     *
     * @return float
     */
    public function rawTotal()
    {
        return max(0, $this->invoice['amount']);
    }
    /**
     * Get the total of the invoice (before discounts).
     *
     * @return string
     */
    public function subtotal()
    {
        if ($this->hasStartingBalance()) {
            return $this->startingBalance();
        }
        return $this->formatAmount(
            max(0, $this->invoice['amount'] - ($this->invoice['discount']['amount'] ?? 0))
        );    
    }
    /**
     * Determine if the account had a starting balance.
     *
     * @return bool
     */
    public function hasStartingBalance()
    {
        return $this->rawStartingBalance() > 0;
    }
    /**
     * Get the starting balance for the invoice.
     *
     * @return string
     */
    public function startingBalance()
    {
        return $this->formatAmount($this->rawStartingBalance());
    }
    /**
     * Determine if the invoice has a discount.
     *
     * @return bool
     */
    public function hasDiscount()
    {
        return isset($this->invoice['discount']);
    }
    /**
     * Get the discount amount.
     *
     * @return string
     */
    public function discount()
    {
        return $this->formatAmount($this->invoice['discount']['amount']);
    }
    /**
     * Determine if the discount is a percentage.
     *
     * @return bool
     */
    public function discountIsPercentage()
    {
        return $this->hasDiscount() && $this->invoice['discount']['type'] == 'percentage' ;
    }
    /**
     * Get the discount percentage for the invoice.
     *
     * @return int
     */
    public function percentOff()
    {
        if ($this->discountIsPercentage()) {
            return $this->invoice['discount']['amount'];
        }
        return 0;
    }
    /**
     * Get the discount amount for the invoice.
     *
     * @return string
     */
    public function amountOff()
    {
        if (isset($this->invoice['discount']['amount_off'])) {
            return $this->formatAmount($this->invoice['discount']['amount_off']);
        }
        return $this->formatAmount(0);
    }
    /**
     * Get the raw invoice balance amount.
     *
     * @return float
     */
    public function rawStartingBalance()
    {
        $totalItemAmount = 0;
        foreach ($this->invoice['line_items'] as $item) {
            $totalItemAmount += $item['amount'];
        }
        return $totalItemAmount;
    }
    /**
     * Get the items applied to the invoice.
     *
     * @return array
     */
    public function invoiceItems()
    {
        $items = [];
        foreach ($this->invoice['line_items'] as $item) {
            $items[] = $item;
        }
        return $items;
    }
    /**
     * Format the given amount into a string based on the user's preferences.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount);
    }
    /**
     * Update instance for the invoice.
     *
     * @param  array  $data
     */
    public function update(array $data)
    {
        $data['customer'] = $this->owner->paystack_id;
 
        return PaystackService::updateInvoice($this->invoice['id'], $data);

    }
    /**
     * Statud for this invoice instance.
     *
     */
    public function status()
    {
        return $this->invoice['status'];
    }
    /**
     * Verify this invoice instance.
     *
     */
    public function verify()
    {
        return PaystackService::verifyInvoice($this->invoice['request_code']);
    }
    /**
     * Notify the customer for this invoice instance.
     *
     */
    public function notify()
    {
        return PaystackService::notifyInvoice($this->invoice['id']);
    }
    /**
     * Finalize this draft invoice instance.
     *
     */
    public function finalize()
    {
        if ($this->status() === 'draft') {
            return PaystackService::finalizeInvoice($this->invoice['id']);
        }
        return $this->notify();
    }
    /**
     * Archive this invoice instance.
     *
     */
    public function archive()
    {
        return PaystackService::archiveInvoice($this->invoice['id']);
    }
    /**
     * Get the View instance for the invoice.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\View\View
     */
    public function view(array $data): ViewContract
    {
        return View::make('cashier::receipt', array_merge(
            $data, ['invoice' => $this, 'owner' => $this->owner, 'user' => $this->owner]
        ));
    }
    /**
     * Capture the invoice as a PDF and return the raw bytes.
     *
     * @param  array  $data
     * @return string
     * @throws \Throwable
     */
    public function pdf(array $data)
    {
        if (! defined('DOMPDF_ENABLE_AUTOLOAD')) {
            define('DOMPDF_ENABLE_AUTOLOAD', false);
        }
        if (file_exists($configPath = base_path().'/vendor/dompdf/dompdf/dompdf_config.inc.php')) {
            require_once $configPath;
        }
        $dompdf = new Dompdf;
        $dompdf->loadHtml($this->view($data)->render());
        $dompdf->render();
        return $dompdf->output();
    }
    /**
     * Create an invoice download response.
     *
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Throwable
     */
    public function download(array $data): Response
    {
        $filename = $data['product'].'_'.$this->date()->month.'_'.$this->date()->year.'.pdf';
        return new Response($this->pdf($data), 200, [
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Get the Paystack invoice instance.
     *
     * @return array
     */
    public function asPaystackInvoice()
    {
        return $this->invoice;
    }

    /**
     * Dynamically get values from the Paystack invoice.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->invoice[$key];
    }
}