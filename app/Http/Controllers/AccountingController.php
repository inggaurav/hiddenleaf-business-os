<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\AccountDashboardService;
use App\Domain\Accounting\FinancialBalanceService;
use App\Domain\Accounting\LedgerService;
use App\Domain\Accounting\Money;
use App\Domain\Accounting\TenantFinancialResolver;
use App\Models\AccountBankTransfer;
use App\Models\AccountCreditNote;
use App\Models\AccountCustomer;
use App\Models\AccountDebitNote;
use App\Models\AccountExpense;
use App\Models\AccountRevenue;
use App\Models\AccountType;
use App\Models\AccountVendor;
use App\Models\BankReconciliation;
use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\VendorPayment;
use App\Models\Workspace;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountingController extends Controller
{
    public function __construct(
        protected AuditLogger $audit
    ) {}

    public function dashboard(Request $request, AccountDashboardService $dashboardService)
    {
        $workspace = $this->workspace($request, 'account.view');
        $data = $dashboardService->getMetrics($workspace);

        return Inertia::render('Accounting/Dashboard', ['metrics' => $data['stats']] + $data);
    }

    // ==========================================
    // CUSTOMERS
    // ==========================================
    public function customers(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');
        $customers = AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)
            ->withCount('invoices')
            ->latest()
            ->paginate(30);

        return Inertia::render('Accounting/Customers/Index', [
            'customers' => $customers,
        ]);
    }

    public function storeCustomer(Request $request)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:100'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_zip' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string'],
            'shipping_name' => ['nullable', 'string', 'max:255'],
            'shipping_country' => ['nullable', 'string', 'max:100'],
            'shipping_state' => ['nullable', 'string', 'max:100'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_phone' => ['nullable', 'string', 'max:50'],
            'shipping_zip' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string'],
        ]);

        $customer = AccountCustomer::create($data + [
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'customer.created', 'customer', (string) $customer->id, ['name' => $customer->name]);

        return back()->with('success', 'Customer created successfully.');
    }

    public function updateCustomer(Request $request, AccountCustomer $customer)
    {
        $workspace = $this->workspace($request, 'account.manage');
        abort_unless((int) $customer->organization_id === (int) $workspace->organization_id && (int) $customer->workspace_id === (int) $workspace->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:100'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_zip' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string'],
            'shipping_name' => ['nullable', 'string', 'max:255'],
            'shipping_country' => ['nullable', 'string', 'max:100'],
            'shipping_state' => ['nullable', 'string', 'max:100'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_phone' => ['nullable', 'string', 'max:50'],
            'shipping_zip' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $customer->update($data);

        return back()->with('success', 'Customer updated.');
    }

    public function destroyCustomer(Request $request, AccountCustomer $customer)
    {
        $workspace = $this->workspace($request, 'account.manage');
        abort_unless((int) $customer->organization_id === (int) $workspace->organization_id && (int) $customer->workspace_id === (int) $workspace->id, 404);

        $hasHistory = CustomerPayment::where('customer_id', $customer->id)->exists()
            || SalesInvoice::where('customer_id', $customer->id)->exists()
            || AccountCreditNote::where('customer_id', $customer->id)->exists()
            || AccountRevenue::where('customer_id', $customer->id)->exists();

        abort_if($hasHistory, 422, 'Cannot delete customer with existing financial history. Deactivate the customer instead.');

        $customer->delete();

        return back()->with('success', 'Customer deleted.');
    }

    // ==========================================
    // VENDORS
    // ==========================================
    public function vendors(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');
        $vendors = AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)
            ->withCount('purchaseInvoices')
            ->latest()
            ->paginate(30);

        return Inertia::render('Accounting/Vendors/Index', [
            'vendors' => $vendors,
        ]);
    }

    public function storeVendor(Request $request)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:100'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_zip' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string'],
            'shipping_name' => ['nullable', 'string', 'max:255'],
            'shipping_country' => ['nullable', 'string', 'max:100'],
            'shipping_state' => ['nullable', 'string', 'max:100'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_phone' => ['nullable', 'string', 'max:50'],
            'shipping_zip' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string'],
        ]);

        $vendor = AccountVendor::create($data + [
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'vendor.created', 'vendor', (string) $vendor->id, ['name' => $vendor->name]);

        return back()->with('success', 'Vendor created successfully.');
    }

    public function updateVendor(Request $request, AccountVendor $vendor)
    {
        $workspace = $this->workspace($request, 'account.manage');
        abort_unless((int) $vendor->organization_id === (int) $workspace->organization_id && (int) $vendor->workspace_id === (int) $workspace->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:100'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_zip' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string'],
            'shipping_name' => ['nullable', 'string', 'max:255'],
            'shipping_country' => ['nullable', 'string', 'max:100'],
            'shipping_state' => ['nullable', 'string', 'max:100'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_phone' => ['nullable', 'string', 'max:50'],
            'shipping_zip' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $vendor->update($data);

        return back()->with('success', 'Vendor updated.');
    }

    public function destroyVendor(Request $request, AccountVendor $vendor)
    {
        $workspace = $this->workspace($request, 'account.manage');
        abort_unless((int) $vendor->organization_id === (int) $workspace->organization_id && (int) $vendor->workspace_id === (int) $workspace->id, 404);

        $hasHistory = VendorPayment::where('vendor_id', $vendor->id)->exists()
            || PurchaseInvoice::where('vendor_id', $vendor->id)->exists()
            || AccountDebitNote::where('vendor_id', $vendor->id)->exists()
            || AccountExpense::where('vendor_id', $vendor->id)->exists();

        abort_if($hasHistory, 422, 'Cannot delete vendor with existing financial history. Deactivate the vendor instead.');

        $vendor->delete();

        return back()->with('success', 'Vendor deleted.');
    }

    // ==========================================
    // CUSTOMER PAYMENTS
    // ==========================================
    public function customerPayments(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');
        $payments = CustomerPayment::forWorkspace($workspace->organization_id, $workspace->id)
            ->with(['customer', 'invoice', 'account'])
            ->latest('payment_date')
            ->paginate(30);

        $customers = AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)->get();
        $invoices = SalesInvoice::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->whereNotIn('status', ['paid', 3])->get();
        $accounts = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->get();

        return Inertia::render('Accounting/Payments/CustomerPayments', [
            'payments' => $payments,
            'customers' => $customers,
            'invoices' => $invoices,
            'accounts' => $accounts,
        ]);
    }

    public function storeCustomerPayment(Request $request, LedgerService $ledger, TenantFinancialResolver $resolver, FinancialBalanceService $balanceService)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'invoice_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $idempotencyKey = $data['idempotency_key'] ?? $request->header('Idempotency-Key');
        $data['idempotency_key'] = $idempotencyKey;

        // Generate deterministic payload fingerprint
        $fingerprint = hash('sha256', json_encode([
            'customer_id' => $data['customer_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'amount' => Money::of($data['amount'])->toStorageString(),
            'payment_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'],
        ]));
        $data['request_fingerprint'] = $fingerprint;

        // Pre-check existing idempotency key
        if (! empty($idempotencyKey)) {
            $existing = CustomerPayment::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                if ($existing->request_fingerprint === $fingerprint || Money::of($existing->amount)->equals(Money::of($data['amount']))) {
                    return back()->with('success', 'Customer payment recorded.');
                }
                abort(409, 'Duplicate payment: this idempotency key has already been used with different parameters.');
            }
        }

        // Tenant-scoped validation of all foreign IDs BEFORE the transaction
        if (! empty($data['customer_id'])) {
            $resolver->resolveCustomer($workspace, $data['customer_id']);
        }
        if (! empty($data['account_id'])) {
            $resolver->resolveLedgerAccount($workspace, $data['account_id']);
        }

        try {
            DB::transaction(function () use ($data, $workspace, $request, $ledger, $resolver, $balanceService) {
                $invoice = null;
                if (! empty($data['invoice_id'])) {
                    $invoice = $resolver->resolveInvoiceForPayment($workspace, $data['invoice_id']);
                    // If customer_id supplied, it must match the invoice
                    if (! empty($data['customer_id'])) {
                        $resolver->assertCustomerMatchesInvoice($data['customer_id'], $invoice);
                    }
                    $data['customer_id'] = $data['customer_id'] ?? $invoice->customer_id;

                    $alreadyPaid = Money::of(CustomerPayment::where('invoice_id', $invoice->id)->sum('amount'));
                    $invoiceTotal = Money::of($invoice->total_amount);
                    $due = $invoiceTotal->subtract($alreadyPaid)->max(Money::zero());
                    $paymentAmount = Money::of($data['amount']);
                    abort_if($paymentAmount->isGreaterThan($due), 422, 'Payment amount exceeds remaining invoice balance.');
                }

                $journalEntryId = null;
                if (! empty($data['account_id'])) {
                    $arAccount = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                        ->where('name', 'Accounts Receivable')
                        ->first() ?? LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                        ->where('is_bank', false)
                        ->first();

                    if ($arAccount) {
                        $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                            'entry_date' => $data['payment_date'],
                            'reference' => $data['reference'] ?? 'PAY-CUST',
                            'description' => 'Customer Payment' . ($invoice ? ' for Invoice ' . ($invoice->invoice_id ?? $invoice->id) : ''),
                            'lines' => [
                                ['account_id' => $data['account_id'], 'debit' => $data['amount'], 'credit' => 0],
                                ['account_id' => $arAccount->id, 'debit' => 0, 'credit' => $data['amount']],
                            ],
                        ], $request->user());
                        $ledger->post($entry, $request->user());
                        $journalEntryId = $entry->id;
                    }
                }

                $payment = CustomerPayment::create($data + [
                    'organization_id' => $workspace->organization_id,
                    'workspace_id' => $workspace->id,
                    'journal_entry_id' => $journalEntryId,
                    'created_by' => $request->user()->id,
                ]);

                // Update invoice payment status using decimal-safe arithmetic
                if ($invoice) {
                    $totalPaid = Money::of(CustomerPayment::where('invoice_id', $invoice->id)->sum('amount'));
                    $invoiceTotal = Money::of($invoice->total_amount);
                    if ($totalPaid->isGreaterThanOrEqual($invoiceTotal)) {
                        $invoice->update(['status' => 'paid']);
                    } else {
                        $invoice->update(['status' => 'partial']);
                    }
                }

                // Synchronize customer balance authoritative state
                if (! empty($data['customer_id'])) {
                    $customer = AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)
                        ->lockForUpdate()
                        ->findOrFail($data['customer_id']);
                    $balanceService->syncCustomerBalance($customer);
                }

                $this->audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'payment.recorded', 'customer_payment', (string) $payment->id, ['amount' => $payment->amount]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
            if (! empty($idempotencyKey) && (str_contains($e->getMessage(), 'idempotency') || str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'cp_ws_idempotency_unique'))) {
                $existing = CustomerPayment::forWorkspace($workspace->organization_id, $workspace->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing && ($existing->request_fingerprint === $fingerprint || Money::of($existing->amount)->equals(Money::of($data['amount'])))) {
                    return back()->with('success', 'Customer payment recorded.');
                }
                abort(409, 'Duplicate payment: this idempotency key has already been used with different parameters.');
            }
            throw $e;
        }

        return back()->with('success', 'Customer payment recorded.');
    }

    // ==========================================
    // VENDOR PAYMENTS
    // ==========================================
    public function vendorPayments(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');
        $payments = VendorPayment::forWorkspace($workspace->organization_id, $workspace->id)
            ->with(['vendor', 'purchaseInvoice', 'account'])
            ->latest('payment_date')
            ->paginate(30);

        $vendors = AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)->get();
        $bills = PurchaseInvoice::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->whereNotIn('status', ['paid', 3])->get();
        $accounts = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->get();

        return Inertia::render('Accounting/Payments/VendorPayments', [
            'payments' => $payments,
            'vendors' => $vendors,
            'bills' => $bills,
            'accounts' => $accounts,
        ]);
    }

    public function storeVendorPayment(Request $request, LedgerService $ledger, TenantFinancialResolver $resolver, FinancialBalanceService $balanceService)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'vendor_id' => ['nullable', 'integer'],
            'purchase_invoice_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $idempotencyKey = $data['idempotency_key'] ?? $request->header('Idempotency-Key');
        $data['idempotency_key'] = $idempotencyKey;

        // Generate deterministic payload fingerprint
        $fingerprint = hash('sha256', json_encode([
            'vendor_id' => $data['vendor_id'] ?? null,
            'purchase_invoice_id' => $data['purchase_invoice_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'amount' => Money::of($data['amount'])->toStorageString(),
            'payment_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'],
        ]));
        $data['request_fingerprint'] = $fingerprint;

        // Pre-check existing idempotency key
        if (! empty($idempotencyKey)) {
            $existing = VendorPayment::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                if ($existing->request_fingerprint === $fingerprint || Money::of($existing->amount)->equals(Money::of($data['amount']))) {
                    return back()->with('success', 'Vendor payment recorded.');
                }
                abort(409, 'Duplicate payment: this idempotency key has already been used with different parameters.');
            }
        }

        // Tenant-scoped validation of all foreign IDs BEFORE the transaction
        if (! empty($data['vendor_id'])) {
            $resolver->resolveVendor($workspace, $data['vendor_id']);
        }
        if (! empty($data['account_id'])) {
            $resolver->resolveLedgerAccount($workspace, $data['account_id']);
        }

        try {
            DB::transaction(function () use ($data, $workspace, $request, $ledger, $resolver, $balanceService) {
                $bill = null;
                if (! empty($data['purchase_invoice_id'])) {
                    $bill = $resolver->resolvePurchaseInvoiceForPayment($workspace, $data['purchase_invoice_id']);
                    // If vendor_id supplied, it must match the bill
                    if (! empty($data['vendor_id'])) {
                        $resolver->assertVendorMatchesBill($data['vendor_id'], $bill);
                    }
                    $data['vendor_id'] = $data['vendor_id'] ?? $bill->vendor_id;

                    $alreadyPaid = Money::of(VendorPayment::where('purchase_invoice_id', $bill->id)->sum('amount'));
                    $billTotal = Money::of($bill->total_amount);
                    $due = $billTotal->subtract($alreadyPaid)->max(Money::zero());
                    $paymentAmount = Money::of($data['amount']);
                    abort_if($paymentAmount->isGreaterThan($due), 422, 'Payment amount exceeds remaining bill balance.');
                }

                $journalEntryId = null;
                if (! empty($data['account_id'])) {
                    $apAccount = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                        ->where('name', 'Accounts Payable')
                        ->first() ?? LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                        ->where('is_bank', false)
                        ->first();

                    if ($apAccount) {
                        $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                            'entry_date' => $data['payment_date'],
                            'reference' => $data['reference'] ?? 'PAY-VEND',
                            'description' => 'Vendor Payment' . ($bill ? ' for Bill ' . ($bill->invoice_id ?? $bill->id) : ''),
                            'lines' => [
                                ['account_id' => $apAccount->id, 'debit' => $data['amount'], 'credit' => 0],
                                ['account_id' => $data['account_id'], 'debit' => 0, 'credit' => $data['amount']],
                            ],
                        ], $request->user());
                        $ledger->post($entry, $request->user());
                        $journalEntryId = $entry->id;
                    }
                }

                $payment = VendorPayment::create($data + [
                    'organization_id' => $workspace->organization_id,
                    'workspace_id' => $workspace->id,
                    'journal_entry_id' => $journalEntryId,
                    'created_by' => $request->user()->id,
                ]);

                // Update bill status using decimal-safe arithmetic
                if ($bill) {
                    $totalPaid = Money::of(VendorPayment::where('purchase_invoice_id', $bill->id)->sum('amount'));
                    $billTotal = Money::of($bill->total_amount);
                    if ($totalPaid->isGreaterThanOrEqual($billTotal)) {
                        $bill->update(['status' => 'paid']);
                    } else {
                        $bill->update(['status' => 'partial']);
                    }
                }

                // Synchronize vendor balance authoritative state
                if (! empty($data['vendor_id'])) {
                    $vendor = AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)
                        ->lockForUpdate()
                        ->findOrFail($data['vendor_id']);
                    $balanceService->syncVendorBalance($vendor);
                }

                $this->audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'payment.recorded', 'vendor_payment', (string) $payment->id, ['amount' => $payment->amount]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
            if (! empty($idempotencyKey) && (str_contains($e->getMessage(), 'idempotency') || str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'vp_ws_idempotency_unique'))) {
                $existing = VendorPayment::forWorkspace($workspace->organization_id, $workspace->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing && ($existing->request_fingerprint === $fingerprint || Money::of($existing->amount)->equals(Money::of($data['amount'])))) {
                    return back()->with('success', 'Vendor payment recorded.');
                }
                abort(409, 'Duplicate payment: this idempotency key has already been used with different parameters.');
            }
            throw $e;
        }

        return back()->with('success', 'Vendor payment recorded.');
    }

    // ==========================================
    // REVENUES & EXPENSES
    // ==========================================
    public function revenues(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');
        $revenues = AccountRevenue::forWorkspace($workspace->organization_id, $workspace->id)
            ->with(['customer', 'account'])
            ->latest('date')
            ->paginate(30);

        $customers = AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)->get();
        $accounts = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->get();

        return Inertia::render('Accounting/Revenues/Index', [
            'revenues' => $revenues,
            'customers' => $customers,
            'accounts' => $accounts,
        ]);
    }

    public function storeRevenue(Request $request, LedgerService $ledger, TenantFinancialResolver $resolver)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'payment_method' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        // Tenant-scoped validation of all foreign IDs
        if (! empty($data['customer_id'])) {
            $resolver->resolveCustomer($workspace, $data['customer_id']);
        }
        if (! empty($data['account_id'])) {
            $resolver->resolveLedgerAccount($workspace, $data['account_id']);
        }

        DB::transaction(function () use ($data, $workspace, $request, $ledger) {
            $journalEntryId = null;
            if (! empty($data['account_id'])) {
                $incomeAccount = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                    ->whereHas('type', fn ($q) => $q->where('classification', 'income'))
                    ->first();

                if ($incomeAccount) {
                    $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                        'entry_date' => $data['date'],
                        'reference' => $data['reference'] ?? 'REV-' . now()->timestamp,
                        'description' => $data['description'] ?? 'Revenue Transaction',
                        'lines' => [
                            ['account_id' => $data['account_id'], 'debit' => $data['amount'], 'credit' => 0],
                            ['account_id' => $incomeAccount->id, 'debit' => 0, 'credit' => $data['amount']],
                        ],
                    ], $request->user());
                    $ledger->post($entry, $request->user());
                    $journalEntryId = $entry->id;
                }
            }

            $revenue = AccountRevenue::create($data + [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'journal_entry_id' => $journalEntryId,
                'created_by' => $request->user()->id,
            ]);

            $this->audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'revenue.recorded', 'revenue', (string) $revenue->id, ['amount' => $revenue->amount]);
        });

        return back()->with('success', 'Revenue recorded.');
    }

    public function expenses(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');
        $expenses = AccountExpense::forWorkspace($workspace->organization_id, $workspace->id)
            ->with(['vendor', 'account'])
            ->latest('date')
            ->paginate(30);

        $vendors = AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)->get();
        $accounts = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->get();

        return Inertia::render('Accounting/Expenses/Index', [
            'expenses' => $expenses,
            'vendors' => $vendors,
            'accounts' => $accounts,
        ]);
    }

    public function storeExpense(Request $request, LedgerService $ledger, TenantFinancialResolver $resolver)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'vendor_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'payment_method' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        // Tenant-scoped validation of all foreign IDs
        if (! empty($data['vendor_id'])) {
            $resolver->resolveVendor($workspace, $data['vendor_id']);
        }
        if (! empty($data['account_id'])) {
            $resolver->resolveLedgerAccount($workspace, $data['account_id']);
        }

        DB::transaction(function () use ($data, $workspace, $request, $ledger) {
            $journalEntryId = null;
            if (! empty($data['account_id'])) {
                $expenseAccount = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                    ->whereHas('type', fn ($q) => $q->where('classification', 'expense'))
                    ->first();

                if ($expenseAccount) {
                    $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                        'entry_date' => $data['date'],
                        'reference' => $data['reference'] ?? 'EXP-' . now()->timestamp,
                        'description' => $data['description'] ?? 'Expense Transaction',
                        'lines' => [
                            ['account_id' => $expenseAccount->id, 'debit' => $data['amount'], 'credit' => 0],
                            ['account_id' => $data['account_id'], 'debit' => 0, 'credit' => $data['amount']],
                        ],
                    ], $request->user());
                    $ledger->post($entry, $request->user());
                    $journalEntryId = $entry->id;
                }
            }

            $expense = AccountExpense::create($data + [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'journal_entry_id' => $journalEntryId,
                'created_by' => $request->user()->id,
            ]);

            $this->audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'expense.recorded', 'expense', (string) $expense->id, ['amount' => $expense->amount]);
        });

        return back()->with('success', 'Expense recorded.');
    }

    // ==========================================
    // CREDIT & DEBIT NOTES
    // ==========================================
    public function creditNotes(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');
        $creditNotes = AccountCreditNote::forWorkspace($workspace->organization_id, $workspace->id)
            ->with(['customer', 'invoice'])
            ->latest('date')
            ->paginate(30);

        $customers = AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)->get();
        $invoices = SalesInvoice::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->get();

        return Inertia::render('Accounting/CreditNotes/Index', [
            'creditNotes' => $creditNotes,
            'customers' => $customers,
            'invoices' => $invoices,
        ]);
    }

    public function storeCreditNote(Request $request, LedgerService $ledger, TenantFinancialResolver $resolver, FinancialBalanceService $balanceService)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'invoice_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        // Tenant-scoped validation
        $invoice = null;
        if (! empty($data['invoice_id'])) {
            $invoice = $resolver->resolveInvoice($workspace, $data['invoice_id']);
            if (! empty($data['customer_id'])) {
                $resolver->assertCustomerMatchesInvoice($data['customer_id'], $invoice);
            } else {
                $data['customer_id'] = $invoice->customer_id;
            }

            // Validate credit note does not exceed invoice total minus existing credit notes
            $invoiceTotal = Money::of($invoice->total_amount);
            $appliedCredits = Money::of(AccountCreditNote::forWorkspace($workspace->organization_id, $workspace->id)->where('invoice_id', $invoice->id)->sum('amount'));
            $maxCredit = $invoiceTotal->subtract($appliedCredits)->max(Money::zero());
            abort_if(Money::of($data['amount'])->isGreaterThan($maxCredit), 422, 'Credit note amount exceeds invoice total amount.');
        }
        if (! empty($data['customer_id'])) {
            $resolver->resolveCustomer($workspace, $data['customer_id']);
        }

        DB::transaction(function () use ($data, $workspace, $request, $ledger, $invoice, $balanceService) {
            $creditNote = AccountCreditNote::create($data + [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'status' => 'applied',
                'created_by' => $request->user()->id,
            ]);

            // Synchronize customer balance authoritative state
            if (! empty($data['customer_id'])) {
                $customer = AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)
                    ->lockForUpdate()
                    ->findOrFail($data['customer_id']);
                $balanceService->syncCustomerBalance($customer);
            }

            // Create journal entry: Debit Revenue/AR, Credit Customer
            $arAccount = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('name', 'Accounts Receivable')
                ->first();
            $incomeAccount = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                ->whereHas('type', fn ($q) => $q->where('classification', 'income'))
                ->first();

            if ($arAccount && $incomeAccount) {
                $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                    'entry_date' => $data['date'],
                    'reference' => 'CN-' . $creditNote->id,
                    'description' => 'Credit Note' . ($invoice ? ' for Invoice ' . ($invoice->invoice_id ?? $invoice->id) : ''),
                    'lines' => [
                        ['account_id' => $incomeAccount->id, 'debit' => $data['amount'], 'credit' => 0],
                        ['account_id' => $arAccount->id, 'debit' => 0, 'credit' => $data['amount']],
                    ],
                ], $request->user());
                $ledger->post($entry, $request->user());
            }

            $this->audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'credit_note.created', 'credit_note', (string) $creditNote->id, ['amount' => $creditNote->amount]);
        });

        return back()->with('success', 'Credit note created.');
    }

    public function debitNotes(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');
        $debitNotes = AccountDebitNote::forWorkspace($workspace->organization_id, $workspace->id)
            ->with(['vendor', 'purchaseInvoice'])
            ->latest('date')
            ->paginate(30);

        $vendors = AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)->get();
        $bills = PurchaseInvoice::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->get();

        return Inertia::render('Accounting/DebitNotes/Index', [
            'debitNotes' => $debitNotes,
            'vendors' => $vendors,
            'bills' => $bills,
        ]);
    }

    public function storeDebitNote(Request $request, LedgerService $ledger, TenantFinancialResolver $resolver, FinancialBalanceService $balanceService)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'purchase_invoice_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        // Tenant-scoped validation
        $bill = null;
        if (! empty($data['purchase_invoice_id'])) {
            $bill = $resolver->resolvePurchaseInvoice($workspace, $data['purchase_invoice_id']);
            if (! empty($data['vendor_id'])) {
                $resolver->assertVendorMatchesBill($data['vendor_id'], $bill);
            } else {
                $data['vendor_id'] = $bill->vendor_id;
            }

            // Validate debit note does not exceed bill total minus existing debit notes
            $billTotal = Money::of($bill->total_amount);
            $appliedDebits = Money::of(AccountDebitNote::forWorkspace($workspace->organization_id, $workspace->id)->where('purchase_invoice_id', $bill->id)->sum('amount'));
            $maxDebit = $billTotal->subtract($appliedDebits)->max(Money::zero());
            abort_if(Money::of($data['amount'])->isGreaterThan($maxDebit), 422, 'Debit note amount exceeds bill total amount.');
        }
        if (! empty($data['vendor_id'])) {
            $resolver->resolveVendor($workspace, $data['vendor_id']);
        }

        DB::transaction(function () use ($data, $workspace, $request, $ledger, $bill, $balanceService) {
            $debitNote = AccountDebitNote::create($data + [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'status' => 'applied',
                'created_by' => $request->user()->id,
            ]);

            // Synchronize vendor balance authoritative state
            if (! empty($data['vendor_id'])) {
                $vendor = AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)
                    ->lockForUpdate()
                    ->findOrFail($data['vendor_id']);
                $balanceService->syncVendorBalance($vendor);
            }

            // Create journal entry: Debit AP, Credit Expense
            $apAccount = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('name', 'Accounts Payable')
                ->first();
            $expenseAccount = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                ->whereHas('type', fn ($q) => $q->where('classification', 'expense'))
                ->first();

            if ($apAccount && $expenseAccount) {
                $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                    'entry_date' => $data['date'],
                    'reference' => 'DN-' . $debitNote->id,
                    'description' => 'Debit Note' . ($bill ? ' for Bill ' . ($bill->invoice_id ?? $bill->id) : ''),
                    'lines' => [
                        ['account_id' => $apAccount->id, 'debit' => $data['amount'], 'credit' => 0],
                        ['account_id' => $expenseAccount->id, 'debit' => 0, 'credit' => $data['amount']],
                    ],
                ], $request->user());
                $ledger->post($entry, $request->user());
            }

            $this->audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'debit_note.created', 'debit_note', (string) $debitNote->id, ['amount' => $debitNote->amount]);
        });

        return back()->with('success', 'Debit note created.');
    }

    // ==========================================
    // CHART OF ACCOUNTS & JOURNALS & REPORTS & BANKING
    // ==========================================
    public function accounts(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');

        return Inertia::render('Accounting/Accounts', [
            'accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->with('type')->orderBy('code')->paginate(50),
            'types' => AccountType::where('workspace_id', $workspace->id)->get(),
            'metrics' => [
                'accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->count(),
                'bank_accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->count(),
                'posted_journals' => JournalEntry::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('status', 'posted')->count(),
                'bank_transfers' => Money::of(AccountBankTransfer::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->sum('amount'))->toFloat(),
            ],
        ]);
    }

    public function storeType(Request $request)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'classification' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
        ]);
        AccountType::firstOrCreate(['workspace_id' => $workspace->id, 'name' => $data['name']], $data + ['organization_id' => $workspace->organization_id]);

        return back()->with('success', 'Account type saved.');
    }

    public function storeAccount(Request $request)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'account_type_id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:32', Rule::unique('ledger_accounts')->where('workspace_id', $workspace->id)],
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'is_bank' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        abort_unless(AccountType::where('workspace_id', $workspace->id)->whereKey($data['account_type_id'])->exists(), 422);
        if (! empty($data['parent_id'])) {
            abort_unless(LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->whereKey($data['parent_id'])->exists(), 422);
        }
        LedgerAccount::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id]);

        return back()->with('success', 'Ledger account created.');
    }

    public function journals(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');

        return Inertia::render('Accounting/Journals', [
            'entries' => JournalEntry::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->with('lines.account')->latest('entry_date')->paginate(30),
            'accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function storeJournal(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $ledger->createEntry($workspace->organization_id, $workspace->id, $data, $request->user());

        return back()->with('success', 'Draft journal entry created.');
    }

    public function postJournal(Request $request, JournalEntry $entry, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        abort_unless((int) $entry->organization_id === (int) $workspace->organization_id && (int) $entry->workspace_id === (int) $workspace->id, 404);
        $ledger->post($entry, $request->user());

        return back()->with('success', 'Journal entry posted.');
    }

    public function reports(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.view');
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $balances = $ledger->balances($workspace->organization_id, $workspace->id, $data['from'] ?? null, $data['to'] ?? null);
        $byClass = $balances->groupBy(fn ($account) => $account->type->classification)->map->sum('balance');

        return Inertia::render('Accounting/Reports', [
            'trialBalance' => $balances,
            'profitAndLoss' => [
                'income' => (float) ($byClass['income'] ?? 0),
                'expenses' => (float) ($byClass['expense'] ?? 0),
                'net_income' => (float) (($byClass['income'] ?? 0) - ($byClass['expense'] ?? 0)),
            ],
            'balanceSheet' => [
                'assets' => Money::of($byClass['asset'] ?? 0)->toFloat(),
                'liabilities' => Money::of($byClass['liability'] ?? 0)->toFloat(),
                'equity' => Money::of($byClass['equity'] ?? 0)->toFloat(),
            ],
        ]);
    }

    public function bankTransfer(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'from_account_id' => ['required', 'integer'],
            'to_account_id' => ['required', 'integer', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transfer_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $accounts = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->whereIn('id', [$data['from_account_id'], $data['to_account_id']])->get()->keyBy('id');
        abort_unless($accounts->count() === 2, 422, 'Both transfer accounts must be tenant bank or cash accounts.');

        DB::transaction(function () use ($data, $workspace, $request, $ledger) {
            $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                'entry_date' => $data['transfer_date'],
                'reference' => $data['reference'] ?? null,
                'description' => 'Bank transfer',
                'lines' => [
                    ['account_id' => $data['to_account_id'], 'debit' => $data['amount'], 'credit' => 0],
                    ['account_id' => $data['from_account_id'], 'debit' => 0, 'credit' => $data['amount']],
                ],
            ], $request->user());
            $ledger->post($entry, $request->user());
            AccountBankTransfer::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'journal_entry_id' => $entry->id, 'created_by' => $request->user()->id]);
        });

        return back()->with('success', 'Bank transfer posted.');
    }

    public function reconcile(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'account_id' => ['required', 'integer'],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
        ]);
        $account = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->findOrFail($data['account_id']);
        $ledgerBalance = Money::of($ledger->balances($workspace->organization_id, $workspace->id, null, $data['statement_date'])->firstWhere('id', $account->id)?->balance ?? 0);
        $statementBalance = Money::of($data['statement_balance']);
        BankReconciliation::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'ledger_account_id' => $account->id,
            'statement_date' => $data['statement_date'],
            'statement_balance' => $statementBalance->toStorageString(),
            'ledger_balance' => $ledgerBalance->toStorageString(),
            'status' => $ledgerBalance->equals($statementBalance) ? 'reconciled' : 'difference',
            'reconciled_by' => $request->user()->id,
            'reconciled_at' => now(),
        ]);

        return back()->with('success', 'Bank reconciliation recorded.');
    }

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $workspace;
    }
}
