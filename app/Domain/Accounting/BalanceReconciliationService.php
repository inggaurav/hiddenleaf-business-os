<?php

namespace App\Domain\Accounting;

use App\Models\AccountCreditNote;
use App\Models\AccountCustomer;
use App\Models\AccountDebitNote;
use App\Models\AccountVendor;
use App\Models\CustomerPayment;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\VendorPayment;

/**
 * Reconciles stored customer/vendor balances against their source-of-truth
 * (invoices, payments, credit/debit notes).
 *
 * The stored `balance` column is a cached derived value. This service can
 * verify it matches the canonical calculation and correct drift.
 */
class BalanceReconciliationService
{
    /**
     * Calculate the true customer balance from source records.
     *
     * balance = Σ(invoice totals) - Σ(payments) - Σ(credit notes)
     */
    public function calculateCustomerBalance(AccountCustomer $customer): Money
    {
        $invoiceTotal = Money::of(
            SalesInvoice::where('organization_id', $customer->organization_id)
                ->where('workspace_id', $customer->workspace_id)
                ->where('customer_id', $customer->id)
                ->whereNotIn('status', ['draft', 0])
                ->sum('total_amount')
        );

        $payments = Money::of(
            CustomerPayment::forWorkspace($customer->organization_id, $customer->workspace_id)
                ->where('customer_id', $customer->id)
                ->sum('amount')
        );

        $creditNotes = Money::of(
            AccountCreditNote::forWorkspace($customer->organization_id, $customer->workspace_id)
                ->where('customer_id', $customer->id)
                ->sum('amount')
        );

        return $invoiceTotal->subtract($payments)->subtract($creditNotes);
    }

    /**
     * Calculate the true vendor balance from source records.
     *
     * balance = Σ(purchase totals) - Σ(payments) - Σ(debit notes)
     */
    public function calculateVendorBalance(AccountVendor $vendor): Money
    {
        $purchaseTotal = Money::of(
            PurchaseInvoice::where('organization_id', $vendor->organization_id)
                ->where('workspace_id', $vendor->workspace_id)
                ->where('vendor_id', $vendor->id)
                ->whereNotIn('status', ['draft', 0])
                ->sum('total_amount')
        );

        $payments = Money::of(
            VendorPayment::forWorkspace($vendor->organization_id, $vendor->workspace_id)
                ->where('vendor_id', $vendor->id)
                ->sum('amount')
        );

        $debitNotes = Money::of(
            AccountDebitNote::forWorkspace($vendor->organization_id, $vendor->workspace_id)
                ->where('vendor_id', $vendor->id)
                ->sum('amount')
        );

        return $purchaseTotal->subtract($payments)->subtract($debitNotes);
    }

    /**
     * Check if the stored balance matches the calculated balance.
     */
    public function isCustomerReconciled(AccountCustomer $customer): bool
    {
        $stored = Money::of($customer->balance);
        $calculated = $this->calculateCustomerBalance($customer);

        return $stored->equals($calculated);
    }

    /**
     * Check if the stored balance matches the calculated balance.
     */
    public function isVendorReconciled(AccountVendor $vendor): bool
    {
        $stored = Money::of($vendor->balance);
        $calculated = $this->calculateVendorBalance($vendor);

        return $stored->equals($calculated);
    }

    /**
     * Force-reconcile the customer's stored balance to match the calculated value.
     */
    public function reconcileCustomer(AccountCustomer $customer): Money
    {
        $calculated = $this->calculateCustomerBalance($customer);
        $customer->update(['balance' => $calculated->toStorageString()]);

        return $calculated;
    }

    /**
     * Force-reconcile the vendor's stored balance to match the calculated value.
     */
    public function reconcileVendor(AccountVendor $vendor): Money
    {
        $calculated = $this->calculateVendorBalance($vendor);
        $vendor->update(['balance' => $calculated->toStorageString()]);

        return $calculated;
    }
}
