<?php

namespace App\Console\Commands;

use App\Models\Retainer;
use App\Models\Invoice;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:generate-retainer-invoices')]
#[Description('Automatically generate draft invoices for retainers due for renewal')]
class GenerateRetainerInvoices extends Command
{
    public function handle()
    {
        $today = Carbon::today()->format('Y-m-d');
        
        $this->info("Checking for retainers due for renewal on or before {$today}...");

        $retainers = Retainer::whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('renewal_date')
            ->whereDate('renewal_date', '<=', $today)
            ->get();

        if ($retainers->isEmpty()) {
            $this->info("No retainers found for auto-billing today.");
            return;
        }

        $count = 0;

        foreach ($retainers as $retainer) {
            // Generate Invoice Number
            $nextId = Invoice::max('id') + 1;
            $invoiceNumber = 'INV-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
            
            // Create Invoice
            $invoice = Invoice::create([
                'client_id' => $retainer->client_id,
                'retainer_id' => $retainer->id,
                'business_id' => $retainer->business_id, // assuming it's available
                'invoice_number' => $invoiceNumber,
                'title' => "Retainer Cycle: {$retainer->name} - " . ucfirst($retainer->billing_cycle),
                'status' => 'draft',
                'issue_date' => $today,
                'due_date' => Carbon::parse($today)->addDays(7)->format('Y-m-d'), // Default 7 days
                'subtotal' => $retainer->amount,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total' => $retainer->amount,
                'amount_paid' => 0,
                'notes' => "Auto-generated invoice for {$retainer->name} renewal.",
            ]);

            // Add an invoice item for the retainer fee
            $invoice->items()->create([
                'description' => "Retainer Fee (" . ucfirst($retainer->billing_cycle) . ")",
                'quantity' => 1,
                'unit_price' => $retainer->amount,
                'amount' => $retainer->amount,
            ]);

            // Bump renewal date based on billing cycle
            $currentRenewal = Carbon::parse($retainer->renewal_date);
            if ($retainer->billing_cycle === 'monthly') {
                $currentRenewal->addMonth();
            } elseif ($retainer->billing_cycle === 'quarterly') {
                $currentRenewal->addMonths(3);
            } elseif ($retainer->billing_cycle === 'yearly') {
                $currentRenewal->addYear();
            } else {
                $currentRenewal->addMonth(); // Fallback
            }

            $retainer->update([
                'renewal_date' => $currentRenewal->format('Y-m-d')
            ]);

            // Log activity
            ActivityLog::create([
                'description' => "System auto-generated Draft Invoice {$invoiceNumber} for Retainer Renewal.",
                'subject_id' => $retainer->id,
                'subject_type' => Retainer::class,
            ]);

            $count++;
            $this->info("Generated Invoice {$invoiceNumber} for Retainer: {$retainer->name}");
        }

        $this->info("Completed. {$count} invoice(s) generated.");
    }
}
