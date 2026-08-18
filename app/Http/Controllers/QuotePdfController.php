<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotePdfController extends Controller
{
    public function download(Quote $quote)
    {
        $quote->load(['client', 'items']);
        
        $pdf = Pdf::loadView('pdf.quote', compact('quote'));
        
        return $pdf->download("Quote-{$quote->quote_number}.pdf");
    }
}
