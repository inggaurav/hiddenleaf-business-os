# Module Browser Workflow Verification

## Execution Environment
- **Host**: `http://127.0.0.1:8000`
- **Frontend Engine**: React 19 / Inertia.js 3 / Tailwind CSS 4 / Vite 6
- **Backend**: Laravel 12 on PHP 8.3 with SQLite & PostgreSQL compatibility

---

## Workflow Browser Smoke Test Matrix

| Module | Screen / Workflow | Action Performed | Result / Evidence | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Auth** | `/login` | Submitted valid Company Admin credentials (`admin@acme.com`) | Session cookie established, redirected to `/dashboard` | `PASS` |
| **Core** | `/dashboard` | Executive Dashboard loads | Metrics rendered across active modules without layout shift | `PASS` |
| **Accounting** | `/accounting/dashboard` | Accounting Dashboard loads | Real counts for clients, vendors, payments, revenues rendered | `PASS` |
| **Accounting** | `/accounting/customers` | Created Customer 'Global Tech Corp' | Customer persisted, balance shows $0.00 | `PASS` |
| **Accounting** | `/accounting/accounts` | Created Ledger Account 'Operating Bank' | Ledger account persisted under Assets | `PASS` |
| **ProductService** | `/productservice` | Created Product 'Enterprise Switch' ($1,200.00) | Product saved with SKU and unit | `PASS` |
| **Sales** | `/sales-invoices` | Created and Posted Invoice #INV-1001 for 10 units | Stock decremented from 50 to 40; invoice status 'posted' | `PASS` |
| **Sales** | `/accounting/customer-payments` | Recorded Partial Payment ($6,000.00) with Idempotency Key | Invoice status changed to 'partial', customer balance updated | `PASS` |
| **Sales Returns**| `/sales-returns` | Submitted Return Order for 2 units | Stock restored to 42; return status 'completed' | `PASS` |
| **HRM** | `/hrm/dashboard` & `/hrm/employees` | Created Employee & Clocked Attendance | Attendance card updated, daily log persisted | `PASS` |
| **CRM** | `/crm` & `/crm/dashboard` | Moved Lead to 'Won' stage in Kanban pipeline | Pipeline value updated, Won deal registered | `PASS` |
| **Taskly** | `/taskly/tasks` | Moved Task from 'In Progress' to 'Done' | Stage persisted, velocity chart updated | `PASS` |
| **POS** | `/pos/terminal` | Added items to cart, checked out with cash tender | Stock adjusted, order receipt generated, session logged | `PASS` |
| **Helpdesk** | `/helpdesk/tickets` | Created Support Ticket & Replied | Ticket message stream updated, status tracked | `PASS` |
| **Media** | `/media` | Opened directory tree & uploaded asset | Media directory tree updated | `PASS` |
