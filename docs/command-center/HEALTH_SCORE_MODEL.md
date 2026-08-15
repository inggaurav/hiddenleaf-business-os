# HiddenLeaf Business Health Score Model

## Overview
The **HiddenLeaf Business Health Engine** evaluates 10 discrete operational and financial dimensions into standardized scores (0 to 100). 

Every score is computed **deterministically** from verified database records. Large Language Models (LLMs) are strictly forbidden from fabricating or modifying scores; Mr. Fox's role is to explain the underlying signals and recommend remediation actions.

---

## Health Status Classifications

| Score Range | Status | Meaning | Action Trigger |
|---|---|---|---|
| **85 – 100** | `healthy` | Optimal operating parameters; negligible risks. | Normal monitoring |
| **70 – 84** | `watch` | Minor anomalies or manageable backlog detected. | Review during weekly sync |
| **50 – 69** | `warning` | Significant overdue items, stockouts, or communication delays. | Executive action recommended |
| **0 – 49** | `critical` | Severe liquidity crunch, critical escalations, or stalled operations. | Immediate priority intervention |
| **N/A** | `unknown` | Dimension disabled by module entitlement or hidden by RBAC. | Omitted / Hidden from UI |

---

## Dimension Calculation Methodologies

### 1. Overall Health
The overall score is a weighted composite of all active, authorized dimensions:
$$\text{Overall Score} = \sum_{i=1}^{N} \left( \text{Score}_i \times W_i \right)$$
*Where $W_i$ is normalized across all accessible modules (Financial: 30%, Sales/CRM: 20%, Operations/Tasks: 20%, Communications: 15%, Automations/Missions: 15%).*

---

### 2. Financial Health (`cash`, `receivables`, `payables`)
* **Cash Score**:
  $$\text{Score}_{\text{cash}} = 100 - \min(60, \text{Net Cash Outflow Ratio} \times 100)$$
* **Receivables Score**:
  $$\text{Score}_{\text{receivables}} = 100 - \left( \frac{\text{Overdue Amount}}{\text{Total Outstanding Receivables}} \times 70 + \min(30, \text{Count}_{\text{overdue}} \times 5) \right)$$
* **Payables Score**:
  $$\text{Score}_{\text{payables}} = 100 - \min(80, \frac{\text{Overdue Payables}}{\text{Total Payables}} \times 80)$$

---

### 3. Sales & CRM Health (`sales`, `crm`)
* **CRM Score**:
  $$\text{Score}_{\text{crm}} = 100 - \min(50, \text{Dormant Leads} \times 10) - \min(30, \text{Stalled Deals} \times 15)$$
* **Sales Conversion Score**:
  $$\text{Score}_{\text{sales}} = 60 + \min(40, \text{Win Rate} \times 40)$$

---

### 4. Customer Communications Health (`communications`)
* Based on attention priority and unresolved urgent messages:
  $$\text{Score}_{\text{comms}} = 100 - \min(60, \text{Urgent Conversations} \times 20) - \min(30, \text{Waiting } > 24\text{h} \times 10) - \min(20, \text{Failed Sends} \times 10)$$

---

### 5. Inventory Health (`inventory`)
* **Stock Availability**:
  $$\text{Score}_{\text{inv}} = 100 - \min(60, \text{Out of Stock Items} \times 20) - \min(40, \text{Low Stock Items} \times 5)$$

---

### 6. Projects & Task Health (`projects`)
* **Task Delivery Rate**:
  $$\text{Score}_{\text{tasks}} = 100 - \min(60, \text{Overdue Tasks} \times 10) - \min(30, \text{Unassigned High-Priority} \times 10)$$

---

### 7. Automations & Missions Health (`operations`)
* **Automation Reliability**:
  $$\text{Score}_{\text{ops}} = 100 - \min(50, \text{Failed Runs (24h)} \times 15) - \min(30, \text{Missions Stalled/Failed} \times 15)$$

---

## Role-Aware Privacy & Security Protection
If a user lacks permission for a domain (e.g. standard employee without `account.manage` or `hrm.manage` permissions):
1. The dimension score is **not computed or returned** to the client.
2. The overall composite score normalizes exclusively over the dimensions the user is authorized to inspect.
3. No aggregated signals or summary inferences leak hidden underlying numbers.
