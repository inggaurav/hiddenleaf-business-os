import React from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { Button } from '@hiddenleaf/ui/Button';
import { Card } from '@hiddenleaf/ui/Card';

export default function AttendanceIndex() {
  const { date = '', employees = [], attendances = {}, flash = {} } = usePage<any>().props;

  const handleSelfMark = () => {
    router.post('/hrm/attendance/self');
  };

  const handleMark = (employeeId: number, status: string) => {
    router.post('/hrm/attendance', {
      employee_id: employeeId,
      attendance_date: date,
      status: status,
    });
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 space-y-6">
      <Head title="HRM ? Attendance" />

      {flash?.success && (
        <div className="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
          {flash.success}
        </div>
      )}

      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight">Daily Attendance Tracker</h1>
          <p className="text-sm text-slate-400">Date: {date} ? Record workforce attendance status</p>
        </div>
        <Button variant="primary" size="sm" onClick={handleSelfMark}>
          Clock In / Out
        </Button>
      </div>

      <Card title="Employee Attendance Roster">
        <div className="space-y-2 pt-2">
          {employees.map((emp: any) => {
            const current = attendances[emp.id];
            return (
              <div key={emp.id} className="flex items-center justify-between p-3 rounded bg-slate-900 border border-slate-800">
                <div>
                  <div className="text-sm font-semibold text-white">{emp.name}</div>
                  <div className="text-xs text-slate-400">{emp.employee_number}</div>
                </div>
                <div className="flex items-center gap-2">
                  <span className={`text-xs uppercase font-bold px-2 py-1 rounded ${current?.status === 'present' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'}`}>
                    {current?.status || 'unmarked'}
                  </span>
                  <Button variant="outline" size="sm" onClick={() => handleMark(emp.id, 'present')}>Present</Button>
                  <Button variant="danger" size="sm" onClick={() => handleMark(emp.id, 'absent')}>Absent</Button>
                </div>
              </div>
            );
          })}
        </div>
      </Card>
    </div>
  );
}
