import React, { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Card, CardBody, MetricCard } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { DataTable, Column } from '@/Components/UI/DataTable';
import { 
  MessageSquare, 
  Send, 
  Settings2, 
  Zap, 
  History, 
  Plus, 
  CheckCircle2, 
  XCircle, 
  Smartphone,
  Check,
  Trash2,
  Eye,
  AlertCircle
} from 'lucide-react';

interface Gateway {
  id: number;
  name: string;
  driver: string;
  is_default: boolean;
  is_active: boolean;
  masked_credentials: Record<string, string>;
  created_at: string;
}

interface Template {
  id: number;
  name: string;
  body: string;
  variables: string[];
  is_active: boolean;
  char_count: number;
  segments: number;
  triggers_count: number;
  created_at: string;
}

interface Trigger {
  id: number;
  event_name: string;
  event_label: string;
  template_id: number;
  template_name: string;
  conditions: Record<string, any>;
  is_active: boolean;
  last_fired_at: string | null;
  created_at: string;
}

interface LogEntry {
  id: number;
  to_number: string;
  to_name: string | null;
  body_sent: string;
  gateway_driver: string;
  status: string;
  error_message: string | null;
  cost_units: number;
  created_at: string;
}

interface Props {
  gateways: Gateway[];
  templates: Template[];
  triggers: Trigger[];
  logs: {
    data: LogEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  metrics: {
    sent_today: number;
    failed_today: number;
    cost_units_today: number;
    active_gateways: number;
  };
  supportedEvents: Record<string, string>;
  canManage: boolean;
}

export default function SmsIndex({
  gateways = [],
  templates = [],
  triggers = [],
  logs,
  metrics,
  supportedEvents = {},
  canManage = true,
}: Props) {
  const [activeTab, setActiveTab] = useState<'gateways' | 'templates' | 'triggers' | 'logs'>('gateways');

  // Modal states
  const [showGatewayModal, setShowGatewayModal] = useState(false);
  const [showTestModal, setShowTestModal] = useState(false);
  const [showTemplateModal, setShowTemplateModal] = useState(false);
  const [showTriggerModal, setShowTriggerModal] = useState(false);
  const [previewTemplate, setPreviewTemplate] = useState<Template | null>(null);
  const [selectedGateway, setSelectedGateway] = useState<Gateway | null>(null);

  // Form states
  const [testPhone, setTestPhone] = useState('');
  const [gatewayForm, setGatewayForm] = useState({
    name: '',
    driver: 'msg91',
    authkey: '',
    sender_id: 'HDNLF',
    route: '4',
    account_sid: '',
    auth_token: '',
    from_number: '',
    api_key: '',
    is_default: false,
  });

  const [templateForm, setTemplateForm] = useState({
    name: '',
    body: '',
  });

  const [triggerForm, setTriggerForm] = useState({
    event_name: Object.keys(supportedEvents)[0] || 'invoice.created',
    template_id: templates[0]?.id || '',
  });

  // Handle Gateway Submit
  const handleGatewaySubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const creds: Record<string, string> = {};
    if (gatewayForm.driver === 'msg91') {
      creds.authkey = gatewayForm.authkey;
      creds.sender_id = gatewayForm.sender_id;
      creds.route = gatewayForm.route;
    } else if (gatewayForm.driver === 'twilio') {
      creds.account_sid = gatewayForm.account_sid;
      creds.auth_token = gatewayForm.auth_token;
      creds.from_number = gatewayForm.from_number;
    } else if (gatewayForm.driver === 'fast2sms') {
      creds.api_key = gatewayForm.api_key;
    }

    router.post('/sms/gateways', {
      name: gatewayForm.name,
      driver: gatewayForm.driver,
      credentials: creds,
      is_default: gatewayForm.is_default,
    }, {
      onSuccess: () => {
        setShowGatewayModal(false);
        setGatewayForm({
          name: '',
          driver: 'msg91',
          authkey: '',
          sender_id: 'HDNLF',
          route: '4',
          account_sid: '',
          auth_token: '',
          from_number: '',
          api_key: '',
          is_default: false,
        });
      },
    });
  };

  // Handle Send Test SMS
  const handleSendTestSms = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedGateway) return;
    router.post(`/sms/gateways/${selectedGateway.id}/test`, {
      phone: testPhone,
    }, {
      onSuccess: () => {
        setShowTestModal(false);
        setTestPhone('');
      },
    });
  };

  // Handle Template Submit
  const handleTemplateSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/sms/templates', templateForm, {
      onSuccess: () => {
        setShowTemplateModal(false);
        setTemplateForm({ name: '', body: '' });
      },
    });
  };

  // Handle Trigger Submit
  const handleTriggerSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/sms/triggers', triggerForm, {
      onSuccess: () => {
        setShowTriggerModal(false);
      },
    });
  };

  // Gateway Columns
  const gatewayColumns: Column<Gateway>[] = [
    {
      header: 'Gateway Name',
      render: (gw) => (
        <div>
          <div className="font-medium text-[var(--text-primary)] flex items-center gap-2">
            {gw.name}
            {gw.is_default && (
              <span className="px-2 py-0.5 text-xs rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-normal">
                Default
              </span>
            )}
          </div>
          <div className="text-xs text-[var(--text-tertiary)] uppercase font-mono mt-0.5">
            Driver: {gw.driver}
          </div>
        </div>
      ),
    },
    {
      header: 'Status',
      render: (gw) => (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${
          gw.is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-zinc-500/10 text-zinc-400'
        }`}>
          <span className={`w-1.5 h-1.5 rounded-full ${gw.is_active ? 'bg-emerald-400' : 'bg-zinc-400'}`} />
          {gw.is_active ? 'Active' : 'Disabled'}
        </span>
      ),
    },
    {
      header: 'Credentials',
      render: (gw) => (
        <div className="text-xs font-mono text-[var(--text-secondary)]">
          {Object.entries(gw.masked_credentials).map(([k, v]) => (
            <span key={k} className="mr-2 inline-block">
              {k}: <span className="text-[var(--text-tertiary)]">{v}</span>
            </span>
          ))}
        </div>
      ),
    },
    {
      header: 'Actions',
      align: 'right',
      render: (gw) => (
        <div className="flex items-center justify-end gap-2">
          {!gw.is_default && canManage && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => router.post(`/sms/gateways/${gw.id}/set-default`)}
            >
              Set Default
            </Button>
          )}
          <Button
            variant="neutral"
            size="sm"
            onClick={() => {
              setSelectedGateway(gw);
              setShowTestModal(true);
            }}
          >
            Send test SMS
          </Button>
        </div>
      ),
    },
  ];

  // Template Columns
  const templateColumns: Column<Template>[] = [
    {
      header: 'Template Name',
      render: (tpl) => (
        <div>
          <span className="font-medium text-[var(--text-primary)]">{tpl.name}</span>
          <div className="text-xs text-[var(--text-tertiary)]">
            Used in {tpl.triggers_count} trigger{tpl.triggers_count !== 1 ? 's' : ''}
          </div>
        </div>
      ),
    },
    {
      header: 'Body Preview',
      render: (tpl) => (
        <p className="text-xs text-[var(--text-secondary)] line-clamp-2 max-w-md font-sans">
          {tpl.body}
        </p>
      ),
    },
    {
      header: 'Length & Segments',
      render: (tpl) => (
        <div className="text-xs">
          <span className="font-mono text-[var(--text-primary)]">{tpl.char_count} chars</span>
          <span className="text-[var(--text-tertiary)]"> ({tpl.segments} segment{tpl.segments > 1 ? 's' : ''})</span>
        </div>
      ),
    },
    {
      header: 'Actions',
      align: 'right',
      render: (tpl) => (
        <div className="flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPreviewTemplate(tpl)}
          >
            <Eye className="w-3.5 h-3.5 mr-1" /> Preview
          </Button>
          {canManage && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                if (confirm(`Delete template "${tpl.name}"?`)) {
                  router.delete(`/sms/templates/${tpl.id}`);
                }
              }}
            >
              <Trash2 className="w-3.5 h-3.5 text-rose-400" />
            </Button>
          )}
        </div>
      ),
    },
  ];

  // Trigger Columns
  const triggerColumns: Column<Trigger>[] = [
    {
      header: 'Event Trigger',
      render: (trig) => (
        <div>
          <span className="font-medium text-[var(--text-primary)]">{trig.event_label}</span>
          <div className="text-xs font-mono text-[var(--text-tertiary)]">{trig.event_name}</div>
        </div>
      ),
    },
    {
      header: 'Linked Template',
      render: (trig) => (
        <span className="text-sm text-[var(--text-secondary)] font-medium">
          {trig.template_name}
        </span>
      ),
    },
    {
      header: 'Last Fired',
      render: (trig) => (
        <span className="text-xs text-[var(--text-tertiary)]">
          {trig.last_fired_at || 'Never'}
        </span>
      ),
    },
    {
      header: 'Status',
      render: (trig) => (
        <button
          disabled={!canManage}
          onClick={() => router.patch(`/sms/triggers/${trig.id}/toggle`)}
          className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${
            trig.is_active ? 'bg-emerald-500' : 'bg-[var(--surface-3)]'
          }`}
        >
          <span
            className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
              trig.is_active ? 'translate-x-4' : 'translate-x-0'
            }`}
          />
        </button>
      ),
    },
    {
      header: 'Actions',
      align: 'right',
      render: (trig) => canManage ? (
        <Button
          variant="outline"
          size="sm"
          onClick={() => {
            if (confirm('Delete this trigger rule?')) {
              router.delete(`/sms/triggers/${trig.id}`);
            }
          }}
        >
          <Trash2 className="w-3.5 h-3.5 text-rose-400" />
        </Button>
      ) : null,
    },
  ];

  // Log Columns: To, Message preview, Gateway, Status, Sent at, Cost
  const logColumns: Column<LogEntry>[] = [
    {
      header: 'To',
      render: (log) => (
        <div>
          <div className="font-medium text-[var(--text-primary)] font-mono">{log.to_number}</div>
          {log.to_name && <div className="text-xs text-[var(--text-tertiary)]">{log.to_name}</div>}
        </div>
      ),
    },
    {
      header: 'Message preview',
      render: (log) => (
        <p className="text-xs text-[var(--text-secondary)] line-clamp-2 max-w-sm">
          {log.body_sent}
        </p>
      ),
    },
    {
      header: 'Gateway',
      render: (log) => (
        <span className="text-xs font-mono uppercase px-2 py-0.5 rounded bg-[var(--surface-2)] text-[var(--text-secondary)]">
          {log.gateway_driver}
        </span>
      ),
    },
    {
      header: 'Status',
      render: (log) => (
        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${
          log.status === 'sent' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'
        }`}>
          {log.status === 'sent' ? <CheckCircle2 className="w-3 h-3" /> : <XCircle className="w-3 h-3" />}
          {log.status}
        </span>
      ),
    },
    {
      header: 'Sent at',
      render: (log) => (
        <span className="text-xs text-[var(--text-tertiary)]">
          {log.created_at}
        </span>
      ),
    },
    {
      header: 'Cost',
      align: 'right',
      render: (log) => (
        <span className="text-xs font-mono font-medium text-[var(--text-secondary)]">
          {log.cost_units} unit{log.cost_units !== 1 ? 's' : ''}
        </span>
      ),
    },
  ];

  return (
    <AppShell title="SMS Notifications">
      <Head title="Communications — SMS Notifications" />

      <div className="space-y-6">
        {/* Section Header */}
        <SectionHeader
          title="SMS Notifications"
          description="Manage SMS gateways, automated transaction triggers, templates, and delivery logs."
          actions={
            <div className="flex items-center gap-2">
              <Link href="/sms/manual">
                <Button variant="neutral" size="sm">
                  <Send className="w-3.5 h-3.5 mr-1" /> Manual Compose
                </Button>
              </Link>
            </div>
          }
        />

        {/* 4 Metrics Cards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            title="Delivered Today"
            value={metrics.sent_today.toString()}
            icon={<CheckCircle2 className="w-4 h-4 text-emerald-400" />}
          />
          <MetricCard
            title="Failed Today"
            value={metrics.failed_today.toString()}
            icon={<XCircle className="w-4 h-4 text-rose-400" />}
          />
          <MetricCard
            title="Credits / Units Used"
            value={metrics.cost_units_today.toString()}
            icon={<Zap className="w-4 h-4 text-amber-400" />}
          />
          <MetricCard
            title="Active Gateways"
            value={metrics.active_gateways.toString()}
            icon={<Smartphone className="w-4 h-4 text-cyan-400" />}
          />
        </div>

        {/* Navigation Tabs */}
        <div className="border-b border-[var(--border-subtle)] flex items-center justify-between">
          <div className="flex space-x-6">
            {[
              { key: 'gateways', label: 'Gateways', icon: Settings2, count: gateways.length },
              { key: 'templates', label: 'Templates', icon: MessageSquare, count: templates.length },
              { key: 'triggers', label: 'Triggers', icon: Zap, count: triggers.length },
              { key: 'logs', label: 'Logs', icon: History, count: logs?.total ?? logs?.data?.length ?? 0 },
            ].map((tab) => {
              const Icon = tab.icon;
              const isActive = activeTab === tab.key;
              return (
                <button
                  key={tab.key}
                  onClick={() => setActiveTab(tab.key as any)}
                  className={`pb-3 text-sm font-medium transition-colors relative flex items-center gap-2 ${
                    isActive
                      ? 'text-[var(--text-primary)] font-semibold'
                      : 'text-[var(--text-tertiary)] hover:text-[var(--text-secondary)]'
                  }`}
                >
                  <Icon className="w-4 h-4" />
                  {tab.label}
                  <span className="text-xs px-1.5 py-0.2 rounded-full bg-[var(--surface-2)] text-[var(--text-secondary)]">
                    {tab.count}
                  </span>
                  {isActive && (
                    <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-[var(--text-primary)] rounded-t-full" />
                  )}
                </button>
              );
            })}
          </div>

          <div>
            {activeTab === 'gateways' && canManage && (
              <Button variant="neutral" size="sm" onClick={() => setShowGatewayModal(true)}>
                <Plus className="w-3.5 h-3.5 mr-1" /> Add Gateway
              </Button>
            )}
            {activeTab === 'templates' && canManage && (
              <Button variant="neutral" size="sm" onClick={() => setShowTemplateModal(true)}>
                <Plus className="w-3.5 h-3.5 mr-1" /> New Template
              </Button>
            )}
            {activeTab === 'triggers' && canManage && (
              <Button variant="neutral" size="sm" onClick={() => setShowTriggerModal(true)}>
                <Plus className="w-3.5 h-3.5 mr-1" /> Connect Trigger
              </Button>
            )}
          </div>
        </div>

        {/* Tab 1: Gateways */}
        {activeTab === 'gateways' && (
          <DataTable
            data={gateways}
            columns={gatewayColumns}
            keyExtractor={(item) => item.id}
            searchable={true}
            searchPlaceholder="Search gateways..."
            emptyTitle="No SMS Gateways Configured"
            emptyDescription="Configure an MSG91, Twilio, or Fast2SMS gateway to begin dispatching transactional SMS."
          />
        )}

        {/* Tab 2: Templates */}
        {activeTab === 'templates' && (
          <DataTable
            data={templates}
            columns={templateColumns}
            keyExtractor={(item) => item.id}
            searchable={true}
            searchPlaceholder="Search templates..."
            emptyTitle="No SMS Templates Created"
            emptyDescription="Create reusable message templates with placeholders like {{customer_name}} and {{invoice_number}}."
          />
        )}

        {/* Tab 3: Triggers */}
        {activeTab === 'triggers' && (
          <DataTable
            data={triggers}
            columns={triggerColumns}
            keyExtractor={(item) => item.id}
            searchable={true}
            searchPlaceholder="Search triggers..."
            emptyTitle="No Automation Triggers Connected"
            emptyDescription="Connect business events (e.g. invoice.paid, lead.created) to send automated SMS notifications."
          />
        )}

        {/* Tab 4: Logs */}
        {activeTab === 'logs' && (
          <DataTable
            data={logs}
            columns={logColumns}
            keyExtractor={(item) => item.id}
            searchable={true}
            searchPlaceholder="Search logs by phone or message..."
            emptyTitle="No SMS Logs Recorded"
            emptyDescription="Dispatched SMS messages and delivery reports will appear here."
          />
        )}

        {/* Modal: Add Gateway */}
        {showGatewayModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <Card className="max-w-lg w-full bg-[var(--surface-1)]">
              <form onSubmit={handleGatewaySubmit} className="p-6 space-y-4">
                <h3 className="text-base font-semibold text-[var(--text-primary)]">Configure SMS Gateway</h3>
                
                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Gateway Name</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Primary MSG91 India"
                    value={gatewayForm.name}
                    onChange={(e) => setGatewayForm({ ...gatewayForm, name: e.target.value })}
                    className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Driver</label>
                  <select
                    value={gatewayForm.driver}
                    onChange={(e) => setGatewayForm({ ...gatewayForm, driver: e.target.value })}
                    className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                  >
                    <option value="msg91">MSG91 (Recommended for India SMBs)</option>
                    <option value="twilio">Twilio</option>
                    <option value="fast2sms">Fast2SMS</option>
                    <option value="fake">Fake Driver (Testing / Offline)</option>
                  </select>
                </div>

                {gatewayForm.driver === 'msg91' && (
                  <>
                    <div>
                      <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">MSG91 AuthKey</label>
                      <input
                        type="password"
                        required
                        value={gatewayForm.authkey}
                        onChange={(e) => setGatewayForm({ ...gatewayForm, authkey: e.target.value })}
                        className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                      />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Sender ID (6 Chars)</label>
                        <input
                          type="text"
                          required
                          value={gatewayForm.sender_id}
                          onChange={(e) => setGatewayForm({ ...gatewayForm, sender_id: e.target.value })}
                          className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Route</label>
                        <input
                          type="text"
                          value={gatewayForm.route}
                          onChange={(e) => setGatewayForm({ ...gatewayForm, route: e.target.value })}
                          className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                        />
                      </div>
                    </div>
                  </>
                )}

                {gatewayForm.driver === 'twilio' && (
                  <>
                    <div>
                      <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Account SID</label>
                      <input
                        type="text"
                        required
                        value={gatewayForm.account_sid}
                        onChange={(e) => setGatewayForm({ ...gatewayForm, account_sid: e.target.value })}
                        className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Auth Token</label>
                      <input
                        type="password"
                        required
                        value={gatewayForm.auth_token}
                        onChange={(e) => setGatewayForm({ ...gatewayForm, auth_token: e.target.value })}
                        className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">From Number / Sender</label>
                      <input
                        type="text"
                        required
                        placeholder="+15551234567"
                        value={gatewayForm.from_number}
                        onChange={(e) => setGatewayForm({ ...gatewayForm, from_number: e.target.value })}
                        className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                      />
                    </div>
                  </>
                )}

                {gatewayForm.driver === 'fast2sms' && (
                  <div>
                    <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Fast2SMS API Key</label>
                    <input
                      type="password"
                      required
                      value={gatewayForm.api_key}
                      onChange={(e) => setGatewayForm({ ...gatewayForm, api_key: e.target.value })}
                      className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                    />
                  </div>
                )}

                <div className="flex items-center gap-2 pt-2">
                  <input
                    type="checkbox"
                    id="is_default"
                    checked={gatewayForm.is_default}
                    onChange={(e) => setGatewayForm({ ...gatewayForm, is_default: e.target.checked })}
                    className="rounded border-[var(--border-medium)]"
                  />
                  <label htmlFor="is_default" className="text-xs text-[var(--text-secondary)]">
                    Make this the default active SMS gateway
                  </label>
                </div>

                <div className="flex justify-end gap-2 pt-4">
                  <Button variant="outline" type="button" onClick={() => setShowGatewayModal(false)}>
                    Cancel
                  </Button>
                  <Button variant="neutral" type="submit">
                    Save Gateway
                  </Button>
                </div>
              </form>
            </Card>
          </div>
        )}

        {/* Modal: Send Test SMS */}
        {showTestModal && selectedGateway && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <Card className="max-w-md w-full bg-[var(--surface-1)]">
              <form onSubmit={handleSendTestSms} className="p-6 space-y-4">
                <h3 className="text-base font-semibold text-[var(--text-primary)]">Send Test SMS</h3>
                <p className="text-xs text-[var(--text-secondary)]">
                  Dispatch a live verification message using <strong>{selectedGateway.name}</strong> ({selectedGateway.driver}).
                </p>

                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Recipient Mobile Number</label>
                  <input
                    type="text"
                    required
                    placeholder="+91 98123 45678"
                    value={testPhone}
                    onChange={(e) => setTestPhone(e.target.value)}
                    className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)] font-mono"
                  />
                </div>

                <div className="flex justify-end gap-2 pt-4">
                  <Button variant="outline" type="button" onClick={() => setShowTestModal(false)}>
                    Cancel
                  </Button>
                  <Button variant="neutral" type="submit">
                    <Send className="w-3.5 h-3.5 mr-1" /> Dispatch Test
                  </Button>
                </div>
              </form>
            </Card>
          </div>
        )}

        {/* Modal: New Template */}
        {showTemplateModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <Card className="max-w-lg w-full bg-[var(--surface-1)]">
              <form onSubmit={handleTemplateSubmit} className="p-6 space-y-4">
                <h3 className="text-base font-semibold text-[var(--text-primary)]">Create SMS Template</h3>
                
                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Template Name</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Invoice Payment Reminder"
                    value={templateForm.name}
                    onChange={(e) => setTemplateForm({ ...templateForm, name: e.target.value })}
                    className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                  />
                </div>

                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="text-xs font-medium text-[var(--text-secondary)]">Message Body</label>
                    <span className="text-xs font-mono text-[var(--text-tertiary)]">
                      {templateForm.body.length} chars ({(Math.ceil(templateForm.body.length / 160) || 1)} segment)
                    </span>
                  </div>
                  <textarea
                    rows={4}
                    required
                    placeholder="Hello {{customer_name}}, your invoice {{invoice_number}} of {{invoice_amount}} is due on {{due_date}}."
                    value={templateForm.body}
                    onChange={(e) => setTemplateForm({ ...templateForm, body: e.target.value })}
                    className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)] font-sans"
                  />
                  {templateForm.body.length > 160 && (
                    <div className="text-xs text-amber-400 mt-1 flex items-center gap-1">
                      <AlertCircle className="w-3.5 h-3.5" /> Exceeds 160 chars and will split into multiple segments.
                    </div>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Quick Variables</label>
                  <div className="flex flex-wrap gap-1.5 text-xs font-mono">
                    {[
                      'customer_name',
                      'invoice_number',
                      'invoice_amount',
                      'due_date',
                      'lead_name',
                      'workspace_name',
                    ].map((v) => (
                      <button
                        type="button"
                        key={v}
                        onClick={() => setTemplateForm({ ...templateForm, body: templateForm.body + `{{${v}}}` })}
                        className="px-2 py-0.5 rounded bg-[var(--surface-2)] hover:bg-[var(--border-medium)] text-[var(--text-secondary)] border border-[var(--border-subtle)]"
                      >
                        +{`{{${v}}}`}
                      </button>
                    ))}
                  </div>
                </div>

                <div className="flex justify-end gap-2 pt-4">
                  <Button variant="outline" type="button" onClick={() => setShowTemplateModal(false)}>
                    Cancel
                  </Button>
                  <Button variant="neutral" type="submit">
                    Save Template
                  </Button>
                </div>
              </form>
            </Card>
          </div>
        )}

        {/* Modal: New Trigger */}
        {showTriggerModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <Card className="max-w-md w-full bg-[var(--surface-1)]">
              <form onSubmit={handleTriggerSubmit} className="p-6 space-y-4">
                <h3 className="text-base font-semibold text-[var(--text-primary)]">Connect Event Trigger</h3>
                
                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">Application Event</label>
                  <select
                    value={triggerForm.event_name}
                    onChange={(e) => setTriggerForm({ ...triggerForm, event_name: e.target.value })}
                    className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                  >
                    {Object.entries(supportedEvents).map(([key, label]) => (
                      <option key={key} value={key}>{label} ({key})</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1">SMS Template to Send</label>
                  <select
                    value={triggerForm.template_id}
                    onChange={(e) => setTriggerForm({ ...triggerForm, template_id: e.target.value })}
                    className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-lg px-3 py-2 text-[var(--text-primary)]"
                  >
                    {templates.map((tpl) => (
                      <option key={tpl.id} value={tpl.id}>{tpl.name}</option>
                    ))}
                  </select>
                </div>

                <div className="flex justify-end gap-2 pt-4">
                  <Button variant="outline" type="button" onClick={() => setShowTriggerModal(false)}>
                    Cancel
                  </Button>
                  <Button variant="neutral" type="submit">
                    Activate Trigger
                  </Button>
                </div>
              </form>
            </Card>
          </div>
        )}

        {/* Modal: Preview Template */}
        {previewTemplate && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <Card className="max-w-md w-full bg-[var(--surface-1)]">
              <div className="p-6 space-y-4">
                <h3 className="text-base font-semibold text-[var(--text-primary)]">Template Preview</h3>
                <div className="text-xs text-[var(--text-secondary)]">{previewTemplate.name}</div>
                
                <div className="p-4 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-sm text-[var(--text-primary)] font-sans">
                  {previewTemplate.body}
                </div>

                <div className="text-xs text-[var(--text-tertiary)] flex justify-between">
                  <span>Characters: {previewTemplate.char_count}</span>
                  <span>Segments: {previewTemplate.segments}</span>
                </div>

                <div className="flex justify-end pt-2">
                  <Button variant="neutral" size="sm" onClick={() => setPreviewTemplate(null)}>
                    Close
                  </Button>
                </div>
              </div>
            </Card>
          </div>
        )}
      </div>
    </AppShell>
  );
}
