import React, { useEffect, useMemo, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, ChevronRight, LogOut, Menu, Search, Sparkles, X } from 'lucide-react';
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
import { Badge } from '@/Components/UI/Badge';

interface AppShellProps {
  title?: string;
  children: React.ReactNode;
  breadcrumbs?: Array<{ label: string; href?: string }>;
}

function pathMatches(href: string | undefined, currentPath: string): boolean {
  if (!href) return false;
  if (href === '/dashboard') return currentPath === '/dashboard';
  return currentPath === href || currentPath.startsWith(`${href}/`);
}

function containsActive(item: NavigationItem, currentPath: string): boolean {
  return (
    pathMatches(item.href, currentPath) ||
    Boolean(item.children?.some((child) => containsActive(child, currentPath)))
  );
}

interface TreeItemProps {
  item: NavigationItem;
  currentPath: string;
  depth?: number;
  onNavigate?: () => void;
}

function TreeItem({ item, currentPath, depth = 0, onNavigate }: TreeItemProps) {
  const hasChildren = Boolean(item.children?.length);
  const active = containsActive(item, currentPath);

  // Only auto-expand if this group is active — all others start collapsed
  const [open, setOpen] = useState(active);
  const Icon = item.icon;

  useEffect(() => {
    if (active) setOpen(true);
  }, [active]);

  if (hasChildren) {
    return (
      <div className="space-y-0.5">
        <button
          type="button"
          onClick={() => setOpen((v) => !v)}
          aria-expanded={open}
          className={`w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13px] font-medium spring-transition select-none ${
            active
              ? 'bg-[var(--brand-primary)]/10 text-[var(--text-primary)] border border-[var(--brand-primary)]/20'
              : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-white/[0.04] border border-transparent'
          }`}
          style={{ paddingLeft: `${12 + depth * 12}px` }}
        >
          {Icon && (
            <Icon
              className={`w-4 h-4 flex-shrink-0 ${
                active ? 'text-[var(--brand-primary)]' : 'text-[var(--text-tertiary)]'
              }`}
            />
          )}
          <span className="truncate flex-1 text-left">{item.name}</span>
          {open ? (
            <ChevronDown className="w-3.5 h-3.5 text-[var(--text-tertiary)]" />
          ) : (
            <ChevronRight className="w-3.5 h-3.5 text-[var(--text-tertiary)]" />
          )}
        </button>

        {open && (
          <div className="ml-3.5 pl-2.5 border-l border-[var(--border-subtle)] space-y-0.5">
            {item.children!.map((child) => (
              <TreeItem
                key={child.id}
                item={child}
                currentPath={currentPath}
                depth={depth + 1}
                onNavigate={onNavigate}
              />
            ))}
          </div>
        )}
      </div>
    );
  }

  if (!item.href) return null;
  const exactActive = pathMatches(item.href, currentPath);

  return (
    <Link
      href={item.href}
      onClick={onNavigate}
      className={`flex items-center gap-2.5 px-3 py-2 rounded-lg text-[13px] font-medium spring-transition group select-none ${
        exactActive
          ? 'bg-[var(--brand-primary)]/10 text-[var(--text-primary)] border border-[var(--brand-primary)]/20'
          : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-white/[0.04] border border-transparent'
      }`}
      style={{ paddingLeft: `${12 + depth * 12}px` }}
    >
      {Icon && (
        <Icon
          className={`w-4 h-4 flex-shrink-0 ${
            exactActive
              ? 'text-[var(--brand-primary)]'
              : 'text-[var(--text-tertiary)] group-hover:text-[var(--text-secondary)]'
          }`}
        />
      )}
      <span className="truncate">{item.name}</span>
    </Link>
  );
}

export default function AppShell({ title, children, breadcrumbs }: AppShellProps) {
  const page = usePage<any>();
  const { auth, tenant, flash, is_impersonating, impersonator_name } = page.props;
  const currentPath = String(page.url || '/').split('?')[0];
  const user = auth?.user;
  const isSuperAdmin = Boolean(user?.is_super_admin);
  const userPermissions = user?.permissions || [];
  const enabledModules = tenant?.modules || [];

  const brandName = tenant?.brand_name || 'HiddenLeaf';
  const brandLogo = tenant?.brand_logo_path;
  const brandColor = tenant?.brand_primary_color;
  const brandFooterText = tenant?.brand_footer_text;

  useEffect(() => {
    if (brandColor) {
      document.documentElement.style.setProperty('--brand-primary', brandColor);
    }
  }, [brandColor]);

  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [commandPaletteOpen, setCommandPaletteOpen] = useState(false);
  const [mrFoxOpen, setMrFoxOpen] = useState(false);

  const authorizedGroups: NavigationGroup[] = useMemo(
    () => filterNavigation(ALL_NAVIGATION_GROUPS, user, isSuperAdmin, userPermissions, enabledModules),
    [user, isSuperAdmin, userPermissions, enabledModules],
  );

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setCommandPaletteOpen((v) => !v);
      } else if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'j') {
        e.preventDefault();
        setMrFoxOpen((v) => !v);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  const handleLogout = () => router.post('/logout');

  return (
    <div className="min-h-screen bg-[var(--bg-0)] text-[var(--text-primary)] font-sans antialiased">
      {/* Impersonation Banner */}
      {is_impersonating && (
        <div className="bg-amber-500/15 border-b border-amber-500/30 px-4 py-2 text-xs text-amber-400 flex items-center justify-between sticky top-0 z-50 backdrop-blur-md">
          <span>
            Impersonating as {auth?.user?.name} — logged in as {impersonator_name}
          </span>
          <button
            onClick={() => router.post('/users/leave-impersonation', {}, { onSuccess: () => router.visit('/super-admin/companies') })}
            className="font-medium hover:text-amber-300 underline cursor-pointer"
          >
            Leave impersonation
          </button>
        </div>
      )}

      {/* Mobile overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* ── Sidebar ─────────────────────────────────────────────────── */}
      <aside
        aria-label="Main navigation"
        className={`fixed top-0 bottom-0 left-0 z-40 w-64 glass-1 border-r border-[var(--border-subtle)] flex flex-col spring-transition lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        {/* Logo */}
        <div className="h-14 flex items-center justify-between px-4 border-b border-[var(--border-subtle)] flex-shrink-0">
          <Link
            href="/dashboard"
            className="flex items-center gap-2.5 group min-w-0"
            onClick={() => setSidebarOpen(false)}
          >
            {brandLogo ? (
              <img src={brandLogo} alt={brandName} className="h-7 w-auto object-contain max-w-[120px]" />
            ) : (
              <div className="w-7 h-7 rounded-lg bg-[var(--brand-primary)]/15 border border-[var(--brand-primary)]/25 flex items-center justify-center flex-shrink-0">
                <MrFoxMark size={16} />
              </div>
            )}
            <div className="flex flex-col min-w-0">
              <span className="font-bold text-sm tracking-tight text-[var(--text-primary)] flex items-center gap-1.5 truncate">
                {brandName}
                {!tenant?.brand_name && <Badge variant="neutral" size="sm">OS</Badge>}
              </span>
              <span className="text-xs text-[var(--text-tertiary)] uppercase tracking-widest font-medium leading-none truncate">
                {tenant?.brand_name ? (brandFooterText || 'Workspace') : 'BusinessOS'}
              </span>
            </div>
          </Link>
          <button
            type="button"
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden p-1.5 rounded-lg hover:bg-white/5 text-[var(--text-tertiary)]"
            aria-label="Close navigation"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Nav */}
        <nav className="flex-1 overflow-y-auto p-3 space-y-5">
          {authorizedGroups.map((group) => (
            <div key={group.id} className="space-y-1">
              {/* Group label — sentence case, 11px, medium weight */}
              <div className="px-3 text-[11px] font-medium text-[var(--text-tertiary)] mb-1.5 select-none">
                {group.title}
              </div>
              <div className="space-y-0.5">
                {group.items.map((item) => (
                  <TreeItem
                    key={item.id}
                    item={item}
                    currentPath={currentPath}
                    onNavigate={() => setSidebarOpen(false)}
                  />
                ))}
              </div>
            </div>
          ))}
        </nav>

        {/* User footer */}
        <div className="p-3 border-t border-[var(--border-subtle)] bg-[var(--surface-2)] flex-shrink-0">
          <div className="flex items-center justify-between gap-2">
            <Link href="/profile" className="flex items-center gap-2.5 min-w-0 group">
              <div className="w-7 h-7 rounded-full bg-[var(--surface-3)] border border-[var(--border-medium)] flex items-center justify-center text-[var(--text-secondary)] text-xs font-bold flex-shrink-0">
                {user?.name?.charAt(0)?.toUpperCase() || 'U'}
              </div>
              <div className="truncate">
                <div className="text-[13px] font-medium truncate text-[var(--text-primary)] leading-tight">
                  {user?.name || 'User'}
                </div>
                <div className="text-[11px] text-[var(--text-tertiary)] truncate leading-tight">
                  {user?.email || ''}
                </div>
              </div>
            </Link>
            <button
              type="button"
              onClick={handleLogout}
              className="p-1.5 rounded-lg text-[var(--text-tertiary)] hover:text-rose-400 hover:bg-rose-500/10 spring-transition"
              aria-label="Sign out"
            >
              <LogOut className="w-4 h-4" />
            </button>
          </div>
        </div>
      </aside>

      {/* ── Main content ─────────────────────────────────────────────── */}
      <div className="lg:pl-64 flex flex-col min-h-screen">
        {/* Header */}
        <header className="sticky top-0 z-30 h-14 glass-1 border-b border-[var(--border-subtle)] px-3 sm:px-5 flex items-center justify-between gap-3">
          {/* Left: mobile toggle + workspace switcher */}
          <div className="flex items-center gap-2 min-w-0">
            <button
              type="button"
              onClick={() => setSidebarOpen(true)}
              className="lg:hidden p-2 rounded-lg bg-[var(--surface-2)] border border-[var(--border-subtle)] text-[var(--text-secondary)]"
              aria-label="Open navigation"
            >
              <Menu className="w-4 h-4" />
            </button>
            <WorkspaceSwitcher />
          </div>

          {/* Center: search */}
          <div className="flex-1 max-w-sm hidden sm:block">
            <button
              type="button"
              onClick={() => setCommandPaletteOpen(true)}
              className="w-full flex items-center justify-between px-3 py-1.5 rounded-lg bg-[var(--surface-2)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)] text-xs text-[var(--text-tertiary)] spring-transition"
            >
              <div className="flex items-center gap-2">
                <Search className="w-3.5 h-3.5" />
                <span>Search anything...</span>
              </div>
              <kbd className="text-xs font-mono px-1.5 py-0.5 rounded bg-white/10 text-[var(--text-tertiary)]">
                ⌘K
              </kbd>
            </button>
          </div>

          {/* Right: tools + MrFox */}
          <div className="flex flex-shrink-0 items-center gap-1.5 sm:gap-2">
            <div className="hidden min-[430px]:block">
              <ThemeSwitcher />
            </div>
            <NotificationCenter />
            {/* MrFox — the only purple element in the header */}
            <button
              type="button"
              onClick={() => setMrFoxOpen(true)}
              className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-[var(--brand-primary)]/10 hover:bg-[var(--brand-primary)]/20 text-[var(--fox-purple)] border border-[var(--brand-primary)]/25 text-xs font-medium spring-transition"
            >
              <Sparkles className="w-3.5 h-3.5" />
              <span className="hidden md:inline">Mr. Fox</span>
            </button>
          </div>
        </header>

        {/* Breadcrumbs */}
        {breadcrumbs?.length ? (
          <div className="px-4 sm:px-6 py-2 border-b border-[var(--border-subtle)] bg-[var(--surface-1)] flex items-center gap-1.5 text-xs text-[var(--text-tertiary)] overflow-x-auto">
            <Link href="/dashboard" className="hover:text-[var(--text-primary)] spring-transition">
              Home
            </Link>
            {breadcrumbs.map((crumb, idx) => (
              <React.Fragment key={`${crumb.label}-${idx}`}>
                <ChevronRight className="w-3 h-3 flex-shrink-0" />
                {crumb.href ? (
                  <Link href={crumb.href} className="hover:text-[var(--text-primary)] whitespace-nowrap">
                    {crumb.label}
                  </Link>
                ) : (
                  <span className="font-medium text-[var(--text-primary)] whitespace-nowrap">
                    {crumb.label}
                  </span>
                )}
              </React.Fragment>
            ))}
          </div>
        ) : null}

        {/* Flash messages */}
        {flash?.success && (
          <div className="mx-4 sm:mx-6 mt-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center gap-2">
            <Check className="w-4 h-4 flex-shrink-0" />
            {flash.success}
          </div>
        )}
        {flash?.error && (
          <div className="mx-4 sm:mx-6 mt-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-center gap-2">
            <X className="w-4 h-4 flex-shrink-0" />
            {flash.error}
          </div>
        )}

        {/* Page content */}
        <main className="flex-1 p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto">
          {children}
        </main>
      </div>

      {/* Floating elements */}
      <MrFoxLauncher onClick={() => setMrFoxOpen(true)} unreadCount={0} />
      <MrFoxPanel
        isOpen={mrFoxOpen}
        onClose={() => setMrFoxOpen(false)}
        contextPage={title || 'Workspace'}
      />
      <CommandPalette
        isOpen={commandPaletteOpen}
        onClose={() => setCommandPaletteOpen(false)}
        onOpenMrFox={() => setMrFoxOpen(true)}
      />
    </div>
  );
}
