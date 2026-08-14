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
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative manager for customer and vendor balances.
 *
 * Guarantees that stored balance columns are strictly derived from
 * posted invoices, payments, and credit/debit notes with zero drift.
 */
class FinancialBalanceService
{
    /**
     * Compute the exact outstanding receivable balance for a customer.
     *
     * Balance = Σ(Invoices where status != draft) - Σ(CustomerPayments) - Σ(CreditNotes)
     */
    public function computeCustomerBalance(AccountCustomer $customer): Money
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
     * Compute the exact outstanding payable balance for a vendor.
     *
     * Balance = Σ(PurchaseInvoices where status != draft) - Σ(VendorPayments) - Σ(DebitNotes)
     */
    public function computeVendorBalance(AccountVendor $vendor): Money
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
     * Atomically recalculate and update a customer's cached balance.
     */
    public function syncCustomerBalance(AccountCustomer $customer): Money
    {
        return DB::transaction(function () use ($customer) {
            $locked = AccountCustomer::where('id', $customer->id)->lockForUpdate()->firstOrFail();
            $calculated = $this->computeCustomerBalance($locked);
            $locked->update(['balance' => $calculated->toStorageString()]);

            return $calculated;
        });
    }

    /**
     * Atomically recalculate and update a vendor's cached balance.
     */
    public function syncVendorBalance(AccountVendor $vendor): Money
    {
        return DB::transaction(function () use ($vendor) {
            $locked = AccountVendor::where('id', $vendor->id)->lockForUpdate()->firstOrFail();
            $calculated = $this->computeVendorBalance($locked);
            $locked->update(['balance' => $calculated->toStorageString()]);

            return $calculated;
        });
    }

    /**
     * Reconcile all customer and vendor balances across a workspace or entire database.
     *
     * @return array{customers_checked: int, customers_fixed: int, vendors_checked: int, vendors_fixed: int}
     */
    public function reconcileWorkspaceBalances(?int $workspaceId = null): array
    {
        $custQuery = AccountCustomer::query();
        $vendQuery = AccountVendor::query();

        if ($workspaceId) {
            $custQuery->where('workspace_id', $workspaceId);
            $vendQuery->where('workspace_id', $workspaceId);
        }

        $customersChecked = 0;
        $customersFixed = 0;
        $vendorsChecked = 0;
        $vendorsFixed = 0;

        foreach ($custQuery->cursor() as $customer) {
            $customersChecked++;
            $current = Money::of($customer->balance);
            $computed = $this->computeCustomerBalance($customer);
            if (! $current->equals($computed)) {
                $customer->update(['balance' => $computed->toStorageString()]);
                $customersFixed++;
            }
        }

        foreach ($vendQuery->cursor() as $vendor) {
            $vendorsChecked++;
            $current = Money::of($vendor->balance);
            $computed = $this->computeVendorBalance($vendor);
            if (! $current->equals($computed)) {
                $vendor->update(['balance' => $computed->toStorageString()]);
                $vendorsFixed++;
            }
        }

        return [
            'customers_checked' => $customersChecked,
            'customers_fixed' => $customersFixed,
            'vendors_checked' => $vendorsChecked,
            'vendors_fixed' => $vendorsFixed,
        ];
    }
}
