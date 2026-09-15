import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
  UserCheck,
  Target,
  Trophy,
  DollarSign,
  Percent,
  Plus,
  ArrowRight,
  Filter,
  CheckCircle2,
  Calendar as CalendarIcon,
  Activity,
  CheckSquare,
  Clock,
  ChevronLeft,
  ChevronRight,
  User,
  Phone,
  Video,
  Mail,
  ListTodo,
  X,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, MetricCard, CardHeader } from '@/Components/UI/Card';
import { Badge, BadgeVariant } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ProgressDistribution } from '@/Components/UI/Charts';
import { formatINR, formatINRShort } from '@/lib/format';

interface CrmStats {
  total_leads: number;
  open_leads: number;
  converted_leads: number;
  total_deals: number;
  open_deals: number;
  won_deals: number;
  lost_deals: number;
  pipeline_value: number;
  won_value: number;
  conversion_rate: number;
}

interface Pipeline {
  id: number;
  name: string;
  is_default: boolean;
}

interface StageDistributionItem {
  stage_id: number;
  name: string;
  deals: number;
  value: number;
  is_closed: boolean;
  outcome: string | null;
}

interface LeadItem {
  id: number;
  name: string;
  email: string;
  company: string;
  estimated_value: number;
  status: string;
  created_at: string;
}

interface DealItem {
  id: number;
  name: string;
  value: number;
  stage_name: string;
  status: string;
  expected_close_on: string | null;
  created_at: string;
}

interface ActivityItem {
  id: number;
  title: string;
  type: string;
  due_at: string | null;
  completed_at?: string | null;
  assigned_name?: string | null;
  created_at: string;
}

interface CalendarTaskItem {
  id: number;
  title: string;
  type: string;
  date: string;
  due_at: string;
  completed_at?: string | null;
  is_completed: boolean;
  assigned_name?: string | null;
}

interface TeamMember {
  id: number;
  name: string;
  email: string;
}

interface CrmDashboardProps {
  stats: CrmStats;
  pipelines: Pipeline[];
  activePipelineId: number | null;
  stageDistribution: StageDistributionItem[];
  recentLeads: LeadItem[];
  recentDeals: DealItem[];
  recentActivities: ActivityItem[];
  calendarTasks?: CalendarTaskItem[];
  teamMembers?: TeamMember[];
}

export default function CrmDashboard({
  stats,
  pipelines = [],
  activePipelineId,
  stageDistribution = [],
  recentLeads = [],
  recentDeals = [],
  recentActivities = [],
  calendarTasks = [],
  teamMembers = [],
}: CrmDashboardProps) {
  // Calendar View Navigation
  const [currentDate, setCurrentDate] = useState(() => new Date());
  const [selectedDate, setSelectedDate] = useState<string>(() => new Date().toISOString().slice(0, 10));
  const [taskModalOpen, setTaskModalOpen] = useState(false);
  const [newTask, setNewTask] = useState({
    title: '',
    type: 'task',
    due_at: new Date().toISOString().slice(0, 16),
    assigned_to: '',
  });
  const [submittingTask, setSubmittingTask] = useState(false);

  const handlePipelineChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const pipelineId = e.target.value;
    router.get('/crm/dashboard', pipelineId ? { pipeline_id: pipelineId } : {}, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const progressItems = stageDistribution.map((s) => ({
    name: s.name,
    value: s.deals,
    formattedValue: `${s.deals} deals (${formatINR(s.value)})`,
  }));

  // Calendar Helper Computations
  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
  ];

  const firstDayOfMonth = new Date(year, month, 1).getDay(); // 0 = Sun
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  const prevMonth = () => setCurrentDate(new Date(year, month - 1, 1));
  const nextMonth = () => setCurrentDate(new Date(year, month + 1, 1));
  const goToToday = () => {
    const now = new Date();
    setCurrentDate(now);
    setSelectedDate(now.toISOString().slice(0, 10));
  };

  // Group tasks by date
  const tasksByDate = React.useMemo(() => {
    const map: Record<string, CalendarTaskItem[]> = {};
    for (const t of calendarTasks) {
      if (!map[t.date]) map[t.date] = [];
      map[t.date].push(t);
    }
    return map;
  }, [calendarTasks]);

  const selectedDateTasks = tasksByDate[selectedDate] || [];

  const toggleTaskComplete = (taskId: number) => {
    router.post(`/crm/activities/${taskId}/toggle`, {}, {
      preserveScroll: true,
    });
  };

  const handleCreateTask = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTask.title.trim()) return;
    setSubmittingTask(true);
    router.post('/crm/tasks', newTask, {
      preserveScroll: true,
      onSuccess: () => {
        setTaskModalOpen(false);
        setNewTask({
          title: '',
          type: 'task',
          due_at: new Date().toISOString().slice(0, 16),
          assigned_to: '',
        });
        setSubmittingTask(false);
      },
      onError: () => setSubmittingTask(false),
    });
  };

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'call':
        return <Phone className="w-3.5 h-3.5 text-blue-400" />;
      case 'meeting':
        return <Video className="w-3.5 h-3.5 text-purple-400" />;
      case 'email':
        return <Mail className="w-3.5 h-3.5 text-amber-400" />;
      default:
        return <CheckSquare className="w-3.5 h-3.5 text-emerald-400" />;
    }
  };

  return (
    <AppShell title="CRM Dashboard">
      <Head title="CRM & Pipeline Dashboard" />

      <div className="space-y-8 pb-12">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <SectionHeader
            title="CRM Executive & Activity Dashboard"
            description="Real-time pipeline analytics, deal velocity, conversion rates, and scheduled CRM task execution."
            badge={<Badge variant="purple" size="sm">Workspace CRM</Badge>}
          />
          <div className="flex items-center gap-3">
            {pipelines.length > 0 && (
              <div className="flex items-center gap-2 bg-[var(--surface-1)] border border-[var(--border-subtle)] px-3 py-1.5 rounded-xl text-xs">
                <Filter className="h-3.5 w-3.5 text-[var(--text-tertiary)]" />
                <select
                  value={activePipelineId ?? ''}
                  onChange={handlePipelineChange}
                  className="bg-transparent border-none text-xs text-[var(--text-primary)] focus:outline-none cursor-pointer pr-4"
                >
                  {pipelines.map((p) => (
                    <option key={p.id} value={p.id} className="bg-[var(--surface-2)]">
                      {p.name}
                    </option>
                  ))}
                </select>
              </div>
            )}
            <Button
              variant="primary"
              size="sm"
              onClick={() => setTaskModalOpen(true)}
              className="flex items-center gap-1.5"
            >
              <Plus className="h-3.5 w-3.5" />
              <span>Schedule Task</span>
            </Button>
          </div>
        </div>

        {/* Executive Metrics Grid */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Total Leads"
            value={stats.total_leads}
            icon={<UserCheck className="w-5 h-5 text-indigo-400" />}
            trend={{
              value: String(stats.open_leads),
              neutral: true,
              label: `${stats.open_leads} active leads`,
            }}
          />
          <MetricCard
            title="Deals Pipeline"
            value={stats.total_deals}
            icon={<Target className="w-5 h-5 text-blue-400" />}
            trend={{
              value: String(stats.open_deals),
              neutral: true,
              label: `${stats.open_deals} deals open`,
            }}
          />
          <MetricCard
            title="Pipeline Value"
            value={formatINRShort(stats.pipeline_value)}
            icon={<DollarSign className="w-5 h-5 text-emerald-400" />}
            trend={{
              value: formatINRShort(stats.won_value),
              positive: true,
              label: `${formatINRShort(stats.won_value)} won`,
            }}
          />
          <MetricCard
            title="Win Conversion"
            value={`${stats.conversion_rate}%`}
            icon={<Percent className="w-5 h-5 text-purple-400" />}
            trend={{
              value: String(stats.won_deals),
              positive: stats.conversion_rate >= 20,
              label: `${stats.won_deals} closed won`,
            }}
          />
        </div>

        {/* Stage Distribution Progress */}
        {stageDistribution.length > 0 && (
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Active Pipeline Funnel Distribution
              </h3>
              <span className="text-xs text-[var(--text-tertiary)]">
                {stageDistribution.reduce((acc, s) => acc + s.deals, 0)} Total Deals in Flow
              </span>
            </div>
            <ProgressDistribution items={progressItems} />
          </Card>
        )}

        {/* Interactive CRM Tasks Calendar & Agenda Section */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Calendar Grid (2 cols) */}
          <Card level={0} className="lg:col-span-2 p-5 space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-[var(--border-subtle)]">
              <div className="flex items-center gap-2">
                <CalendarIcon className="w-5 h-5 text-indigo-400" />
                <h3 className="text-base font-bold text-[var(--text-primary)]">
                  CRM Tasks & Activities Calendar
                </h3>
              </div>
              <div className="flex items-center gap-2">
                <Button size="sm" variant="ghost" onClick={prevMonth}>
                  <ChevronLeft className="w-4 h-4" />
                </Button>
                <span className="text-xs font-semibold px-2 text-[var(--text-primary)] min-w-28 text-center">
                  {monthNames[month]} {year}
                </span>
                <Button size="sm" variant="ghost" onClick={nextMonth}>
                  <ChevronRight className="w-4 h-4" />
                </Button>
                <Button size="sm" variant="ghost" onClick={goToToday} className="text-xs text-indigo-400">
                  Today
                </Button>
              </div>
            </div>

            {/* Days of Week */}
            <div className="grid grid-cols-7 text-center text-xs font-semibold text-[var(--text-tertiary)] py-1">
              <span>Sun</span>
              <span>Mon</span>
              <span>Tue</span>
              <span>Wed</span>
              <span>Thu</span>
              <span>Fri</span>
              <span>Sat</span>
            </div>

            {/* Calendar Days Matrix */}
            <div className="grid grid-cols-7 gap-1 sm:gap-2">
              {/* Empty leading days */}
              {Array.from({ length: firstDayOfMonth }).map((_, i) => (
                <div key={`empty-${i}`} className="h-16 sm:h-20 rounded-xl bg-transparent opacity-20" />
              ))}

              {/* Month Days */}
              {Array.from({ length: daysInMonth }).map((_, i) => {
                const dayNum = i + 1;
                const dStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
                const isSelected = selectedDate === dStr;
                const isToday = new Date().toISOString().slice(0, 10) === dStr;
                const dayTasks = tasksByDate[dStr] || [];

                return (
                  <div
                    key={dStr}
                    onClick={() => setSelectedDate(dStr)}
                    className={`h-16 sm:h-20 p-1.5 rounded-xl border transition-all cursor-pointer flex flex-col justify-between select-none ${
                      isSelected
                        ? 'bg-[var(--brand-primary)]/15 border-[var(--brand-primary)] shadow-sm'
                        : isToday
                        ? 'bg-white/[0.04] border-indigo-500/40 hover:bg-white/[0.08]'
                        : 'bg-[var(--surface-1)] border-[var(--border-subtle)] hover:bg-white/[0.03]'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <span
                        className={`text-xs font-bold leading-none ${
                          isSelected
                            ? 'text-white'
                            : isToday
                            ? 'text-indigo-400'
                            : 'text-[var(--text-secondary)]'
                        }`}
                      >
                        {dayNum}
                      </span>
                      {dayTasks.length > 0 && (
                        <span className="w-2 h-2 rounded-full bg-emerald-400" />
                      )}
                    </div>

                    <div className="space-y-0.5 overflow-hidden">
                      {dayTasks.slice(0, 2).map((task) => (
                        <div
                          key={task.id}
                          className={`text-[9px] px-1 py-0.5 rounded truncate font-medium ${
                            task.is_completed
                              ? 'line-through bg-white/[0.03] text-[var(--text-tertiary)]'
                              : 'bg-indigo-500/20 text-indigo-200'
                          }`}
                        >
                          {task.title}
                        </div>
                      ))}
                      {dayTasks.length > 2 && (
                        <div className="text-[8px] text-[var(--text-tertiary)] px-1">
                          +{dayTasks.length - 2} more
                        </div>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </Card>

          {/* Agenda on Selected Date (1 col) */}
          <Card level={0} className="p-5 flex flex-col justify-between space-y-4">
            <div>
              <div className="flex items-center justify-between pb-3 border-b border-[var(--border-subtle)]">
                <div>
                  <h4 className="text-sm font-bold text-[var(--text-primary)]">Day Agenda</h4>
                  <p className="text-xs text-[var(--text-tertiary)] mt-0.5">
                    {new Date(selectedDate + 'T00:00:00').toLocaleDateString(undefined, {
                      weekday: 'long',
                      month: 'short',
                      day: 'numeric',
                      year: 'numeric',
                    })}
                  </p>
                </div>
                <Badge variant={selectedDateTasks.length > 0 ? 'purple' : 'neutral'} size="sm">
                  {selectedDateTasks.length} {selectedDateTasks.length === 1 ? 'task' : 'tasks'}
                </Badge>
              </div>

              <div className="mt-4 space-y-2.5 max-h-[340px] overflow-y-auto pr-1">
                {selectedDateTasks.length === 0 ? (
                  <div className="py-12 text-center text-xs text-[var(--text-tertiary)]">
                    No scheduled CRM tasks on this day.
                  </div>
                ) : (
                  selectedDateTasks.map((t) => (
                    <div
                      key={t.id}
                      className={`p-3 rounded-xl border transition-all flex items-start justify-between gap-3 ${
                        t.is_completed
                          ? 'bg-white/[0.02] border-[var(--border-subtle)] opacity-60'
                          : 'bg-[var(--surface-2)] border-[var(--border-subtle)] hover:border-indigo-500/30'
                      }`}
                    >
                      <div className="flex items-start gap-2.5">
                        <button
                          onClick={() => toggleTaskComplete(t.id)}
                          className={`mt-0.5 p-0.5 rounded cursor-pointer transition ${
                            t.is_completed
                              ? 'text-emerald-400 bg-emerald-500/10'
                              : 'text-[var(--text-tertiary)] hover:text-white'
                          }`}
                        >
                          <CheckCircle2 className="w-4 h-4" />
                        </button>
                        <div className="space-y-1">
                          <div
                            className={`text-xs font-semibold ${
                              t.is_completed ? 'line-through text-[var(--text-tertiary)]' : 'text-[var(--text-primary)]'
                            }`}
                          >
                            {t.title}
                          </div>
                          <div className="text-[11px] text-[var(--text-tertiary)] flex items-center gap-2">
                            <span className="flex items-center gap-1">
                              {getActivityIcon(t.type)}
                              <span className="capitalize">{t.type}</span>
                            </span>
                            {t.assigned_name && (
                              <>
                                <span>•</span>
                                <span className="flex items-center gap-1">
                                  <User className="w-3 h-3" />
                                  {t.assigned_name}
                                </span>
                              </>
                            )}
                          </div>
                        </div>
                      </div>
                      <Badge
                        variant={t.is_completed ? 'success' : 'neutral'}
                        size="sm"
                      >
                        {t.is_completed ? 'Done' : 'Pending'}
                      </Badge>
                    </div>
                  ))
                )}
              </div>
            </div>

            <Button
              variant="secondary"
              size="sm"
              className="w-full mt-4"
              onClick={() => {
                setNewTask({ ...newTask, due_at: `${selectedDate}T10:00` });
                setTaskModalOpen(true);
              }}
            >
              <Plus className="w-3.5 h-3.5 mr-1" />
              Add Task for This Date
            </Button>
          </Card>
        </div>

        {/* Bottom Details Grid: Leads, Deals, Recent Log */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* Recent Leads */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Recent Inbound Leads
              </h3>
              <Link
                href="/crm/leads"
                className="text-xs text-[var(--brand-primary)] hover:underline flex items-center gap-1"
              >
                <span>View all</span>
                <ArrowRight className="h-3 w-3" />
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentLeads.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No leads created yet.
                </div>
              ) : (
                recentLeads.map((lead) => (
                  <div key={lead.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {lead.name}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {lead.company}
                      </div>
                      <div className="text-xs text-[var(--text-tertiary)]">
                        {lead.created_at}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-[var(--text-primary)]">
                        {formatINR(lead.estimated_value)}
                      </div>
                      <Badge
                        variant={
                          lead.status === 'converted'
                            ? 'success'
                            : lead.status === 'open'
                            ? 'purple'
                            : 'neutral'
                        }
                        size="sm"
                      >
                        {lead.status}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent Deals */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Active Deals in Flow
              </h3>
              <Link
                href="/crm/deals"
                className="text-xs text-[var(--brand-primary)] hover:underline flex items-center gap-1"
              >
                <span>Kanban</span>
                <ArrowRight className="h-3 w-3" />
              </Link>
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentDeals.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No deals created yet.
                </div>
              ) : (
                recentDeals.map((deal) => (
                  <div key={deal.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[65%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {deal.name}
                      </div>
                      <div className="text-[11px] text-[var(--text-tertiary)] truncate">
                        {deal.stage_name}
                      </div>
                      <div className="text-xs text-[var(--text-tertiary)]">
                        Target: {deal.expected_close_on ?? 'TBD'}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs font-bold text-emerald-400">
                        {formatINR(deal.value)}
                      </div>
                      <Badge
                        variant={
                          deal.status === 'won'
                            ? 'success'
                            : deal.status === 'lost'
                            ? 'danger'
                            : 'neutral'
                        }
                        size="sm"
                      >
                        {deal.status}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          {/* Recent Activities Log */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">
                Activity Audit Trail
              </h3>
              <Activity className="h-4 w-4 text-violet-400" />
            </div>
            <div className="divide-y divide-[var(--border-subtle)]">
              {recentActivities.length === 0 ? (
                <div className="py-8 text-center text-xs text-[var(--text-tertiary)]">
                  No CRM activities logged.
                </div>
              ) : (
                recentActivities.map((activity) => (
                  <div key={activity.id} className="py-3 flex items-center justify-between">
                    <div className="space-y-0.5 max-w-[70%]">
                      <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                        {activity.title}
                      </div>
                      <div className="text-xs text-[var(--text-tertiary)] flex items-center gap-1.5">
                        <span>{activity.created_at}</span>
                        {activity.assigned_name && (
                          <>
                            <span>•</span>
                            <span>{activity.assigned_name}</span>
                          </>
                        )}
                      </div>
                    </div>
                    <div className="flex items-center gap-1.5">
                      <Badge variant="neutral" size="sm">
                        {activity.type}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>
        </div>
      </div>

      {/* Schedule CRM Task Modal */}
      {taskModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-[var(--surface-1)] border border-[var(--border-subtle)] rounded-2xl shadow-2xl p-6 space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-[var(--border-subtle)]">
              <div className="flex items-center gap-2">
                <ListTodo className="w-4 h-4 text-indigo-400" />
                <h3 className="text-base font-bold text-[var(--text-primary)]">Schedule CRM Task</h3>
              </div>
              <button
                onClick={() => setTaskModalOpen(false)}
                className="p-1 rounded-lg text-[var(--text-tertiary)] hover:text-white hover:bg-white/[0.05]"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleCreateTask} className="space-y-3 text-xs">
              <Input
                label="Task / Agenda Title"
                placeholder="Follow-up call with client regarding proposal..."
                value={newTask.title}
                onChange={(e) => setNewTask({ ...newTask, title: e.target.value })}
                required
              />

              <div className="grid grid-cols-2 gap-2">
                <Select
                  label="Activity Type"
                  value={newTask.type}
                  onChange={(e) => setNewTask({ ...newTask, type: e.target.value })}
                >
                  <option value="call">Call</option>
                  <option value="meeting">Meeting</option>
                  <option value="email">Email Followup</option>
                  <option value="task">General Task</option>
                </Select>

                <Select
                  label="Assign To"
                  value={newTask.assigned_to}
                  onChange={(e) => setNewTask({ ...newTask, assigned_to: e.target.value })}
                >
                  <option value="">Myself</option>
                  {teamMembers.map((m) => (
                    <option key={m.id} value={m.id}>
                      {m.name}
                    </option>
                  ))}
                </Select>
              </div>

              <Input
                label="Scheduled Date & Time"
                type="datetime-local"
                value={newTask.due_at}
                onChange={(e) => setNewTask({ ...newTask, due_at: e.target.value })}
                required
              />

              <div className="pt-2 flex justify-end gap-2">
                <Button variant="ghost" size="sm" onClick={() => setTaskModalOpen(false)}>
                  Cancel
                </Button>
                <Button variant="primary" size="sm" type="submit" disabled={submittingTask}>
                  {submittingTask ? 'Scheduling...' : 'Save & Schedule'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </AppShell>
  );
}
