# Gideon Missions & Workflow Inventory

**Discovery Date**: 2026-08-15  
**Auditor**: Antigravity AI Engine  
**Target Platform**: HiddenLeaf Business OS (`develop/hiddenleaf-v2`)  

---

## 1. Discovered Gideon Mission Assets

| Source Path | Language | Module / Class | Persistence Model | Trigger Mechanism | Execution Model | Approval Integration | Status |
|---|---|---|---|---|---|---|---|
| `Mr. Fox OS/Application/Missions/MissionStateMachine.php` | PHP | `MissionStateMachine` | `gideon_mission_runs` | Explicit / State transition | State Machine (Draft → Queued → Running → Completed) | Strict transition matrix | **REUSE & ADAPT** |
| `Mr. Fox OS/Domain/Missions/MissionDefinition.php` | PHP | `MissionDefinition` | YAML / Memory | YAML Spec Loading | Limits, Allowlist, Permissions | Declarative approval gates | **REUSE & ADAPT** |
| `Mr. Fox OS/Infrastructure/Persistence/Models/MissionRun.php` | PHP (Laravel) | `MissionRun` | `gideon_mission_runs` | Scheduled / User / Event | Step-by-step stateful run | Confirmation & approval pointers | **REUSE & ADAPT** |
| `Mr. Fox OS/Infrastructure/Persistence/Models/MissionStep.php` | PHP (Laravel) | `MissionStep` | `gideon_mission_steps` | Sequential planner | Step execution with checkpoints | Per-step confirmation | **REUSE & ADAPT** |
| `Mr. Fox OS/Infrastructure/Persistence/Models/MissionEvent.php` | PHP (Laravel) | `MissionEvent` | `gideon_mission_events` | Observable lifecycle | Event audit ledger | Step & run transitions | **REUSE & ADAPT** |
| `Mr. Fox OS/Missions/WeeklySocialCampaign/mission.yaml` | YAML | YAML Spec | File / System | Goal Trigger | Multi-step marketing flow | Draft review & submission approval | **REUSE & ADAPT** |
| `Mr. Fox OS/Missions/ContentStrategyReview/mission.yaml` | YAML | YAML Spec | File / System | Goal Trigger | Content review & skill execution | Review gates | **REUSE & ADAPT** |
| `Mr. Fox OS/Missions/AnalyticsToContent/mission.yaml` | YAML | YAML Spec | File / System | Goal Trigger | Analytics → Draft flow | Review gates | **REUSE & ADAPT** |
| `Mr. Fox OS/Missions/ApprovalRemediation/mission.yaml` | YAML | YAML Spec | File / System | Approval Event | Remediation flow | Step confirmations | **REUSE & ADAPT** |

---

## 2. Capability Overlap Analysis

1. **Deterministic Business Workflows**:
   - Gideon did not have a general-purpose deterministic event-condition-action (ECA) engine for ERP events (e.g. Lead created → Task created; Invoice overdue → Task created; Low stock → Restock task).
   - HiddenLeaf requires a high-throughput deterministic Automation Engine (`automation_rules`, `automation_runs`, `automation_run_steps`) for instant, rule-based execution without LLM overhead.

2. **Goal-Oriented AI Missions**:
   - Gideon provided a mature state machine, YAML definition spec, step executor, and step checkpointing architecture for multi-step AI-governed objectives.
   - We adapt Gideon's `MissionDefinition`, `MissionRun`, `MissionStep`, and `MissionStateMachine` under Mr. Fox's tool and approval infrastructure (`ActionApprovalService`, `MrFoxToolRegistry`, `MrFoxBrandProfile`, `ContentReviewEngine`).

---

## 3. Merged Unified Architecture

There will be **ONE** automation platform with **TWO** execution modes:
1. **Deterministic Automations**: `Business Event → Trigger → Conditions (AND/OR) → Registered Safe Actions → (Optional Approval if High-Risk)`.
2. **Intelligent Missions**: `Goal/Objective → Mr. Fox Plan → Step-by-Step Tool Execution → Observation Loop → Approval Gate → Completion`.
