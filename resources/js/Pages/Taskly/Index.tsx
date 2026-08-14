import ModuleDashboard from '@/Components/ModuleDashboard';

export default function TasklyIndex() {
  return <ModuleDashboard title="Projects & Tasks" description="Live project delivery, task completion, milestone, and timesheet performance." metricLabels={{ projects: 'Projects', active_projects: 'Active Projects', tasks: 'Tasks', completed_tasks: 'Completed Tasks', overdue_tasks: 'Overdue Tasks', open_milestones: 'Open Milestones', approved_hours: 'Approved Hours' }} collections={[{ key: 'projects', title: 'Projects', columns: ['name', 'status', 'starts_on', 'due_on', 'budget'] }, { key: 'tasks', title: 'Tasks', columns: ['title', 'priority', 'due_on', 'completed_at'] }]} />;
}
