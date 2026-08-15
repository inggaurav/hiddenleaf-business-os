# WorkDo Behavior Parity Baseline

## 1. Frozen Baseline Commit Hashes

- **WorkDo Reference Repository**: `C:\Users\manag\Documents\Mr. Fox\workdo-dash-reference`
  - **Commit SHA**: `0b996a0050abcdffa2770a82fbb9261eeb2805bd`
- **HiddenLeaf Business OS Repository**: `C:\Users\manag\Documents\Mr. Fox\hiddenleaf-business-os`
  - **Branch**: `develop/workdo-behavior-parity` (branched from `develop/addon-framework`)
  - **Commit SHA**: `c8d30e860fbd760202e8b4746581d8a072a5cae7`

---

## 2. Audit Scope & Methodology

This phase evaluates end-to-end behavior parity between WorkDo Dash SaaS and HiddenLeaf Business OS across all user roles:
1. **Super Admin**
2. **Company Owner / Company Admin**
3. **Team User / Employee**
4. **Custom-Role User**

The assessment encompasses:
- Navigation trees, hierarchical menus, expand/collapse persistence
- Screen inventory (Dashboards, Index tables, Filters, Modals, Forms, Detail/Show views, Actions, Reports, Settings)
- Module gates and permission authorization
- Multi-viewport accessibility (Desktop 1440+, Mobile iPhone 390x844, Mobile Android 412x915)
- Exact feature parity vs. intentional design/architectural enhancements
