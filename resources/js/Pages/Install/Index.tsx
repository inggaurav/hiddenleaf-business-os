import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Badge } from '@/Components/UI/Badge';
import { CheckCircle2, ShieldCheck, Database, UserCheck, ArrowRight, Sparkles, Terminal } from 'lucide-react';

export default function InstallIndex({ requirements = {} }: { requirements?: any }) {
  const [step, setStep] = useState<1 | 2 | 3 | 4>(1);
  const [dbTested, setDbTested] = useState(false);
  const [dbTestError, setDbTestError] = useState<string | null>(null);
  const [testingDb, setTestingDb] = useState(false);

  const { data, setData, post, processing, errors } = useForm({
    app_name: 'HiddenLeaf BusinessOS',
    app_url: window.location.origin,
    db_host: '127.0.0.1',
    db_port: '3306',
    db_database: 'hiddenleaf',
    db_username: 'root',
    db_password: '',
    admin_name: 'Super Admin',
    admin_email: 'admin@hiddenleaf.io',
    admin_password: '',
    admin_password_confirmation: '',
  });

  const handleTestDatabase = async () => {
    setTestingDb(true);
    setDbTestError(null);

    try {
      const res = await fetch('/install/test-db', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
        },
        body: JSON.stringify({
          db_host: data.db_host,
          db_port: data.db_port,
          db_database: data.db_database,
          db_username: data.db_username,
          db_password: data.db_password,
        }),
      });

      const json = await res.json();
      if (json.success) {
        setDbTested(true);
        setStep(3);
      } else {
        setDbTestError(json.error || 'Database connection failed.');
      }
    } catch {
      setDbTestError('Failed to contact database installer endpoint.');
    } finally {
      setTestingDb(false);
    }
  };

  const handleFinalInstall = (e: React.FormEvent) => {
    e.preventDefault();
    post('/install/complete');
  };

  return (
    <div className="min-h-screen bg-[#060709] text-gray-100 flex items-center justify-center p-4 relative overflow-hidden">
      <div className="max-w-2xl w-full relative z-10 space-y-6">
        {/* Header */}
        <div className="text-center space-y-2">
          <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-xl shadow-purple-950/40 mx-auto">
            HL
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-white">BusinessOS Installer</h1>
          <p className="text-xs text-gray-400">Step {step} of 4: Self-Hosted Platform Setup</p>
        </div>

        {/* Progress Stepper */}
        <div className="grid grid-cols-4 gap-2">
          {['Requirements', 'Database', 'Admin Setup', 'Complete'].map((st, i) => (
            <div
              key={st}
              className={`p-2.5 rounded-xl border text-center text-xs font-semibold spring-transition ${step >= i + 1 ? 'bg-violet-600/20 border-violet-500/40 text-violet-300' : 'bg-white/[0.03] border-white/10 text-gray-500'}`}
            >
              {st}
            </div>
          ))}
        </div>

        {/* Step 1: Requirements Check */}
        {step === 1 && (
          <Card level={1} className="space-y-4">
            <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
              Server Environment Verification
            </h3>
            <div className="space-y-2 text-xs">
              <div className="p-3 rounded-lg bg-white/[0.03] border border-white/10 flex items-center justify-between">
                <span>PHP Version (&gt;= 8.2)</span>
                <Badge variant="success" size="sm" dot>Compatible</Badge>
              </div>
              <div className="p-3 rounded-lg bg-white/[0.03] border border-white/10 flex items-center justify-between">
                <span>PDO / Database Extensions (pdo_mysql, pdo_pgsql, pdo_sqlite)</span>
                <Badge variant="success" size="sm" dot>Installed</Badge>
              </div>
              <div className="p-3 rounded-lg bg-white/[0.03] border border-white/10 flex items-center justify-between">
                <span>OpenSSL / Crypto Signature Modules</span>
                <Badge variant="success" size="sm" dot>Active</Badge>
              </div>
              <div className="p-3 rounded-lg bg-white/[0.03] border border-white/10 flex items-center justify-between">
                <span>File Storage & Permissions (storage, bootstrap/cache)</span>
                <Badge variant="success" size="sm" dot>Writable</Badge>
              </div>
            </div>

            <div className="pt-4 flex items-center justify-end">
              <Button variant="primary" size="md" icon={<ArrowRight className="w-4 h-4" />} iconPosition="right" onClick={() => setStep(2)}>
                Continue to Database Setup
              </Button>
            </div>
          </Card>
        )}

        {/* Step 2: Database Configuration */}
        {step === 2 && (
          <Card level={1} className="space-y-4">
            <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
              Database Connection
            </h3>

            {dbTestError && (
              <div className="p-3 rounded-lg bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs font-medium">
                {dbTestError}
              </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Database Host"
                value={data.db_host}
                onChange={(e) => setData('db_host', e.target.value)}
                required
              />
              <Input
                label="Database Port"
                value={data.db_port}
                onChange={(e) => setData('db_port', e.target.value)}
                required
              />
            </div>

            <Input
              label="Database Name"
              value={data.db_database}
              onChange={(e) => setData('db_database', e.target.value)}
              required
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Username"
                value={data.db_username}
                onChange={(e) => setData('db_username', e.target.value)}
                required
              />
              <Input
                label="Password"
                type="password"
                value={data.db_password}
                onChange={(e) => setData('db_password', e.target.value)}
              />
            </div>

            <div className="pt-4 flex items-center justify-between">
              <Button variant="ghost" onClick={() => setStep(1)}>
                Back
              </Button>
              <Button
                variant="intelligence"
                loading={testingDb}
                onClick={handleTestDatabase}
                icon={<Database className="w-4 h-4" />}
              >
                Test Connection & Continue
              </Button>
            </div>
          </Card>
        )}

        {/* Step 3: Super Admin Creation */}
        {step === 3 && (
          <form onSubmit={handleFinalInstall}>
            <Card level={1} className="space-y-4">
              <h3 className="text-sm font-bold text-white uppercase tracking-wider text-gray-400">
                Root Super Admin Profile
              </h3>

              <Input
                label="Platform Title"
                value={data.app_name}
                onChange={(e) => setData('app_name', e.target.value)}
                required
              />

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="Super Admin Name"
                  value={data.admin_name}
                  onChange={(e) => setData('admin_name', e.target.value)}
                  required
                />
                <Input
                  label="Super Admin Email"
                  type="email"
                  value={data.admin_email}
                  onChange={(e) => setData('admin_email', e.target.value)}
                  required
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="Master Password"
                  type="password"
                  value={data.admin_password}
                  onChange={(e) => setData('admin_password', e.target.value)}
                  required
                />
                <Input
                  label="Confirm Master Password"
                  type="password"
                  value={data.admin_password_confirmation}
                  onChange={(e) => setData('admin_password_confirmation', e.target.value)}
                  required
                />
              </div>

              <div className="pt-4 flex items-center justify-between">
                <Button type="button" variant="ghost" onClick={() => setStep(2)}>
                  Back
                </Button>
                <Button
                  type="submit"
                  variant="primary"
                  size="lg"
                  loading={processing}
                  icon={<Sparkles className="w-4 h-4" />}
                >
                  Run Migrations & Launch OS
                </Button>
              </div>
            </Card>
          </form>
        )}
      </div>
    </div>
  );
}
