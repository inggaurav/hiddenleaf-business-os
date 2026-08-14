<?php

use App\Http\Controllers\AccountReferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'module.status:account'])->prefix('accounting')->name('account-reference.')->group(function () {
    Route::get('bank-accounts', [AccountReferenceController::class, 'bankAccounts'])->name('bank-accounts.index');
    Route::post('bank-accounts', [AccountReferenceController::class, 'storeBankAccount'])->name('bank-accounts.store');
    Route::get('bank-accounts/api/list', [AccountReferenceController::class, 'bankAccountList'])->name('bank-accounts.api-list');
    Route::get('bank-accounts/{bankAccount}/edit', [AccountReferenceController::class, 'editBankAccount'])->name('bank-accounts.edit');
    Route::put('bank-accounts/{bankAccount}', [AccountReferenceController::class, 'updateBankAccount'])->name('bank-accounts.update');
    Route::delete('bank-accounts/{bankAccount}', [AccountReferenceController::class, 'destroyBankAccount'])->name('bank-accounts.destroy');

    Route::get('account-types', [AccountReferenceController::class, 'accountTypes'])->name('account-types.index');
    Route::put('account-types/{accountType}', [AccountReferenceController::class, 'updateAccountType'])->name('account-types.update');
    Route::delete('account-types/{accountType}', [AccountReferenceController::class, 'destroyAccountType'])->name('account-types.destroy');
    Route::get('accounts/{ledgerAccount}', [AccountReferenceController::class, 'showLedgerAccount'])->name('accounts.show');
    Route::get('accounts/{ledgerAccount}/edit', [AccountReferenceController::class, 'editLedgerAccount'])->name('accounts.edit');
    Route::put('accounts/{ledgerAccount}', [AccountReferenceController::class, 'updateLedgerAccount'])->name('accounts.update');
    Route::delete('accounts/{ledgerAccount}', [AccountReferenceController::class, 'destroyLedgerAccount'])->name('accounts.destroy');

    Route::get('customer-payments/customers/{customerId}/outstanding', [AccountReferenceController::class, 'customerOutstanding'])->name('customer-payments.outstanding');
    Route::patch('customer-payments/{customerPayment}/status', [AccountReferenceController::class, 'updateCustomerPaymentStatus'])->name('customer-payments.status');
    Route::delete('customer-payments/{customerPayment}', [AccountReferenceController::class, 'destroyCustomerPayment'])->name('customer-payments.destroy');
    Route::get('vendor-payments/vendors/{vendorId}/outstanding', [AccountReferenceController::class, 'vendorOutstanding'])->name('vendor-payments.outstanding');
    Route::patch('vendor-payments/{vendorPayment}/status', [AccountReferenceController::class, 'updateVendorPaymentStatus'])->name('vendor-payments.status');
    Route::delete('vendor-payments/{vendorPayment}', [AccountReferenceController::class, 'destroyVendorPayment'])->name('vendor-payments.destroy');

    Route::get('bank-transactions', [AccountReferenceController::class, 'bankTransactions'])->name('bank-transactions.index');
    Route::post('bank-transactions/{journalLine}/mark-reconciled', [AccountReferenceController::class, 'markBankTransactionReconciled'])->name('bank-transactions.reconcile');

    Route::get('bank-transfers', [AccountReferenceController::class, 'bankTransfers'])->name('bank-transfers.index');
    Route::post('bank-transfer-drafts', [AccountReferenceController::class, 'storeBankTransferDraft'])->name('bank-transfers.draft');
    Route::put('bank-transfers/{bankTransfer}', [AccountReferenceController::class, 'updateBankTransfer'])->name('bank-transfers.update');
    Route::delete('bank-transfers/{bankTransfer}', [AccountReferenceController::class, 'destroyBankTransfer'])->name('bank-transfers.destroy');
    Route::post('bank-transfers/{bankTransfer}/process', [AccountReferenceController::class, 'processBankTransfer'])->name('bank-transfers.process');

    Route::get('revenue-categories', [AccountReferenceController::class, 'categories'])->defaults('type', 'revenue')->name('revenue-categories.index');
    Route::post('revenue-categories', [AccountReferenceController::class, 'storeCategory'])->defaults('type', 'revenue')->name('revenue-categories.store');
    Route::get('revenue-categories/{category}/edit', [AccountReferenceController::class, 'editCategory'])->defaults('type', 'revenue')->name('revenue-categories.edit');
    Route::put('revenue-categories/{category}', [AccountReferenceController::class, 'updateCategory'])->defaults('type', 'revenue')->name('revenue-categories.update');
    Route::delete('revenue-categories/{category}', [AccountReferenceController::class, 'destroyCategory'])->defaults('type', 'revenue')->name('revenue-categories.destroy');

    Route::get('expense-categories', [AccountReferenceController::class, 'categories'])->defaults('type', 'expense')->name('expense-categories.index');
    Route::post('expense-categories', [AccountReferenceController::class, 'storeCategory'])->defaults('type', 'expense')->name('expense-categories.store');
    Route::get('expense-categories/{category}/edit', [AccountReferenceController::class, 'editCategory'])->defaults('type', 'expense')->name('expense-categories.edit');
    Route::put('expense-categories/{category}', [AccountReferenceController::class, 'updateCategory'])->defaults('type', 'expense')->name('expense-categories.update');
    Route::delete('expense-categories/{category}', [AccountReferenceController::class, 'destroyCategory'])->defaults('type', 'expense')->name('expense-categories.destroy');

    Route::post('revenues/draft', [AccountReferenceController::class, 'storeRevenueDraft'])->name('revenues.draft');
    Route::get('revenues/{revenue}', [AccountReferenceController::class, 'showRevenue'])->name('revenues.show');
    Route::put('revenues/{revenue}', [AccountReferenceController::class, 'updateRevenue'])->name('revenues.update');
    Route::delete('revenues/{revenue}', [AccountReferenceController::class, 'destroyRevenue'])->name('revenues.destroy');
    Route::post('revenues/{revenue}/approve', [AccountReferenceController::class, 'approveRevenue'])->name('revenues.approve');
    Route::post('revenues/{revenue}/post', [AccountReferenceController::class, 'postRevenue'])->name('revenues.post');

    Route::post('expenses/draft', [AccountReferenceController::class, 'storeExpenseDraft'])->name('expenses.draft');
    Route::get('expenses/{expense}', [AccountReferenceController::class, 'showExpense'])->name('expenses.show');
    Route::put('expenses/{expense}', [AccountReferenceController::class, 'updateExpense'])->name('expenses.update');
    Route::delete('expenses/{expense}', [AccountReferenceController::class, 'destroyExpense'])->name('expenses.destroy');
    Route::post('expenses/{expense}/approve', [AccountReferenceController::class, 'approveExpense'])->name('expenses.approve');
    Route::post('expenses/{expense}/post', [AccountReferenceController::class, 'postExpense'])->name('expenses.post');

    Route::get('credit-notes/{creditNote}', [AccountReferenceController::class, 'showCreditNote'])->name('credit-notes.show');
    Route::post('credit-notes/{creditNote}/approve', [AccountReferenceController::class, 'approveCreditNote'])->name('credit-notes.approve');
    Route::delete('credit-notes/{creditNote}', [AccountReferenceController::class, 'destroyCreditNote'])->name('credit-notes.destroy');
    Route::get('debit-notes/{debitNote}', [AccountReferenceController::class, 'showDebitNote'])->name('debit-notes.show');
    Route::post('debit-notes/{debitNote}/approve', [AccountReferenceController::class, 'approveDebitNote'])->name('debit-notes.approve');
    Route::delete('debit-notes/{debitNote}', [AccountReferenceController::class, 'destroyDebitNote'])->name('debit-notes.destroy');

    Route::get('reports/invoice-aging', [AccountReferenceController::class, 'invoiceAging'])->name('reports.invoice-aging');
    Route::get('reports/invoice-aging/print', [AccountReferenceController::class, 'printReport'])->defaults('report', 'invoice-aging')->name('reports.invoice-aging.print');
    Route::get('reports/bill-aging', [AccountReferenceController::class, 'billAging'])->name('reports.bill-aging');
    Route::get('reports/bill-aging/print', [AccountReferenceController::class, 'printReport'])->defaults('report', 'bill-aging')->name('reports.bill-aging.print');
    Route::get('reports/tax-summary', [AccountReferenceController::class, 'taxSummary'])->name('reports.tax-summary');
    Route::get('reports/tax-summary/print', [AccountReferenceController::class, 'printReport'])->defaults('report', 'tax-summary')->name('reports.tax-summary.print');
    Route::get('reports/customer-balance', [AccountReferenceController::class, 'customerBalance'])->name('reports.customer-balance');
    Route::get('reports/customer-balance/print', [AccountReferenceController::class, 'printReport'])->defaults('report', 'customer-balance')->name('reports.customer-balance.print');
    Route::get('reports/vendor-balance', [AccountReferenceController::class, 'vendorBalance'])->name('reports.vendor-balance');
    Route::get('reports/vendor-balance/print', [AccountReferenceController::class, 'printReport'])->defaults('report', 'vendor-balance')->name('reports.vendor-balance.print');

    Route::get('reports/customer/{customerId}', [AccountReferenceController::class, 'customerDetail'])->name('reports.customer-detail');
    Route::get('reports/customer/{customerId}/print', [AccountReferenceController::class, 'printCustomerDetail'])->name('reports.customer-detail.print');
    Route::get('reports/vendor/{vendorId}', [AccountReferenceController::class, 'vendorDetail'])->name('reports.vendor-detail');
    Route::get('reports/vendor/{vendorId}/print', [AccountReferenceController::class, 'printVendorDetail'])->name('reports.vendor-detail.print');
});
