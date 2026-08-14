# Tenant Attack Coverage

This matrix is the release evidence index for cross-organization and cross-workspace isolation. A row is covered only when the cited test sends a foreign identifier or verifies that foreign data is absent from a tenant-scoped response. Ordinary happy-path CRUD is not counted as adversarial coverage.

| Surface | Attack or isolation assertion | Automated evidence | Status |
| --- | --- | --- | --- |
| Tenant context | Forged organization/workspace headers and unauthorized workspace switching are denied | `Tenancy/AdversarialTenancyTest::test_user_a_cannot_access_or_mutate_org_b_or_workspace_b`; `Tenancy/MultiTenancySecurityTest::test_workspace_switch_fails_for_unauthorized_organization_workspace` | Covered |
| Products/services | Foreign catalog records cannot be read or edited; listings exclude them | `ProductService/ProductServiceInventoryTest::test_cross_tenant_catalog_and_warehouse_idor_are_rejected`; `Api/ApiV1SuiteTest::test_products_and_services_are_paginated_and_tenant_scoped` | Covered |
| Warehouses | Foreign warehouse mutation and use in catalog endpoints are rejected | `ProductService/ProductServiceInventoryTest::test_cross_tenant_catalog_and_warehouse_idor_are_rejected`; `SalesProcurement/SalesProcurementCoreTest::test_catalog_endpoint_rejects_a_warehouse_from_another_tenant` | Covered |
| Inventory | Stock adjustment is tenant-scoped and a foreign product/warehouse pair is rejected | `ProductService/ProductServiceInventoryTest::test_catalog_crud_and_stock_adjustments_are_tenant_scoped_and_audited` and `::test_cross_tenant_catalog_and_warehouse_idor_are_rejected` | Covered |
| Purchases | Foreign invoices cannot be read, deleted, or posted; foreign warehouses cannot be selected | `SalesProcurement/SalesProcurementCoreTest::test_foreign_purchase_and_sales_invoices_cannot_be_read_mutated_or_posted`; `::test_catalog_endpoint_rejects_a_warehouse_from_another_tenant` | Covered |
| Sales | Foreign invoices cannot be read, deleted, or posted; catalog responses are tenant-scoped | `SalesProcurement/SalesProcurementCoreTest::test_foreign_purchase_and_sales_invoices_cannot_be_read_mutated_or_posted`; `::test_invoice_catalog_endpoints_return_real_tenant_scoped_products_and_services` | Covered |
| Accounting | A journal containing foreign-tenant accounts is rejected | `Modules/AccountingModuleTest::test_unbalanced_and_cross_tenant_journals_are_rejected` | Covered |
| Employees/HRM | A tenant cannot review a foreign leave request | `Modules/HrmModuleTest::test_leave_allowance_and_cross_tenant_review_are_rejected` | Covered |
| CRM | Foreign deals and nonmember assignments are rejected | `Modules/CrmModuleTest::test_cross_tenant_deal_and_nonmember_assignment_are_rejected` | Covered |
| Projects/tasks | Foreign tasks and nonmember assignment are rejected | `Modules/TasklyModuleTest::test_nonmember_assignment_and_cross_tenant_task_are_rejected` | Covered |
| POS | Foreign registers are rejected and insufficient stock cannot be bypassed | `Modules/PosModuleTest::test_insufficient_stock_and_cross_tenant_register_are_rejected` | Covered |
| Helpdesk | Foreign ticket access/mutation is rejected | `CommunicationsAndHelpdesk/CommunicationsHelpdeskMediaTest::test_helpdesk_media_and_messenger_reject_cross_workspace_idor` | Covered |
| Media | Foreign media/directory IDOR and private-download access are rejected | `CommunicationsAndHelpdesk/CommunicationsHelpdeskMediaTest::test_helpdesk_media_and_messenger_reject_cross_workspace_idor`; `::test_presence_and_private_media_download_are_functional` | Covered |
| Settings | Workspace and organization scope ownership is enforced; secrets are encrypted | `Settings/SettingsLocalizationTemplatesTest::test_system_settings_and_tenant_workspace_isolation`; `::test_hierarchical_settings_encrypt_secrets_and_enforce_scope_ownership` | Covered |
| Subscriptions | Subscription response uses the owned workspace context | `Api/ApiV1SuiteTest::test_role_directories_and_subscription_are_workspace_scoped` | Covered |
| Orders | Company administrators cannot access another organization's order | `SaaS/CompleteSaaSEngineTest::test_orders_are_tenant_isolated_for_companies` | Covered |
| Webhooks | Cross-tenant deletion and private-network destinations are rejected | `Webhooks/WebhookRuntimeTest::test_private_destination_and_cross_tenant_delete_are_rejected` | Covered |
| User administration | A company administrator cannot mutate a foreign user's status or password | `Tenancy/CrossTenantAdminSecurityTest::test_org_a_company_admin_cannot_change_password_or_toggle_status_of_org_b_user` | Covered |
| Dashboards | Counts and module metrics exclude foreign organization/workspace records | `Tenancy/DashboardTenantIsolationTest::test_tenant_dashboard_only_returns_scoped_organization_and_workspace_metrics`; `DashboardContractsTest::test_workspace_and_module_metrics_are_real_and_tenant_scoped` | Covered |

## Release rule

All cited tests must pass on SQLite and PostgreSQL. A new tenant-owned route or model is not covered by this matrix automatically; its pull request must add a foreign-ID read or mutation attempt and update this document.
