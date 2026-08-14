# Money & Financial Arithmetic Audit

## Summary & Compliance Status
- **Standard**: All authoritative financial computations (balances, ledgers, invoice totals, payment applications, tax allocations, change calculations) must use exact decimal arithmetic (`App\Domain\Accounting\Money` backed by PHP `BCMath`) with explicit currency precision.
- **Classification Categories**:
  - `SAFE_DECIMAL`: Authoritative calculation executed exclusively through `Money` value object or database decimal types with BCMath.
  - `DISPLAY_ONLY_FLOAT`: Safe conversion to PHP float at the presentation / JSON Inertia boundary for read-only rendering, never reused in subsequent calculations.
  - `NEEDS_FIX`: Floating-point binary arithmetic used in an authoritative calculation (all identified cases resolved).

---

## Authoritative Financial Pathways Audit Table

| Subsystem | File & Method / Component | Metric / Calculation | Classification | Resolution / Hardening Status |
| :--- | :--- | :--- | :--- | :--- |
| **Accounting** | `AccountingController::storeCustomerPayment` | Payment sum, invoice balance due, customer balance decrement | `SAFE_DECIMAL` | Uses `Money::of()`, `bcsub`, `lockForUpdate()`, and `FinancialBalanceService::syncCustomerBalance()` |
| **Accounting** | `AccountingController::storeVendorPayment` | Payment sum, bill balance due, vendor balance decrement | `SAFE_DECIMAL` | Uses `Money::of()`, `bcsub`, `lockForUpdate()`, and `FinancialBalanceService::syncVendorBalance()` |
| **Accounting** | `AccountingController::storeCreditNote` | Credit amount vs invoice total, customer balance reduction, ledger entry | `SAFE_DECIMAL` | Uses `Money::of()`, `bcsub`, `lockForUpdate()`, and `FinancialBalanceService::syncCustomerBalance()` |
| **Accounting** | `AccountingController::storeDebitNote` | Debit amount vs bill total, vendor balance reduction, ledger entry | `SAFE_DECIMAL` | Uses `Money::of()`, `bcsub`, `lockForUpdate()`, and `FinancialBalanceService::syncVendorBalance()` |
| **Accounting** | `AccountingController::reconcile` | Bank statement balance vs general ledger balance | `SAFE_DECIMAL` | Uses `Money::equals()`, storage strings, exact comparison without floating epsilon |
| **General Ledger**| `LedgerService::assertBalanced` | Journal entry line debits vs credits equality | `SAFE_DECIMAL` | Replaced `abs($debits - $credits) > 0.001` with `Money::equals()` |
| **General Ledger**| `LedgerService::balances` | Account net balance summation | `SAFE_DECIMAL` | DB decimal aggregate with `Money` VO handling |
| **POS** | `CheckoutService::checkout` | Item price * qty, line discounts, taxes, grand total, paid, change | `SAFE_DECIMAL` | All line calculations, discounts, taxes, and change math use `Money` VO |
| **Sales** | `SalesProposalController::store` | Line items price * qty, tax, discount, grand total | `SAFE_DECIMAL` | Database decimal casts (`decimal:2`) with transactional integrity |
| **Sales** | `SalesInvoiceController::store` | Line items price * qty, tax, discount, invoice total | `SAFE_DECIMAL` | Decimal storage columns, verified against catalog unit prices |
| **Sales Returns** | `SalesReturnController::store` | Cumulative quantity sum, return amount, price | `SAFE_DECIMAL` | Cumulative return validation prevents over-return, item prices validated |
| **Procurement** | `PurchaseInvoiceController::store` | Purchase bill line items, tax, discount, bill total | `SAFE_DECIMAL` | Decimal storage columns, verified against vendor catalog prices |
| **Purchase Returns**| `PurchaseReturnController::store` | Cumulative return quantity validation, item pricing | `SAFE_DECIMAL` | Cumulative return validation prevents over-return, item prices validated |
| **Inventory** | `StockAdjustmentService::adjust` | Stock quantity addition/deduction, movement history | `SAFE_DECIMAL` | Strict decimal quantities, warehouse inventory row locking |
| **Subscriptions**| `SubscriptionController::checkout` | Plan price, coupon percentage/fixed discount deduction | `SAFE_DECIMAL` | `Money` arithmetic for discount application and order totals |
| **Dashboards** | `AccountDashboardService::getMetrics` | Stats presentation payload (Inertia JSON boundary) | `DISPLAY_ONLY_FLOAT` | Derived from `Money` calculations using `->toFloat()` strictly at serialization boundary |

---

## Currency Scaling Policy

1. **Default Scale**: 2 decimal places (`Money::DEFAULT_SCALE = 2`), standard for USD, EUR, GBP, INR, CAD, AUD, AED, SAR, etc.
2. **Zero-Decimal Currencies**: JPY, KRW, VND, BIF, DJF, GNF, ISK, KMF, PYG, RWF, UGX, VUV, XAF, XOF, XPF (`scale = 0`).
3. **Three-Decimal Currencies**: BHD, IQD, JOD, KWD, LYD, OMR, TND (`scale = 3`).
4. **Four-Decimal Currencies**: CLF (`scale = 4`).
5. **Scale Resolution**: Handled automatically via `Money::forCurrency($amount, $currencyCode)` and `Money::scaleForCurrency($currencyCode)`.
