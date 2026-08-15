# Mr. Fox Executive Command Center Architecture

## 1. System Vision & Architecture
The **Mr. Fox Executive Command Center** is the unified intelligence and operating layer sitting above the HiddenLeaf Business OS. It aggregates canonical domain services across CRM, Accounting, Invoicing, Procurement, Inventory, HRM, Projects/Taskly, Unified Communications, Automations, and AI Missions into a cohesive, role-aware executive surface.

```text
                           HIDDENLEAF BUSINESS OS
                                     │
                                     ▼
                        BUSINESS HEALTH & SIGNAL ENGINE
                                     │
               ┌─────────────────────┼─────────────────────┐
               ▼                     ▼                     ▼
     DETERMINISTIC HEALTH    BUSINESS PRIORITIES    EXECUTIVE BRIEFINGS
      (Scores 0-100,          (Ranked Multi-Factor   (Period-over-Period
       Signals & Evidence)     Action Pipelines)      Deltas & Anomaly)
               │                     │                     │
               └─────────────────────┼─────────────────────┘
                                     │
                                     ▼
                         EXECUTIVE RECOMMENDATIONS
                        (Evidence-Backed Actions)
                                     │
                                     ▼
                         MR. FOX INTELLIGENCE CORE
                      (Multi-Tool Reasoning & Tools)
                                     │
                                     ▼
                          UNIFIED APPROVAL CENTER
                     (Human-in-the-Loop Authorization)
```

---

## 2. Core Subsystems

### A. Business Health Model
* Evaluates 10 core dimensions: `overall`, `receivables`, `payables`, `cash`, `crm`, `sales`, `inventory`, `projects`, `communications`, `hr`, and `operations`.
* **Zero LLM Hallucinations**: All metrics are calculated deterministically using mathematical formulas from verified database records.
* **Role-Aware Security (Phase 48 & 49)**: Users without appropriate module or RBAC permissions (e.g. Finance, HR) have those dimensions omitted. No confidential figures are leaked through aggregate totals.

### B. Signal Engine & Registry
* Auto-detects real operational risks:
  * `finance.overdue_receivables`
  * `finance.overdue_payables`
  * `crm.inactive_warm_leads`
  * `communications.urgent`
  * `communications.failed_delivery`
  * `inventory.low_stock`
  * `tasks.overdue`
  * `helpdesk.escalated`
  * `automation.failed_runs`
  * `mission.waiting_approval`
* Every signal carries typed severity, value, thresholds, and unified evidence links.

### C. Priority Ranking Engine
* Ranks signals into an actionable sequence:
  $$\text{Score} = \text{Severity Weight} + \min\left(500, \frac{\text{Financial Impact}}{100}\right) + \text{Customer Urgency Bonus}$$
* Severity Weights: `critical` = 1000, `warning` = 500, `attention` = 200, `info` = 50.

### D. Executive Recommendation Engine
* Derives safe recommendations directly from detected signals.
* SHA-256 fingerprint deduplication prevents duplicate recommendation cards on subsequent page loads.
* Exposes safe action routes: Open Invoices, Create Restock Task, Launch Collection Mission, Open Unified Inbox.

### E. Cross-Domain Executive Search & Command Palette (Cmd+K)
* Searches across Leads, Invoices, Customer Messages, Products, Tasks, Knowledge, Automations, Missions, and HR Employees.
* Gated by tenant scoping (`workspace_id`) and module/RBAC permissions.

### F. Mr. Fox Executive Tools
* `executive.health`: Returns health scores, statuses, and contributing signals.
* `executive.priorities`: Returns ranked priority items.
* `executive.briefing`: Returns morning briefing snapshot with period comparisons.
* `executive.recommendations`: Returns evidence-backed recommendations.
* `executive.activity`: Returns real-time cross-system event stream.
* `business.search`: Cross-domain search tool for natural language inquiries.

### G. Unified Approval Center
* Consolidates high-risk actions from AI missions, automations, and outbound communication replies.
* Enforces SHA-256 payload integrity, execution-time RBAC re-verification, and pessimistic row locking (`lockForUpdate()`).
