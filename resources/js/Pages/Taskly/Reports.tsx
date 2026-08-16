import React from 'react';
import AppShell from '@/Layouts/AppShell';
import { Badge } from '@/Components/UI/Badge';
import { Card } from '@/Components/UI/Card';
import { SectionHeader } from '@/Components/UI/SectionHeader';

export default function Reports({ projects = [] }: any) {
  return <AppShell title="Projects Report" breadcrumbs={[{ label: 'Projects' }, { label: 'Reports' }]}><div className="space-y-6"><SectionHeader title="Projects Report" description="Budget, delivery, task completion, approved hours, membership and collected payment performance." badge={<Badge variant="purple" size="sm">{projects.length} Projects</Badge>} /><Card level={0} className="overflow-x-auto"><table className="w-full text-xs"><thead><tr className="text-left border-b border-[var(--border-subtle)]"><th className="p-4">Project</th><th>Status</th><th>Dates</th><th>Members</th><th>Tasks</th><th>Completion</th><th>Hours</th><th>Budget</th><th>Payments</th></tr></thead><tbody>{projects.map((project: any) => { const completion = project.tasks ? Math.round((project.completed_tasks / project.tasks) * 100) : 0; return <tr key={project.id} className="border-b border-[var(--border-subtle)]"><td className="p-4 font-semibold">{project.name}</td><td>{project.status}</td><td>{project.starts_on || '—'} → {project.due_on || '—'}</td><td>{project.members_count}</td><td>{project.completed_tasks}/{project.tasks}</td><td>{completion}%</td><td>{project.approved_hours}</td><td>{Number(project.budget || 0).toFixed(2)}</td><td>{Number(project.payments || 0).toFixed(2)}</td></tr>; })}</tbody></table></Card></div></AppShell>;
}
