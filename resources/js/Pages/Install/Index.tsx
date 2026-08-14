import React, { useMemo, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import {
  AlertCircle,
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  CircleDashed,
  Database,
  Eye,
  EyeOff,
  Globe2,
  KeyRound,
  Leaf,
  Loader2,
  LockKeyhole,
  PackageCheck,
  RefreshCw,
  ServerCog,
  ShieldCheck,
  Sparkles,
  UserRound,
  WalletCards,
  XCircle,
} from 'lucide-react';
import InstallerLayout, { InstallerStep } from '@/Layouts/InstallerLayout';
import { Button } from '@/Components/UI/Button';
import { Checkbox } from '@/Components/UI/Checkbox';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';

type RequirementMap = Record<string, boolean>;

interface InstallerRequirements {
  php?: boolean;
  php_version?: string;
  minimum_php?: string;
  extensions?: RequirementMap;
  permissions?: RequirementMap;
}

interface InstallerModule {
  alias: string;
  name: string;
}

interface InstallerProps {
  steps?: string[];
  requirements?: InstallerRequirements;
  modules?: InstallerModule[];
  appVersion?: string;
  environment?: string;
}

type DatabaseState = 'idle' | 'testing' | 'success' | 'error';
type LicenseState = 'idle' | 'ready' | 'invalid';

const defaultSteps: InstallerStep[] = [
  { label: 'Welcome', shortLabel: 'Welcome' },
  { label: 'Requirements', shortLabel: 'System' },
  { label: 'Permissions', shortLabel: 'Access' },
  { label: 'Environment', shortLabel: 'App' },
  { label: 'Database', shortLabel: 'Database' },
  { label: 'License', shortLabel: 'License' },
  { label: 'Super Admin', shortLabel: 'Admin' },
  { label: 'Modules', shortLabel: 'Modules' },
  { label: 'Finalize', shortLabel: 'Finalize' },
];

const fieldClass = 'w-full rounded-xl border border-white/10 bg-[#111720] px-3.5 py-2.5 text-sm text-slate-100 outline-none transition placeholder:text-slate-600 hover:border-white/20 focus:border-emerald-400/60 focus:ring-2 focus:ring-emerald-400/10 disabled:cursor-not-allowed disabled:opacity-50';

function csrfToken(): string {
  const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
  return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : '';
}

function StepHeading({ eyebrow, title, description, icon }: { eyebrow: string; title: string; description: string; icon: React.ReactNode }) {
  return (
    <div className="border-b border-white/[0.07] px-5 py-5 sm:px-7 sm:py-6 lg:px-9">
      <div className="flex items-start gap-4">
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-emerald-400/20 bg-emerald-400/[0.08] text-emerald-300">
          {icon}
        </span>
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-400">{eyebrow}</p>
          <h1 className="mt-1 text-2xl font-semibold tracking-[-0.03em] text-white sm:text-3xl">{title}</h1>
          <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">{description}</p>
        </div>
      </div>
    </div>
  );
}

function StepBody({ children }: { children: React.ReactNode }) {
  return <div className="px-5 py-5 sm:px-7 sm:py-7 lg:px-9">{children}</div>;
}

function StepFooter({ back, next, nextLabel = 'Continue', nextDisabled = false }: { back?: () => void; next?: () => void; nextLabel?: string; nextDisabled?: boolean }) {
  return (
    <div className="flex flex-col-reverse gap-3 border-t border-white/[0.07] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7 lg:px-9">
      {back ? (
        <Button type="button" variant="ghost" onClick={back} icon={<ArrowLeft className="h-4 w-4" />}>Back</Button>
      ) : <span />}
      {next && (
        <Button type="button" variant="primary" size="lg" onClick={next} disabled={nextDisabled} icon={<ArrowRight className="h-4 w-4" />} iconPosition="right" className="w-full sm:w-auto">
          {nextLabel}
        </Button>
      )}
    </div>
  );
}

function StatusRow({ label, detail, passed }: { label: string; detail?: string; passed: boolean }) {
  return (
    <div className={`flex items-center justify-between gap-4 rounded-xl border p-3.5 ${passed ? 'border-emerald-400/15 bg-emerald-400/[0.045]' : 'border-rose-400/20 bg-rose-400/[0.06]'}`}>
      <div className="min-w-0">
        <p className="text-sm font-medium text-slate-200">{label}</p>
        {detail && <p className="mt-0.5 text-xs text-slate-500">{detail}</p>}
      </div>
      {passed ? <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-400" aria-label="Passed" /> : <XCircle className="h-5 w-5 shrink-0 text-rose-400" aria-label="Failed" />}
    </div>
  );
}

function ErrorBanner({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div role="alert" className="flex items-start gap-3 rounded-xl border border-rose-400/25 bg-rose-400/[0.08] p-4 text-sm text-rose-100">
      <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-rose-400" aria-hidden="true" />
      <div className="min-w-0 flex-1">
        <p className="font-semibold">This step needs attention</p>
        <p className="mt-1 break-words leading-5 text-rose-200/80">{message}</p>
      </div>
      {onRetry && <button type="button" onClick={onRetry} className="rounded-lg p-1 text-rose-300 hover:bg-white/10 focus-ring" aria-label="Retry"><RefreshCw className="h-4 w-4" /></button>}
    </div>
  );
}

export default function InstallIndex({ steps, requirements = {}, modules = [], appVersion, environment }: InstallerProps) {
  const normalizedSteps = (steps?.length === 9 ? steps : defaultSteps.map((step) => step.label)).map((label, index) => ({ label, shortLabel: defaultSteps[index]?.shortLabel ?? label }));
  const [currentStep, setCurrentStep] = useState(0);
  const [databaseState, setDatabaseState] = useState<DatabaseState>('idle');
  const [databaseMessage, setDatabaseMessage] = useState('');
  const [licenseState, setLicenseState] = useState<LicenseState>('idle');
  const [showDatabasePassword, setShowDatabasePassword] = useState(false);
  const [showAdminPassword, setShowAdminPassword] = useState(false);
  const [installing, setInstalling] = useState(false);
  const databasePassword = useRef('');

  const { data, setData, post, processing, errors, clearErrors, transform } = useForm({
    site_name: 'HiddenLeaf BusinessOS',
    app_url: window.location.origin,
    app_environment: 'production',
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
    language: 'en',
    currency: 'USD',
    storage_driver: 'local',
    db_connection: 'pgsql',
    db_host: '127.0.0.1',
    db_port: '5432',
    db_database: 'hiddenleaf_business_os',
    db_username: 'hiddenleaf',
    db_password: '',
    license_token: '',
    license_domain: window.location.hostname,
    admin_name: '',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
    modules: modules.map((module) => module.alias),
  });

  const extensionChecks = requirements.extensions ?? {};
  const permissionChecks = requirements.permissions ?? {};
  const requirementsPass = Boolean(requirements.php) && Object.values(extensionChecks).every(Boolean);
  const permissionsPass = ['storage', 'bootstrap_cache', 'environment'].every((key) => permissionChecks[key] !== false);
  const passwordScore = useMemo(() => {
    const password = data.admin_password;
    return [password.length >= 12, /[A-Z]/.test(password), /[a-z]/.test(password), /\d/.test(password), /[^A-Za-z0-9]/.test(password)].filter(Boolean).length;
  }, [data.admin_password]);
  const adminReady = data.admin_name.trim().length > 1 && /\S+@\S+\.\S+/.test(data.admin_email) && data.admin_password.length >= 12 && data.admin_password === data.admin_password_confirmation;
  const licenseReady = licenseState === 'ready' && data.license_token.trim().length > 0 && data.license_domain.trim().length > 0;

  const updateDatabaseField = (field: 'db_connection' | 'db_host' | 'db_port' | 'db_database' | 'db_username' | 'db_password', value: string) => {
    setData(field, value);
    if (field === 'db_password') databasePassword.current = value;
    setDatabaseState('idle');
    setDatabaseMessage('');
  };

  const testDatabase = async () => {
    setDatabaseState('testing');
    setDatabaseMessage('');
    const password = data.db_password || databasePassword.current;
    databasePassword.current = password;
    try {
      const response = await fetch('/install/test-db', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
          db_connection: data.db_connection,
          db_host: data.db_host,
          db_port: Number(data.db_port),
          db_database: data.db_database,
          db_username: data.db_username,
          db_password: password,
        }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.success) throw new Error(payload.message || 'Connection failed. Verify the database details and try again.');
      setDatabaseState('success');
      setDatabaseMessage(payload.message || 'Database connection successful.');
      setData('db_password', '');
    } catch (error) {
      setDatabaseState('error');
      setDatabaseMessage(error instanceof Error ? error.message : 'Database connection failed.');
      databasePassword.current = '';
      setData('db_password', '');
    }
  };

  const validateLicenseInput = () => {
    clearErrors('license_token', 'license_domain');
    const domainValid = /^(localhost|(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}|\d{1,3}(?:\.\d{1,3}){3})$/i.test(data.license_domain.trim());
    if (!data.license_token.trim() || !domainValid) {
      setLicenseState('invalid');
      return;
    }
    setLicenseState('ready');
  };

  const toggleModule = (alias: string) => {
    setData('modules', data.modules.includes(alias) ? data.modules.filter((item) => item !== alias) : [...data.modules, alias]);
  };

  const submitInstallation = () => {
    setInstalling(true);
    clearErrors();
    transform((formData) => ({ ...formData, db_password: databasePassword.current || formData.db_password }));
    post('/install', {
      preserveScroll: true,
      onError: (formErrors) => {
        setInstalling(false);
        databasePassword.current = '';
        setData((current) => ({ ...current, db_password: '', license_token: '', admin_password: '', admin_password_confirmation: '' }));
        const message = String(formErrors.installation ?? '');
        if (/license|domain|entitlement|token|expired|revoked|suspended/i.test(message)) {
          setLicenseState('invalid');
          setCurrentStep(5);
        } else if (/database|driver|host|credential|connection/i.test(message)) {
          setDatabaseState('error');
          setDatabaseMessage(message || 'Database validation failed during installation.');
          setCurrentStep(4);
        }
      },
      onFinish: () => setInstalling(false),
    });
  };

  const installationError = (errors as Record<string, string | undefined>).installation;

  return (
    <InstallerLayout steps={normalizedSteps} currentStep={currentStep}>
      {currentStep === 0 && (
        <>
          <StepHeading eyebrow="Welcome" title="Set up your BusinessOS" description="Set up your workspace, database, licensing, administrator account, and core modules." icon={<Sparkles className="h-5 w-5" />} />
          <StepBody>
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(260px,.6fr)]">
              <div className="rounded-2xl border border-white/[0.08] bg-gradient-to-br from-emerald-400/[0.10] via-white/[0.035] to-violet-500/[0.08] p-6 sm:p-8">
                <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-cyan-500 text-slate-950 shadow-xl shadow-emerald-500/20"><Leaf className="h-7 w-7" /></span>
                <h2 className="mt-6 text-2xl font-semibold tracking-[-0.03em] text-white">A guided, secure installation</h2>
                <p className="mt-3 max-w-xl text-sm leading-6 text-slate-400">This wizard checks your server, validates connectivity, captures your signed entitlement, and initializes only the modules you select.</p>
                <div className="mt-6 flex flex-wrap gap-2 text-xs text-slate-300">
                  {['No default credentials', 'Signed licensing', 'Private secrets'].map((item) => <span key={item} className="rounded-full border border-white/10 bg-black/15 px-3 py-1.5">{item}</span>)}
                </div>
              </div>
              <dl className="grid gap-3">
                <div className="rounded-xl border border-white/[0.08] bg-white/[0.025] p-4"><dt className="text-xs text-slate-500">BusinessOS version</dt><dd className="mt-1 text-sm font-medium text-slate-200">{appVersion ?? 'Managed by server release'}</dd></div>
                <div className="rounded-xl border border-white/[0.08] bg-white/[0.025] p-4"><dt className="text-xs text-slate-500">PHP detected</dt><dd className="mt-1 text-sm font-medium text-slate-200">{requirements.php_version ?? 'Not reported'}</dd></div>
                <div className="rounded-xl border border-white/[0.08] bg-white/[0.025] p-4"><dt className="text-xs text-slate-500">Environment</dt><dd className="mt-1 text-sm font-medium text-slate-200">{environment ?? 'Installer mode'}</dd></div>
              </dl>
            </div>
          </StepBody>
          <StepFooter next={() => setCurrentStep(1)} nextLabel="Start Installation" />
        </>
      )}

      {currentStep === 1 && (
        <>
          <StepHeading eyebrow="System check" title="Requirements" description="Mandatory runtime capabilities are checked by the server. Resolve any failed item before continuing." icon={<ServerCog className="h-5 w-5" />} />
          <StepBody>
            <div className="grid gap-3 md:grid-cols-2">
              <StatusRow label={`PHP ${requirements.minimum_php ?? '8.4.0'}+`} detail={`Detected: ${requirements.php_version ?? 'Not reported'}`} passed={Boolean(requirements.php)} />
              {Object.entries(extensionChecks).map(([name, passed]) => <StatusRow key={name} label={name === 'pdo' ? 'PDO' : name.charAt(0).toUpperCase() + name.slice(1)} passed={passed} />)}
            </div>
            <div role="status" className={`mt-5 flex items-center gap-3 rounded-xl border p-4 text-sm ${requirementsPass ? 'border-emerald-400/20 bg-emerald-400/[0.06] text-emerald-200' : 'border-rose-400/20 bg-rose-400/[0.06] text-rose-200'}`}>
              {requirementsPass ? <CheckCircle2 className="h-5 w-5" /> : <XCircle className="h-5 w-5" />}
              {requirementsPass ? 'All system requirements are satisfied.' : 'Resolve the highlighted requirements before continuing.'}
            </div>
          </StepBody>
          <StepFooter back={() => setCurrentStep(0)} next={() => setCurrentStep(2)} nextDisabled={!requirementsPass} />
        </>
      )}

      {currentStep === 2 && (
        <>
          <StepHeading eyebrow="Filesystem access" title="Permissions" description="BusinessOS needs limited write access for application state, caches, and environment configuration." icon={<ShieldCheck className="h-5 w-5" />} />
          <StepBody>
            <div className="space-y-3">
              <StatusRow label="storage/" detail="Application logs, private files, sessions, and generated state." passed={permissionChecks.storage !== false} />
              <StatusRow label="bootstrap/cache/" detail="Laravel bootstrap and optimized configuration cache." passed={permissionChecks.bootstrap_cache !== false} />
              <StatusRow label=".env writable or creatable" detail="Database and application environment settings." passed={permissionChecks.environment !== false} />
            </div>
            {!permissionsPass && <div className="mt-5"><ErrorBanner message="Update the highlighted directory or environment-file permissions, then reload this page to run the checks again." onRetry={() => window.location.reload()} /></div>}
          </StepBody>
          <StepFooter back={() => setCurrentStep(1)} next={() => setCurrentStep(3)} nextDisabled={!permissionsPass} />
        </>
      )}

      {currentStep === 3 && (
        <>
          <StepHeading eyebrow="Application defaults" title="Environment" description="Define the public identity and regional defaults for this BusinessOS installation." icon={<Globe2 className="h-5 w-5" />} />
          <StepBody>
            <div className="grid gap-5 sm:grid-cols-2">
              <div className="sm:col-span-2"><Input label="Application Name" value={data.site_name} onChange={(event) => setData('site_name', event.target.value)} error={errors.site_name} autoComplete="organization" required /></div>
              <div className="sm:col-span-2"><Input label="Application URL" type="url" value={data.app_url} onChange={(event) => setData('app_url', event.target.value)} error={errors.app_url} placeholder="https://business.example.com" required /></div>
              <Select label="Environment" value={data.app_environment} onChange={(event) => setData('app_environment', event.target.value)} error={errors.app_environment} options={[{ value: 'production', label: 'Production' }, { value: 'staging', label: 'Staging' }, { value: 'local', label: 'Local' }]} />
              <Input label="Timezone" value={data.timezone} onChange={(event) => setData('timezone', event.target.value)} error={errors.timezone} hint="Use an IANA timezone, for example Asia/Kolkata." required />
              <Select label="Default Language" value={data.language} onChange={(event) => setData('language', event.target.value)} error={errors.language} options={[{ value: 'en', label: 'English' }]} />
              <Select label="Default Currency" value={data.currency} onChange={(event) => setData('currency', event.target.value)} error={errors.currency} options={[{ value: 'USD', label: 'USD — US Dollar' }, { value: 'EUR', label: 'EUR — Euro' }, { value: 'GBP', label: 'GBP — Pound Sterling' }, { value: 'INR', label: 'INR — Indian Rupee' }]} />
              <div className="sm:col-span-2"><Select label="Storage Driver" value={data.storage_driver} onChange={(event) => setData('storage_driver', event.target.value)} error={errors.storage_driver} hint={data.storage_driver === 's3' ? 'S3 credentials are read from the server environment; this wizard never displays them.' : 'Files will be stored on this server.'} options={[{ value: 'local', label: 'Local storage' }, { value: 's3', label: 'Amazon S3-compatible storage' }]} /></div>
            </div>
          </StepBody>
          <StepFooter back={() => setCurrentStep(2)} next={() => setCurrentStep(4)} nextDisabled={!data.site_name || !data.app_url || !data.timezone} />
        </>
      )}

      {currentStep === 4 && (
        <>
          <StepHeading eyebrow="Connectivity" title="Database" description="Connect to an empty or dedicated database. Credentials stay masked and are cleared after failed tests." icon={<Database className="h-5 w-5" />} />
          <StepBody>
            <div className="grid gap-5 sm:grid-cols-2">
              <Select label="Database Driver" value={data.db_connection} onChange={(event) => { const driver = event.target.value; updateDatabaseField('db_connection', driver); updateDatabaseField('db_port', driver === 'pgsql' ? '5432' : '3306'); }} error={errors.db_connection} options={[{ value: 'pgsql', label: 'PostgreSQL' }, { value: 'mysql', label: 'MySQL' }]} />
              <Input label="Port" inputMode="numeric" value={data.db_port} onChange={(event) => updateDatabaseField('db_port', event.target.value)} error={errors.db_port} required />
              <Input label="Host" value={data.db_host} onChange={(event) => updateDatabaseField('db_host', event.target.value)} error={errors.db_host} autoComplete="off" required />
              <Input label="Database" value={data.db_database} onChange={(event) => updateDatabaseField('db_database', event.target.value)} error={errors.db_database} autoComplete="off" required />
              <Input label="Username" value={data.db_username} onChange={(event) => updateDatabaseField('db_username', event.target.value)} error={errors.db_username} autoComplete="username" required />
              <Input label="Password" type={showDatabasePassword ? 'text' : 'password'} value={data.db_password} onChange={(event) => updateDatabaseField('db_password', event.target.value)} error={errors.db_password} autoComplete="new-password" rightIcon={<button type="button" onClick={() => setShowDatabasePassword((visible) => !visible)} className="rounded p-1 hover:text-white focus-ring" aria-label={showDatabasePassword ? 'Hide database password' : 'Show database password'}>{showDatabasePassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}</button>} hint={databaseState === 'success' && !data.db_password ? 'Credential retained only for this setup session and hidden from the form.' : undefined} />
            </div>

            <div className="mt-6 flex flex-col gap-3 rounded-xl border border-white/[0.08] bg-white/[0.025] p-4 sm:flex-row sm:items-center sm:justify-between">
              <div aria-live="polite" className="min-w-0">
                <p className="text-sm font-medium text-slate-200">Connection status</p>
                <p className={`mt-1 text-xs ${databaseState === 'success' ? 'text-emerald-300' : databaseState === 'error' ? 'text-rose-300' : 'text-slate-500'}`}>{databaseMessage || 'Not tested yet.'}</p>
              </div>
              <Button type="button" variant="secondary" loading={databaseState === 'testing'} onClick={testDatabase} icon={<Database className="h-4 w-4" />} className="shrink-0">Test Connection</Button>
            </div>
            {databaseState === 'error' && <div className="mt-4"><ErrorBanner message={databaseMessage} onRetry={testDatabase} /></div>}
          </StepBody>
          <StepFooter back={() => setCurrentStep(3)} next={() => setCurrentStep(5)} nextDisabled={databaseState !== 'success'} />
        </>
      )}

      {currentStep === 5 && (
        <>
          <StepHeading eyebrow="Commercial entitlement" title="License" description="Your signed entitlement validates this installation, its licensed domain, and enabled commercial capabilities." icon={<KeyRound className="h-5 w-5" />} />
          <StepBody>
            {installationError && <div className="mb-5"><ErrorBanner message={installationError} /></div>}
            <div className="space-y-5">
              <div>
                <label htmlFor="license-token" className="mb-1.5 block text-xs font-medium tracking-wide text-slate-300">License / entitlement token</label>
                <textarea id="license-token" rows={5} className={`${fieldClass} resize-y font-mono text-xs leading-5`} value={data.license_token} onChange={(event) => { setData('license_token', event.target.value); setLicenseState('idle'); }} aria-describedby="license-token-help" aria-invalid={Boolean(errors.license_token)} autoComplete="off" spellCheck={false} required />
                <p id="license-token-help" className="mt-1.5 text-xs text-slate-500">The token is submitted only to the local installer and is never included in summaries or error details.</p>
                {errors.license_token && <p className="mt-1 text-xs text-rose-400">{errors.license_token}</p>}
              </div>
              <Input label="Licensed Domain" value={data.license_domain} onChange={(event) => { setData('license_domain', event.target.value); setLicenseState('idle'); }} error={errors.license_domain} placeholder="business.example.com" autoComplete="url" required />
            </div>

            <div className="mt-6 rounded-xl border border-white/[0.08] bg-white/[0.025] p-4">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div aria-live="polite">
                  <p className="text-sm font-medium text-slate-200">Entitlement status</p>
                  <p className={`mt-1 text-xs ${licenseState === 'ready' ? 'text-emerald-300' : licenseState === 'invalid' ? 'text-rose-300' : 'text-slate-500'}`}>
                    {licenseState === 'ready' ? 'Ready for signed verification during installation.' : licenseState === 'invalid' ? 'Enter a token and a valid licensed domain.' : 'Not checked yet.'}
                  </p>
                </div>
                <Button type="button" variant="secondary" onClick={validateLicenseInput} icon={<ShieldCheck className="h-4 w-4" />}>Prepare License</Button>
              </div>
              <p className="mt-3 border-t border-white/[0.07] pt-3 text-xs leading-5 text-slate-500">Cryptographic states such as expired, revoked, suspended, or domain mismatch are returned by the secure installer during final verification. No signing keys or token internals are exposed here.</p>
            </div>
          </StepBody>
          <StepFooter back={() => setCurrentStep(4)} next={() => setCurrentStep(6)} nextDisabled={!licenseReady} />
        </>
      )}

      {currentStep === 6 && (
        <>
          <StepHeading eyebrow="Platform owner" title="Super Admin" description="Create the first platform administrator. There is no default password and credentials are never printed after installation." icon={<UserRound className="h-5 w-5" />} />
          <StepBody>
            <div className="grid gap-5 sm:grid-cols-2">
              <Input label="Administrator Name" value={data.admin_name} onChange={(event) => setData('admin_name', event.target.value)} error={errors.admin_name} autoComplete="name" required />
              <Input label="Email" type="email" value={data.admin_email} onChange={(event) => setData('admin_email', event.target.value)} error={errors.admin_email} autoComplete="email" required />
              <Input label="Password" type={showAdminPassword ? 'text' : 'password'} value={data.admin_password} onChange={(event) => setData('admin_password', event.target.value)} error={errors.admin_password} autoComplete="new-password" rightIcon={<button type="button" onClick={() => setShowAdminPassword((visible) => !visible)} className="rounded p-1 hover:text-white focus-ring" aria-label={showAdminPassword ? 'Hide administrator password' : 'Show administrator password'}>{showAdminPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}</button>} required />
              <Input label="Confirm Password" type={showAdminPassword ? 'text' : 'password'} value={data.admin_password_confirmation} onChange={(event) => setData('admin_password_confirmation', event.target.value)} error={errors.admin_password_confirmation} autoComplete="new-password" required />
            </div>

            <div className="mt-5 rounded-xl border border-white/[0.08] bg-white/[0.025] p-4">
              <div className="flex items-center justify-between gap-3"><p className="text-sm font-medium text-slate-200">Password strength</p><p className={`text-xs font-semibold ${passwordScore >= 4 ? 'text-emerald-300' : passwordScore >= 2 ? 'text-amber-300' : 'text-slate-500'}`}>{passwordScore >= 4 ? 'Strong' : passwordScore >= 2 ? 'Developing' : 'Too weak'}</p></div>
              <div className="mt-3 grid grid-cols-5 gap-1.5" aria-hidden="true">{[1, 2, 3, 4, 5].map((score) => <span key={score} className={`h-1.5 rounded-full ${passwordScore >= score ? passwordScore >= 4 ? 'bg-emerald-400' : 'bg-amber-400' : 'bg-white/10'}`} />)}</div>
              <ul className="mt-3 grid gap-1 text-xs text-slate-500 sm:grid-cols-2"><li>At least 12 characters</li><li>Upper and lowercase letters</li><li>A number</li><li>A symbol</li></ul>
            </div>
          </StepBody>
          <StepFooter back={() => setCurrentStep(5)} next={() => setCurrentStep(7)} nextDisabled={!adminReady} />
        </>
      )}

      {currentStep === 7 && (
        <>
          <StepHeading eyebrow="Bundled capabilities" title="Core Modules" description="Choose from the real modules discovered by the server. You can manage entitled modules later from BusinessOS." icon={<PackageCheck className="h-5 w-5" />} />
          <StepBody>
            {modules.length === 0 ? (
              <div className="rounded-xl border border-amber-400/20 bg-amber-400/[0.06] p-5 text-sm text-amber-100"><p className="font-semibold">No bundled modules were reported.</p><p className="mt-1 text-xs leading-5 text-amber-200/70">Reload after confirming the module registry is available. The installer will not invent module selections.</p></div>
            ) : (
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {modules.map((module) => {
                  const selected = data.modules.includes(module.alias);
                  return (
                    <label key={module.alias} className={`cursor-pointer rounded-xl border p-4 transition ${selected ? 'border-emerald-400/30 bg-emerald-400/[0.07]' : 'border-white/[0.08] bg-white/[0.025] hover:border-white/15'}`}>
                      <div className="flex items-start justify-between gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-white/[0.06] text-slate-300"><WalletCards className="h-4 w-4" /></span>
                        <Checkbox aria-label={`Select ${module.name}`} checked={selected} onChange={() => toggleModule(module.alias)} />
                      </div>
                      <p className="mt-4 text-sm font-semibold text-white">{module.name}</p>
                      <p className="mt-1 text-xs text-slate-500">Bundled core module</p>
                    </label>
                  );
                })}
              </div>
            )}
            {errors.modules && <p className="mt-3 text-xs text-rose-400">{errors.modules}</p>}
          </StepBody>
          <StepFooter back={() => setCurrentStep(6)} next={() => setCurrentStep(8)} nextDisabled={data.modules.length === 0} />
        </>
      )}

      {currentStep === 8 && (
        <>
          <StepHeading eyebrow="Review and install" title="Finalize" description="Review non-sensitive configuration before starting the secure server-side installation." icon={<LockKeyhole className="h-5 w-5" />} />
          <StepBody>
            {installationError && <div className="mb-5"><ErrorBanner message={installationError} /></div>}
            <dl className="grid gap-3 sm:grid-cols-2">
              {[
                ['Environment', data.app_environment],
                ['Database driver', data.db_connection === 'pgsql' ? 'PostgreSQL' : 'MySQL'],
                ['Licensed domain', data.license_domain],
                ['Administrator', data.admin_email],
                ['Language', data.language.toUpperCase()],
                ['Currency', data.currency],
                ['Timezone', data.timezone],
                ['Storage', data.storage_driver.toUpperCase()],
              ].map(([label, value]) => <div key={label} className="rounded-xl border border-white/[0.08] bg-white/[0.025] p-4"><dt className="text-xs text-slate-500">{label}</dt><dd className="mt-1 break-words text-sm font-medium text-slate-200">{value}</dd></div>)}
              <div className="rounded-xl border border-white/[0.08] bg-white/[0.025] p-4 sm:col-span-2"><dt className="text-xs text-slate-500">Selected modules</dt><dd className="mt-2 flex flex-wrap gap-2">{modules.filter((module) => data.modules.includes(module.alias)).map((module) => <span key={module.alias} className="rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-1 text-xs text-slate-300">{module.name}</span>)}</dd></div>
            </dl>

            <div className="mt-5 rounded-xl border border-emerald-400/15 bg-emerald-400/[0.045] p-4">
              <div className="flex items-start gap-3"><ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" /><div><p className="text-sm font-medium text-emerald-100">Sensitive values are hidden</p><p className="mt-1 text-xs leading-5 text-emerald-200/60">Database password, administrator password, and entitlement token are deliberately excluded from this summary.</p></div></div>
            </div>

            {(installing || processing) && (
              <div role="status" aria-live="polite" className="mt-5 rounded-xl border border-violet-400/20 bg-violet-400/[0.06] p-5">
                <div className="flex items-center gap-3"><Loader2 className="h-5 w-5 animate-spin text-violet-300" /><div><p className="text-sm font-semibold text-white">Installing HiddenLeaf BusinessOS</p><p className="mt-0.5 text-xs text-slate-400">Keep this window open while the secure installer completes.</p></div></div>
                <ol className="mt-5 grid gap-2 text-xs text-slate-400 sm:grid-cols-2">
                  {['Preparing environment', 'Connecting database', 'Running migrations', 'Creating administrator', 'Configuring modules', 'Finalizing installation'].map((phase, index) => <li key={phase} className="flex items-center gap-2"><span className={`flex h-5 w-5 items-center justify-center rounded-full border ${index === 0 ? 'border-violet-300/40 text-violet-300' : 'border-white/10 text-slate-600'}`}>{index === 0 ? <CircleDashed className="h-3 w-3 animate-spin" /> : index + 1}</span>{phase}</li>)}
                </ol>
              </div>
            )}
          </StepBody>
          <div className="flex flex-col-reverse gap-3 border-t border-white/[0.07] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7 lg:px-9">
            <Button type="button" variant="ghost" onClick={() => setCurrentStep(7)} disabled={installing || processing} icon={<ArrowLeft className="h-4 w-4" />}>Back</Button>
            <Button type="button" variant="primary" size="lg" onClick={submitInstallation} loading={installing || processing} icon={<Sparkles className="h-4 w-4" />} className="w-full sm:w-auto">Install HiddenLeaf BusinessOS</Button>
          </div>
        </>
      )}
    </InstallerLayout>
  );
}
