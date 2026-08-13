import React, { useState } from 'react';
import { usePage, useForm, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { Switch } from '@/Components/UI/Checkbox';
import { Badge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Sliders, Mail, HardDrive, Shield, RefreshCw, Save, CheckCircle2, Globe } from 'lucide-react';

export default function SettingsIndex() {
  const { settings = {}, isSuperAdmin } = usePage<any>().props;

  const [testEmailAddress, setTestEmailAddress] = useState('');
  const [testMailStatus, setTestMailStatus] = useState<string | null>(null);
  const [testMailLoading, setTestMailLoading] = useState(false);

  // Settings Form
  const { data, setData, post, processing, errors } = useForm({
    app_name: settings.app_name || 'HiddenLeaf BusinessOS',
    company_name: settings.company_name || 'HiddenLeaf Enterprise',
    currency_symbol: settings.currency_symbol || '$',
    default_language: settings.default_language || 'en',
    mail_driver: settings.mail_driver || 'smtp',
    mail_host: settings.mail_host || 'smtp.mailgun.org',
    mail_port: settings.mail_port || '587',
    mail_username: settings.mail_username || '',
    mail_password: settings.mail_password || '',
    mail_encryption: settings.mail_encryption || 'tls',
    mail_from_address: settings.mail_from_address || 'noreply@hiddenleaf.io',
    mail_from_name: settings.mail_from_name || 'HiddenLeaf OS',
    storage_driver: settings.storage_driver || 'local',
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
        setTestMailStatus(`Error: ${json.error || 'Failed to send test email'}`);
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

  return (
    <AppShell title="System & Workspace Settings">
      <div className="max-w-4xl mx-auto space-y-6">
        <SectionHeader
          title="Settings & Workspace Preferences"
          description="Configure company branding, currency formats, outgoing SMTP credentials, and storage adapters."
          badge={<Badge variant="purple" size="sm">System Config</Badge>}
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

        <form onSubmit={handleSaveSettings} className="space-y-6">
          {/* General Branding Card */}
          <Card level={0} className="space-y-4">
            <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
              General Identity & Branding
            </h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Currency Symbol"
                placeholder="$"
                value={data.currency_symbol}
                onChange={(e) => setData('currency_symbol', e.target.value)}
                required
              />
              <Select
                label="Default System Language"
                value={data.default_language}
                onChange={(e) => setData('default_language', e.target.value)}
              >
                <option value="en">English (US)</option>
                <option value="es">Spanish (Español)</option>
                <option value="fr">French (Français)</option>
                <option value="de">German (Deutsch)</option>
              </Select>
            </div>
          </Card>

          {/* Outgoing Email / SMTP Card */}
          <Card level={0} className="space-y-4">
            <div className="flex items-center justify-between pb-2 border-b border-white/10">
              <div className="flex items-center gap-2">
                <Mail className="w-4 h-4 text-violet-400" />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
                  SMTP Mail Gateway
                </h3>
              </div>
              <Badge variant="neutral" size="sm">Transactional</Badge>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Input
                label="SMTP Host"
                value={data.mail_host}
                onChange={(e) => setData('mail_host', e.target.value)}
              />
              <Input
                label="SMTP Port"
                value={data.mail_port}
                onChange={(e) => setData('mail_port', e.target.value)}
              />
              <Select
                label="Encryption Protocol"
                value={data.mail_encryption}
                onChange={(e) => setData('mail_encryption', e.target.value)}
              >
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
                <option value="none">None</option>
              </Select>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="SMTP Username"
                value={data.mail_username}
                onChange={(e) => setData('mail_username', e.target.value)}
              />
              <Input
                label="SMTP Password"
                type="password"
                value={data.mail_password}
                onChange={(e) => setData('mail_password', e.target.value)}
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="From Address"
                value={data.mail_from_address}
                onChange={(e) => setData('mail_from_address', e.target.value)}
              />
              <Input
                label="From Name"
                value={data.mail_from_name}
                onChange={(e) => setData('mail_from_name', e.target.value)}
              />
            </div>
          </Card>

          {/* Test Mail Form */}
          <Card level={1} className="space-y-3">
            <h4 className="text-xs font-bold text-white uppercase tracking-wider">
              Verify SMTP Connection
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
                Send Test Email
              </Button>
            </div>
            {testMailStatus && (
              <p className="text-xs text-violet-300 font-medium">{testMailStatus}</p>
            )}
          </Card>

          {/* Submit Action */}
          <div className="flex items-center justify-end">
            <Button
              type="submit"
              variant="primary"
              size="lg"
              loading={processing}
              icon={<Save className="w-4 h-4" />}
            >
              Save Settings
            </Button>
          </div>
        </form>
      </div>
    </AppShell>
  );
}
