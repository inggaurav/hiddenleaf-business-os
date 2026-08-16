import React from 'react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { SectionHeader } from '@/Components/UI/SectionHeader';

export default function LeaveBalances({ employees = [], leaveTypes = [], used = {} }: any) {
  const usage = Array.isArray(used) ? Object.fromEntries(used.map((row: any) => [`${row.employee_id}:${row.leave_type_id}`, row])) : used;
  return <AppShell title="Leave Balance" breadcrumbs={[{ label: 'HRM' }, { label: 'Leave Balance' }]}><div className="space-y-6"><SectionHeader title="Employee Leave Balances" description="Annual allowance, approved usage, and remaining balance by employee and leave type." /><Card level={0} className="overflow-x-auto"><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-4">Employee</th>{leaveTypes.map((type: any) => <th key={type.id}>{type.name}</th>)}</tr></thead><tbody>{employees.map((employee: any) => <tr key={employee.id} className="border-b border-[var(--border-subtle)]"><td className="p-4 font-semibold">{employee.name}</td>{leaveTypes.map((type: any) => { const consumed = Number(usage[`${employee.id}:${type.id}`]?.used || 0); const remaining = Number(type.annual_allowance || 0) - consumed; return <td key={type.id}><span className="font-semibold">{remaining}</span><span className="text-[var(--text-tertiary)]"> / {type.annual_allowance} days</span></td>; })}</tr>)}</tbody></table></Card></div></AppShell>;
}
