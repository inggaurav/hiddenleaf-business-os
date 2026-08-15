import React from 'react';
import { router } from '@inertiajs/react';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { CheckCircle2, GitBranch, PlayCircle } from 'lucide-react';

interface Props {
  module: string;
  section: string;
  canManage: boolean;
  lookups: any;
}

const inputClass = 'w-full px-3 py-2 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-sm outline-none focus:border-purple-500/50';

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label className="space-y-1"><span className="block text-[11px] font-semibold text-[var(--text-secondary)]">{label}</span>{children}</label>;
}

function WorkflowCard({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  return <Card level={0} className="space-y-3"><div><h3 className="text-sm font-bold flex items-center gap-2"><PlayCircle className="w-4 h-4 text-purple-400" />{title}</h3><p className="text-[11px] text-[var(--text-tertiary)] mt-1">{description}</p></div>{children}</Card>;
}

function DealMove({ lookups }: any) {
  const deals = (lookups.deals || []).filter((deal: any) => deal.status === 'open');
  const [dealId, setDealId] = React.useState('');
  const [stageId, setStageId] = React.useState('');
  const [lossReason, setLossReason] = React.useState('');
  const deal = deals.find((item: any) => Number(item.id) === Number(dealId));
  const pipeline = (lookups.pipelines || []).find((item: any) => Number(item.id) === Number(deal?.pipeline_id));
  return <WorkflowCard title="Move Deal Stage" description="Advance an open deal through its pipeline; closed stages preserve the resulting won/lost status."><form onSubmit={e => { e.preventDefault(); if (dealId && stageId) router.post(`/crm/deals/${dealId}/move`, { stage_id: stageId, loss_reason: lossReason || null }, { preserveScroll: true }); }} className="grid grid-cols-1 md:grid-cols-3 gap-3"><Field label="Deal"><select className={inputClass} value={dealId} onChange={e => { setDealId(e.target.value); setStageId(''); }} required><option value="">Select</option>{deals.map((item:any)=><option key={item.id} value={item.id}>{item.name}</option>)}</select></Field><Field label="Stage"><select className={inputClass} value={stageId} onChange={e => setStageId(e.target.value)} required><option value="">Select</option>{(pipeline?.stages || []).map((stage:any)=><option key={stage.id} value={stage.id}>{stage.name}{stage.is_closed ? ` (${stage.outcome || 'closed'})` : ''}</option>)}</select></Field><Field label="Loss Reason (if lost)"><input className={inputClass} value={lossReason} onChange={e => setLossReason(e.target.value)} /></Field><div className="md:col-span-3 flex justify-end"><Button type="submit" variant="primary" icon={<GitBranch className="w-3.5 h-3.5" />}>Move Deal</Button></div></form></WorkflowCard>;
}

function LeaveReview({ lookups }: any) {
  const [id, setId] = React.useState(''); const [decision, setDecision] = React.useState('approved'); const [note, setNote] = React.useState('');
  return <WorkflowCard title="Review Leave" description="Approve or reject pending leave requests with an auditable review decision."><form onSubmit={e => { e.preventDefault(); if(id) router.post(`/hrm/leaves/${id}/review`, { decision, review_note: note || null }, { preserveScroll:true }); }} className="grid grid-cols-1 md:grid-cols-3 gap-3"><Field label="Pending Request"><select className={inputClass} value={id} onChange={e=>setId(e.target.value)} required><option value="">Select</option>{(lookups.pendingLeaves||[]).map((item:any)=><option key={item.id} value={item.id}>#{item.id} · Employee {item.employee_id} · {item.starts_on} → {item.ends_on}</option>)}</select></Field><Field label="Decision"><select className={inputClass} value={decision} onChange={e=>setDecision(e.target.value)}><option value="approved">Approve</option><option value="rejected">Reject</option></select></Field><Field label="Review Note"><input className={inputClass} value={note} onChange={e=>setNote(e.target.value)} /></Field><div className="md:col-span-3 flex justify-end"><Button type="submit" variant="primary" icon={<CheckCircle2 className="w-3.5 h-3.5" />}>Submit Review</Button></div></form></WorkflowCard>;
}

function PayslipPay({ lookups }: any) {
  const [id,setId]=React.useState('');
  return <WorkflowCard title="Pay Payslip" description="Mark a generated payslip paid using the canonical payroll posting service."><form onSubmit={e=>{e.preventDefault();if(id)router.post(`/hrm/payslips/${id}/pay`,{}, {preserveScroll:true});}} className="flex flex-col md:flex-row gap-3 md:items-end"><Field label="Unpaid Payslip"><select className={inputClass} value={id} onChange={e=>setId(e.target.value)} required><option value="">Select</option>{(lookups.unpaidPayslips||[]).map((item:any)=><option key={item.id} value={item.id}>#{item.id} · Employee {item.employee_id} · {item.period_start} → {item.period_end} · {item.net_pay}</option>)}</select></Field><Button type="submit" variant="primary">Mark Paid</Button></form></WorkflowCard>;
}

function TaskMove({ lookups }: any) {
  const [taskId,setTaskId]=React.useState(''); const [stageId,setStageId]=React.useState('');
  const task=(lookups.tasks||[]).find((item:any)=>Number(item.id)===Number(taskId));
  const project=(lookups.projects||[]).find((item:any)=>Number(item.id)===Number(task?.project_id));
  return <WorkflowCard title="Move Task" description="Move a task between project stages; completion stages automatically mark it completed."><form onSubmit={e=>{e.preventDefault();if(taskId&&stageId)router.post(`/taskly/tasks/${taskId}/move`,{stage_id:stageId},{preserveScroll:true});}} className="grid grid-cols-1 md:grid-cols-2 gap-3"><Field label="Task"><select className={inputClass} value={taskId} onChange={e=>{setTaskId(e.target.value);setStageId('');}} required><option value="">Select</option>{(lookups.tasks||[]).map((item:any)=><option key={item.id} value={item.id}>{item.title}</option>)}</select></Field><Field label="Target Stage"><select className={inputClass} value={stageId} onChange={e=>setStageId(e.target.value)} required><option value="">Select</option>{(project?.stages||[]).map((stage:any)=><option key={stage.id} value={stage.id}>{stage.name}</option>)}</select></Field><div className="md:col-span-2 flex justify-end"><Button type="submit" variant="primary">Move Task</Button></div></form></WorkflowCard>;
}

function ApproveTimesheet({ lookups }: any) {
  const [id,setId]=React.useState('');
  return <WorkflowCard title="Approve Timesheet" description="Approve submitted time and make it available to project cost reporting."><form onSubmit={e=>{e.preventDefault();if(id)router.post(`/taskly/timesheets/${id}/approve`,{}, {preserveScroll:true});}} className="flex flex-col md:flex-row gap-3 md:items-end"><Field label="Submitted Timesheet"><select className={inputClass} value={id} onChange={e=>setId(e.target.value)} required><option value="">Select</option>{(lookups.submittedTimesheets||[]).map((item:any)=><option key={item.id} value={item.id}>#{item.id} · Project {item.project_id} · User {item.user_id} · {item.hours}h · {item.work_date}</option>)}</select></Field><Button type="submit" variant="primary">Approve Time</Button></form></WorkflowCard>;
}

export function ModuleLifecycleActions({ module, section, canManage, lookups }: Props) {
  if (!canManage) return null;
  if (module === 'CRM' && section === 'deals') return <DealMove lookups={lookups} />;
  if (module === 'HRM' && section === 'leave-requests') return <LeaveReview lookups={lookups} />;
  if (module === 'HRM' && section === 'payroll') return <PayslipPay lookups={lookups} />;
  if (module === 'Projects & Tasks' && section === 'tasks') return <TaskMove lookups={lookups} />;
  if (module === 'Projects & Tasks' && section === 'timesheets') return <ApproveTimesheet lookups={lookups} />;
  return null;
}
