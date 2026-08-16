import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { Input } from '@/Components/UI/Input';
import { SectionHeader } from '@/Components/UI/SectionHeader';

export default function Salaries({ employees = [] }: any) {
  const [values, setValues] = useState<Record<number, string>>({});
  return <AppShell title="Set Salary" breadcrumbs={[{ label: 'HRM' }, { label: 'Set Salary' }]}><div className="space-y-6"><SectionHeader title="Employee Salary Setup" description="Maintain each employee's base salary before payroll components and deductions are applied." /><Card level={0} className="overflow-x-auto"><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-4">Employee</th><th>Number</th><th>Status</th><th>Basic Salary</th><th className="pr-4">Action</th></tr></thead><tbody>{employees.map((employee: any) => <tr key={employee.id} className="border-b border-[var(--border-subtle)]"><td className="p-4 font-semibold">{employee.name}</td><td>{employee.employee_number}</td><td>{employee.status}</td><td className="w-56"><Input type="number" min="0" step="0.01" value={values[employee.id] ?? employee.basic_salary} onChange={e => setValues({ ...values, [employee.id]: e.target.value })} /></td><td className="pr-4"><Button size="sm" variant="primary" onClick={() => router.put(`/hrm/employees/${employee.id}/salary`, { basic_salary: values[employee.id] ?? employee.basic_salary })}>Save</Button></td></tr>)}</tbody></table></Card></div></AppShell>;
}
