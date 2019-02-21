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
        $carbon = Carbon::instance($this->invoice->created_at);
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
        return max(0, $this->invoice->amount);
    }
    /**
     * Get the total of the invoice (before discounts).
     *
     * @return string
     */
    public function subtotal()
    {
        return $this->formatAmount(
            max(0, $this->invoice->amount + $this->discountAmount())
        );
    }
    /**
     * Determine if the invoice has any add-ons.
     *
     * @return bool
     */
    public function hasItems()
    {
        return count($this->invoice->items) > 0;
    }
    /**
     * Get the discount amount.
     *
     * @return string
     */
    public function item()
    {
        return $this->formatAmount($this->itemAmount());
    }
    /**
     * Get the raw item amount.
     *
     * @return float
     */
    public function itemAmount()
    {
        $totalAddOn = 0;
        foreach ($this->invoice->items as $item) {
            $totalItemAmount += $item->amount;
        }
        return (float) $totalItemAmount;
    }
    /**
     * Get the items applied to the invoice.
     *
     * @return array
     */
    public function items()
    {
        $items = [];
        foreach ($this->invoice->line_items as $item) {
            $items[] = $item;
        }
        return $items;
    }
   
    /**
     * Get the raw discount amount.
     *
     * @return float
     */
    public function discountAmount()
    {
        $totalDiscount = 0;
        // Paystack give us discount amount
        return (float) $totalDiscount;
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
 
        return PaystackService::updateInvoice($this->invoice->id, $data);

    }
    /**
     * Verify this invoice instance.
     *
     */
    public function verify()
    {
        return PaystackService::verifyInvoice($this->invoice->id);
    }
    /**
     * Notify the customer for this invoice instance.
     *
     */
    public function notify()
    {
        return PaystackService::notifyInvoice($this->invoice->id);
    }
    /**
     * Finalize a draft instance for the invoice.
     *
     */
    public function finalize()
    {
        return PaystackService::finalizeInvoice($this->invoice->id);
    }
    /**
     * Finalize a draft instance for the invoice.
     *
     */
    public function archive()
    {
        return PaystackService::archiveInvoice($this->invoice->id);
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
     * @return \Paystack\invoice
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
        return $this->invoice->{$key};
    }
}