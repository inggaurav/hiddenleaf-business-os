import React, { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Badge, BadgeVariant } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import {
  BriefcaseBusiness,
  CreditCard,
  FileText,
  Receipt,
  CheckCircle2,
  XCircle,
  Clock,
  ExternalLink,
  DollarSign,
  Calendar,
  Send,
  ArrowUpRight,
  Filter,
  Eye,
  X,
  Building,
  User,
  ShieldCheck,
  ChevronRight,
} from 'lucide-react';
import { formatINR } from '@/lib/format';

interface PortalProps {
  portalRole: 'client' | 'vendor';
  party: {
    id: number;
    name: string;
    email?: string;
    contact?: string;
    balance?: number | string;
    billing_address?: string;
  };
  metrics?: {
    outstanding?: number;
    invoices?: number;
    proposals?: number;
    returns?: number;
    payments?: number;
    projects?: number;
  };
  invoices?: any[];
  proposals?: any[];
  returns?: any[];
  payments?: any[];
  notes?: any[];
  projects?: any[];
  projectPayments?: any[];
}

const money = (val: unknown) => {
  const n = Number(val || 0);
  return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const rows = (value: any) => (Array.isArray(value) ? value : value?.data || []);

export default function PortalDashboard({
  portalRole = 'client',
  party,
  metrics = {},
  invoices = [],
  proposals = [],
  returns = [],
  payments = [],
  notes = [],
  projects = [],
  projectPayments = [],
}: PortalProps) {
  const isClient = portalRole === 'client';
  const [activeTab, setActiveTab] = useState<string>(isClient ? 'overview' : 'invoices');
  const [selectedItem, setSelectedItem] = useState<{ type: string; data: any } | null>(null);

  // Payment form state
  const [payment, setPayment] = useState({
    project_id: '',
    payment_date: new Date().toISOString().slice(0, 10),
    amount: '',
    payment_method: 'bank',
    reference: '',
    notes: '',
  });
  const [submittingPayment, setSubmittingPayment] = useState(false);

  const proposalList = rows(proposals);
  const invoiceList = rows(invoices);
  const returnList = rows(returns);
  const paymentList = rows(payments);
  const noteList = rows(notes);
  const projectList = rows(projects);
  const projectPaymentList = rows(projectPayments);

  const submitPayment = (event: React.FormEvent) => {
    event.preventDefault();
    if (!payment.project_id || !payment.amount) return;
    setSubmittingPayment(true);
    router.post('/portal/project-payments', payment, {
      preserveScroll: true,
      onSuccess: () => {
        setPayment({
          project_id: '',
          payment_date: new Date().toISOString().slice(0, 10),
          amount: '',
          payment_method: 'bank',
          reference: '',
          notes: '',
        });
        setSubmittingPayment(false);
      },
      onError: () => setSubmittingPayment(false),
    });
  };

  const handleProposalDecision = (proposalId: number, decision: 'accepted' | 'rejected') => {
    router.post(`/portal/proposals/${proposalId}/decision`, { decision }, {
      preserveScroll: true,
      onSuccess: () => {
        if (selectedItem && selectedItem.data.id === proposalId) {
          setSelectedItem(null);
        }
      },
    });
  };

  const getStatusBadge = (status: string): { variant: BadgeVariant; label: string } => {
    const s = String(status || '').toLowerCase();
    if (['paid', 'accepted', 'approved', 'active', 'completed'].includes(s)) {
      return { variant: 'success', label: s.toUpperCase() };
    }
    if (['sent', 'pending', 'submitted', 'in_progress', 'draft'].includes(s)) {
      return { variant: 'warning', label: s.toUpperCase() };
    }
    if (['rejected', 'cancelled', 'overdue', 'unpaid'].includes(s)) {
      return { variant: 'danger', label: s.toUpperCase() };
    }
    return { variant: 'neutral', label: s.toUpperCase() };
  };

  const tabs = useMemo(() => {
    if (isClient) {
      return [
        { id: 'overview', label: 'Overview', count: null },
        { id: 'proposals', label: 'Proposals', count: proposalList.length },
        { id: 'invoices', label: 'Invoices', count: invoiceList.length },
        { id: 'payments', label: 'Payments', count: paymentList.length + projectPaymentList.length },
        { id: 'projects', label: 'Projects', count: projectList.length },
        { id: 'notes', label: 'Credit Notes', count: noteList.length },
      ];
    }
    return [
      { id: 'invoices', label: 'Purchase Invoices', count: invoiceList.length },
      { id: 'returns', label: 'Purchase Returns', count: returnList.length },
      { id: 'payments', label: 'Payment Records', count: paymentList.length },
      { id: 'notes', label: 'Debit Notes', count: noteList.length },
    ];
  }, [isClient, proposalList, invoiceList, paymentList, projectPaymentList, projectList, noteList, returnList]);

  return (
    <AppShell
      title={`${isClient ? 'Client' : 'Vendor'} Portal`}
      breadcrumbs={[{ label: 'Portal' }, { label: isClient ? 'Client Area' : 'Vendor Area' }]}
    >
      <Head title={`${isClient ? 'Client' : 'Vendor'} Portal - ${party?.name || 'Dashboard'}`} />

      <div className="max-w-7xl mx-auto space-y-6 pb-12">
        {/* Header Banner */}
        <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-violet-600/20 via-indigo-600/10 to-transparent border border-[var(--border-subtle)] p-6 sm:p-8">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-2 mb-2">
                <Badge variant={isClient ? 'purple' : 'brand'} size="sm">
                  {isClient ? 'Client Portal' : 'Vendor Portal'}
                </Badge>
                <span className="text-xs text-[var(--text-tertiary)] flex items-center gap-1">
                  <ShieldCheck className="w-3.5 h-3.5 text-emerald-400" /> Verified Account
                </span>
              </div>
              <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-[var(--text-primary)]">
                Welcome, {party?.name || 'Valued Partner'}
              </h1>
              <p className="text-sm text-[var(--text-secondary)] mt-1 max-w-2xl">
                {isClient
                  ? 'Access your commercial proposals, track real-time invoice status, review project progress, and manage settlements.'
                  : 'Track submitted purchase orders, verify received disbursements, and reconcile debit credit positions.'}
              </p>
            </div>
            <div className="bg-[var(--surface-1)] border border-[var(--border-subtle)] rounded-xl p-4 sm:text-right flex-shrink-0">
              <span className="text-xs font-medium text-[var(--text-tertiary)] uppercase tracking-wider block">
                Outstanding Balance
              </span>
              <span className={`text-2xl font-bold tracking-tight ${Number(metrics.outstanding || 0) > 0 ? 'text-amber-400' : 'text-emerald-400'}`}>
                {money(metrics.outstanding)}
              </span>
            </div>
          </div>
        </div>

        {/* Metric Cards Grid */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <Card level={0} className="p-4 sm:p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-xs text-[var(--text-tertiary)] uppercase font-semibold">
                  {isClient ? 'Active Invoices' : 'Bills / Invoices'}
                </p>
                <p className="text-2xl font-bold mt-1 text-[var(--text-primary)]">{metrics.invoices || 0}</p>
              </div>
              <div className="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400">
                <Receipt className="w-5 h-5" />
              </div>
            </div>
          </Card>

          <Card level={0} className="p-4 sm:p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-xs text-[var(--text-tertiary)] uppercase font-semibold">
                  {isClient ? 'Proposals' : 'Returns'}
                </p>
                <p className="text-2xl font-bold mt-1 text-[var(--text-primary)]">
                  {isClient ? metrics.proposals || 0 : metrics.returns || 0}
                </p>
              </div>
              <div className="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400">
                <FileText className="w-5 h-5" />
              </div>
            </div>
          </Card>

          <Card level={0} className="p-4 sm:p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-xs text-[var(--text-tertiary)] uppercase font-semibold">
                  {isClient ? 'Live Projects' : 'Payments'}
                </p>
                <p className="text-2xl font-bold mt-1 text-[var(--text-primary)]">
                  {isClient ? metrics.projects || 0 : metrics.payments || 0}
                </p>
              </div>
              <div className="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                {isClient ? <BriefcaseBusiness className="w-5 h-5" /> : <CreditCard className="w-5 h-5" />}
              </div>
            </div>
          </Card>

          <Card level={0} className="p-4 sm:p-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-xs text-[var(--text-tertiary)] uppercase font-semibold">
                  {isClient ? 'Adjustments' : 'Debit Balance'}
                </p>
                <p className="text-2xl font-bold mt-1 text-[var(--text-primary)]">{noteList.length}</p>
              </div>
              <div className="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                <DollarSign className="w-5 h-5" />
              </div>
            </div>
          </Card>
        </div>

        {/* Navigation Tabs */}
        <div className="flex items-center gap-2 border-b border-[var(--border-subtle)] pb-px overflow-x-auto">
          {tabs.map((tab) => {
            const active = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-2 px-4 py-2.5 text-xs font-semibold rounded-t-lg transition-all border-b-2 cursor-pointer whitespace-nowrap ${
                  active
                    ? 'border-[var(--brand-primary)] text-[var(--text-primary)] bg-[var(--surface-1)]'
                    : 'border-transparent text-[var(--text-tertiary)] hover:text-[var(--text-secondary)] hover:bg-white/[0.02]'
                }`}
              >
                <span>{tab.label}</span>
                {tab.count !== null && (
                  <span
                    className={`px-1.5 py-0.5 rounded-full text-[10px] ${
                      active ? 'bg-[var(--brand-primary)]/20 text-indigo-300 font-bold' : 'bg-white/[0.05] text-[var(--text-tertiary)]'
                    }`}
                  >
                    {tab.count}
                  </span>
                )}
              </button>
            );
          })}
        </div>

        {/* Tab Content */}
        <div className="space-y-6">
          {/* Overview Tab (Client Only) */}
          {isClient && activeTab === 'overview' && (
            <div className="space-y-6">
              {/* Proposals pending action */}
              {proposalList.some((p: any) => ['sent', 'pending', 'draft'].includes(String(p.status))) && (
                <Card level={0} className="border-amber-500/30 bg-amber-500/5 p-5">
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                      <Clock className="w-4 h-4 text-amber-400" />
                      <h3 className="text-sm font-bold text-amber-300">Action Required: Pending Proposals</h3>
                    </div>
                    <Badge variant="warning" size="sm">Requires Decision</Badge>
                  </div>
                  <div className="divide-y divide-amber-500/15">
                    {proposalList
                      .filter((p: any) => ['sent', 'pending', 'draft'].includes(String(p.status)))
                      .map((p: any) => (
                        <div key={p.id} className="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                          <div>
                            <div className="text-sm font-semibold text-[var(--text-primary)]">
                              Proposal #{p.proposal_id || p.id}
                            </div>
                            <div className="text-xs text-[var(--text-tertiary)] mt-0.5">
                              Issued: {p.issue_date} • Total: <span className="font-bold text-white">{money(p.total_amount)}</span>
                            </div>
                          </div>
                          <div className="flex items-center gap-2">
                            <Button
                              size="sm"
                              variant="primary"
                              onClick={() => handleProposalDecision(p.id, 'accepted')}
                            >
                              Accept Proposal
                            </Button>
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => handleProposalDecision(p.id, 'rejected')}
                            >
                              Reject
                            </Button>
                          </div>
                        </div>
                      ))}
                  </div>
                </Card>
              )}

              {/* Projects & Quick Payment */}
              <div className="grid lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-4">
                  <Card level={0} className="p-0 overflow-hidden">
                    <CardHeader
                      title="Your Assigned Projects"
                      subtitle="Current projects and delivery status"
                      actions={
                        <Button size="sm" variant="ghost" onClick={() => setActiveTab('projects')}>
                          View All <ChevronRight className="w-3.5 h-3.5 ml-1" />
                        </Button>
                      }
                    />
                    <div className="divide-y divide-[var(--border-subtle)]">
                      {projectList.length === 0 ? (
                        <div className="p-8 text-center text-xs text-[var(--text-tertiary)]">
                          No active projects assigned to your account.
                        </div>
                      ) : (
                        projectList.slice(0, 4).map((project: any) => (
                          <div key={project.id} className="p-4 flex items-center justify-between hover:bg-white/[0.02] transition">
                            <div className="space-y-1">
                              <div className="text-sm font-semibold text-[var(--text-primary)]">{project.name}</div>
                              <div className="text-xs text-[var(--text-tertiary)] flex items-center gap-2">
                                <span>Budget: {money(project.budget)}</span>
                                <span>•</span>
                                <span>Due: {project.due_on || 'Ongoing'}</span>
                              </div>
                            </div>
                            <Badge variant={project.status === 'completed' ? 'success' : 'brand'} size="sm">
                              {project.status || 'Active'}
                            </Badge>
                          </div>
                        ))
                      )}
                    </div>
                  </Card>

                  <Card level={0} className="p-0 overflow-hidden">
                    <CardHeader
                      title="Recent Invoices"
                      subtitle="Latest billing records and payments due"
                      actions={
                        <Button size="sm" variant="ghost" onClick={() => setActiveTab('invoices')}>
                          View All <ChevronRight className="w-3.5 h-3.5 ml-1" />
                        </Button>
                      }
                    />
                    <div className="divide-y divide-[var(--border-subtle)]">
                      {invoiceList.length === 0 ? (
                        <div className="p-8 text-center text-xs text-[var(--text-tertiary)]">
                          No billing invoices generated yet.
                        </div>
                      ) : (
                        invoiceList.slice(0, 4).map((inv: any) => {
                          const badge = getStatusBadge(inv.status);
                          return (
                            <div key={inv.id} className="p-4 flex items-center justify-between hover:bg-white/[0.02] transition">
                              <div>
                                <div className="text-sm font-semibold text-[var(--text-primary)]">
                                  Invoice #{inv.invoice_id || inv.id}
                                </div>
                                <div className="text-xs text-[var(--text-tertiary)] mt-0.5">
                                  Due: {inv.due_date || 'N/A'} • Issue: {inv.issue_date}
                                </div>
                              </div>
                              <div className="text-right">
                                <div className="text-sm font-bold text-[var(--text-primary)]">
                                  {money(inv.total_amount)}
                                </div>
                                <Badge variant={badge.variant} size="sm">
                                  {badge.label}
                                </Badge>
                              </div>
                            </div>
                          );
                        })
                      )}
                    </div>
                  </Card>
                </div>

                {/* Submit Payment Widget */}
                <div>
                  <Card level={0} className="p-5 border-[var(--brand-primary)]/20 sticky top-20">
                    <div className="flex items-center gap-2 mb-4">
                      <div className="w-8 h-8 rounded-lg bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] flex items-center justify-center">
                        <CreditCard className="w-4 h-4" />
                      </div>
                      <div>
                        <h3 className="text-sm font-bold text-[var(--text-primary)]">Submit Project Payment</h3>
                        <p className="text-[11px] text-[var(--text-tertiary)]">Notify team of an offline or bank payment</p>
                      </div>
                    </div>

                    <form onSubmit={submitPayment} className="space-y-3">
                      <Select
                        label="Project"
                        value={payment.project_id}
                        onChange={(e) => setPayment({ ...payment, project_id: e.target.value })}
                        required
                      >
                        <option value="">Select an assigned project...</option>
                        {projectList.map((p: any) => (
                          <option key={p.id} value={p.id}>
                            {p.name}
                          </option>
                        ))}
                      </Select>

                      <div className="grid grid-cols-2 gap-2">
                        <Input
                          label="Amount (₹)"
                          type="number"
                          step="0.01"
                          min="0.01"
                          value={payment.amount}
                          onChange={(e) => setPayment({ ...payment, amount: e.target.value })}
                          required
                        />
                        <Input
                          label="Date"
                          type="date"
                          value={payment.payment_date}
                          onChange={(e) => setPayment({ ...payment, payment_date: e.target.value })}
                          required
                        />
                      </div>

                      <Select
                        label="Method"
                        value={payment.payment_method}
                        onChange={(e) => setPayment({ ...payment, payment_method: e.target.value })}
                      >
                        <option value="bank">Bank Transfer / NEFT / IMPS</option>
                        <option value="card">Credit / Debit Card</option>
                        <option value="online">UPI / Payment Gateway</option>
                        <option value="cash">Cash</option>
                        <option value="other">Other</option>
                      </Select>

                      <Input
                        label="Reference / Transaction ID"
                        placeholder="UTR / Txn Ref..."
                        value={payment.reference}
                        onChange={(e) => setPayment({ ...payment, reference: e.target.value })}
                      />

                      <Input
                        label="Notes (Optional)"
                        placeholder="Additional details..."
                        value={payment.notes}
                        onChange={(e) => setPayment({ ...payment, notes: e.target.value })}
                      />

                      <Button
                        type="submit"
                        variant="primary"
                        className="w-full mt-2"
                        disabled={submittingPayment}
                      >
                        {submittingPayment ? 'Submitting...' : 'Submit Payment Advice'}
                      </Button>
                    </form>
                  </Card>
                </div>
              </div>
            </div>
          )}

          {/* Proposals Tab */}
          {activeTab === 'proposals' && (
            <Card level={0} className="p-0 overflow-hidden">
              <CardHeader
                title="Commercial Proposals"
                subtitle="Review, approve, or reject sales proposals provided by your vendor."
              />
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="text-left border-b border-[var(--border-subtle)] bg-[var(--surface-1)]">
                      <th className="p-3.5 font-semibold">Proposal ID</th>
                      <th className="p-3.5 font-semibold">Issue Date</th>
                      <th className="p-3.5 font-semibold">Total Amount</th>
                      <th className="p-3.5 font-semibold">Status</th>
                      <th className="p-3.5 font-semibold text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border-subtle)]">
                    {proposalList.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="p-8 text-center text-[var(--text-tertiary)]">
                          No proposals have been issued yet.
                        </td>
                      </tr>
                    ) : (
                      proposalList.map((p: any) => {
                        const badge = getStatusBadge(p.status);
                        const canDecide = ['draft', 'sent', 'pending'].includes(String(p.status));
                        return (
                          <tr key={p.id} className="hover:bg-white/[0.02]">
                            <td className="p-3.5 font-medium text-[var(--text-primary)]">
                              #{p.proposal_id || p.id}
                            </td>
                            <td className="p-3.5 text-[var(--text-secondary)]">{p.issue_date}</td>
                            <td className="p-3.5 font-bold text-white">{money(p.total_amount)}</td>
                            <td className="p-3.5">
                              <Badge variant={badge.variant} size="sm">
                                {badge.label}
                              </Badge>
                            </td>
                            <td className="p-3.5 text-right">
                              {canDecide ? (
                                <div className="flex items-center justify-end gap-2">
                                  <Button
                                    size="sm"
                                    variant="primary"
                                    onClick={() => handleProposalDecision(p.id, 'accepted')}
                                  >
                                    Accept
                                  </Button>
                                  <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => handleProposalDecision(p.id, 'rejected')}
                                  >
                                    Reject
                                  </Button>
                                </div>
                              ) : (
                                <Button
                                  size="sm"
                                  variant="ghost"
                                  onClick={() => setSelectedItem({ type: 'proposal', data: p })}
                                >
                                  <Eye className="w-3.5 h-3.5 mr-1" /> View Details
                                </Button>
                              )}
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </Card>
          )}

          {/* Invoices Tab */}
          {activeTab === 'invoices' && (
            <Card level={0} className="p-0 overflow-hidden">
              <CardHeader
                title={isClient ? 'Sales Invoices' : 'Purchase Invoices'}
                subtitle={isClient ? 'View all invoices and payment deadlines.' : 'Supplier invoices submitted to the workspace.'}
              />
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="text-left border-b border-[var(--border-subtle)] bg-[var(--surface-1)]">
                      <th className="p-3.5 font-semibold">Invoice ID</th>
                      <th className="p-3.5 font-semibold">{isClient ? 'Issue Date' : 'Bill Date'}</th>
                      <th className="p-3.5 font-semibold">Due Date</th>
                      <th className="p-3.5 font-semibold">Amount</th>
                      <th className="p-3.5 font-semibold">Status</th>
                      <th className="p-3.5 font-semibold text-right">View</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border-subtle)]">
                    {invoiceList.length === 0 ? (
                      <tr>
                        <td colSpan={6} className="p-8 text-center text-[var(--text-tertiary)]">
                          No invoices recorded.
                        </td>
                      </tr>
                    ) : (
                      invoiceList.map((inv: any) => {
                        const badge = getStatusBadge(inv.status);
                        return (
                          <tr key={inv.id} className="hover:bg-white/[0.02]">
                            <td className="p-3.5 font-medium text-[var(--text-primary)]">
                              #{inv.invoice_id || inv.id}
                            </td>
                            <td className="p-3.5 text-[var(--text-secondary)]">
                              {inv.issue_date || inv.purchase_date || '—'}
                            </td>
                            <td className="p-3.5 text-[var(--text-secondary)]">{inv.due_date || '—'}</td>
                            <td className="p-3.5 font-bold text-white">{money(inv.total_amount)}</td>
                            <td className="p-3.5">
                              <Badge variant={badge.variant} size="sm">
                                {badge.label}
                              </Badge>
                            </td>
                            <td className="p-3.5 text-right">
                              <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setSelectedItem({ type: 'invoice', data: inv })}
                              >
                                <Eye className="w-3.5 h-3.5 mr-1" /> Inspect
                              </Button>
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </Card>
          )}

          {/* Payments Tab */}
          {activeTab === 'payments' && (
            <div className="space-y-6">
              <Card level={0} className="p-0 overflow-hidden">
                <CardHeader
                  title="Completed Payments"
                  subtitle="Recorded transactions and accounting settlements"
                />
                <div className="overflow-x-auto">
                  <table className="w-full text-xs">
                    <thead>
                      <tr className="text-left border-b border-[var(--border-subtle)] bg-[var(--surface-1)]">
                        <th className="p-3.5 font-semibold">Date</th>
                        <th className="p-3.5 font-semibold">Amount</th>
                        <th className="p-3.5 font-semibold">Method</th>
                        <th className="p-3.5 font-semibold">Reference</th>
                        <th className="p-3.5 font-semibold">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-[var(--border-subtle)]">
                      {paymentList.length === 0 ? (
                        <tr>
                          <td colSpan={5} className="p-8 text-center text-[var(--text-tertiary)]">
                            No payment transactions recorded yet.
                          </td>
                        </tr>
                      ) : (
                        paymentList.map((p: any) => {
                          const badge = getStatusBadge(p.status || 'paid');
                          return (
                            <tr key={p.id} className="hover:bg-white/[0.02]">
                              <td className="p-3.5 text-[var(--text-secondary)]">{p.payment_date || p.date}</td>
                              <td className="p-3.5 font-bold text-emerald-400">{money(p.amount)}</td>
                              <td className="p-3.5 capitalize text-[var(--text-secondary)]">
                                {p.payment_method || 'Bank'}
                              </td>
                              <td className="p-3.5 text-[var(--text-secondary)]">{p.reference || '—'}</td>
                              <td className="p-3.5">
                                <Badge variant={badge.variant} size="sm">
                                  {badge.label}
                                </Badge>
                              </td>
                            </tr>
                          );
                        })
                      )}
                    </tbody>
                  </table>
                </div>
              </Card>

              {isClient && (
                <Card level={0} className="p-0 overflow-hidden">
                  <CardHeader
                    title="Submitted Project Payments"
                    subtitle="Direct client-submitted payment advices awaiting verification"
                  />
                  <div className="overflow-x-auto">
                    <table className="w-full text-xs">
                      <thead>
                        <tr className="text-left border-b border-[var(--border-subtle)] bg-[var(--surface-1)]">
                          <th className="p-3.5 font-semibold">Payment Date</th>
                          <th className="p-3.5 font-semibold">Amount</th>
                          <th className="p-3.5 font-semibold">Method</th>
                          <th className="p-3.5 font-semibold">Reference</th>
                          <th className="p-3.5 font-semibold">Status</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[var(--border-subtle)]">
                        {projectPaymentList.length === 0 ? (
                          <tr>
                            <td colSpan={5} className="p-8 text-center text-[var(--text-tertiary)]">
                              No project payments submitted.
                            </td>
                          </tr>
                        ) : (
                          projectPaymentList.map((pp: any) => {
                            const badge = getStatusBadge(pp.status);
                            return (
                              <tr key={pp.id} className="hover:bg-white/[0.02]">
                                <td className="p-3.5 text-[var(--text-secondary)]">{pp.payment_date}</td>
                                <td className="p-3.5 font-bold text-white">{money(pp.amount)}</td>
                                <td className="p-3.5 capitalize text-[var(--text-secondary)]">{pp.payment_method}</td>
                                <td className="p-3.5 text-[var(--text-secondary)]">{pp.reference || '—'}</td>
                                <td className="p-3.5">
                                  <Badge variant={badge.variant} size="sm">
                                    {badge.label}
                                  </Badge>
                                </td>
                              </tr>
                            );
                          })
                        )}
                      </tbody>
                    </table>
                  </div>
                </Card>
              )}
            </div>
          )}

          {/* Projects Tab (Client Only) */}
          {isClient && activeTab === 'projects' && (
            <Card level={0} className="p-0 overflow-hidden">
              <CardHeader
                title="Delivery Projects"
                subtitle="All projects where you are assigned as stakeholder."
              />
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="text-left border-b border-[var(--border-subtle)] bg-[var(--surface-1)]">
                      <th className="p-3.5 font-semibold">Project Name</th>
                      <th className="p-3.5 font-semibold">Start Date</th>
                      <th className="p-3.5 font-semibold">Due Date</th>
                      <th className="p-3.5 font-semibold">Budget</th>
                      <th className="p-3.5 font-semibold">Status</th>
                      <th className="p-3.5 font-semibold text-right">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border-subtle)]">
                    {projectList.length === 0 ? (
                      <tr>
                        <td colSpan={6} className="p-8 text-center text-[var(--text-tertiary)]">
                          No projects found.
                        </td>
                      </tr>
                    ) : (
                      projectList.map((p: any) => {
                        const badge = getStatusBadge(p.status || 'active');
                        return (
                          <tr key={p.id} className="hover:bg-white/[0.02]">
                            <td className="p-3.5 font-medium text-[var(--text-primary)]">{p.name}</td>
                            <td className="p-3.5 text-[var(--text-secondary)]">{p.starts_on || '—'}</td>
                            <td className="p-3.5 text-[var(--text-secondary)]">{p.due_on || '—'}</td>
                            <td className="p-3.5 font-bold text-white">{money(p.budget)}</td>
                            <td className="p-3.5">
                              <Badge variant={badge.variant} size="sm">
                                {badge.label}
                              </Badge>
                            </td>
                            <td className="p-3.5 text-right">
                              <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                  setPayment({ ...payment, project_id: String(p.id) });
                                  setActiveTab('overview');
                                }}
                              >
                                Pay Against
                              </Button>
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </Card>
          )}

          {/* Notes Tab (Credit or Debit Notes) */}
          {activeTab === 'notes' && (
            <Card level={0} className="p-0 overflow-hidden">
              <CardHeader
                title={isClient ? 'Credit Notes' : 'Debit Notes'}
                subtitle="Issued adjustments, refunds, and discounts"
              />
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="text-left border-b border-[var(--border-subtle)] bg-[var(--surface-1)]">
                      <th className="p-3.5 font-semibold">Note #</th>
                      <th className="p-3.5 font-semibold">Date</th>
                      <th className="p-3.5 font-semibold">Amount</th>
                      <th className="p-3.5 font-semibold">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border-subtle)]">
                    {noteList.length === 0 ? (
                      <tr>
                        <td colSpan={4} className="p-8 text-center text-[var(--text-tertiary)]">
                          No notes issued.
                        </td>
                      </tr>
                    ) : (
                      noteList.map((n: any) => {
                        const badge = getStatusBadge(n.status || 'completed');
                        return (
                          <tr key={n.id} className="hover:bg-white/[0.02]">
                            <td className="p-3.5 font-medium text-[var(--text-primary)]">
                              #{n.note_number || n.id}
                            </td>
                            <td className="p-3.5 text-[var(--text-secondary)]">{n.date || '—'}</td>
                            <td className="p-3.5 font-bold text-white">{money(n.amount)}</td>
                            <td className="p-3.5">
                              <Badge variant={badge.variant} size="sm">
                                {badge.label}
                              </Badge>
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </Card>
          )}

          {/* Returns Tab (Vendor Only) */}
          {!isClient && activeTab === 'returns' && (
            <Card level={0} className="p-0 overflow-hidden">
              <CardHeader title="Purchase Returns" subtitle="Goods return vouchers and credit adjustments" />
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="text-left border-b border-[var(--border-subtle)] bg-[var(--surface-1)]">
                      <th className="p-3.5 font-semibold">Return ID</th>
                      <th className="p-3.5 font-semibold">Date</th>
                      <th className="p-3.5 font-semibold">Total Amount</th>
                      <th className="p-3.5 font-semibold">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border-subtle)]">
                    {returnList.length === 0 ? (
                      <tr>
                        <td colSpan={4} className="p-8 text-center text-[var(--text-tertiary)]">
                          No returns recorded.
                        </td>
                      </tr>
                    ) : (
                      returnList.map((r: any) => {
                        const badge = getStatusBadge(r.status);
                        return (
                          <tr key={r.id} className="hover:bg-white/[0.02]">
                            <td className="p-3.5 font-medium text-[var(--text-primary)]">
                              #{r.return_id || r.id}
                            </td>
                            <td className="p-3.5 text-[var(--text-secondary)]">{r.date}</td>
                            <td className="p-3.5 font-bold text-white">{money(r.total_amount)}</td>
                            <td className="p-3.5">
                              <Badge variant={badge.variant} size="sm">
                                {badge.label}
                              </Badge>
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </Card>
          )}
        </div>
      </div>

      {/* Slide-in Detail Drawer for Inspecting Items */}
      {selectedItem && (
        <div className="fixed inset-0 z-50 overflow-hidden">
          <div
            className="absolute inset-0 bg-black/60 backdrop-blur-sm"
            onClick={() => setSelectedItem(null)}
          />
          <div className="absolute inset-y-0 right-0 max-w-full flex pl-10">
            <div className="w-screen max-w-md bg-[var(--surface-1)] border-l border-[var(--border-subtle)] shadow-2xl p-6 flex flex-col justify-between overflow-y-auto">
              <div>
                <div className="flex items-center justify-between pb-4 border-b border-[var(--border-subtle)]">
                  <div>
                    <span className="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)]">
                      {selectedItem.type.toUpperCase()} DETAILS
                    </span>
                    <h2 className="text-lg font-bold text-[var(--text-primary)] mt-0.5">
                      #{selectedItem.data.invoice_id || selectedItem.data.proposal_id || selectedItem.data.id}
                    </h2>
                  </div>
                  <button
                    onClick={() => setSelectedItem(null)}
                    className="p-1 rounded-lg text-[var(--text-tertiary)] hover:text-white hover:bg-white/[0.05]"
                  >
                    <X className="w-5 h-5" />
                  </button>
                </div>

                <div className="mt-6 space-y-4 text-xs">
                  <div className="bg-[var(--surface-2)] p-4 rounded-xl space-y-2 border border-[var(--border-subtle)]">
                    <div className="flex justify-between">
                      <span className="text-[var(--text-tertiary)]">Total Valuation</span>
                      <span className="font-bold text-base text-white">
                        {money(selectedItem.data.total_amount || selectedItem.data.amount)}
                      </span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-[var(--text-tertiary)]">Status</span>
                      <Badge {...getStatusBadge(selectedItem.data.status)} size="sm" />
                    </div>
                    {selectedItem.data.issue_date && (
                      <div className="flex justify-between">
                        <span className="text-[var(--text-tertiary)]">Issue Date</span>
                        <span className="text-[var(--text-primary)]">{selectedItem.data.issue_date}</span>
                      </div>
                    )}
                    {selectedItem.data.due_date && (
                      <div className="flex justify-between">
                        <span className="text-[var(--text-tertiary)]">Due Date</span>
                        <span className="text-[var(--text-primary)]">{selectedItem.data.due_date}</span>
                      </div>
                    )}
                  </div>

                  {selectedItem.type === 'proposal' && (
                    <div className="space-y-3 pt-4">
                      <h4 className="font-semibold text-sm text-[var(--text-primary)]">Proposal Review</h4>
                      <p className="text-[var(--text-secondary)] leading-relaxed">
                        This commercial proposal is generated by your provider. You may accept or decline it directly from your portal dashboard.
                      </p>
                      {['draft', 'sent', 'pending'].includes(String(selectedItem.data.status)) && (
                        <div className="flex gap-2 pt-2">
                          <Button
                            variant="primary"
                            className="flex-1"
                            onClick={() => handleProposalDecision(selectedItem.data.id, 'accepted')}
                          >
                            Accept
                          </Button>
                          <Button
                            variant="ghost"
                            className="flex-1"
                            onClick={() => handleProposalDecision(selectedItem.data.id, 'rejected')}
                          >
                            Reject
                          </Button>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>

              <div className="pt-6 border-t border-[var(--border-subtle)]">
                <Button variant="ghost" className="w-full" onClick={() => setSelectedItem(null)}>
                  Close
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
