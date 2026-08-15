# Automations & Mr. Fox Missions Guide

## 1. Deterministic Automations vs. Intelligent Missions

| Capability | Best For | Token Cost | Human Approval |
|---|---|---|---|
| **Deterministic Automations** (`/automations`) | Predictable, rule-based workflows (e.g., when invoice overdue $\to$ create task) | Zero tokens | Automatic |
| **Mr. Fox Missions** (`/missions`) | Complex, multi-step autonomous goals requiring analysis (e.g., Q3 Inactive Lead Re-engagement) | AI model inference | High-risk steps require approval |

---

## 2. Setting Up a Deterministic Automation
1. Navigate to `/automations` and click **"Create Automation"**.
2. **Trigger**: Select an event (e.g., `lead.created`, `invoice.overdue`, `stock.low`).
3. **Condition**: Define criteria (e.g., `invoice.amount > 5000`).
4. **Action**: Choose the action (e.g., `task.create`, `notification.send`).
5. Click **Save & Enable**.

---

## 3. Launching a Mr. Fox Mission
1. Navigate to `/missions` and click **"New Mission"**.
2. Enter a natural language goal:
   > *"Analyze all dormant qualified leads and prepare draft re-engagement outreach."*
3. Mr. Fox breaks the goal into structured steps with tool allowlists and a maximum step budget.
4. If a step involves sending an external communication, Mr. Fox pauses in `waiting_for_approval`.
5. You approve or reject the action in the **Unified Approval Center** (`/command-center/approvals`).
