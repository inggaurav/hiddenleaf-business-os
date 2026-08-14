import ModuleDashboard from '@/Components/ModuleDashboard';

export default function HRMIndex() {
  return <ModuleDashboard title="Human Resources" description="Live employee, attendance, leave, payroll, and department metrics." metricLabels={{ employees: 'Active Employees', attendance_today: 'Attendance Today', pending_leaves: 'Pending Leave', payroll_month: 'Monthly Payroll', departments: 'Departments', upcoming_holidays: 'Upcoming Holidays' }} collections={[{ key: 'employees', title: 'Employees', columns: ['employee_code', 'first_name', 'last_name', 'work_email', 'status', 'joined_on'] }]} />;
}
