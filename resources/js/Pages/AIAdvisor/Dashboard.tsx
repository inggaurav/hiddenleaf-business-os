import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
  Activity,
  AlertTriangle,
  CheckCircle,
  Info,
  Lightbulb,
  RefreshCw,
  TrendingUp,
  History,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { Badge } from '@/Components/UI/Badge';
import { formatRelative } from '@/lib/format';
import type { AdvisorData, Insight, Recommendation, Alert } from './types';

interface Props {
  workspace: { id: number; name?: string };
  advisor: AdvisorData;
}

function scoreColor(score: number): string {
  if (score >= 80) return 'text-emerald-400';
  if (score >= 60) return 'text-sky-400';
  if (score >= 40) return 'text-amber-400';
  return 'text-red-400';
}

function scoreBg(score: number): string {
  if (score >= 80) return 'bg-emerald-500';
  if (score >= 60) return 'bg-sky-500';
  if (score >= 40) return 'bg-amber-500';
  return 'bg-red-500';
}

function severityIcon(severity: string) {
  switch (severity) {
    case 'critical': return <AlertTriangle className="h-4 w-4 text-red-400" />;
    case 'warning': return <AlertTriangle className="h-4 w-4 text-amber-400" />;
    case 'positive': return <CheckCircle className="h-4 w-4 text-emerald-400" />;
    default: return <Info className="h-4 w-4 text-sky-400" />;
  }
}

function severityBorder(severity: string): string {
  switch (severity) {
    case 'critical': return 'border-red-500/30 bg-red-500/5';
    case 'warning': return 'border-amber-500/30 bg-amber-500/5';
    case 'positive': return 'border-emerald-500/30 bg-emerald-500/5';
    default: return 'border-sky-500/30 bg-sky-500/5';
  }
}

function priorityVariant(priority: string): 'danger' | 'warning' | 'neutral' {
  switch (priority) {
    case 'high': return 'danger';
    case 'medium': return 'warning';
    default: return 'neutral';
  }
}

export default function Dashboard({ workspace, advisor }: Props) {
  const { health_score: hs, insights, recommendations, alerts, last_analysis } = advisor;
  const [analyzing, setAnalyzing] = React.useState(false);

  const handleAnalyze = () => {
    setAnalyzing(true);
    router.post('/ai-advisor/analyze', {}, {
      onFinish: () => setAnalyzing(false),
    });
  };

  const scores = [
    { label: 'Financial', value: hs.financial_score },
    { label: 'Team', value: hs.team_score },
    { label: 'Sales', value: hs.sales_score },
    { label: 'Projects', value: hs.project_score },
    { label: 'Operations', value: hs.operations_score },
  ];

  return (
    <AppShell>
      <Head title="AI Business Advisor" />

      <div className="space-y-6 p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-semibold text-[var(--text-primary)]">AI Business Advisor</h1>
            <p className="mt-0.5 text-sm text-[var(--text-secondary)]">
              AI-powered health scores, insights, and recommendations
              {last_analysis && <span className="ml-2 text-[var(--text-tertiary)]">· Last analysis: {formatRelative(last_analysis)}</span>}
            </p>
          </div>
          <div className="flex gap-2">
            <Link href="/ai-advisor/history">
              <button className="rounded-lg bg-[var(--surface-3)] px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition flex items-center gap-1.5">
                <History className="h-3.5 w-3.5" />
                History
              </button>
            </Link>
            <button
              onClick={handleAnalyze}
              disabled={analyzing}
              className="rounded-lg bg-[var(--surface-3)] px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition flex items-center gap-1.5 disabled:opacity-50"
            >
              <RefreshCw className={`h-3.5 w-3.5 ${analyzing ? 'animate-spin' : ''}`} />
              {analyzing ? 'Analyzing...' : 'Run Analysis'}
            </button>
          </div>
        </div>

        {/* Health Score Ring + Sub-scores */}
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-6">
          {/* Overall score */}
          <Card className="lg:col-span-1 flex flex-col items-center justify-center">
            <Activity className={`h-6 w-6 ${scoreColor(hs.score)}`} />
            <p className={`mt-2 text-4xl font-bold ${scoreColor(hs.score)}`}>{Math.round(hs.score)}</p>
            <p className="text-xs text-[var(--text-tertiary)]">Overall Health</p>
            <div className="mt-2 h-2 w-24 overflow-hidden rounded-full bg-[var(--surface-3)]">
              <div className={`h-full rounded-full ${scoreBg(hs.score)} transition-all`} style={{ width: `${hs.score}%` }} />
            </div>
          </Card>

          {/* Sub-scores */}
          {scores.map((s) => (
            <Card key={s.label}>
              <p className="text-xs text-[var(--text-tertiary)]">{s.label}</p>
              <p className={`mt-1 text-2xl font-bold ${scoreColor(s.value)}`}>{Math.round(s.value)}</p>
              <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-[var(--surface-3)]">
                <div className={`h-full rounded-full ${scoreBg(s.value)} transition-all`} style={{ width: `${s.value}%` }} />
              </div>
            </Card>
          ))}
        </div>

        {/* Alerts */}
        {alerts.length > 0 && (
          <Card padded={false}>
            <CardHeader title={`Alerts (${alerts.length})`} />
            <CardBody>
              <div className="space-y-2">
                {alerts.map((a: Alert, i: number) => (
                  <div key={i} className={`flex items-start gap-3 rounded-lg border p-3 ${a.severity === 'critical' ? 'border-red-500/30 bg-red-500/5' : 'border-amber-500/30 bg-amber-500/5'}`}>
                    <AlertTriangle className={`h-4 w-4 mt-0.5 ${a.severity === 'critical' ? 'text-red-400' : 'text-amber-400'}`} />
                    <div>
                      <p className="text-sm font-medium text-[var(--text-primary)]">{a.title}</p>
                      <p className="text-xs text-[var(--text-secondary)]">{a.message}</p>
                    </div>
                    <Badge variant={a.severity === 'critical' ? 'danger' : 'warning'} className="ml-auto shrink-0">{a.severity}</Badge>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        )}

        {/* Insights + Recommendations */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          {/* Insights */}
          <Card padded={false}>
            <CardHeader title={`Insights (${insights.length})`} />
            <CardBody>
              <div className="space-y-2">
                {insights.length === 0 && (
                  <p className="text-xs text-[var(--text-tertiary)]">No insights yet. Run an analysis to generate them.</p>
                )}
                {insights.map((ins: Insight, i: number) => (
                  <div key={i} className={`flex items-start gap-3 rounded-lg border p-3 ${severityBorder(ins.severity)}`}>
                    {severityIcon(ins.severity)}
                    <div>
                      <p className="text-sm font-medium text-[var(--text-primary)]">{ins.title}</p>
                      <p className="text-xs text-[var(--text-secondary)]">{ins.description}</p>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>

          {/* Recommendations */}
          <Card padded={false}>
            <CardHeader title={`Recommendations (${recommendations.length})`} />
            <CardBody>
              <div className="space-y-2">
                {recommendations.length === 0 && (
                  <p className="text-xs text-[var(--text-tertiary)]">No recommendations yet.</p>
                )}
                {recommendations.map((rec: Recommendation, i: number) => (
                  <div key={i} className="flex items-start gap-3 rounded-lg border border-[var(--border-subtle)] bg-[var(--surface-2)] p-3">
                    <Lightbulb className="h-4 w-4 mt-0.5 text-amber-400" />
                    <div className="flex-1">
                      <p className="text-sm font-medium text-[var(--text-primary)]">{rec.recommendation}</p>
                      <p className="text-xs text-[var(--text-secondary)]">{rec.reason}</p>
                      <div className="mt-1 flex gap-2">
                        <Badge variant={priorityVariant(rec.priority)} size="sm">{rec.priority}</Badge>
                        {rec.related_module && <Badge variant="neutral" size="sm">{rec.related_module}</Badge>}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </CardBody>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
