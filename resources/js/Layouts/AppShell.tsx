import React, { useState, useEffect } from 'react';
import { Link, usePage, router, Head } from '@inertiajs/react';
import { 
  LayoutDashboard, 
  Layers, 
  Users, 
  Settings, 
  CreditCard, 
  LogOut, 
  Menu, 
  X, 
  Search, 
  FileText, 
  DollarSign, 
  Headphones, 
  Image, 
  MessageSquare, 
  Building, 
  ShieldCheck, 
  Tag, 
  Globe, 
  Mail, 
  Sliders, 
  Bot,
  Package,
  Activity,
  ArrowRightLeft,
  Calendar,
  Sparkles,
  UserCheck
} from 'lucide-react';
import { CommandPalette } from '@/Components/Navigation/CommandPalette';
import { WorkspaceSwitcher } from '@/Components/Navigation/WorkspaceSwitcher';
import { NotificationCenter } from '@/Components/Navigation/NotificationCenter';
import { ThemeSwitcher } from '@/Components/Navigation/ThemeSwitcher';
import { MrFoxLauncher } from '@/Components/MrFox/MrFoxLauncher';
import { MrFoxPanel } from '@/Components/MrFox/MrFoxPanel';
import { MrFoxMark } from '@/Components/MrFox/MrFoxMark';
import { MrFoxOrb } from '@/Components/MrFox/MrFoxOrb';
import { Badge } from '@/Components/UI/Badge';
import { 
  ALL_NAVIGATION_GROUPS, 
  filterNavigation, 
  NavigationItem 
} from '@/Navigation/NavigationRegistry';

interface AppShellProps {
  title?: string;
  children: React.ReactNode;
}

export default function AppShell({ title, children }: AppShellProps) {
  const { auth, tenant, impersonating } = usePage<any>().props;

  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [commandPaletteOpen, setCommandPaletteOpen] = useState(false);
  const [foxPanelOpen, setFoxPanelOpen] = useState(false);

  const isSuperAdmin = Boolean(auth?.user?.is_super_admin);
  const userPermissions = auth?.user?.permissions || [];

  const navigationGroups = filterNavigation(
    ALL_NAVIGATION_GROUPS,
    auth?.user,
    isSuperAdmin,
    userPermissions
  );

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        setCommandPaletteOpen((prev) => !prev);
      }
      if ((e.metaKey || e.ctrlKey) && e.key === 'j') {
        e.preventDefault();
        setFoxPanelOpen((prev) => !prev);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  const handleLeaveImpersonation = () => {
    router.post('/impersonate/leave');
  };

  return (
    <div className="min-h-screen bg-[var(--bg-0)] text-[var(--text-primary)] flex flex-col antialiased selection:bg-purple-600 selection:text-white">
      {title && <Head title={title} />}

      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 z-50 px-4 py-2 bg-purple-600 text-white rounded-lg font-bold shadow-lg"
      >
        Skip to main content
      </a>

      {impersonating && (
        <div className="bg-gradient-to-r from-amber-500 to-rose-600 px-4 py-1.5 text-xs text-black font-bold flex items-center justify-between z-50">
          <div className="flex items-center gap-2">
            <UserCheck className="w-4 h-4" />
            <span>Active Impersonation Session: <strong>{auth?.user?.name}</strong></span>
          </div>
          <button
            type="button"
            onClick={handleLeaveImpersonation}
            className="underline hover:text-white font-black cursor-pointer"
          >
            Leave Impersonation
          </button>
        </div>
      )}

      <div className="flex flex-1 min-h-0 relative">
        {sidebarOpen && (
          <div
            className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden spring-transition"
            onClick={() => setSidebarOpen(false)}
          />
        )}

        <aside
          aria-label="Sidebar Navigation"
          className={`
            fixed inset-y-0 left-0 z-40 w-64 glass-1 border-r border-[var(--border-subtle)] flex flex-col justify-between transform lg:relative lg:translate-x-0 spring-transition
            ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
          `}
        >
          <div className="h-16 px-4 border-b border-[var(--border-subtle)] flex items-center justify-between">
            <Link href="/dashboard" className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center text-white font-black text-sm shadow-md">
                HL
              </div>
              <div>
                <span className="text-sm font-bold tracking-tight text-white block">HiddenLeaf</span>
                <span className="text-[10px] font-medium text-violet-400 block -mt-1">BusinessOS</span>
              </div>
            </Link>

            <button
              type="button"
              className="lg:hidden p-1 text-gray-400 hover:text-white"
              onClick={() => setSidebarOpen(false)}
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="flex-1 overflow-y-auto py-4 px-3 space-y-5">
            {navigationGroups.map((group) => (
              <div key={group.group} className="space-y-1">
                <div className="px-3 text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">
                  {group.group}
                </div>
                {group.items.map((item) => {
                  const isActive = typeof window !== 'undefined' && window.location.pathname.startsWith(item.href);
                  const Icon = item.icon;

                  return (
                    <Link
                      key={item.id}
                      href={item.href}
                      className={`
                        flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium spring-transition group select-none
                        ${isActive 
                          ? 'bg-purple-600/20 text-white font-semibold border border-purple-500/30' 
                          : 'text-[var(--text-secondary)] hover:text-white hover:bg-white/[0.04]'}
                      `}
                    >
                      <Icon className={`w-4 h-4 ${isActive ? 'text-purple-400' : 'text-gray-400 group-hover:text-purple-300'}`} />
                      <span className="truncate">{item.name}</span>
                    </Link>
                  );
                })}
              </div>
            ))}
          </div>

          <div className="p-3 border-t border-[var(--border-subtle)] space-y-2 bg-[var(--surface-1)]">
            <div className="flex items-center justify-between text-xs px-2 py-1">
              <span className="text-[11px] text-[var(--text-tertiary)]">Theme</span>
              <ThemeSwitcher />
            </div>

            <div className="p-2 rounded-xl bg-white/[0.02] border border-[var(--border-subtle)] flex items-center justify-between">
              <div className="flex items-center gap-2.5 truncate">
                <div className="w-8 h-8 rounded-lg bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white font-bold text-xs">
                  {auth?.user?.name?.charAt(0) || 'U'}
                </div>
                <div className="truncate">
                  <span className="text-xs font-semibold text-white block truncate">{auth?.user?.name || 'User'}</span>
                  <span className="text-[10px] text-gray-400 block truncate">{auth?.user?.email}</span>
                </div>
              </div>

              <Link
                href="/logout"
                method="post"
                as="button"
                className="p-1.5 text-gray-400 hover:text-rose-400 spring-transition cursor-pointer"
                title="Sign Out"
              >
                <LogOut className="w-4 h-4" />
              </Link>
            </div>
          </div>
        </aside>

        <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
          <header className="h-16 glass-1 border-b border-[var(--border-subtle)] px-4 sm:px-6 flex items-center justify-between gap-3 z-30">
            <div className="flex items-center gap-3">
              <button
                type="button"
                className="lg:hidden p-2 rounded-lg bg-white/[0.04] text-gray-300 hover:text-white"
                onClick={() => setSidebarOpen(true)}
                aria-label="Open sidebar menu"
              >
                <Menu className="w-5 h-5" />
              </button>

              <WorkspaceSwitcher />
            </div>

            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => setCommandPaletteOpen(true)}
                className="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/[0.03] hover:bg-white/[0.06] border border-[var(--border-medium)] text-xs text-gray-400 hover:text-gray-200 spring-transition cursor-pointer"
              >
                <Search className="w-3.5 h-3.5" />
                <span>Search everything...</span>
                <kbd className="text-[10px] font-mono bg-white/10 px-1.5 py-0.5 rounded text-gray-300">⌘K</kbd>
              </button>

              <NotificationCenter />
            </div>
          </header>

          <main id="main-content" className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 pb-24 lg:pb-8">
            {children}
          </main>
        </div>
      </div>

      <nav
        aria-label="Mobile Navigation"
        className="lg:hidden fixed bottom-0 inset-x-0 z-30 glass-dropdown border-t border-[var(--border-subtle)] px-4 py-2 flex items-center justify-around text-[10px] font-medium"
      >
        <Link href="/dashboard" className="flex flex-col items-center gap-1 text-gray-400 hover:text-white">
          <LayoutDashboard className="w-5 h-5" />
          <span>Dashboard</span>
        </Link>
        <Link href="/sales-invoices" className="flex flex-col items-center gap-1 text-gray-400 hover:text-white">
          <FileText className="w-5 h-5" />
          <span>Work</span>
        </Link>
        
        <button
          type="button"
          onClick={() => setFoxPanelOpen(true)}
          className="flex flex-col items-center -mt-5"
          aria-label="Ask Mr Fox Copilot"
        >
          <MrFoxOrb size="sm" />
          <span className="text-[10px] text-purple-300 font-bold mt-1">Mr Fox</span>
        </button>

        <Link href="/chats" className="flex flex-col items-center gap-1 text-gray-400 hover:text-white">
          <MessageSquare className="w-5 h-5" />
          <span>Inbox</span>
        </Link>
        <button
          type="button"
          onClick={() => setSidebarOpen(true)}
          className="flex flex-col items-center gap-1 text-gray-400 hover:text-white"
        >
          <Menu className="w-5 h-5" />
          <span>More</span>
        </button>
      </nav>

      <div className="hidden lg:block">
        <MrFoxLauncher onClick={() => setFoxPanelOpen(true)} />
      </div>

      <MrFoxPanel
        isOpen={foxPanelOpen}
        onClose={() => setFoxPanelOpen(false)}
        contextPage={title || 'Executive Dashboard'}
      />

      <CommandPalette
        isOpen={commandPaletteOpen}
        onClose={() => setCommandPaletteOpen(false)}
      />
    </div>
  );
}
