# WorkDo Company Admin Experience Baseline

## 1. Landing & Dashboard
- **URL / Route**: `/dashboard` (`dashboard`)
- **Dashboard Switcher**: Global Overview + Domain-specific Dashboards:
  - Project Dashboard (`project.dashboard.index`)
  - HRM Dashboard (`hrm.index`)
  - POS Dashboard (`pos.index`)
  - CRM Dashboard (`lead.index`)
  - Accounting Dashboard (`account.index`)
- **Top Navigation**: Workspace Switcher, Brand Logo, Menu Search input, Notification Center, User Profile dropdown.

## 2. Navigation Sidebar Architecture (Nested Module Tree)
- **User Management**: Roles (`roles.index`), Users (`users.index`)
- **Proposal**: Sales Proposals (`sales-proposals.index`)
- **Sales Invoice**: Invoices (`sales-invoices.index`), Returns (`sales-returns.index`)
- **Purchase**: Bills (`purchase-invoices.index`), Returns (`purchase-returns.index`), Warehouses (`warehouses.index`), Transfers (`transfers.index`)
- **Accounting**:
  - Banking: Accounts (`account.bank-accounts.index`), Transactions (`account.bank-transactions.index`), Transfers (`account.bank-transfers.index`)
  - Chart of Accounts (`account.chart-of-accounts.index`)
  - Payments: Vendor Payments (`account.vendor-payments.index`), Customer Payments (`account.customer-payments.index`)
  - Cashflow: Revenue (`account.revenues.index`), Expense (`account.expenses.index`)
  - Credit & Debit Notes: Debit Notes (`account.debit-notes.index`), Credit Notes (`account.credit-notes.index`)
  - Reports (`account.reports.index`), System Setup (`account.account-types.index`)
- **Project (Taskly)**: Projects (`project.index`), Payments (`project-payments.index`), Reports (`project.report.index`), System Setup (`project.task-stages.index`)
- **HRM**:
  - Employees (`hrm.employees.index`)
  - Payslip: Set Salary (`hrm.set-salary.index`), Payroll (`hrm.payrolls.index`)
  - Attendance: Shifts (`hrm.shifts.index`), Attendances (`hrm.attendances.index`)
  - Leave Management: Types (`hrm.leave-types.index`), Applications (`hrm.leave-applications.calendar`), Balance (`hrm.leave-balance.index`)
  - Lifecycle: Holidays, Awards, Promotions, Resignations, Terminations, Warnings, Complaints, Transfers, Documents, Announcements, Events, Policies
  - System Setup (`hrm.branches.index`)
- **POS**: Add POS (`pos.create`), Orders (`pos.orders`), Returns (`pos.returns.index`), Barcodes (`pos.barcode`), Counters (`pos.billing-counters`), Discounts (`pos.discounts.index`), Reports (`pos.reports.sales`)
- **CRM**: Leads (`lead.leads.index`), Deals (`lead.deals.index`), Reports (`lead.reports.leads`), System Setup (`lead.pipelines.index`)
- **Operations**: Media Library, Messenger, AI Agent, Helpdesk
- **Subscription / Plan**: Setup Subscription (`plans.index`), Bank Transfer Requests (`bank-transfer.index`), Orders (`orders.index`)
- **Settings**: Company Settings, Brand, System Setup (Product & Service items, categories, taxes, units)
