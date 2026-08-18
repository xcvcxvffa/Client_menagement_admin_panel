<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfController extends Controller
{
    public function download(Invoice $invoice)
    {
        if ($invoice->business_id !== auth()->user()->current_business_id) {
            abort(403, 'Unauthorized action.');
        }

        $invoice->load(['client', 'items', 'payments']);
        
        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));
        
        return $pdf->download("Invoice-{$invoice->invoice_number}.pdf");
    }
}
