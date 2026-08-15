import React, { useState } from 'react';
import { usePage, useForm, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import {
  Building2,
  Globe,
  Hash,
  Mail,
  CreditCard,
  BellRing,
  Cpu,
  FileCode2,
  RefreshCw,
  Save,
  CheckCircle2,
  Sliders,
  Sparkles,
  ShieldCheck,
  Send,
} from 'lucide-react';

export default function SettingsIndex() {
  const { resolvedSettings = {}, settings = {}, isSuperAdmin } = usePage<any>().props;
  const cfg = { ...settings, ...resolvedSettings };

  const [activeTab, setActiveTab] = useState<'brand' | 'system' | 'prefix' | 'email' | 'payment' | 'notifications' | 'integrations' | 'print'>('brand');

  const [testEmailAddress, setTestEmailAddress] = useState('');
  const [testMailStatus, setTestMailStatus] = useState<string | null>(null);
  const [testMailLoading, setTestMailLoading] = useState(false);

  // Settings Form matching WorkDo 1:1 settings dictionary
  const { data, setData, post, processing, errors } = useForm({
    // Brand & General
    app_name: cfg.app_name || 'HiddenLeaf BusinessOS',
    company_name: cfg.company_name || 'HiddenLeaf Agency',
    company_address: cfg.company_address || '100 Innovation Parkway',
    company_city: cfg.company_city || 'San Francisco',
    company_state: cfg.company_state || 'CA',
    company_country: cfg.company_country || 'United States',
    company_zipcode: cfg.company_zipcode || '94105',
    company_telephone: cfg.company_telephone || '+1 (555) 019-2834',
    company_email: cfg.company_email || 'contact@hiddenleaf.io',
    footer_text: cfg.footer_text || 'Copyright © 2026 HiddenLeaf Agency. All rights reserved.',
    
    // System & Localization
    site_currency: cfg.site_currency || 'USD',
    currency_symbol: cfg.currency_symbol || '$',
    currency_position: cfg.currency_position || 'before',
    decimal_digits: cfg.decimal_digits || '2',
    date_format: cfg.date_format || 'YYYY-MM-DD',
    time_format: cfg.time_format || '12',
    timezone: cfg.timezone || 'UTC',
    default_language: cfg.default_language || 'en',

    // Prefix & Sequences
    invoice_prefix: cfg.invoice_prefix || 'INV-',
    proposal_prefix: cfg.proposal_prefix || 'PROP-',
    bill_prefix: cfg.bill_prefix || 'BILL-',
    pos_prefix: cfg.pos_prefix || 'POS-',
    journal_prefix: cfg.journal_prefix || 'JRN-',
    employee_prefix: cfg.employee_prefix || 'EMP-',
    lead_prefix: cfg.lead_prefix || 'LEAD-',
    deal_prefix: cfg.deal_prefix || 'DEAL-',
    project_prefix: cfg.project_prefix || 'PROJ-',
    customer_prefix: cfg.customer_prefix || 'CUST-',
    vendor_prefix: cfg.vendor_prefix || 'VEND-',

    // SMTP Mail
    mail_driver: cfg.mail_driver || 'smtp',
    mail_host: cfg.mail_host || 'smtp.mailgun.org',
    mail_port: cfg.mail_port || '587',
    mail_username: cfg.mail_username || '',
    mail_password: cfg.mail_password || '',
    mail_encryption: cfg.mail_encryption || 'tls',
    mail_from_address: cfg.mail_from_address || 'noreply@hiddenleaf.io',
    mail_from_name: cfg.mail_from_name || 'HiddenLeaf BusinessOS',

    // Payments
    stripe_enabled: cfg.stripe_enabled ?? 'on',
    stripe_key: cfg.stripe_key || '',
    stripe_secret: cfg.stripe_secret || '',
    stripe_webhook_secret: cfg.stripe_webhook_secret || '',

    paypal_enabled: cfg.paypal_enabled ?? 'on',
    paypal_client_id: cfg.paypal_client_id || '',
    paypal_secret: cfg.paypal_secret || '',
    paypal_mode: cfg.paypal_mode || 'sandbox',

    bank_transfer_enabled: cfg.bank_transfer_enabled ?? 'on',
    bank_name: cfg.bank_name || 'Silicon Valley Bank',
    bank_account_number: cfg.bank_account_number || '****-****-****-8821',
    bank_swift_code: cfg.bank_swift_code || 'SVBUS33',
    bank_instructions: cfg.bank_instructions || 'Please include your Invoice/Order reference in the payment wire memo.',
    cash_payment_enabled: cfg.cash_payment_enabled ?? 'on',

    // Notifications
    slack_webhook_url: cfg.slack_webhook_url || '',
    slack_enabled: cfg.slack_enabled ?? 'off',
    telegram_bot_token: cfg.telegram_bot_token || '',
    telegram_chat_id: cfg.telegram_chat_id || '',
    telegram_enabled: cfg.telegram_enabled ?? 'off',
    twilio_sid: cfg.twilio_sid || '',
    twilio_token: cfg.twilio_token || '',
    twilio_from: cfg.twilio_from || '',
    twilio_enabled: cfg.twilio_enabled ?? 'off',

    // Integrations & Storage
    zoom_client_id: cfg.zoom_client_id || '',
    zoom_client_secret: cfg.zoom_client_secret || '',
    zoom_account_id: cfg.zoom_account_id || '',
    openai_api_key: cfg.openai_api_key || '',
    openai_model: cfg.openai_model || 'gpt-4o',
    storage_driver: cfg.storage_driver || 'local',
    aws_access_key_id: cfg.aws_access_key_id || '',
    aws_secret_access_key: cfg.aws_secret_access_key || '',
    aws_default_region: cfg.aws_default_region || 'us-east-1',
    aws_bucket: cfg.aws_bucket || '',

    // Print & Templates
    print_accent_color: cfg.print_accent_color || '#6366f1',
    show_qr_code: cfg.show_qr_code ?? 'on',
    proposal_terms: cfg.proposal_terms || 'This estimate is valid for 30 days from date of issue.',
    invoice_footer_notes: cfg.invoice_footer_notes || 'Thank you for your business!',
  });

  const handleSaveSettings = (e: React.FormEvent) => {
    e.preventDefault();
    post('/settings');
  };

  const handleSendTestMail = async () => {
    if (!testEmailAddress) return;
    setTestMailLoading(true);
    setTestMailStatus(null);

    try {
      const res = await fetch('/settings/test-mail', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
        },
        body: JSON.stringify({ email: testEmailAddress }),
      });
      const json = await res.json();
      if (json.success) {
        setTestMailStatus('Test email dispatched successfully.');
      } else {
        setTestMailStatus(`Error: ${json.message || 'Failed to send test email'}`);
      }
    } catch {
      setTestMailStatus('Error contacting server.');
    } finally {
      setTestMailLoading(false);
    }
  };

  const handleClearCache = () => {
    if (confirm('Clear system view, configuration, and route caches?')) {
      router.post('/settings/clear-cache');
    }
  };

  const tabs = [
    { id: 'brand', label: 'Company & Brand', icon: Building2 },
    { id: 'system', label: 'System & Currency', icon: Globe },
    { id: 'prefix', label: 'Prefixes & Sequences', icon: Hash },
    { id: 'email', label: 'SMTP Gateway', icon: Mail },
    { id: 'payment', label: 'Payment Gateways', icon: CreditCard },
    { id: 'notifications', label: 'Notification Channels', icon: BellRing },
    { id: 'integrations', label: 'Integrations & Cloud', icon: Cpu },
    { id: 'print', label: 'Print & PDF Templates', icon: FileCode2 },
  ] as const;

  return (
    <AppShell title="System & Workspace Settings">
      <div className="max-w-6xl mx-auto space-y-6">
        <SectionHeader
          title="Master Settings & Enterprise Preferences"
          description="Manage 100% of WorkDo-compatible workspace parameters, payment providers, sequences, channels, and brand assets."
          badge={<Badge variant="purple" size="sm">Canonical Configuration</Badge>}
          actions={
            <Button
              variant="outline"
              size="sm"
              icon={<RefreshCw className="w-3.5 h-3.5" />}
              onClick={handleClearCache}
            >
              Clear Cache
            </Button>
          }
        />

        {/* Tab Navigation */}
        <div className="flex items-center gap-1.5 overflow-x-auto pb-2 border-b border-white/10 no-scrollbar">
          {tabs.map((tab) => {
            const Icon = tab.icon;
            const active = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                type="button"
                onClick={() => setActiveTab(tab.id)}
                className={`
                  flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition-all
                  ${
                    active
                      ? 'bg-purple-600/20 text-purple-300 border border-purple-500/30 shadow-sm'
                      : 'text-gray-400 hover:text-gray-200 hover:bg-white/5 border border-transparent'
                  }
                `}
              >
                <Icon className={`w-4 h-4 ${active ? 'text-purple-400' : 'text-gray-400'}`} />
                {tab.label}
              </button>
            );
          })}
        </div>

        <form onSubmit={handleSaveSettings} className="space-y-6">
          {/* TAB 1: BRAND & COMPANY */}
          {activeTab === 'brand' && (
            <Card level={0} className="space-y-5">
              <div className="flex items-center justify-between pb-3 border-b border-white/10">
                <div className="flex items-center gap-2">
                  <Building2 className="w-4 h-4 text-purple-400" />
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">Company Identity & Contact Info</h3>
                </div>
                <Badge variant="purple" size="sm">Core Brand</Badge>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Input
                  label="Application Name"
                  value={data.app_name}
                  onChange={(e) => setData('app_name', e.target.value)}
                  error={errors.app_name}
                  required
                />
                <Input
                  label="Company Name"
                  value={data.company_name}
                  onChange={(e) => setData('company_name', e.target.value)}
                  error={errors.company_name}
                  required
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Input
                  label="Company Email"
                  type="email"
                  value={data.company_email}
                  onChange={(e) => setData('company_email', e.target.value)}
                />
                <Input
                  label="Company Telephone"
                  value={data.company_telephone}
                  onChange={(e) => setData('company_telephone', e.target.value)}
                />
              </div>

              <div className="space-y-4">
                <Input
                  label="Registered Address"
                  value={data.company_address}
                  onChange={(e) => setData('company_address', e.target.value)}
                />
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <Input label="City" value={data.company_city} onChange={(e) => setData('company_city', e.target.value)} />
                  <Input label="State / Province" value={data.company_state} onChange={(e) => setData('company_state', e.target.value)} />
                  <Input label="Postal / ZIP Code" value={data.company_zipcode} onChange={(e) => setData('company_zipcode', e.target.value)} />
                  <Input label="Country" value={data.company_country} onChange={(e) => setData('company_country', e.target.value)} />
                </div>
              </div>

              <Input
                label="Footer Copyright Text"
                value={data.footer_text}
                onChange={(e) => setData('footer_text', e.target.value)}
              />
            </Card>
          )}

          {/* TAB 2: SYSTEM & CURRENCY */}
          {activeTab === 'system' && (
            <Card level={0} className="space-y-5">
              <div className="flex items-center justify-between pb-3 border-b border-white/10">
                <div className="flex items-center gap-2">
                  <Globe className="w-4 h-4 text-sky-400" />
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">System, Currency & Regional Formats</h3>
                </div>
                <Badge variant="neutral" size="sm">Localization</Badge>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Input
                  label="Currency ISO Code"
                  placeholder="USD"
                  value={data.site_currency}
                  onChange={(e) => setData('site_currency', e.target.value)}
                  required
                />
                <Input
                  label="Currency Symbol"
                  placeholder="$"
                  value={data.currency_symbol}
                  onChange={(e) => setData('currency_symbol', e.target.value)}
                  required
                />
                <Select
                  label="Currency Symbol Position"
                  value={data.currency_position}
                  onChange={(e) => setData('currency_position', e.target.value)}
                >
                  <option value="before">Before Amount ($100.00)</option>
                  <option value="after">After Amount (100.00 $)</option>
                </Select>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Select
                  label="Date Format"
                  value={data.date_format}
                  onChange={(e) => setData('date_format', e.target.value)}
                >
                  <option value="YYYY-MM-DD">YYYY-MM-DD (2026-08-15)</option>
                  <option value="DD-MM-YYYY">DD-MM-YYYY (15-08-2026)</option>
                  <option value="MM/DD/YYYY">MM/DD/YYYY (08/15/2026)</option>
                  <option value="DD/MM/YYYY">DD/MM/YYYY (15/08/2026)</option>
                </Select>

                <Select
                  label="Time Format"
                  value={data.time_format}
                  onChange={(e) => setData('time_format', e.target.value)}
                >
                  <option value="12">12-Hour (02:30 PM)</option>
                  <option value="24">24-Hour (14:30)</option>
                </Select>

                <Select
                  label="Decimal Precision"
                  value={data.decimal_digits}
                  onChange={(e) => setData('decimal_digits', e.target.value)}
                >
                  <option value="2">2 Decimal Places (0.00)</option>
                  <option value="3">3 Decimal Places (0.000)</option>
                  <option value="4">4 Decimal Places (0.0000)</option>
                </Select>

                <Select
                  label="Default Language"
                  value={data.default_language}
                  onChange={(e) => setData('default_language', e.target.value)}
                >
                  <option value="en">English (US)</option>
                  <option value="es">Spanish (Español)</option>
                  <option value="fr">French (Français)</option>
                  <option value="de">German (Deutsch)</option>
                  <option value="ar">Arabic (العربية)</option>
                </Select>
              </div>
            </Card>
          )}

          {/* TAB 3: PREFIXES & SEQUENCES */}
          {activeTab === 'prefix' && (
            <Card level={0} className="space-y-5">
              <div className="flex items-center justify-between pb-3 border-b border-white/10">
                <div className="flex items-center gap-2">
                  <Hash className="w-4 h-4 text-emerald-400" />
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">Sequential Numbering & Document Prefixes</h3>
                </div>
                <Badge variant="emerald" size="sm">Pessimistic Sequence Guard</Badge>
              </div>

              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <Input label="Sales Invoice Prefix" value={data.invoice_prefix} onChange={(e) => setData('invoice_prefix', e.target.value)} />
                <Input label="Sales Proposal Prefix" value={data.proposal_prefix} onChange={(e) => setData('proposal_prefix', e.target.value)} />
                <Input label="Purchase Bill Prefix" value={data.bill_prefix} onChange={(e) => setData('bill_prefix', e.target.value)} />
                <Input label="POS Receipt Prefix" value={data.pos_prefix} onChange={(e) => setData('pos_prefix', e.target.value)} />
              </div>

              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <Input label="Journal Entry Prefix" value={data.journal_prefix} onChange={(e) => setData('journal_prefix', e.target.value)} />
                <Input label="Employee ID Prefix" value={data.employee_prefix} onChange={(e) => setData('employee_prefix', e.target.value)} />
                <Input label="CRM Lead Prefix" value={data.lead_prefix} onChange={(e) => setData('lead_prefix', e.target.value)} />
                <Input label="CRM Deal Prefix" value={data.deal_prefix} onChange={(e) => setData('deal_prefix', e.target.value)} />
              </div>

              <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                <Input label="Project Taskly Prefix" value={data.project_prefix} onChange={(e) => setData('project_prefix', e.target.value)} />
                <Input label="Customer ID Prefix" value={data.customer_prefix} onChange={(e) => setData('customer_prefix', e.target.value)} />
                <Input label="Vendor ID Prefix" value={data.vendor_prefix} onChange={(e) => setData('vendor_prefix', e.target.value)} />
              </div>
            </Card>
          )}

          {/* TAB 4: SMTP GATEWAY */}
          {activeTab === 'email' && (
            <Card level={0} className="space-y-5">
              <div className="flex items-center justify-between pb-3 border-b border-white/10">
                <div className="flex items-center gap-2">
                  <Mail className="w-4 h-4 text-violet-400" />
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">Outgoing SMTP Mail Server</h3>
                </div>
                <Badge variant="purple" size="sm">Transactional</Badge>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Input label="SMTP Host" value={data.mail_host} onChange={(e) => setData('mail_host', e.target.value)} />
                <Input label="SMTP Port" value={data.mail_port} onChange={(e) => setData('mail_port', e.target.value)} />
                <Select label="Encryption" value={data.mail_encryption} onChange={(e) => setData('mail_encryption', e.target.value)}>
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                  <option value="none">None</option>
                </Select>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Input label="SMTP Username" value={data.mail_username} onChange={(e) => setData('mail_username', e.target.value)} />
                <Input label="SMTP Password" type="password" value={data.mail_password} onChange={(e) => setData('mail_password', e.target.value)} />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Input label="From Address" value={data.mail_from_address} onChange={(e) => setData('mail_from_address', e.target.value)} />
                <Input label="From Name" value={data.mail_from_name} onChange={(e) => setData('mail_from_name', e.target.value)} />
              </div>

              <Card level={1} className="space-y-3 mt-4">
                <h4 className="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                  <Send className="w-3.5 h-3.5 text-purple-400" /> Send Live Test Email
                </h4>
                <div className="flex gap-2">
                  <Input
                    placeholder="Enter recipient email address..."
                    value={testEmailAddress}
                    onChange={(e) => setTestEmailAddress(e.target.value)}
                  />
                  <Button
                    type="button"
                    variant="secondary"
                    size="md"
                    onClick={handleSendTestMail}
                    loading={testMailLoading}
                  >
                    Dispatch Test
                  </Button>
                </div>
                {testMailStatus && (
                  <p className="text-xs text-purple-300 font-medium">{testMailStatus}</p>
                )}
              </Card>
            </Card>
          )}

          {/* TAB 5: PAYMENT GATEWAYS */}
          {activeTab === 'payment' && (
            <div className="space-y-6">
              {/* Stripe */}
              <Card level={0} className="space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-white/10">
                  <div className="flex items-center gap-2">
                    <CreditCard className="w-4 h-4 text-indigo-400" />
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider">Stripe Payment Gateway</h3>
                  </div>
                  <Select
                    value={data.stripe_enabled}
                    onChange={(e) => setData('stripe_enabled', e.target.value)}
                    className="w-32"
                  >
                    <option value="on">Enabled</option>
                    <option value="off">Disabled</option>
                  </Select>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <Input label="Stripe Publishable Key" value={data.stripe_key} onChange={(e) => setData('stripe_key', e.target.value)} />
                  <Input label="Stripe Secret Key" type="password" value={data.stripe_secret} onChange={(e) => setData('stripe_secret', e.target.value)} />
                </div>
                <Input label="Stripe Webhook Signing Secret" type="password" value={data.stripe_webhook_secret} onChange={(e) => setData('stripe_webhook_secret', e.target.value)} />
              </Card>

              {/* PayPal */}
              <Card level={0} className="space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-white/10">
                  <div className="flex items-center gap-2">
                    <CreditCard className="w-4 h-4 text-sky-400" />
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider">PayPal Express Checkout</h3>
                  </div>
                  <Select
                    value={data.paypal_enabled}
                    onChange={(e) => setData('paypal_enabled', e.target.value)}
                    className="w-32"
                  >
                    <option value="on">Enabled</option>
                    <option value="off">Disabled</option>
                  </Select>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <Input label="PayPal Client ID" value={data.paypal_client_id} onChange={(e) => setData('paypal_client_id', e.target.value)} />
                  <Input label="PayPal Secret Key" type="password" value={data.paypal_secret} onChange={(e) => setData('paypal_secret', e.target.value)} />
                  <Select label="Environment Mode" value={data.paypal_mode} onChange={(e) => setData('paypal_mode', e.target.value)}>
                    <option value="sandbox">Sandbox (Testing)</option>
                    <option value="live">Live (Production)</option>
                  </Select>
                </div>
              </Card>

              {/* Bank Transfer / Manual */}
              <Card level={0} className="space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-white/10">
                  <div className="flex items-center gap-2">
                    <Building2 className="w-4 h-4 text-emerald-400" />
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider">Direct Bank Transfer (Wire / Offline)</h3>
                  </div>
                  <Select
                    value={data.bank_transfer_enabled}
                    onChange={(e) => setData('bank_transfer_enabled', e.target.value)}
                    className="w-32"
                  >
                    <option value="on">Enabled</option>
                    <option value="off">Disabled</option>
                  </Select>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <Input label="Bank Name" value={data.bank_name} onChange={(e) => setData('bank_name', e.target.value)} />
                  <Input label="Account Number / IBAN" value={data.bank_account_number} onChange={(e) => setData('bank_account_number', e.target.value)} />
                  <Input label="SWIFT / BIC Code" value={data.bank_swift_code} onChange={(e) => setData('bank_swift_code', e.target.value)} />
                </div>
                <Input label="Payment Instructions for Customers" value={data.bank_instructions} onChange={(e) => setData('bank_instructions', e.target.value)} />
              </Card>
            </div>
          )}

          {/* TAB 6: NOTIFICATIONS */}
          {activeTab === 'notifications' && (
            <div className="space-y-6">
              {/* Slack */}
              <Card level={0} className="space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-white/10">
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">Slack Incoming Webhooks</h3>
                  <Select value={data.slack_enabled} onChange={(e) => setData('slack_enabled', e.target.value)} className="w-32">
                    <option value="on">Enabled</option>
                    <option value="off">Disabled</option>
                  </Select>
                </div>
                <Input label="Slack Webhook URL" placeholder="https://hooks.slack.com/services/..." value={data.slack_webhook_url} onChange={(e) => setData('slack_webhook_url', e.target.value)} />
              </Card>

              {/* Telegram */}
              <Card level={0} className="space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-white/10">
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">Telegram Notifications Bot</h3>
                  <Select value={data.telegram_enabled} onChange={(e) => setData('telegram_enabled', e.target.value)} className="w-32">
                    <option value="on">Enabled</option>
                    <option value="off">Disabled</option>
                  </Select>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <Input label="Telegram Bot Token" value={data.telegram_bot_token} onChange={(e) => setData('telegram_bot_token', e.target.value)} />
                  <Input label="Telegram Chat ID" value={data.telegram_chat_id} onChange={(e) => setData('telegram_chat_id', e.target.value)} />
                </div>
              </Card>

              {/* Twilio */}
              <Card level={0} className="space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-white/10">
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">Twilio SMS Gateway</h3>
                  <Select value={data.twilio_enabled} onChange={(e) => setData('twilio_enabled', e.target.value)} className="w-32">
                    <option value="on">Enabled</option>
                    <option value="off">Disabled</option>
                  </Select>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <Input label="Twilio Account SID" value={data.twilio_sid} onChange={(e) => setData('twilio_sid', e.target.value)} />
                  <Input label="Twilio Auth Token" type="password" value={data.twilio_token} onChange={(e) => setData('twilio_token', e.target.value)} />
                  <Input label="Twilio Sender Phone Number" value={data.twilio_from} onChange={(e) => setData('twilio_from', e.target.value)} />
                </div>
              </Card>
            </div>
          )}

          {/* TAB 7: INTEGRATIONS & CLOUD */}
          {activeTab === 'integrations' && (
            <div className="space-y-6">
              {/* Zoom */}
              <Card level={0} className="space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-white/10">
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">Zoom Video Meetings</h3>
                  <Badge variant="neutral" size="sm">Video Sync</Badge>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <Input label="Zoom Client ID" value={data.zoom_client_id} onChange={(e) => setData('zoom_client_id', e.target.value)} />
                  <Input label="Zoom Client Secret" type="password" value={data.zoom_client_secret} onChange={(e) => setData('zoom_client_secret', e.target.value)} />
                  <Input label="Zoom Account ID" value={data.zoom_account_id} onChange={(e) => setData('zoom_account_id', e.target.value)} />
                </div>
              </Card>

              {/* OpenAI / AI Assistant */}
              <Card level={0} className="space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-white/10">
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">OpenAI / Mr. Fox Assistant</h3>
                  <Badge variant="purple" size="sm">AI Agent</Badge>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <Input label="OpenAI API Key" type="password" value={data.openai_api_key} onChange={(e) => setData('openai_api_key', e.target.value)} />
                  <Select label="Model" value={data.openai_model} onChange={(e) => setData('openai_model', e.target.value)}>
                    <option value="gpt-4o">GPT-4o (High Speed & Intelligence)</option>
                    <option value="gpt-4-turbo">GPT-4 Turbo</option>
                    <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                  </Select>
                </div>
              </Card>

              {/* AWS S3 Storage */}
              <Card level={0} className="space-y-4">
                <div className="flex items-center justify-between pb-3 border-b border-white/10">
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">Cloud Storage Adapter</h3>
                  <Select value={data.storage_driver} onChange={(e) => setData('storage_driver', e.target.value)} className="w-36">
                    <option value="local">Local Storage</option>
                    <option value="s3">AWS S3 / MinIO</option>
                  </Select>
                </div>
                {data.storage_driver === 's3' && (
                  <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Input label="AWS Access Key ID" value={data.aws_access_key_id} onChange={(e) => setData('aws_access_key_id', e.target.value)} />
                    <Input label="AWS Secret Access Key" type="password" value={data.aws_secret_access_key} onChange={(e) => setData('aws_secret_access_key', e.target.value)} />
                    <Input label="AWS Default Region" value={data.aws_default_region} onChange={(e) => setData('aws_default_region', e.target.value)} />
                    <Input label="S3 Bucket Name" value={data.aws_bucket} onChange={(e) => setData('aws_bucket', e.target.value)} />
                  </div>
                )}
              </Card>
            </div>
          )}

          {/* TAB 8: PRINT & TEMPLATES */}
          {activeTab === 'print' && (
            <Card level={0} className="space-y-5">
              <div className="flex items-center justify-between pb-3 border-b border-white/10">
                <div className="flex items-center gap-2">
                  <FileCode2 className="w-4 h-4 text-pink-400" />
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">Document & PDF Print Styling</h3>
                </div>
                <Badge variant="purple" size="sm">Template Engine</Badge>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Input label="Theme Accent Color (Hex)" value={data.print_accent_color} onChange={(e) => setData('print_accent_color', e.target.value)} />
                <Select label="Display QR Code on Invoices" value={data.show_qr_code} onChange={(e) => setData('show_qr_code', e.target.value)}>
                  <option value="on">Yes, display QR verification code</option>
                  <option value="off">No, hide QR code</option>
                </Select>
              </div>

              <Input label="Proposal Terms & Conditions Text" value={data.proposal_terms} onChange={(e) => setData('proposal_terms', e.target.value)} />
              <Input label="Invoice Footer Notes" value={data.invoice_footer_notes} onChange={(e) => setData('invoice_footer_notes', e.target.value)} />
            </Card>
          )}

          {/* Submit Action */}
          <div className="flex items-center justify-between pt-4 border-t border-white/10">
            <div className="text-xs text-gray-400 flex items-center gap-1.5">
              <ShieldCheck className="w-4 h-4 text-emerald-400" />
              Settings are strictly encrypted and scoped to the active workspace.
            </div>

            <Button
              type="submit"
              variant="primary"
              size="lg"
              loading={processing}
              icon={<Save className="w-4 h-4" />}
            >
              Save All Settings
            </Button>
          </div>
        </form>
      </div>
    </AppShell>
  );
}
