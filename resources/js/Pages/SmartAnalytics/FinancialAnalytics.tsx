import React from 'react';
import { Head, Link } from '@inertiajs/react';
import {
  TrendingUp,
  TrendingDown,
  ArrowLeft,
} from 'lucide-react';
import AppShell from '@/Layouts/AppShell';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { SimpleBarChart, ProgressDistribution } from '@/Components/UI/Charts';
import { formatINR, formatINRShort } from '@/lib/format';
import type { FinancialData, FinancialTransaction } from './types';

interface Props {
  workspace: { id: number; name?: string };
  financial: FinancialData;
}

export default function FinancialAnalytics({ workspace, financial }: Props) {
  const { revenue_analysis: rev, expense_analysis: exp, profitability, cash_flow, transactions } = financial;

  return (
    <AppShell>
      <Head title="Financial Analytics — Smart Analytics" />

      <div className="space-y-6 p-6">
        {/* Header */}
        <div className="flex items-center gap-3">
          <Link href="/smart-analytics/dashboard">
            <button className="rounded-lg bg-[var(--surface-3)] p-2 border border-[var(--border-medium)] hover:bg-[var(--border-medium)] active:scale-[0.98] transition">
              <ArrowLeft className="h-4 w-4 text-[var(--text-primary)]" />
            </button>
          </Link>
          <div>
            <h1 className="text-xl font-semibold text-[var(--text-primary)]">Financial Analytics</h1>
            <p className="mt-0.5 text-sm text-[var(--text-secondary)]">Revenue, expenses, and profitability analysis</p>
          </div>
        </div>

        {/* KPI Row */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card>
            <div className="flex items-center justify-between">
              <span className="text-xs text-[var(--text-tertiary)]">Revenue</span>
              {rev.growth >= 0 ? <TrendingUp className="h-4 w-4 text-emerald-400" /> : <TrendingDown className="h-4 w-4 text-red-400" />}
            </div>
            <p className="mt-1 text-2xl font-bold text-[var(--text-primary)]">{formatINRShort(rev.current)}</p>
            <p className={`text-xs font-medium ${rev.growth >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
              {rev.growth >= 0 ? '+' : ''}{rev.growth}% vs last month
            </p>
          </Card>
          <Card>
            <div className="flex items-center justify-between">
              <span className="text-xs text-[var(--text-tertiary)]">Expenses</span>
              {exp.growth >= 0 ? <TrendingUp className="h-4 w-4 text-red-400" /> : <TrendingDown className="h-4 w-4 text-emerald-400" />}
            </div>
            <p className="mt-1 text-2xl font-bold text-[var(--text-primary)]">{formatINRShort(exp.current)}</p>
            <p className={`text-xs font-medium ${exp.growth <= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
              {exp.growth >= 0 ? '+' : ''}{exp.growth}%
            </p>
          </Card>
          <Card>
            <span className="text-xs text-[var(--text-tertiary)]">Net Profit</span>
            <p className={`mt-1 text-2xl font-bold ${profitability.net_profit >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
              {formatINRShort(profitability.net_profit)}
            </p>
            <p className="text-xs text-[var(--text-secondary)]">{profitability.margin}% margin</p>
          </Card>
          <Card>
            <span className="text-xs text-[var(--text-tertiary)]">Cash Flow</span>
            <p className={`mt-1 text-2xl font-bold ${cash_flow.net >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
              {formatINRShort(cash_flow.net)}
            </p>
            <p className="text-xs text-[var(--text-secondary)]">In: {formatINRShort(cash_flow.inflow)} · Out: {formatINRShort(cash_flow.outflow)}</p>
          </Card>
        </div>

        {/* Revenue vs Expenses Trend + Revenue Categories */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Card padded={false}>
            <CardHeader title="Revenue vs Expenses (6 Months)" />
            <CardBody>
              <SimpleBarChart
                data={profitability.trend}
                primaryLabel="Revenue"
                secondaryLabel="Expenses"
                primaryColor="bg-emerald-500"
                secondaryColor="bg-red-500"
                height={200}
              />
            </CardBody>
          </Card>

          <Card padded={false}>
            <CardHeader title="Revenue by Category" />
            <CardBody>
              {rev.categories.length === 0 ? (
                <p className="text-xs text-[var(--text-tertiary)]">No categorized revenue data.</p>
              ) : (
                <ProgressDistribution items={rev.categories} />
              )}
            </CardBody>
          </Card>
        </div>

        {/* 12-Month Revenue Trend */}
        <Card padded={false}>
          <CardHeader title="Revenue Trend (12 Months)" />
          <CardBody>
            <SimpleBarChart
              data={rev.trend}
              primaryLabel="Revenue"
              primaryColor="bg-emerald-500"
              height={200}
              emptyMessage="No revenue trend data."
            />
          </CardBody>
        </Card>

        {/* Expense Categories + Transactions */}
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Card padded={false}>
            <CardHeader title="Expense Categories" />
            <CardBody>
              {exp.categories.length === 0 ? (
                <p className="text-xs text-[var(--text-tertiary)]">No categorized expense data.</p>
              ) : (
                <ProgressDistribution items={exp.categories} />
              )}
            </CardBody>
          </Card>

          <Card padded={false}>
            <CardHeader title="Recent Transactions" />
            <CardBody>
              <div className="space-y-2 max-h-80 overflow-y-auto">
                {transactions.length === 0 && <p className="text-xs text-[var(--text-tertiary)]">No transactions.</p>}
                {transactions.map((t: FinancialTransaction, i: number) => (
                  <div key={i} className="flex items-center justify-between rounded-lg border border-[var(--border-subtle)] bg-[var(--surface-2)] p-2.5">
                    <div>
                      <p className="text-sm font-medium text-[var(--text-primary)]">{t.reference}</p>
                      <p className="text-xs text-[var(--text-tertiary)]">{t.date} · {t.category}</p>
                    </div>
                    <p className={`text-sm font-semibold ${t.type === 'Revenue' ? 'text-emerald-400' : 'text-red-400'}`}>
                      {t.type === 'Revenue' ? '+' : '-'}{formatINR(t.amount)}
                    </p>
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
