# Module Browser Workflow Verification

## Evidence status

**Current status: NOT EXECUTED for the GitHub-only hardening pass.**

The previous document was invalidated because it mixed automated-test results with claimed browser execution and contained stale runtime/version and route information. Browser PASS status must only be recorded after an operator actually performs the workflow against the current branch and records the resulting route, action and observable state change.

## Current branch verification targets

The following current routes are the browser-smoke targets for the next interactive QA run:

| Module | Current route / workflow | Browser status |
|---|---|---|
| Core | `/dashboard` | NOT_EXECUTED |
| Account | `/accounting/dashboard` | NOT_EXECUTED |
| Account | `/accounting/customers` | NOT_EXECUTED |
| Account | `/accounting/vendor-payments` | NOT_EXECUTED |
| Account | `/accounting/customer-payments` | NOT_EXECUTED |
| ProductService | `/product-service` | NOT_EXECUTED |
| Inventory | `/inventory/dashboard` | NOT_EXECUTED |
| Sales | `/sales/dashboard` | NOT_EXECUTED |
| Sales | `/sales-invoices` | NOT_EXECUTED |
| Sales | `/sales-proposals` | NOT_EXECUTED |
| Sales | `/sales-returns` | NOT_EXECUTED |
| Procurement | `/procurement/dashboard` | NOT_EXECUTED |
| Procurement | `/purchase-invoices` | NOT_EXECUTED |
| Procurement | `/purchase-returns` | NOT_EXECUTED |
| HRM | `/hrm/dashboard` | NOT_EXECUTED |
| CRM | `/crm/dashboard` | NOT_EXECUTED |
| Taskly | `/taskly/dashboard` | NOT_EXECUTED |
| POS | `/pos/dashboard` | NOT_EXECUTED |
| POS | `/pos/terminal` | NOT_EXECUTED |
| Helpdesk | `/helpdesk-tickets` | NOT_EXECUTED |
| Media | `/media/page` | NOT_EXECUTED |
| Messenger | `/chats` | NOT_EXECUTED |

## Acceptance rule

A row may be changed to `PASS` only when all of the following are observed in a real browser session on the current commit:

1. The route loads without console/runtime failure.
2. Permission/module/tenant guards behave correctly.
3. The form or action is submitted through the UI.
4. The expected backend record/state transition is observed.
5. Related dashboard/navigation state refreshes correctly where applicable.
6. The exact commit SHA and test account/workspace are recorded in the QA report.

Automated PHPUnit coverage must be reported separately and must never be used as a substitute for browser execution.
