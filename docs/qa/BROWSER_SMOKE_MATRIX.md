# Browser smoke matrix

This matrix is deterministic execution guidance for the final integrated UI. Rows are **NOT EXECUTED** until a tester records browser evidence against the integration build. Automated backend tests are separate evidence and do not silently convert these rows to PASS.

| Area | Route | Actor / permission | Expected result | Mutation | Tenant-security expectation | Status |
|---|---|---|---|---|---|---|
| Authentication | `/login` | Guest | Valid login reaches dashboard; invalid credentials stay generic | Session created | No tenant selected from request input | NOT EXECUTED |
| Dashboard | `/dashboard` | Workspace member | Real workspace metrics and recent audit activity render | None | Active organization/workspace only | NOT EXECUTED |
| Workspace switching | `/workspaces/{workspace}/switch` | Workspace member | Context changes and dependent props refresh | Session context | Foreign workspace returns 403/404 | NOT EXECUTED |
| Roles | `/roles` | `roles.manage` | Role list and permission assignments render | Create/update role | Foreign-organization role rejected | NOT EXECUTED |
| Members | `/users` | `users.view` | Scoped users and roles render | Invite/update/status | No cross-tenant membership mutation | NOT EXECUTED |
| Plans | `/plans` | Super Admin | Plan limits, prices, modules render | Create/update plan | Platform-only mutation | NOT EXECUTED |
| Subscriptions | `/subscriptions` | Organization owner | Current lifecycle and entitlements render | Subscribe/cancel | Own organization only | NOT EXECUTED |
| Coupons | `/coupons` | Super Admin | Rules and usage render | Create/update/redeem | Redemption scoped to actor/order | NOT EXECUTED |
| Bank transfer | `/bank-transfer` | Subscriber/Admin reviewer | Proof submission or review queue renders | Submit/approve/reject | Applicant cannot review; foreign order hidden | NOT EXECUTED |
| Catalog | `/product-service` | `product_service.manage` | Products/services and inventory metrics render | CRUD item | Foreign item is 404 | NOT EXECUTED |
| Warehouses | `/warehouses` | Workspace inventory manager | Scoped warehouses render | CRUD warehouse | Foreign warehouse is 404 | NOT EXECUTED |
| Transfers | `/transfers` | Workspace inventory manager | Transfer lifecycle and stock validation render | Create/post transfer | Both warehouses must be in active workspace | NOT EXECUTED |
| Purchasing | `/purchase-invoices` | Procurement manager | Scoped invoices and canonical products render | Create/post invoice | Foreign product/warehouse rejected | NOT EXECUTED |
| Purchase returns | `/purchase-returns` | Procurement manager | Posted purchase is returnable | Create/complete return | Foreign invoice hidden | NOT EXECUTED |
| Proposals | `/sales-proposals` | Sales manager | Proposal lifecycle renders | Create/send/accept/convert | Foreign customer/proposal hidden | NOT EXECUTED |
| Sales invoices | `/sales-invoices` | Sales manager | Sales metrics and invoice lifecycle render | Create/post/pay | Foreign product/warehouse/invoice rejected | NOT EXECUTED |
| Sales returns | `/sales-returns` | Sales manager | Posted invoice lines are returnable | Create/complete return | Foreign invoice hidden | NOT EXECUTED |
| Accounting | `/accounting/accounts` | `account.view` | Accounts and real metrics render | Create account/journal/post | Foreign account/journal is 404 | NOT EXECUTED |
| HRM | `/hrm` | `hrm.view` | Employee, attendance, leave, payroll metrics render | HR lifecycle | Foreign employee/leave is 404 | NOT EXECUTED |
| CRM | `/crm` | `crm.view` | Leads, deals, pipeline metrics render | Lead conversion/deal update | Foreign lead/deal is 404 | NOT EXECUTED |
| Taskly | `/taskly` | `taskly.view` | Projects, tasks, cost and completion metrics render | Project/task/timesheet | Foreign project/task is 404 | NOT EXECUTED |
| POS | `/pos` | `pos.manage` | Register state, revenue and low-stock metrics render | Open/checkout/refund/close | Foreign register/order is 404 | NOT EXECUTED |
| Helpdesk | `/helpdesk/tickets` | `helpdesk.view` | Scoped tickets, filters and replies render | Ticket/reply/status | Foreign ticket/attachment hidden | NOT EXECUTED |
| Media | `/media` | Authenticated member | Directory tree and private files render | Upload/move/delete/download | Foreign file/directory is 404 | NOT EXECUTED |
| Messenger | `/messenger` | Authenticated member | Conversations, unread and pin state render | Send/edit/delete/pin | Nonparticipant conversation hidden | NOT EXECUTED |
| AI assistant shell | `/ai-assistant` | Authenticated member | Provider-neutral sessions/messages render | Create/send/archive | Foreign session hidden | NOT EXECUTED |
| Settings | `/settings` | Scoped settings permission | Effective hierarchy and safe fields render | Update setting | Scope escalation rejected; secrets masked | NOT EXECUTED |
| Localization | `/languages` | Super Admin | Languages, defaults and RTL metadata render | Enable/default/import | Platform-only mutation | NOT EXECUTED |
| Email templates | `/settings/email-templates` | Settings manager | Localized variants and variables render | Update/preview/test | Workspace/platform scope enforced | NOT EXECUTED |
| Notification templates | `/settings/notification-templates` | Settings manager | Localized channel variants render | Update template | Scope enforced | NOT EXECUTED |
| Notifications | `/notifications` | Authenticated member | Unread/read state renders | Mark read/all read | Only actor notifications mutate | NOT EXECUTED |
| Modules | `/modules` | `modules.manage` or Super Admin | Installed/entitled state renders | Activate/deactivate/install | Foreign workspace and unsigned ZIP rejected | NOT EXECUTED |
| Webhooks | `/webhooks` | Workspace webhook manager | Secrets remain hidden; delivery state renders | Create/rotate/replay | Foreign subscription/delivery hidden | NOT EXECUTED |
| API tokens | `/settings/api-tokens` | Authenticated member | Token inventory renders without token values | Create/revoke | Only actor tokens mutate | NOT EXECUTED |
| Installer | `/install` | Fresh deployment only | Multi-step installer completes once | Install lock/database/admin | Locked installation returns 404/redirect | NOT EXECUTED |
| Updater | `/update` | Super Admin | Signed manifest/history/rollback render | Install/rollback | Non-admin forbidden; maintenance always exits | NOT EXECUTED |
| Licensing authority | `/api/v1/licensing/activate` | Authority deployment only | Persisted valid license returns signed entitlement | Activation/deactivation | Route absent on customer installs | NOT EXECUTED |

For every executed row record build SHA, browser, actor IDs, organization/workspace IDs, request ID, expected/actual result, and any cleanup performed.
