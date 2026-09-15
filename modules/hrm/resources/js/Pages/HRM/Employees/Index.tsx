import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';
import { Table } from '@hiddenleaf/ui/Table';

export default function EmployeesIndex() {
  const { employees = { data: [] }, branches = [], departments = [], designations = [], flash = {} } = usePage<any>().props;
  const [formOpen, setFormOpen] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    department_id: '',
    designation_id: '',
    basic_salary: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/hrm/employees', formData, {
      onSuccess: () => {
        setFormOpen(false);
        setFormData({ name: '', email: '', phone: '', department_id: '', designation_id: '', basic_salary: '' });
      },
    });
  };

  const columns = [
    { header: 'Employee Number', accessor: 'employee_number' as const },
    { header: 'Name', accessor: 'name' as const },
    { header: 'Email', accessor: 'email' as const },
    { header: 'Phone', accessor: 'phone' as const },
    { header: 'Status', accessor: 'status' as const },
    {
      header: 'Actions',
      accessor: 'id' as const,
      render: (id: any) => (
        <Link href={`/hrm/employees/${id}`}>
          <Button variant="ghost" size="sm">View Profile</Button>
        </Link>
      ),
    },
  ];

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Employees" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Employees Directory</h1>
          <p className="text-sm text-slate-400">Manage workforce directory, compensation, and profile records.</p>
        </div>
        <Button variant="primary" size="sm" onClick={() => setFormOpen(!formOpen)}>
          {formOpen ? 'Cancel' : 'Add Employee'}
        </Button>
      </div>

      {formOpen && (
        <Card title="Register New Employee" subtitle="Create employee profile in workspace">
          <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
            <div>
              <label className="block text-xs text-slate-400 mb-1">Full Name</label>
              <input
                className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                value={formData.name}
                onChange={e => setFormData({ ...formData, name: e.target.value })}
                required
              />
            </div>
            <div>
              <label className="block text-xs text-slate-400 mb-1">Email Address</label>
              <input
                type="email"
                className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                value={formData.email}
                onChange={e => setFormData({ ...formData, email: e.target.value })}
                required
              />
            </div>
            <div>
              <label className="block text-xs text-slate-400 mb-1">Phone</label>
              <input
                className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                value={formData.phone}
                onChange={e => setFormData({ ...formData, phone: e.target.value })}
              />
            </div>
            <div>
              <label className="block text-xs text-slate-400 mb-1">Department</label>
              <select
                className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                value={formData.department_id}
                onChange={e => setFormData({ ...formData, department_id: e.target.value })}
              >
                <option value="">Select Department</option>
                {departments.map((d: any) => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs text-slate-400 mb-1">Designation</label>
              <select
                className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                value={formData.designation_id}
                onChange={e => setFormData({ ...formData, designation_id: e.target.value })}
              >
                <option value="">Select Designation</option>
                {designations.map((d: any) => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs text-slate-400 mb-1">Basic Monthly Salary</label>
              <input
                type="number"
                step="0.01"
                className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
                value={formData.basic_salary}
                onChange={e => setFormData({ ...formData, basic_salary: e.target.value })}
              />
            </div>
            <div className="md:col-span-3 flex justify-end">
              <Button type="submit" variant="primary">Save Employee</Button>
            </div>
          </form>
        </Card>
      )}

      <Card title="Employee Records">
        <Table columns={columns} data={employees.data || []} emptyMessage="No employees registered." />
      </Card>
    </div>
  );
}
