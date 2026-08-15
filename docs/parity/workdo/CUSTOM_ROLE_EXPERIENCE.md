# WorkDo Custom Role Experience Baseline

## 1. Dynamic Granular Permission Filtering
- Permissions in WorkDo follow fine-grained action naming: `manage-X`, `create-X`, `edit-X`, `delete-X`, `show-X`.
- The frontend sidebar automatically collapses parent menu containers if none of their child routes are permitted.

## 2. Example: Restricted CRM + Taskly Role
- Permissions: `manage-leads`, `manage-deals`, `manage-project`, `manage-tasks`.
- Excluded: `manage-account`, `manage-hrm`, `manage-pos`, `manage-plans`, `manage-settings`.
- **Resulting UI**:
  - Sidebar ONLY displays Dashboard, CRM (Leads, Deals), and Project (Projects, Tasks).
  - Direct GET navigation to `/accounting/*`, `/hrm/*`, `/pos/*` throws 403 Forbidden.
  - Command palette filters out prohibited items.
