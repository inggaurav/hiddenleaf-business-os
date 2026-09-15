import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';

export default function ExitClearance() {
  const { employee = {}, clearances = [], isFullyCleared = false, flash = {} } = usePage<any>().props;
  const [dept, setDept] = useState('IT');
  const [item, setItem] = useState('');

  const handleAddItem = (e: React.FormEvent) => {
    e.preventDefault();
    router.post(`/hrm/exit/${employee.id}/clearance`, {
      items: [{ department: dept, clearance_item: item }],
    }, {
      onSuccess: () => setItem(''),
    });
  };

  const handleClear = (clearanceId: number) => {
    router.post(`/hrm/exit/${employee.id}/clearance/${clearanceId}/clear`);
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title={`HRM ? Clearance: ${employee.name}`} />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div>
        <Link href="/hrm/exit" className="text-xs text-indigo-400 hover:underline">? Back to Exits</Link>
        <h1 className="text-2xl font-bold text-white tracking-tight">Clearance Checklist: {employee.name}</h1>
        <p className="text-sm text-slate-400">Status: {isFullyCleared ? <span className="font-semibold text-emerald-400">Fully Cleared</span> : <span className="font-semibold text-amber-400">Pending Clearances</span>}</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card title="Add Clearance Item" subtitle="Assign departmental signoff">
          <form onSubmit={handleAddItem} className="space-y-4">
            <select
              className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={dept}
              onChange={e => setDept(e.target.value)}
            >
              <option value="IT">IT & Hardware</option>
              <option value="HR">HR & Admin</option>
              <option value="Finance">Finance & Accounts</option>
              <option value="Security">Facility & Security</option>
            </select>
            <input
              placeholder="e.g. Laptop & Charger Returned"
              className="w-full bg-slate-900 border border-slate-800 rounded p-2 text-sm text-white"
              value={item}
              onChange={e => setItem(e.target.value)}
              required
            />
            <Button type="submit" variant="primary">Add Checklist Item</Button>
          </form>
        </Card>

        <div className="md:col-span-2">
          <Card title="Required Clearances" subtitle="Departmental items to clear">
            <div className="space-y-2 pt-2">
              {clearances.map((c: any) => (
                <div key={c.id} className="flex items-center justify-between p-3 rounded bg-slate-900 border border-slate-800">
                  <div>
                    <div className="text-sm font-semibold text-white">{c.clearance_item}</div>
                    <div className="text-xs text-slate-400">Dept: {c.department}</div>
                  </div>
                  {c.status !== 'cleared' ? (
                    <Button variant="primary" size="sm" onClick={() => handleClear(c.id)}>Sign Off / Clear</Button>
                  ) : (
                    <span className="text-xs font-semibold text-emerald-400 uppercase">Cleared</span>
                  )}
                </div>
              ))}
              {clearances.length === 0 && (
                <div className="text-sm text-slate-400">No clearance items assigned yet.</div>
              )}
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}
