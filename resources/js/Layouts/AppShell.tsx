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
  return pathMatches(item.href, currentPath) || Boolean(item.children?.some((child) => containsActive(child, currentPath)));
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
          onClick={() => setOpen((value) => !value)}
          aria-expanded={open}
          className={`w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium spring-transition select-none ${
            active
              ? 'bg-purple-600/10 text-[var(--text-primary)] border border-purple-500/20'
              : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-white/[0.04] border border-transparent'
          }`}
          style={{ paddingLeft: `${12 + depth * 12}px` }}
        >
          <Icon className={`w-4 h-4 flex-shrink-0 ${active ? 'text-purple-400' : 'text-[var(--text-tertiary)]'}`} />
          <span className="truncate flex-1 text-left">{item.name}</span>
          {open ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
        </button>

        {open && (
          <div className="ml-4 pl-2 border-l border-[var(--border-subtle)] space-y-0.5">
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
      className={`flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium spring-transition group select-none ${
        exactActive
          ? 'bg-purple-600/20 text-[var(--text-primary)] font-semibold border border-purple-500/30'
          : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-white/[0.04] border border-transparent'
      }`}
      style={{ paddingLeft: `${12 + depth * 12}px` }}
    >
      <Icon className={`w-4 h-4 flex-shrink-0 ${exactActive ? 'text-purple-400' : 'text-[var(--text-tertiary)] group-hover:text-purple-300'}`} />
      <span className="truncate">{item.name}</span>
    </Link>
  );
}

export default function AppShell({ title, children, breadcrumbs }: AppShellProps) {
  const page = usePage<any>();
  const { auth, tenant, flash } = page.props;
  const currentPath = String(page.url || '/').split('?')[0];
  const user = auth?.user;
  const isSuperAdmin = Boolean(user?.is_super_admin);
  const userPermissions = user?.permissions || [];
  const enabledModules = tenant?.modules || [];

  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [commandPaletteOpen, setCommandPaletteOpen] = useState(false);
  const [mrFoxOpen, setMrFoxOpen] = useState(false);

  const authorizedGroups: NavigationGroup[] = useMemo(
    () => filterNavigation(ALL_NAVIGATION_GROUPS, user, isSuperAdmin, userPermissions, enabledModules),
    [user, isSuperAdmin, userPermissions, enabledModules]
  );

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setCommandPaletteOpen((value) => !value);
      } else if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'j') {
        e.preventDefault();
        setMrFoxOpen((value) => !value);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  const handleLogout = () => router.post('/logout');

  return (
    <div className="min-h-screen bg-[var(--bg-0)] text-[var(--text-primary)] font-sans antialiased selection:bg-purple-500/30">
      {sidebarOpen && (
        <div className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" onClick={() => setSidebarOpen(false)} />
      )}

      <aside
        aria-label="Main Navigation"
        className={`fixed top-0 bottom-0 left-0 z-40 w-72 glass-1 border-r border-[var(--border-subtle)] flex flex-col bg-[var(--surface-1)] spring-transition lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="h-16 flex items-center justify-between px-5 border-b border-[var(--border-subtle)] flex-shrink-0">
          <Link href="/dashboard" className="flex items-center gap-2.5 group" onClick={() => setSidebarOpen(false)}>
            <div className="w-8 h-8 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center shadow-md shadow-purple-500/20">
              <MrFoxMark size={20} />
            </div>
            <div className="flex flex-col">
              <span className="font-bold text-sm tracking-tight flex items-center gap-1.5">
                HiddenLeaf <Badge variant="purple" size="sm">OS</Badge>
              </span>
              <span className="text-[10px] text-[var(--text-tertiary)] uppercase tracking-wider font-semibold">BusinessOS</span>
            </div>
          </Link>
          <button type="button" onClick={() => setSidebarOpen(false)} className="lg:hidden p-1.5 rounded-lg hover:bg-white/5" aria-label="Close navigation">
            <X className="w-5 h-5" />
          </button>
        </div>

        <nav className="flex-1 overflow-y-auto p-3 space-y-5">
          {authorizedGroups.map((group) => (
            <div key={group.id} className="space-y-1">
              <div className="px-3 text-[10px] font-bold uppercase tracking-wider text-[var(--text-tertiary)]">{group.title}</div>
              <div className="space-y-0.5 mt-1">
                {group.items.map((item) => (
                  <TreeItem key={item.id} item={item} currentPath={currentPath} onNavigate={() => setSidebarOpen(false)} />
                ))}
              </div>
            </div>
          ))}
        </nav>

        <div className="p-3 border-t border-[var(--border-subtle)] bg-[var(--surface-2)] flex-shrink-0">
          <div className="flex items-center justify-between gap-2">
            <Link href="/profile" className="flex items-center gap-2.5 min-w-0 group">
              <div className="w-8 h-8 rounded-full bg-purple-900/40 border border-purple-500/30 flex items-center justify-center text-purple-300 text-xs font-bold flex-shrink-0">
                {user?.name?.charAt(0) || 'U'}
              </div>
              <div className="truncate">
                <div className="text-xs font-semibold truncate">{user?.name || 'User'}</div>
                <div className="text-[10px] text-[var(--text-tertiary)] truncate">{user?.email || ''}</div>
              </div>
            </Link>
            <button type="button" onClick={handleLogout} className="p-1.5 rounded-lg text-[var(--text-tertiary)] hover:text-rose-400 hover:bg-rose-500/10" aria-label="Sign Out">
              <LogOut className="w-4 h-4" />
            </button>
          </div>
        </div>
      </aside>

      <div className="lg:pl-72 flex flex-col min-h-screen">
        <header className="sticky top-0 z-30 h-16 glass-1 border-b border-[var(--border-subtle)] px-2 sm:px-6 flex items-center justify-between gap-2 sm:gap-4 bg-[var(--surface-1)]">
          <div className="flex min-w-0 items-center gap-2 sm:gap-3">
            <button type="button" onClick={() => setSidebarOpen(true)} className="lg:hidden p-2 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)]" aria-label="Open Navigation Sidebar">
              <Menu className="w-4 h-4" />
            </button>
            <WorkspaceSwitcher />
          </div>

          <div className="flex-1 max-w-md hidden sm:block">
            <button type="button" onClick={() => setCommandPaletteOpen(true)} className="w-full flex items-center justify-between px-3 py-1.5 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] hover:border-purple-500/50 text-xs text-[var(--text-tertiary)]">
              <div className="flex items-center gap-2"><Search className="w-3.5 h-3.5" /><span>Search modules, pages, actions...</span></div>
              <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-white/10">⌘K</kbd>
            </button>
          </div>

          <div className="flex flex-shrink-0 items-center gap-1 sm:gap-3">
            <div className="hidden min-[430px]:block"><ThemeSwitcher /></div>
            <NotificationCenter />
            <button type="button" onClick={() => setMrFoxOpen(true)} className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-purple-600/10 hover:bg-purple-600/20 text-purple-300 border border-purple-500/30 text-xs font-semibold">
              <Sparkles className="w-3.5 h-3.5 text-purple-400" /><span className="hidden md:inline">Mr Fox</span>
            </button>
          </div>
        </header>

        {breadcrumbs?.length ? (
          <div className="px-4 sm:px-6 py-2.5 border-b border-[var(--border-subtle)] bg-[var(--surface-1)] flex items-center gap-1.5 text-xs text-[var(--text-tertiary)] overflow-x-auto">
            <Link href="/dashboard" className="hover:text-[var(--text-primary)]">Home</Link>
            {breadcrumbs.map((crumb, idx) => (
              <React.Fragment key={`${crumb.label}-${idx}`}>
                <ChevronRight className="w-3 h-3 flex-shrink-0" />
                {crumb.href ? <Link href={crumb.href} className="hover:text-[var(--text-primary)] whitespace-nowrap">{crumb.label}</Link> : <span className="font-semibold text-[var(--text-primary)] whitespace-nowrap">{crumb.label}</span>}
              </React.Fragment>
            ))}
          </div>
        ) : null}

        {flash?.success && (
          <div className="mx-4 sm:mx-6 mt-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs flex items-center gap-2"><Check className="w-4 h-4" />{flash.success}</div>
        )}
        {flash?.error && (
          <div className="mx-4 sm:mx-6 mt-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-2"><X className="w-4 h-4" />{flash.error}</div>
        )}

        <main className="flex-1 p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto">{children}</main>
      </div>

      <MrFoxLauncher onClick={() => setMrFoxOpen(true)} unreadCount={0} />
      <MrFoxPanel isOpen={mrFoxOpen} onClose={() => setMrFoxOpen(false)} contextPage={title || 'Workspace'} />
      <CommandPalette isOpen={commandPaletteOpen} onClose={() => setCommandPaletteOpen(false)} onOpenMrFox={() => setMrFoxOpen(true)} />
    </div>
  );
}
