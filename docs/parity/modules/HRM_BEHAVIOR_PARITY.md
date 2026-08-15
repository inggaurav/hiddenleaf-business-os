# HRM Module Behavior Parity Audit

## WorkDo HRM Scope vs HiddenLeaf Business OS

| Capability / Screen | WorkDo Route | WorkDo UI Component | HiddenLeaf Route | HiddenLeaf Component | Parity Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **HRM Dashboard** | `hrm.index` | `Dashboard/Dashboard.tsx` | `/hrm/dashboard` | `Pages/Hrm/Dashboard.tsx` | EXACT |
| **Employees Directory** | `hrm.employees.index` | `Employees/Index.tsx` | `/hrm/employees` | `Pages/Hrm/Employees/Index.tsx` | EXACT |
| **Employee Profile / Show** | `hrm.employees.show` | `Employees/View.tsx` | `/hrm/employees/{id}` | `Pages/Hrm/Employees/Show.tsx` | EXACT |
| **Set Salary / Payroll Setup**| `hrm.set-salary.index` | `SetSalary/Index.tsx` | `/hrm/salary` | Backend / Combined | MISSING_NAVIGATION |
| **Payroll Processing** | `hrm.payrolls.index` | `Payrolls/Index.tsx` | `/hrm/payroll` | `Pages/Hrm/Payroll.tsx` | FUNCTIONALLY_EQUIVALENT |
| **Shifts Management** | `hrm.shifts.index` | `Shifts/Index.tsx` | `/hrm/shifts` | Backend only | MISSING_UI |
| **Attendances Tracking** | `hrm.attendances.index` | `Attendances/Index.tsx` | `/hrm/attendance` | `Pages/Hrm/Attendance.tsx` | EXACT |
| **Leave Types Setup** | `hrm.leave-types.index` | `LeaveTypes/Index.tsx` | `/hrm/leave-types` | Backend only | MISSING_NAVIGATION |
| **Leave Applications Calendar**| `hrm.leave-applications.calendar` | `LeaveApplications/Calendar.tsx` | `/hrm/leaves` | `Pages/Hrm/Leaves.tsx` | FUNCTIONALLY_EQUIVALENT |
| **Holidays** | `hrm.holidays.index` | `Holidays/Index.tsx` | `/hrm/holidays` | Backend only | MISSING_UI |
| **Branches, Depts, Designations**| `hrm.branches.index` | `SystemSetup/Branches/Index.tsx` | `/hrm/structure` | Backend only | MISSING_NAVIGATION |
| **Awards, Warnings, Promotions**| `hrm.awards.index` | `Awards/Index.tsx` | `/hrm/events` | Backend only | MISSING_UI |
