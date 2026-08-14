<?php

namespace App\Domain\Accounting;

use App\Models\AccountCustomer;
use App\Models\AccountVendor;
use App\Models\LedgerAccount;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Centralized resolver for tenant-scoped financial entities.
 *
 * Every user-supplied foreign ID in the accounting domain MUST pass through
 * this service to prevent cross-tenant (IDOR) mutations.
 */
class TenantFinancialResolver
{
    /**
     * Resolve an AccountCustomer scoped to the active workspace.
     *
     * @throws ModelNotFoundException|HttpException
     */
    public function resolveCustomer(Workspace $workspace, int $id): AccountCustomer
    {
        return AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)
            ->findOrFail($id);
    }

    /**
     * Resolve an AccountVendor scoped to the active workspace.
     *
     * @throws ModelNotFoundException|HttpException
     */
    public function resolveVendor(Workspace $workspace, int $id): AccountVendor
    {
        return AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)
            ->findOrFail($id);
    }

    /**
     * Resolve a SalesInvoice scoped to the active workspace.
     *
     * @throws ModelNotFoundException|HttpException
     */
    public function resolveInvoice(Workspace $workspace, int $id): SalesInvoice
    {
        return SalesInvoice::where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->findOrFail($id);
    }

    /**
     * Resolve a PurchaseInvoice scoped to the active workspace.
     *
     * @throws ModelNotFoundException|HttpException
     */
    public function resolvePurchaseInvoice(Workspace $workspace, int $id): PurchaseInvoice
    {
        return PurchaseInvoice::where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->findOrFail($id);
    }

    /**
     * Resolve a LedgerAccount scoped to the active workspace.
     *
     * @throws ModelNotFoundException|HttpException
     */
    public function resolveLedgerAccount(Workspace $workspace, int $id): LedgerAccount
    {
        return LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
            ->findOrFail($id);
    }

    /**
     * Resolve a SalesInvoice with pessimistic lock for payment operations.
     *
     * @throws ModelNotFoundException|HttpException
     */
    public function resolveInvoiceForPayment(Workspace $workspace, int $id): SalesInvoice
    {
        return SalesInvoice::where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->lockForUpdate()
            ->findOrFail($id);
    }

    /**
     * Resolve a PurchaseInvoice with pessimistic lock for payment operations.
     *
     * @throws ModelNotFoundException|HttpException
     */
    public function resolvePurchaseInvoiceForPayment(Workspace $workspace, int $id): PurchaseInvoice
    {
        return PurchaseInvoice::where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->lockForUpdate()
            ->findOrFail($id);
    }

    /**
     * Assert that a customer_id matches the invoice's customer_id when both are supplied.
     *
     * @throws HttpException
     */
    public function assertCustomerMatchesInvoice(int $customerId, SalesInvoice $invoice): void
    {
        if ((int) $invoice->customer_id !== $customerId) {
            abort(422, 'Supplied customer_id does not match the invoice customer.');
        }
    }

    /**
     * Assert that a vendor_id matches the purchase invoice's vendor_id when both are supplied.
     *
     * @throws HttpException
     */
    public function assertVendorMatchesBill(int $vendorId, PurchaseInvoice $bill): void
    {
        if ((int) $bill->vendor_id !== $vendorId) {
            abort(422, 'Supplied vendor_id does not match the purchase invoice vendor.');
        }
    }
}
