import React, { useState, useEffect } from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import {
  Menu,
  X,
  Search,
  ChevronRight,
  LogOut,
  User,
  ShieldCheck,
  Building,
  Sparkles,
  Command,
  HelpCircle,
  FileText,
  Warehouse,
  ShoppingBag,
  Layers,
  ArrowRight,
  Check,
  Laptop,
} from 'lucide-react';
import {
  ALL_NAVIGATION_GROUPS,
  filterNavigation,
  NavigationGroup,
  NavigationItem,
} from '@/Navigation/NavigationRegistry';
import { CommandPalette } from '@/Components/Navigation/CommandPalette';
import { WorkspaceSwitcher } from '@/Components/Navigation/WorkspaceSwitcher';
import { NotificationCenter } from '@/Components/Navigation/NotificationCenter';
import { ThemeSwitcher } from '@/Components/Navigation/ThemeSwitcher';
import { MrFoxLauncher } from '@/Components/MrFox/MrFoxLauncher';
import { MrFoxPanel } from '@/Components/MrFox/MrFoxPanel';
import { MrFoxMark } from '@/Components/MrFox/MrFoxMark';
import { MrFoxOrb } from '@/Components/MrFox/MrFoxOrb';
import { Badge } from '@/Components/UI/Badge';

interface AppShellProps {
  title?: string;
  children: React.ReactNode;
  breadcrumbs?: Array<{ label: string; href?: string }>;
}

export default function AppShell({ title, children, breadcrumbs }: AppShellProps) {
  const { auth, tenant, flash } = usePage<any>().props;
  const user = auth?.user;
  const isSuperAdmin = Boolean(user?.is_super_admin);
  const userPermissions = user?.permissions || [];
  const enabledModules = tenant?.modules || [];

  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [commandPaletteOpen, setCommandPaletteOpen] = useState(false);
  const [mrFoxOpen, setMrFoxOpen] = useState(false);
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);

  // Filter groups authorized for current user
  const authorizedGroups: NavigationGroup[] = filterNavigation(
    ALL_NAVIGATION_GROUPS,
    user,
    isSuperAdmin,
    userPermissions,
    enabledModules
  );

  // Global keyboard shortcuts (Cmd+K and Cmd+J)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        setCommandPaletteOpen((prev) => !prev);
      } else if ((e.metaKey || e.ctrlKey) && e.key === 'j') {
        e.preventDefault();
        setMrFoxOpen((prev) => !prev);
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  const handleLogout = () => {
    router.post('/logout');
  };

  return (
    <div className="min-h-screen bg-[var(--background)] text-[var(--text-primary)] font-sans antialiased selection:bg-purple-500/30">
      {/* ========================================================================= */}
      {/* SIDEBAR NAVIGATION (Desktop: fixed w-64, Mobile: off-canvas drawer) */}
      {/* ========================================================================= */}

      {/* Mobile Drawer Overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden spring-transition"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar Container */}
      <aside
        aria-label="Main Navigation"
        className={`
          fixed top-0 bottom-0 left-0 z-40 w-64 glass-1 border-r border-[var(--border-subtle)] flex flex-col justify-between
          spring-transition lg:translate-x-0 bg-[var(--surface-1)]
          ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
        `}
      >
        {/* Brand Header */}
        <div>
          <div className="h-16 flex items-center justify-between px-5 border-b border-[var(--border-subtle)]">
            <Link href="/dashboard" className="flex items-center gap-2.5 group">
              <div className="w-8 h-8 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center shadow-md shadow-purple-500/20 group-hover:scale-105 spring-transition">
                <MrFoxMark size={20} />
              </div>
              <div className="flex flex-col">
                <span className="font-bold text-sm tracking-tight text-[var(--text-primary)] flex items-center gap-1.5">
                  HiddenLeaf
                  <Badge variant="purple" size="sm">OS</Badge>
                </span>
                <span className="text-[10px] text-[var(--text-tertiary)] uppercase tracking-wider font-semibold">
                  BusinessOS
                </span>
              </div>
            </Link>

            <button
              type="button"
              onClick={() => setSidebarOpen(false)}
              className="lg:hidden p-1 rounded-lg text-[var(--text-tertiary)] hover:text-[var(--text-primary)] hover:bg-white/5"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Navigation Items */}
          <nav className="p-3 space-y-6 overflow-y-auto max-h-[calc(100vh-140px)]">
            {authorizedGroups.map((group) => (
              <div key={group.id} className="space-y-1">
                <div className="px-3 text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">
                  {group.title}
                </div>

                <div className="space-y-0.5 mt-1">
                  {group.items.map((item) => {
                    const Icon = item.icon;
                    const isActive = window.location.pathname === item.href;

                    return (
                      <Link
                        key={item.id}
                        href={item.href}
                        className={`
                          flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium spring-transition group select-none
                          ${isActive 
                            ? 'bg-purple-600/20 text-[var(--text-primary)] font-semibold border border-purple-500/30' 
                            : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-white/[0.04]'}
                        `}
                      >
                        <Icon
                          className={`w-4 h-4 spring-transition ${
                            isActive ? 'text-purple-400' : 'text-[var(--text-tertiary)] group-hover:text-purple-300'
                          }`}
                        />
                        <span className="truncate">{item.name}</span>
                      </Link>
                    );
                  })}
                </div>
              </div>
            ))}
          </nav>
        </div>

        {/* User Profile Mini Bar */}
        <div className="p-3 border-t border-[var(--border-subtle)] bg-[var(--surface-2)]">
          <div className="flex items-center justify-between">
            <Link
              href="/profile"
              className="flex items-center gap-2.5 min-w-0 group hover:opacity-80 spring-transition"
            >
              <div className="w-8 h-8 rounded-full bg-purple-900/40 border border-purple-500/30 flex items-center justify-center text-purple-300 text-xs font-bold flex-shrink-0">
                {user?.name?.charAt(0) || 'U'}
              </div>
              <div className="truncate">
                <div className="text-xs font-semibold text-[var(--text-primary)] truncate">
                  {user?.name || 'User'}
                </div>
                <div className="text-[10px] text-[var(--text-tertiary)] truncate">
                  {user?.email || 'user@example.com'}
                </div>
              </div>
            </Link>

            <button
              type="button"
              onClick={handleLogout}
              className="p-1.5 rounded-lg text-[var(--text-tertiary)] hover:text-rose-400 hover:bg-rose-500/10 spring-transition cursor-pointer"
              title="Sign Out"
              aria-label="Sign Out"
            >
              <LogOut className="w-4 h-4" />
            </button>
          </div>
        </div>
      </aside>

      {/* ========================================================================= */}
      {/* MAIN CONTENT AREA */}
      {/* ========================================================================= */}
      <div className="lg:pl-64 flex flex-col min-h-screen">
        {/* Top Header Navbar */}
        <header className="sticky top-0 z-30 h-16 glass-1 border-b border-[var(--border-subtle)] px-4 sm:px-6 flex items-center justify-between gap-4 bg-[var(--surface-1)]">
          <div className="flex items-center gap-3">
            {/* Mobile Menu Trigger */}
            <button
              type="button"
              onClick={() => setSidebarOpen(true)}
              className="lg:hidden p-2 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]"
              aria-label="Open Navigation Sidebar"
            >
              <Menu className="w-4 h-4" />
            </button>

            {/* Workspace Switcher */}
            <WorkspaceSwitcher />
          </div>

          {/* Center Search / Command Trigger */}
          <div className="flex-1 max-w-md hidden sm:block">
            <button
              type="button"
              onClick={() => setCommandPaletteOpen(true)}
              className="w-full flex items-center justify-between px-3 py-1.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] hover:border-purple-500/50 text-xs text-[var(--text-tertiary)] spring-transition cursor-pointer"
            >
              <div className="flex items-center gap-2">
                <Search className="w-3.5 h-3.5" />
                <span>Search features, actions, tools...</span>
              </div>
              <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-white/10 text-[var(--text-tertiary)]">
                ⌘K
              </kbd>
            </button>
          </div>

          {/* Right Header Actions */}
          <div className="flex items-center gap-2 sm:gap-3">
            {/* Theme Mode Switcher */}
            <ThemeSwitcher />

            {/* Notification Center */}
            <NotificationCenter />

            {/* Ask Mr Fox Header Shortcut */}
            <button
              type="button"
              onClick={() => setMrFoxOpen(true)}
              className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-purple-600/10 hover:bg-purple-600/20 text-purple-300 border border-purple-500/30 text-xs font-semibold spring-transition cursor-pointer"
            >
              <Sparkles className="w-3.5 h-3.5 text-purple-400" />
              <span className="hidden md:inline">Mr Fox</span>
            </button>
          </div>
        </header>

        {/* Page Breadcrumbs */}
        {breadcrumbs && breadcrumbs.length > 0 && (
          <div className="px-4 sm:px-6 py-2.5 border-b border-[var(--border-subtle)] bg-[var(--surface-1)] flex items-center gap-1.5 text-xs text-[var(--text-tertiary)]">
            <Link href="/dashboard" className="hover:text-[var(--text-primary)] spring-transition">
              Home
            </Link>
            {breadcrumbs.map((crumb, idx) => (
              <React.Fragment key={idx}>
                <ChevronRight className="w-3 h-3 text-[var(--text-tertiary)]" />
                {crumb.href ? (
                  <Link href={crumb.href} className="hover:text-[var(--text-primary)] spring-transition">
                    {crumb.label}
                  </Link>
                ) : (
                  <span className="font-semibold text-[var(--text-primary)]">{crumb.label}</span>
                )}
              </React.Fragment>
            ))}
          </div>
        )}

        {/* Flash Notifications */}
        {flash?.success && (
          <div className="mx-4 sm:mx-6 mt-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Check className="w-4 h-4" />
              <span>{flash.success}</span>
            </div>
          </div>
        )}

        {flash?.error && (
          <div className="mx-4 sm:mx-6 mt-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center justify-between">
            <div className="flex items-center gap-2">
              <X className="w-4 h-4" />
              <span>{flash.error}</span>
            </div>
          </div>
        )}

        {/* Main Content Body */}
        <main className="flex-1 p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto">
          {children}
        </main>
      </div>

      {/* Floating Mr Fox AI Launcher */}
      <MrFoxLauncher onClick={() => setMrFoxOpen(true)} unreadCount={0} />

      {/* Right-Sheet Mr Fox Drawer */}
      <MrFoxPanel
        isOpen={mrFoxOpen}
        onClose={() => setMrFoxOpen(false)}
        contextPage={title || 'Workspace'}
      />

      {/* Command Palette (Cmd+K) */}
      <CommandPalette
        isOpen={commandPaletteOpen}
        onClose={() => setCommandPaletteOpen(false)}
        onOpenMrFox={() => setMrFoxOpen(true)}
      />
    </div>
  );
}
