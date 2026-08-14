<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Granular RBAC for the WorkDo-equivalent Account surface.
 *
 * Legacy account.view/account.manage permissions remain accepted as umbrella
 * permissions so existing tenants are not locked out during the V1 migration.
 */
class EnsureAccountPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace, 403);

        $permission = $this->permissionForRoute((string) $request->route()?->getName());
        if ($permission === null) {
            return $next($request);
        }

        $user = $request->user();
        abort_unless($user, 401);

        $legacy = str_ends_with($permission, '.view') || str_ends_with($permission, '.print')
            ? 'account.view'
            : 'account.manage';

        abort_unless(
            $user->canInWorkspace($permission, $workspace)
                || $user->canInWorkspace($legacy, $workspace),
            403
        );

        return $next($request);
    }

    private function permissionForRoute(string $routeName): ?string
    {
        $name = str_replace('account-reference.', '', $routeName);

        return match (true) {
            str_starts_with($name, 'bank-accounts.') => $this->crudPermission('bank_account', $name),
            str_starts_with($name, 'account-types.') => $this->crudPermission('account_type', $name),
            str_starts_with($name, 'accounts.') => $this->crudPermission('ledger_account', $name),

            $name === 'customer-payments.outstanding' => 'account.customer_payment.view',
            $name === 'customer-payments.status' || $name === 'customer-payments.destroy' => 'account.customer_payment.void',
            $name === 'vendor-payments.outstanding' => 'account.vendor_payment.view',
            $name === 'vendor-payments.status' || $name === 'vendor-payments.destroy' => 'account.vendor_payment.void',

            $name === 'bank-transactions.index' => 'account.bank_transaction.view',
            $name === 'bank-transactions.reconcile' => 'account.bank_transaction.reconcile',

            $name === 'bank-transfers.index' => 'account.bank_transfer.view',
            $name === 'bank-transfers.draft' => 'account.bank_transfer.create',
            $name === 'bank-transfers.update' => 'account.bank_transfer.update',
            $name === 'bank-transfers.destroy' => 'account.bank_transfer.delete',
            $name === 'bank-transfers.process' => 'account.bank_transfer.process',

            str_starts_with($name, 'revenue-categories.') => $this->crudPermission('revenue_category', $name),
            str_starts_with($name, 'expense-categories.') => $this->crudPermission('expense_category', $name),

            $name === 'revenues.show' => 'account.revenue.view',
            $name === 'revenues.draft' => 'account.revenue.create',
            $name === 'revenues.update' => 'account.revenue.update',
            $name === 'revenues.destroy' => 'account.revenue.delete',
            $name === 'revenues.approve' => 'account.revenue.approve',
            $name === 'revenues.post' => 'account.revenue.post',

            $name === 'expenses.show' => 'account.expense.view',
            $name === 'expenses.draft' => 'account.expense.create',
            $name === 'expenses.update' => 'account.expense.update',
            $name === 'expenses.destroy' => 'account.expense.delete',
            $name === 'expenses.approve' => 'account.expense.approve',
            $name === 'expenses.post' => 'account.expense.post',

            $name === 'credit-notes.show' => 'account.credit_note.view',
            $name === 'credit-notes.approve' => 'account.credit_note.approve',
            $name === 'credit-notes.destroy' => 'account.credit_note.delete',
            $name === 'debit-notes.show' => 'account.debit_note.view',
            $name === 'debit-notes.approve' => 'account.debit_note.approve',
            $name === 'debit-notes.destroy' => 'account.debit_note.delete',

            str_starts_with($name, 'reports.') && str_ends_with($name, '.print') => 'account.report.print',
            str_starts_with($name, 'reports.') => 'account.report.view',
            default => null,
        };
    }

    private function crudPermission(string $resource, string $routeName): string
    {
        $action = match (true) {
            str_ends_with($routeName, '.index'), str_ends_with($routeName, '.show'), str_ends_with($routeName, '.edit'), str_ends_with($routeName, '.api-list') => 'view',
            str_ends_with($routeName, '.store') => 'create',
            str_ends_with($routeName, '.update') => 'update',
            str_ends_with($routeName, '.destroy') => 'delete',
            default => 'view',
        };

        return "account.{$resource}.{$action}";
    }
}
