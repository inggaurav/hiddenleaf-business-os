import React, { useState, useEffect } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Card, CardHeader, CardBody } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { 
  Send, 
  ArrowLeft, 
  User, 
  Smartphone, 
  MessageSquare, 
  AlertCircle, 
  CheckCircle2,
  Sparkles
} from 'lucide-react';

interface CustomerOption {
  id: number;
  name: string;
  phone: string;
  email: string | null;
}

interface TemplateOption {
  id: number;
  name: string;
  body: string;
  variables: string[];
}

interface Props {
  customers: CustomerOption[];
  templates: TemplateOption[];
  activeGateway: {
    name: string;
    driver: string;
  } | null;
  allowedVariables: string[];
  canManage: boolean;
}

export default function SmsManual({
  customers = [],
  templates = [],
  activeGateway = null,
  allowedVariables = [],
  canManage = true,
}: Props) {
  const [selectedCustomerId, setSelectedCustomerId] = useState<number | ''>(customers[0]?.id || '');
  const [selectedTemplateId, setSelectedTemplateId] = useState<number | ''>('');
  const [messageBody, setMessageBody] = useState('');
  const [sending, setSending] = useState(false);

  const selectedCustomer = customers.find((c) => c.id === Number(selectedCustomerId));

  // If a template is picked, populate message body
  const handleTemplateChange = (tplId: string) => {
    setSelectedTemplateId(tplId ? Number(tplId) : '');
    if (!tplId) return;
    const tpl = templates.find((t) => t.id === Number(tplId));
    if (tpl) {
      setMessageBody(tpl.body);
    }
  };

  // Character analysis
  const charCount = messageBody.length;
  const isSingleSegment = charCount <= 160;
  const segments = charCount === 0 ? 1 : (isSingleSegment ? 1 : Math.ceil(charCount / 153));
  const remainingInSegment = isSingleSegment ? 160 - charCount : (segments * 153) - charCount;

  // Render preview substituting customer variables if selected
  const renderedPreview = messageBody
    .replace(/\{\{\s*customer_name\s*\}\}/g, selectedCustomer?.name || '[Customer Name]')
    .replace(/\{\{\s*customer_phone\s*\}\}/g, selectedCustomer?.phone || '[Phone Number]');

  const handleSend = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedCustomerId || !messageBody.trim()) return;

    setSending(true);
    router.post('/sms/send', {
      customer_id: selectedCustomerId,
      body: messageBody,
    }, {
      onFinish: () => setSending(false),
      onSuccess: () => {
        setMessageBody('');
        setSelectedTemplateId('');
      },
    });
  };

  return (
    <AppShell title="Compose SMS">
      <Head title="Communications — Compose SMS" />

      <div className="space-y-6">
        <SectionHeader
          title="Compose SMS"
          description="Directly dispatch targeted or template-based SMS notifications to customers."
          actions={
            <Link href="/sms">
              <Button variant="outline" size="sm">
                <ArrowLeft className="w-3.5 h-3.5 mr-1" /> Back to SMS Gateways
              </Button>
            </Link>
          }
        />

        {/* Gateway indicator banner */}
        <div className="p-3.5 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] flex items-center justify-between text-xs">
          <div className="flex items-center gap-2 text-[var(--text-secondary)]">
            <Smartphone className="w-4 h-4 text-emerald-400" />
            <span>Active Default Gateway:</span>
            {activeGateway ? (
              <span className="font-medium text-[var(--text-primary)]">
                {activeGateway.name} ({activeGateway.driver.toUpperCase()})
              </span>
            ) : (
              <span className="text-amber-400 font-medium">No default gateway active (using local fake driver)</span>
            )}
          </div>
          <Link href="/sms" className="text-xs text-[var(--text-tertiary)] hover:text-[var(--text-primary)] transition-colors">
            Configure Gateways →
          </Link>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Compose Form */}
          <div className="lg:col-span-2">
            <Card>
              <CardHeader title="Message Composer" subtitle="Select recipient and customize message content" />
              <CardBody>
                <form onSubmit={handleSend} className="space-y-5">
                  {/* Recipient Selector */}
                  <div>
                    <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1.5 flex items-center gap-1.5">
                      <User className="w-3.5 h-3.5 text-[var(--text-tertiary)]" /> Recipient Customer
                    </label>
                    <select
                      required
                      value={selectedCustomerId}
                      onChange={(e) => setSelectedCustomerId(Number(e.target.value))}
                      className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl px-3.5 py-2.5 text-[var(--text-primary)]"
                    >
                      <option value="">Choose customer...</option>
                      {customers.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name} ({c.phone})
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Template Picker */}
                  <div>
                    <label className="block text-xs font-medium text-[var(--text-secondary)] mb-1.5 flex items-center gap-1.5">
                      <MessageSquare className="w-3.5 h-3.5 text-[var(--text-tertiary)]" /> Load From Template (Optional)
                    </label>
                    <select
                      value={selectedTemplateId}
                      onChange={(e) => handleTemplateChange(e.target.value)}
                      className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl px-3.5 py-2.5 text-[var(--text-primary)]"
                    >
                      <option value="">Custom Message (No Template)</option>
                      {templates.map((tpl) => (
                        <option key={tpl.id} value={tpl.id}>
                          {tpl.name} ({tpl.body.substring(0, 35)}...)
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Message Body */}
                  <div>
                    <div className="flex items-center justify-between mb-1.5">
                      <label className="text-xs font-medium text-[var(--text-secondary)]">Message Text</label>
                      <div className="text-xs font-mono flex items-center gap-2">
                        <span className={charCount > 160 ? 'text-amber-400 font-semibold' : 'text-[var(--text-primary)]'}>
                          {charCount} chars
                        </span>
                        <span className="text-[var(--text-tertiary)]">
                          ({segments} segment{segments !== 1 ? 's' : ''})
                        </span>
                      </div>
                    </div>
                    <textarea
                      rows={5}
                      required
                      placeholder="Type your SMS message here. Use variables like {{customer_name}} or {{invoice_number}}."
                      value={messageBody}
                      onChange={(e) => setMessageBody(e.target.value)}
                      className="w-full text-sm bg-[var(--surface-2)] border border-[var(--border-subtle)] rounded-xl p-3.5 text-[var(--text-primary)] font-sans"
                    />

                    {charCount > 160 && (
                      <div className="mt-2 p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-xs text-amber-400 flex items-center gap-2">
                        <AlertCircle className="w-4 h-4 shrink-0" />
                        <span>This message exceeds 160 characters and will be billed as {segments} SMS segments.</span>
                      </div>
                    )}
                  </div>

                  {/* Allowed Variables Pills */}
                  <div>
                    <label className="block text-xs font-medium text-[var(--text-secondary)] mb-2 flex items-center gap-1.5">
                      <Sparkles className="w-3.5 h-3.5 text-[var(--text-tertiary)]" /> Insert Variable Tags
                    </label>
                    <div className="flex flex-wrap gap-1.5">
                      {allowedVariables.map((variable) => (
                        <button
                          type="button"
                          key={variable}
                          onClick={() => setMessageBody((prev) => prev + `{{${variable}}}`)}
                          className="px-2.5 py-1 text-xs font-mono rounded-lg bg-[var(--surface-2)] hover:bg-[var(--border-medium)] text-[var(--text-secondary)] border border-[var(--border-subtle)] transition-colors"
                        >
                          +{`{{${variable}}}`}
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="pt-2 flex items-center justify-end gap-3 border-t border-[var(--border-subtle)]">
                    <Button
                      variant="neutral"
                      type="submit"
                      disabled={sending || !selectedCustomerId || !messageBody.trim() || !canManage}
                    >
                      <Send className="w-3.5 h-3.5 mr-1.5" />
                      {sending ? 'Sending...' : 'Dispatch SMS'}
                    </Button>
                  </div>
                </form>
              </CardBody>
            </Card>
          </div>

          {/* Live Mobile Device Preview Panel */}
          <div>
            <Card>
              <CardHeader title="Live Preview" subtitle="Simulated SMS recipient view" />
              <CardBody className="space-y-4">
                <div className="p-4 rounded-2xl bg-[var(--surface-2)] border border-[var(--border-subtle)] space-y-3">
                  <div className="text-xs text-[var(--text-tertiary)] flex items-center justify-between border-b border-[var(--border-subtle)] pb-2">
                    <span>TO: {selectedCustomer?.phone || 'Select recipient'}</span>
                    <span className="font-mono">NOW</span>
                  </div>

                  <div className="p-3.5 rounded-xl bg-[var(--surface-3)] text-sm text-[var(--text-primary)] whitespace-pre-wrap font-sans leading-relaxed">
                    {renderedPreview || 'Start typing a message to see how it renders for the recipient...'}
                  </div>

                  <div className="text-xs text-[var(--text-tertiary)] flex items-center justify-between pt-1">
                    <span>Estimated segments: {segments}</span>
                    <span className="font-mono text-emerald-400">Standard GSM</span>
                  </div>
                </div>

                <div className="text-xs text-[var(--text-secondary)] space-y-1.5 pt-2">
                  <div className="font-medium text-[var(--text-primary)]">Delivery Notice</div>
                  <p>
                    Messages sent from this console are logged in the audit ledger and billed according to the configured gateway rates.
                  </p>
                </div>
              </CardBody>
            </Card>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
