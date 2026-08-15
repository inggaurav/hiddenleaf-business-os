# Mission & Automation Classification Matrix — HiddenLeaf Business OS

**Target Platform**: HiddenLeaf Business OS  
**Architecture**: Unified Automation & Missions Platform  

---

## 1. Classification of Execution Types

| Type | Execution Mode | Reasoning Layer | Trigger Mechanism | Statefulness | Approval Boundary | Example Use Cases |
|---|---|---|---|---|---|---|
| **Deterministic Automation** | Exact code execution | None (0 LLM tokens) | Domain Event / Webhook / DB Observer | Atomic run & steps | Automatic for safe actions; `ActionApprovalService` for high risk | • Lead created → Create Task<br>• Invoice overdue → Create task<br>• Low stock → Restock task<br>• Urgent message → Notify rep |
| **Marketing Campaign Mission** | Multi-step agent loop | LLM reasoning + Brand Profile + Skills | Scheduled / Goal request | Checkpointed Mission Run | Pre-execution confirmation + Draft approval | • "Prepare next week's social campaign based on Brand Profile"<br>• Content repurposing batch |
| **Sales Follow-up Mission** | Analytical & conversational loop | LLM reasoning + CRM tools | Goal / Scheduled | Checkpointed Mission Run | Approval required before outbound send | • "Review warm leads inactive for 5 days and draft personalized follow-ups" |
| **Collection Mission** | Financial inquiry + draft loop | LLM reasoning + Accounting tools | Goal / Scheduled | Checkpointed Mission Run | Approval required before sending notices | • "Review overdue invoices $\ge 14$ days and prepare collection messages" |
| **Knowledge & Policy Mission** | Read-only RAG synthesis | Governed Knowledge RAG | Goal / Ad-hoc | Checkpointed Mission Run | No mutation approval needed (Read-only) | • "Review our employee handbook and summarize vacation policies" |
| **Analytics & Anomaly Mission** | Read-only telemetry | Executive Insights + Data tools | Scheduled / Goal | Checkpointed Mission Run | No mutation approval needed (Read-only) | • "Analyze weekly sales anomalies across POS counter registers" |

---

## 2. Guardrails & Separation Rules

1. **Deterministic Rule Safety**:
   - Simple automations must NEVER require an LLM call to evaluate conditions (e.g. `amount > 500`, `stage == 'qualified'`).
   - All conditions are parsed deterministically.

2. **Mission Tool Allowlist & Risk Ceilings**:
   - Each mission declares its `allowed_tools` and `risk_ceiling`.
   - Even if the mission prompt requests destructive actions, the application layer blocks tools outside the allowlist.

3. **No Autonomous Critical Actions**:
   - High-risk actions (sending communications, financial writes, refunds, publishing) always pause for human approval via `ActionApprovalService`.
