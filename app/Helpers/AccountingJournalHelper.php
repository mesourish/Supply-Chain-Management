<?php

namespace App\Helpers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Invoice;
use App\Models\AccountPayable;
use App\Models\PaymentLog;
use App\Models\Shipment;
use App\Models\ManufacturingOrder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountingJournalHelper
{
    /**
     * Standard Chart of Accounts definitions.
     */
    public static $standardAccounts = [
        '10100' => ['name' => 'Cash & Bank', 'type' => 'asset'],
        '12000' => ['name' => 'Accounts Receivable', 'type' => 'asset'],
        '14000' => ['name' => 'Inventory Asset', 'type' => 'asset'],
        '21000' => ['name' => 'Accounts Payable', 'type' => 'liability'],
        '41000' => ['name' => 'Sales Revenue', 'type' => 'revenue'],
        '51000' => ['name' => 'Cost of Goods Sold (COGS)', 'type' => 'expense'],
        '52000' => ['name' => 'Manufacturing Expense', 'type' => 'expense'],
    ];

    /**
     * Ensure the Chart of Accounts is initialized in the DB.
     */
    public static function ensureAccountsExist(): void
    {
        foreach (self::$standardAccounts as $code => $data) {
            Account::firstOrCreate(
                ['code' => $code],
                ['name' => $data['name'], 'account_type' => $data['type'], 'is_active' => true]
            );
        }
    }

    /**
     * Helper to create a balanced journal entry.
     */
    public static function createJournalEntry(string $description, string $reference, array $lines, ?string $date = null): ?JournalEntry
    {
        return DB::transaction(function () use ($description, $reference, $lines, $date) {
            self::ensureAccountsExist();

            $postingDate = $date ? Carbon::parse($date) : now();
            
            // Check if entry already exists to prevent duplicates
            $exists = JournalEntry::where('reference_source', $reference)->exists();
            if ($exists) {
                return null;
            }

            $nextId = JournalEntry::max('id') + 1;
            $entryNumber = 'JV-' . now()->format('Y') . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'reference_source' => $reference,
                'posting_date' => $postingDate->toDateString(),
                'description' => $description,
            ]);

            $debitSum = 0;
            $creditSum = 0;

            foreach ($lines as $line) {
                $account = Account::where('code', $line['account_code'])->first();
                if (!$account) {
                    throw new \Exception("Ledger Account with code {$line['account_code']} not found.");
                }

                $debit = $line['debit'] ?? 0.00;
                $credit = $line['credit'] ?? 0.00;

                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $account->id,
                    'debit_amount' => $debit,
                    'credit_amount' => $credit,
                ]);

                $debitSum += $debit;
                $creditSum += $credit;
            }

            // Verify entry balances
            if (abs($debitSum - $creditSum) > 0.001) {
                throw new \Exception("Journal Entry {$entryNumber} is unbalanced. Debits: {$debitSum}, Credits: {$creditSum}.");
            }

            return $entry;
        });
    }

    /**
     * Trigger: Invoice issued.
     * Debit Accounts Receivable (12000) / Credit Sales Revenue (41000)
     */
    public static function postInvoiceIssued(Invoice $invoice): void
    {
        $ref = 'Invoice-' . $invoice->id;
        $desc = "Issued customer invoice INV-#{$invoice->id} for Sales Order SO-#{$invoice->sales_order_id}";

        $lines = [
            ['account_code' => '12000', 'debit' => $invoice->amount, 'credit' => 0.00],
            ['account_code' => '41000', 'debit' => 0.00, 'credit' => $invoice->amount],
        ];

        self::createJournalEntry($desc, $ref, $lines, $invoice->created_at);
    }

    /**
     * Trigger: Goods Receipt partial/full invoice generated (AccountPayable created).
     * Debit Inventory Asset (14000) / Credit Accounts Payable (21000)
     */
    public static function postGoodsReceiptReceived(AccountPayable $ap): void
    {
        $ref = 'AP-' . $ap->id;
        $desc = "Inventory asset receipt from Goods Receipt Note (PO-#{$ap->purchase_order_id})";

        $lines = [
            ['account_code' => '14000', 'debit' => $ap->amount, 'credit' => 0.00],
            ['account_code' => '21000', 'debit' => 0.00, 'credit' => $ap->amount],
        ];

        self::createJournalEntry($desc, $ref, $lines, $ap->created_at);
    }

    /**
     * Trigger: Payment transaction logged.
     */
    public static function postPaymentLogged(PaymentLog $log): void
    {
        $ref = 'PaymentLog-' . $log->id;
        
        if ($log->account_receivable_id) {
            // Customer Payment Received
            // Debit Cash (10100) / Credit Accounts Receivable (12000)
            $desc = "Customer payment received for Accounts Receivable AR-#{$log->account_receivable_id}";
            $lines = [
                ['account_code' => '10100', 'debit' => $log->amount, 'credit' => 0.00],
                ['account_code' => '12000', 'debit' => 0.00, 'credit' => $log->amount],
            ];
            self::createJournalEntry($desc, $ref, $lines, $log->payment_date);
        } elseif ($log->account_payable_id) {
            // Vendor Bill Paid
            // Debit Accounts Payable (21000) / Credit Cash (10100)
            $desc = "Vendor payment processed for Accounts Payable AP-#{$log->account_payable_id}";
            $lines = [
                ['account_code' => '21000', 'debit' => $log->amount, 'credit' => 0.00],
                ['account_code' => '10100', 'debit' => 0.00, 'credit' => $log->amount],
            ];
            self::createJournalEntry($desc, $ref, $lines, $log->payment_date);
        }
    }

    /**
     * Trigger: Shipment status set to delivered.
     * Debit Cost of Goods Sold (51000) / Credit Inventory Asset (14000)
     */
    public static function postShipmentDelivered(Shipment $shipment): void
    {
        $ref = 'ShipmentDelivered-' . $shipment->id;
        $desc = "COGS auto-posting upon delivery of Shipment SHP-{$shipment->id} (SO-#{$shipment->sales_order_id})";

        // Calculate Cost of Goods Sold based on order item products
        $cogsTotal = 0;
        $salesOrder = $shipment->salesOrder;
        if ($salesOrder) {
            $salesOrder->load('items.product');
            foreach ($salesOrder->items as $item) {
                $product = $item->product;
                if ($product) {
                    $cogsTotal += ($item->quantity * $product->cost_price);
                }
            }
        }

        if ($cogsTotal > 0) {
            $lines = [
                ['account_code' => '51000', 'debit' => $cogsTotal, 'credit' => 0.00],
                ['account_code' => '14000', 'debit' => 0.00, 'credit' => $cogsTotal],
            ];
            self::createJournalEntry($desc, $ref, $lines, $shipment->updated_at);
        }
    }

    /**
     * Trigger: Manufacturing Order completed.
     * Debit Inventory Asset (14000) / Credit Manufacturing Expense (52000)
     */
    public static function postManufacturingCompleted(ManufacturingOrder $mo): void
    {
        $ref = 'MOCompleted-' . $mo->id;
        $desc = "Finished goods receipt into stock for Manufacturing Order {$mo->mo_number}";

        $totalCost = 0;
        $product = $mo->product;
        if ($product) {
            $totalCost = $mo->quantity_to_produce * $product->cost_price;
        }

        if ($totalCost > 0) {
            $lines = [
                ['account_code' => '14000', 'debit' => $totalCost, 'credit' => 0.00],
                ['account_code' => '52000', 'debit' => 0.00, 'credit' => $totalCost],
            ];
            self::createJournalEntry($desc, $ref, $lines, $mo->actual_completed_date ?? now());
        }
    }
}
