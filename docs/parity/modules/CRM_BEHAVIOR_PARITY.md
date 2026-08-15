# CRM Module Behavior Parity Audit

## WorkDo CRM Scope vs HiddenLeaf Business OS

| Capability / Screen | WorkDo Route | WorkDo UI Component | HiddenLeaf Route | HiddenLeaf Component | Parity Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **CRM Dashboard** | `lead.index` | `packages/workdo/Lead/.../Dashboard` | `/crm` | `Pages/Crm/Dashboard.tsx` | EXACT |
| **Leads Kanban & List** | `lead.leads.index` | `Pages/Leads/Index.tsx` | `/crm/leads/list` | `Pages/Crm/Leads/List.tsx` | FUNCTIONALLY_EQUIVALENT |
| **Lead Create / Edit** | `lead.leads.create` | Modal / Drawer | `/crm/leads/create` | Modal / Drawer | EXACT |
| **Lead Detail / Show** | `lead.leads.show` | `Pages/Leads/View.tsx` | `/crm/leads/{lead}` | `Pages/Crm/Leads/Show.tsx` | EXACT |
| **Lead Conversion to Deal** | `lead.leads.convert-to-deal` | Conversion Dialog | `/crm/leads/{lead}/convert` | Conversion Dialog | EXACT |
| **Deals Kanban & List** | `lead.deals.index` | `Pages/Deals/Index.tsx` | `/crm/deals` | `Pages/Crm/Deals/Index.tsx` | FUNCTIONALLY_EQUIVALENT |
| **Deals Show / Pipeline** | `lead.deals.show` | `Pages/Deals/View.tsx` | `/crm/deals/{deal}` | `Pages/Crm/Deals/Show.tsx` | EXACT |
| **Pipelines Setup** | `lead.pipelines.index` | `Pages/SystemSetup/Pipelines/Index.tsx` | `/crm/pipelines` | Backend only / In Drawer | MISSING_NAVIGATION |
| **Stages Setup** | `lead.lead-stages.index` | `Pages/SystemSetup/LeadStages/Index.tsx` | `/crm/stages` | Backend only | MISSING_NAVIGATION |
| **Lead & Deal Reports** | `lead.reports.leads` | `Pages/Report/Index.tsx` | `/crm/reports` | Backend only | MISSING_UI |
