import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { SimpleBarChart } from '@/Components/UI/Charts';
import { formatDate } from '@/lib/format';
import type { ScoreHistory } from './types';

interface Props {
  workspace: { id: number; name?: string };
  scores: ScoreHistory[];
}

function scoreColor(score: number): string {
  if (score >= 80) return 'text-emerald-400';
  if (score >= 60) return 'text-sky-400';
  if (score >= 40) return 'text-amber-400';
  return 'text-red-400';
}

export default function History({ workspace, scores }: Props) {
  const chartData = [...scores].reverse().map((s) => ({
    label: formatDate(s.scored_on),
    value: s.overall,
    formattedValue: `${Math.round(s.overall)}/100`,
  }));

  return (
    <AppShell>
      <Head title="Health Score History — AI Advisor" />

      <div className="space-y-6 p-6">
        {/* Header */}
        <div className="flex items-center gap-3">
          <Link href="/ai-advisor/dashboard">
            <button className="rounded-lg bg-[var(--surface-3)] p-2 border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition">
              <ArrowLeft className="h-4 w-4 text-[var(--text-primary)]" />
            </button>
          </Link>
          <div>
            <h1 className="text-xl font-semibold text-[var(--text-primary)]">Health Score History</h1>
            <p className="mt-0.5 text-sm text-[var(--text-secondary)]">Track your business health over time</p>
          </div>
        </div>

        {/* Trend Chart */}
        <Card padded={false}>
          <CardHeader title="Overall Health Trend" />
          <CardBody>
            {chartData.length === 0 ? (
              <p className="text-xs text-[var(--text-tertiary)]">No analysis history yet. Run your first analysis from the dashboard.</p>
            ) : (
              <SimpleBarChart
                data={chartData}
                primaryLabel="Health Score"
                primaryColor="bg-emerald-500"
                height={200}
              />
            )}
          </CardBody>
        </Card>

        {/* History Table */}
        <Card padded={false}>
          <CardHeader title="Score Breakdown" />
          <CardBody>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--border-subtle)]">
                    <th className="pb-2 text-left font-medium text-[var(--text-tertiary)]">Date</th>
                    <th className="pb-2 text-right font-medium text-[var(--text-tertiary)]">Overall</th>
                    <th className="pb-2 text-right font-medium text-[var(--text-tertiary)]">Financial</th>
                    <th className="pb-2 text-right font-medium text-[var(--text-tertiary)]">Team</th>
                    <th className="pb-2 text-right font-medium text-[var(--text-tertiary)]">Sales</th>
                    <th className="pb-2 text-right font-medium text-[var(--text-tertiary)]">Projects</th>
                  </tr>
                </thead>
                <tbody>
                  {scores.length === 0 && (
                    <tr>
                      <td colSpan={6} className="py-6 text-center text-xs text-[var(--text-tertiary)]">No history available.</td>
                    </tr>
                  )}
                  {scores.map((s) => (
                    <tr key={s.id} className="border-b border-[var(--border-subtle)] last:border-0">
                      <td className="py-2.5 text-[var(--text-primary)]">{formatDate(s.scored_on)}</td>
                      <td className={`py-2.5 text-right font-semibold ${scoreColor(s.overall)}`}>{Math.round(s.overall)}</td>
                      <td className={`py-2.5 text-right ${scoreColor(s.financial)}`}>{Math.round(s.financial)}</td>
                      <td className={`py-2.5 text-right ${scoreColor(s.team)}`}>{Math.round(s.team)}</td>
                      <td className={`py-2.5 text-right ${scoreColor(s.sales)}`}>{Math.round(s.sales)}</td>
                      <td className={`py-2.5 text-right ${scoreColor(s.project)}`}>{Math.round(s.project)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      </div>
    </AppShell>
  );
}
