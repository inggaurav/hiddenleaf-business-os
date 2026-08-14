<?php

namespace App\Domain\Accounting;

use App\Models\AccountCreditNote;
use App\Models\AccountCustomer;
use App\Models\AccountDebitNote;
use App\Models\AccountVendor;
use App\Models\CustomerPayment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\VendorPayment;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Tenant-scoped Account reporting service implementing the business outcomes
 * exposed by the WorkDo Account report suite without coupling HiddenLeaf to
 * WorkDo's view or routing architecture.
 */
class AccountReportService
{
    public function invoiceAging(Workspace $workspace, ?string $asOfDate = null): array
    {
        $asOf = Carbon::parse($asOfDate ?: now()->toDateString())->endOfDay();
        $customers = AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)
            ->get()->keyBy('id');

        $rows = SalesInvoice::where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('status', ['draft', 0, 'void'])
            ->whereDate('issue_date', '<=', $asOf->toDateString())
            ->orderBy('due_date')
            ->get()
            ->map(function (SalesInvoice $invoice) use ($workspace, $asOf, $customers) {
                $outstanding = $this->salesOutstanding($workspace, $invoice);
                if ($outstanding->isZero()) {
                    return null;
                }

                $due = $invoice->due_date ? Carbon::parse($invoice->due_date) : Carbon::parse($invoice->issue_date);
                $daysOverdue = max(0, (int) $due->diffInDays($asOf, false));

                return [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_id,
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $customers->get($invoice->customer_id)?->name,
                    'issue_date' => optional($invoice->issue_date)->toDateString(),
                    'due_date' => optional($invoice->due_date)->toDateString(),
                    'days_overdue' => $daysOverdue,
                    'outstanding' => $outstanding->toFloat(),
                    'bucket' => $this->agingBucket($daysOverdue),
                ];
            })
            ->filter()
            ->values();

        return $this->withAgingTotals($rows, 'invoice');
    }

    public function billAging(Workspace $workspace, ?string $asOfDate = null): array
    {
        $asOf = Carbon::parse($asOfDate ?: now()->toDateString())->endOfDay();
        $vendors = AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)
            ->get()->keyBy('id');

        $rows = PurchaseInvoice::where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('status', ['draft', 0, 'void'])
            ->whereDate('purchase_date', '<=', $asOf->toDateString())
            ->orderBy('due_date')
            ->get()
            ->map(function (PurchaseInvoice $invoice) use ($workspace, $asOf, $vendors) {
                $outstanding = $this->purchaseOutstanding($workspace, $invoice);
                if ($outstanding->isZero()) {
                    return null;
                }

                $due = $invoice->due_date ? Carbon::parse($invoice->due_date) : Carbon::parse($invoice->purchase_date);
                $daysOverdue = max(0, (int) $due->diffInDays($asOf, false));

                return [
                    'bill_id' => $invoice->id,
                    'bill_number' => $invoice->invoice_id,
                    'vendor_id' => $invoice->vendor_id,
                    'vendor_name' => $vendors->get($invoice->vendor_id)?->name,
                    'purchase_date' => optional($invoice->purchase_date)->toDateString(),
                    'due_date' => optional($invoice->due_date)->toDateString(),
                    'days_overdue' => $daysOverdue,
                    'outstanding' => $outstanding->toFloat(),
                    'bucket' => $this->agingBucket($daysOverdue),
                ];
            })
            ->filter()
            ->values();

        return $this->withAgingTotals($rows, 'bill');
    }

    public function taxSummary(Workspace $workspace, ?string $fromDate = null, ?string $toDate = null): array
    {
        $from = $fromDate ?: now()->startOfYear()->toDateString();
        $to = $toDate ?: now()->endOfYear()->toDateString();

        $salesTax = Money::of(
            SalesInvoiceItem::whereHas('invoice', function ($query) use ($workspace, $from, $to) {
                $query->where('organization_id', $workspace->organization_id)
                    ->where('workspace_id', $workspace->id)
                    ->whereNotIn('status', ['draft', 0, 'void'])
                    ->whereBetween('issue_date', [$from, $to]);
            })->sum('tax')
        );

        $purchaseTax = Money::of(
            PurchaseInvoiceItem::whereHas('invoice', function ($query) use ($workspace, $from, $to) {
                $query->where('organization_id', $workspace->organization_id)
                    ->where('workspace_id', $workspace->id)
                    ->whereNotIn('status', ['draft', 0, 'void'])
                    ->whereBetween('purchase_date', [$from, $to]);
            })->sum('tax')
        );

        return [
            'from_date' => $from,
            'to_date' => $to,
            'sales_tax' => $salesTax->toFloat(),
            'purchase_tax' => $purchaseTax->toFloat(),
            'net_tax' => $salesTax->subtract($purchaseTax)->toFloat(),
        ];
    }

    public function customerBalanceSummary(Workspace $workspace, ?string $asOfDate = null, bool $showZero = false): array
    {
        $asOf = $asOfDate ?: now()->toDateString();

        $rows = AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)
            ->orderBy('name')
            ->get()
            ->map(function (AccountCustomer $customer) use ($workspace, $asOf) {
                $balance = $this->customerBalanceAsOf($workspace, $customer->id, $asOf);

                return [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'email' => $customer->email,
                    'balance' => $balance->toFloat(),
                ];
            })
            ->filter(fn (array $row) => $showZero || Money::of($row['balance'])->isPositive() || Money::of($row['balance'])->isNegative())
            ->values();

        return [
            'as_of_date' => $asOf,
            'rows' => $rows,
            'total_balance' => $this->sumRows($rows, 'balance')->toFloat(),
        ];
    }

    public function vendorBalanceSummary(Workspace $workspace, ?string $asOfDate = null, bool $showZero = false): array
    {
        $asOf = $asOfDate ?: now()->toDateString();

        $rows = AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)
            ->orderBy('name')
            ->get()
            ->map(function (AccountVendor $vendor) use ($workspace, $asOf) {
                $balance = $this->vendorBalanceAsOf($workspace, $vendor->id, $asOf);

                return [
                    'vendor_id' => $vendor->id,
                    'vendor_name' => $vendor->name,
                    'email' => $vendor->email,
                    'balance' => $balance->toFloat(),
                ];
            })
            ->filter(fn (array $row) => $showZero || Money::of($row['balance'])->isPositive() || Money::of($row['balance'])->isNegative())
            ->values();

        return [
            'as_of_date' => $asOf,
            'rows' => $rows,
            'total_balance' => $this->sumRows($rows, 'balance')->toFloat(),
        ];
    }

    public function customerDetail(Workspace $workspace, int $customerId, ?string $startDate = null, ?string $endDate = null): array
    {
        $customer = AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($customerId);

        $invoices = SalesInvoice::where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where('customer_id', $customer->id)
            ->when($startDate, fn ($q) => $q->whereDate('issue_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('issue_date', '<=', $endDate))
            ->orderBy('issue_date')
            ->get()
            ->map(fn (SalesInvoice $invoice) => [
                'type' => 'invoice',
                'id' => $invoice->id,
                'reference' => $invoice->invoice_id,
                'date' => optional($invoice->issue_date)->toDateString(),
                'debit' => Money::of($invoice->total_amount)->toFloat(),
                'credit' => 0.0,
                'status' => (string) $invoice->status,
            ]);

        $payments = CustomerPayment::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'void')
            ->when($startDate, fn ($q) => $q->whereDate('payment_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('payment_date', '<=', $endDate))
            ->orderBy('payment_date')
            ->get()
            ->map(fn (CustomerPayment $payment) => [
                'type' => 'payment',
                'id' => $payment->id,
                'reference' => $payment->reference,
                'date' => optional($payment->payment_date)->toDateString(),
                'debit' => 0.0,
                'credit' => Money::of($payment->amount)->toFloat(),
                'status' => $payment->status,
            ]);

        $credits = AccountCreditNote::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('customer_id', $customer->id)
            ->when($startDate, fn ($q) => $q->whereDate('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('date', '<=', $endDate))
            ->orderBy('date')
            ->get()
            ->map(fn (AccountCreditNote $note) => [
                'type' => 'credit_note',
                'id' => $note->id,
                'reference' => 'CN-'.$note->id,
                'date' => optional($note->date)->toDateString(),
                'debit' => 0.0,
                'credit' => Money::of($note->amount)->toFloat(),
                'status' => $note->status,
            ]);

        $transactions = $invoices->concat($payments)->concat($credits)->sortBy('date')->values();

        return [
            'customer' => $customer,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'transactions' => $transactions,
            'balance' => $this->customerBalanceAsOf($workspace, $customer->id, $endDate ?: now()->toDateString())->toFloat(),
        ];
    }

    public function vendorDetail(Workspace $workspace, int $vendorId, ?string $startDate = null, ?string $endDate = null): array
    {
        $vendor = AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($vendorId);

        $bills = PurchaseInvoice::where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where('vendor_id', $vendor->id)
            ->when($startDate, fn ($q) => $q->whereDate('purchase_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('purchase_date', '<=', $endDate))
            ->orderBy('purchase_date')
            ->get()
            ->map(fn (PurchaseInvoice $invoice) => [
                'type' => 'bill',
                'id' => $invoice->id,
                'reference' => $invoice->invoice_id,
                'date' => optional($invoice->purchase_date)->toDateString(),
                'debit' => 0.0,
                'credit' => Money::of($invoice->total_amount)->toFloat(),
                'status' => (string) $invoice->status,
            ]);

        $payments = VendorPayment::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('vendor_id', $vendor->id)
            ->where('status', '!=', 'void')
            ->when($startDate, fn ($q) => $q->whereDate('payment_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('payment_date', '<=', $endDate))
            ->orderBy('payment_date')
            ->get()
            ->map(fn (VendorPayment $payment) => [
                'type' => 'payment',
                'id' => $payment->id,
                'reference' => $payment->reference,
                'date' => optional($payment->payment_date)->toDateString(),
                'debit' => Money::of($payment->amount)->toFloat(),
                'credit' => 0.0,
                'status' => $payment->status,
            ]);

        $debits = AccountDebitNote::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('vendor_id', $vendor->id)
            ->when($startDate, fn ($q) => $q->whereDate('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('date', '<=', $endDate))
            ->orderBy('date')
            ->get()
            ->map(fn (AccountDebitNote $note) => [
                'type' => 'debit_note',
                'id' => $note->id,
                'reference' => 'DN-'.$note->id,
                'date' => optional($note->date)->toDateString(),
                'debit' => Money::of($note->amount)->toFloat(),
                'credit' => 0.0,
                'status' => $note->status,
            ]);

        $transactions = $bills->concat($payments)->concat($debits)->sortBy('date')->values();

        return [
            'vendor' => $vendor,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'transactions' => $transactions,
            'balance' => $this->vendorBalanceAsOf($workspace, $vendor->id, $endDate ?: now()->toDateString())->toFloat(),
        ];
    }

    public function salesOutstanding(Workspace $workspace, SalesInvoice $invoice): Money
    {
        $payments = Money::of(
            CustomerPayment::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('invoice_id', $invoice->id)
                ->where('status', '!=', 'void')
                ->sum('amount')
        );
        $credits = Money::of(
            AccountCreditNote::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('invoice_id', $invoice->id)
                ->whereNotIn('status', ['void', 'rejected'])
                ->sum('amount')
        );

        return Money::of($invoice->total_amount)->subtract($payments)->subtract($credits)->max(Money::zero());
    }

    public function purchaseOutstanding(Workspace $workspace, PurchaseInvoice $invoice): Money
    {
        $payments = Money::of(
            VendorPayment::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('purchase_invoice_id', $invoice->id)
                ->where('status', '!=', 'void')
                ->sum('amount')
        );
        $debits = Money::of(
            AccountDebitNote::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('purchase_invoice_id', $invoice->id)
                ->whereNotIn('status', ['void', 'rejected'])
                ->sum('amount')
        );

        return Money::of($invoice->total_amount)->subtract($payments)->subtract($debits)->max(Money::zero());
    }

    private function customerBalanceAsOf(Workspace $workspace, int $customerId, string $asOf): Money
    {
        $invoices = Money::of(
            SalesInvoice::where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)
                ->where('customer_id', $customerId)
                ->whereNotIn('status', ['draft', 0, 'void'])
                ->whereDate('issue_date', '<=', $asOf)
                ->sum('total_amount')
        );
        $payments = Money::of(
            CustomerPayment::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('customer_id', $customerId)
                ->where('status', '!=', 'void')
                ->whereDate('payment_date', '<=', $asOf)
                ->sum('amount')
        );
        $credits = Money::of(
            AccountCreditNote::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('customer_id', $customerId)
                ->whereNotIn('status', ['void', 'rejected'])
                ->whereDate('date', '<=', $asOf)
                ->sum('amount')
        );

        return $invoices->subtract($payments)->subtract($credits);
    }

    private function vendorBalanceAsOf(Workspace $workspace, int $vendorId, string $asOf): Money
    {
        $bills = Money::of(
            PurchaseInvoice::where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)
                ->where('vendor_id', $vendorId)
                ->whereNotIn('status', ['draft', 0, 'void'])
                ->whereDate('purchase_date', '<=', $asOf)
                ->sum('total_amount')
        );
        $payments = Money::of(
            VendorPayment::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('vendor_id', $vendorId)
                ->where('status', '!=', 'void')
                ->whereDate('payment_date', '<=', $asOf)
                ->sum('amount')
        );
        $debits = Money::of(
            AccountDebitNote::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('vendor_id', $vendorId)
                ->whereNotIn('status', ['void', 'rejected'])
                ->whereDate('date', '<=', $asOf)
                ->sum('amount')
        );

        return $bills->subtract($payments)->subtract($debits);
    }

    private function agingBucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => '1_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => '90_plus',
        };
    }

    private function withAgingTotals(Collection $rows, string $type): array
    {
        $buckets = [
            'current' => Money::zero(),
            '1_30' => Money::zero(),
            '31_60' => Money::zero(),
            '61_90' => Money::zero(),
            '90_plus' => Money::zero(),
        ];

        foreach ($rows as $row) {
            $buckets[$row['bucket']] = $buckets[$row['bucket']]->add(Money::of($row['outstanding']));
        }

        return [
            'type' => $type,
            'rows' => $rows,
            'totals' => collect($buckets)->map(fn (Money $money) => $money->toFloat())->all(),
            'grand_total' => $this->sumRows($rows, 'outstanding')->toFloat(),
        ];
    }

    private function sumRows(Collection $rows, string $key): Money
    {
        return $rows->reduce(
            fn (Money $total, array $row) => $total->add(Money::of($row[$key] ?? 0)),
            Money::zero()
        );
    }
}
