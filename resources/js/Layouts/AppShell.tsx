import React, { useState, useEffect } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
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
  Sparkles, 
  ChevronDown, 
  Bell, 
  UserCheck, 
  Tag, 
  Globe, 
  Mail, 
  Sliders, 
  Bot,
  Package,
  Activity,
  ArrowRightLeft
} from 'lucide-react';
import { CommandPalette } from '../Components/Navigation/CommandPalette';
import { MrFoxLauncher } from '../Components/MrFox/MrFoxLauncher';
import { MrFoxPanel } from '../Components/MrFox/MrFoxPanel';
import { Badge } from '../Components/UI/Badge';
import { Button } from '../Components/UI/Button';
import { IconButton } from '../Components/UI/IconButton';

interface AppShellProps {
  children: React.ReactNode;
  title?: string;
}

export default function AppShell({ children, title }: AppShellProps) {
  const page = usePage<any>();
  const { auth, tenant, flash } = page.props;

  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [commandPaletteOpen, setCommandPaletteOpen] = useState(false);
  const [mrFoxOpen, setMrFoxOpen] = useState(false);
  const [mrFoxPrompt, setMrFoxPrompt] = useState('');
  const [workspaceDropdownOpen, setWorkspaceDropdownOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);

  // Global Keybindings: Cmd+K / Ctrl+K for Search, Cmd+J / Ctrl+J for Mr Fox
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        setCommandPaletteOpen((prev) => !prev);
      }
      if ((e.metaKey || e.ctrlKey) && e.key === 'j') {
        e.preventDefault();
        setMrFoxOpen((prev) => !prev);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  const navigationGroups = [
    {
      group: 'Overview',
      items: [
        { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
      ],
    },
    {
      group: 'Sales & Invoicing',
      items: [
        { name: 'Sales Invoices', href: '/sales-invoices', icon: FileText },
        { name: 'Sales Proposals', href: '/sales-proposals', icon: FileText },
        { name: 'Sales Returns', href: '/sales-returns', icon: ArrowRightLeft },
        { name: 'Orders', href: '/orders', icon: DollarSign },
      ],
    },
    {
      group: 'Operations',
      items: [
        { name: 'Warehouses', href: '/warehouses', icon: Building },
        { name: 'Stock Transfers', href: '/transfers', icon: ArrowRightLeft },
        { name: 'Purchase Invoices', href: '/purchase-invoices', icon: FileText },
        { name: 'Purchase Returns', href: '/purchase-returns', icon: ArrowRightLeft },
      ],
    },
    {
      group: 'Finance & SaaS',
      items: [
        { name: 'Plans & Pricing', href: '/plans', icon: CreditCard },
        { name: 'Coupons', href: '/coupons', icon: Tag },
        { name: 'Bank Transfers', href: '/bank-transfer', icon: DollarSign },
      ],
    },
    {
      group: 'Communications',
      items: [
        { name: 'Helpdesk Tickets', href: '/helpdesk-tickets', icon: Headphones },
        { name: 'Media Library', href: '/media/page', icon: Image },
        { name: 'Team Messenger', href: '/chats', icon: MessageSquare },
        { name: 'AI Assistant', href: '/ai-agent/chat', icon: Bot },
      ],
    },
    {
      group: 'Administration',
      items: [
        { name: 'Workspaces', href: '/workspaces', icon: Layers },
        { name: 'Roles & RBAC', href: '/roles', icon: ShieldCheck },
        { name: 'Team & Users', href: '/users', icon: Users },
        { name: 'Login History', href: '/users-login-history', icon: Activity },
        { name: 'Module Catalog', href: '/modules', icon: Package },
        { name: 'Languages', href: '/languages', icon: Globe },
        { name: 'Email Templates', href: '/email-templates', icon: Mail },
        { name: 'Settings', href: '/settings', icon: Sliders },
      ],
    },
  ];

  const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

  return (
    <div className="min-h-screen bg-[#060709] text-gray-100 flex flex-col antialiased">
      {/* Impersonation Banner */}
      {auth?.impersonator && (
        <div className="bg-gradient-to-r from-amber-600 via-purple-600 to-amber-600 text-black px-4 py-2 text-xs font-bold flex items-center justify-between shadow-lg sticky top-0 z-50">
          <div className="flex items-center gap-2">
            <UserCheck className="w-4 h-4" />
            <span>IMPERSONATION MODE: Currently impersonating <u>{auth?.user?.name}</u> ({auth?.user?.email})</span>
          </div>
          <form method="POST" action="/users/leave-impersonation">
            <Button type="submit" size="sm" variant="secondary" className="!bg-black !text-white !py-1 !text-[11px]">
              Return to Super Admin
            </Button>
          </form>
        </div>
      )}

      {/* Flash Messages */}
      {flash?.success && (
        <div className="fixed top-4 right-4 z-50 p-4 rounded-xl glass-2 border border-emerald-500/30 text-emerald-200 text-xs font-medium shadow-2xl flex items-center gap-3 animate-in slide-in-from-top duration-200">
          <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
          <span>{flash.success}</span>
        </div>
      )}
      {flash?.error && (
        <div className="fixed top-4 right-4 z-50 p-4 rounded-xl glass-2 border border-rose-500/30 text-rose-200 text-xs font-medium shadow-2xl flex items-center gap-3 animate-in slide-in-from-top duration-200">
          <span className="w-2 h-2 rounded-full bg-rose-400 animate-pulse" />
          <span>{flash.error}</span>
        </div>
      )}

      <div className="flex-1 flex overflow-hidden">
        {/* Mobile Sidebar Backdrop */}
        {sidebarOpen && (
          <div 
            className="fixed inset-0 z-30 bg-black/80 backdrop-blur-sm md:hidden"
            onClick={() => setSidebarOpen(false)}
          />
        )}

        {/* Sidebar */}
        <aside className={`
          fixed inset-y-0 left-0 z-40 w-64 glass-1 border-r border-white/10 flex flex-col justify-between transform transition-transform duration-200 ease-in-out md:relative md:translate-x-0
          ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
        `}>
          <div>
            {/* App Brand Header */}
            <div className="h-16 px-5 border-b border-white/10 flex items-center justify-between">
              <Link href="/dashboard" className="flex items-center gap-2.5 group">
                <div className="w-8 h-8 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center text-white font-black text-sm shadow-md group-hover:scale-105 spring-transition">
                  HL
                </div>
                <div>
                  <span className="text-sm font-bold tracking-tight text-white block">HiddenLeaf</span>
                  <span className="text-[10px] font-medium text-violet-400 block -mt-1">BusinessOS 2026</span>
                </div>
              </Link>
              <IconButton label="Close Sidebar" size="sm" className="md:hidden" onClick={() => setSidebarOpen(false)}>
                <X className="w-4 h-4" />
              </IconButton>
            </div>

            {/* Navigation Groups */}
            <div className="overflow-y-auto max-h-[calc(100vh-10rem)] p-3 space-y-5">
              {navigationGroups.map((grp) => (
                <div key={grp.group} className="space-y-1">
                  <div className="px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400 select-none">
                    {grp.group}
                  </div>
                  <nav className="space-y-0.5">
                    {grp.items.map((item) => {
                      const isActive = currentPath === item.href || currentPath.startsWith(`${item.href}/`);
                      return (
                        <Link
                          key={item.name}
                          href={item.href}
                          className={`
                            group flex items-center px-3 py-2 text-xs font-medium rounded-lg spring-transition select-none
                            ${isActive 
                              ? 'bg-violet-600/20 text-violet-200 border border-violet-500/30 shadow-sm font-semibold' 
                              : 'text-gray-400 hover:bg-white/[0.04] hover:text-gray-200'}
                          `}
                        >
                          <item.icon className={`mr-2.5 flex-shrink-0 h-4 w-4 ${isActive ? 'text-violet-400' : 'text-gray-500 group-hover:text-gray-300'}`} />
                          <span className="truncate">{item.name}</span>
                        </Link>
                      );
                    })}
                  </nav>
                </div>
              ))}
            </div>
          </div>

          {/* Sidebar Bottom Workspace / Profile Widget */}
          <div className="p-3 border-t border-white/10 bg-black/20">
            {tenant?.workspace_title && (
              <div className="px-3 py-2 rounded-lg bg-white/[0.03] border border-white/10 mb-2">
                <span className="text-[10px] uppercase font-bold text-gray-500 block">Workspace</span>
                <span className="text-xs font-semibold text-violet-300 truncate block">{tenant.workspace_title}</span>
              </div>
            )}
            <div className="flex items-center justify-between px-2 pt-1">
              <div className="flex items-center gap-2 overflow-hidden">
                <div className="w-7 h-7 rounded-full bg-violet-700 text-white font-bold text-xs flex items-center justify-center flex-shrink-0">
                  {auth?.user?.name?.charAt(0) || 'U'}
                </div>
                <div className="truncate text-left">
                  <p className="text-xs font-medium text-gray-200 truncate">{auth?.user?.name || 'User'}</p>
                  <p className="text-[10px] text-gray-500 capitalize truncate">{auth?.user?.role || 'Member'}</p>
                </div>
              </div>
              <Link href="/logout" method="post" as="button" title="Sign out" className="text-gray-500 hover:text-rose-400 p-1.5 rounded-md hover:bg-white/[0.05] spring-transition">
                <LogOut className="w-4 h-4" />
              </Link>
            </div>
          </div>
        </aside>

        {/* Main View Area */}
        <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
          {/* Topbar */}
          <header className="h-16 glass-1 border-b border-white/10 px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-4 z-20">
            <div className="flex items-center gap-3">
              <IconButton 
                label="Toggle menu" 
                size="sm" 
                className="md:hidden" 
                onClick={() => setSidebarOpen(true)}
              >
                <Menu className="w-5 h-5" />
              </IconButton>

              {/* Breadcrumb / Title */}
              <div className="flex items-center gap-2 text-xs">
                <span className="text-gray-500 hidden sm:inline">HiddenLeaf</span>
                <span className="text-gray-600 hidden sm:inline">/</span>
                <span className="font-semibold text-white truncate max-w-[200px] sm:max-w-none">
                  {title || 'Overview'}
                </span>
              </div>
            </div>

            {/* Center / Right Spotlight Bar & Actions */}
            <div className="flex items-center gap-2.5">
              {/* Spotlight Command Palette Trigger */}
              <button
                onClick={() => setCommandPaletteOpen(true)}
                className="hidden sm:flex items-center gap-3 px-3 py-1.5 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 text-gray-400 hover:text-white spring-transition text-xs cursor-pointer shadow-inner"
              >
                <Search className="w-3.5 h-3.5" />
                <span>Search pages, commands or ask Mr Fox...</span>
                <kbd className="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-white/10 text-gray-300">⌘K</kbd>
              </button>

              <IconButton
                label="Search"
                size="sm"
                className="sm:hidden"
                onClick={() => setCommandPaletteOpen(true)}
              >
                <Search className="w-4 h-4" />
              </IconButton>

              {/* Mr Fox Quick Trigger */}
              <Button
                variant="intelligence"
                size="sm"
                onClick={() => setMrFoxOpen(true)}
                icon={<Sparkles className="w-3.5 h-3.5" />}
                className="hidden md:inline-flex text-xs"
              >
                Mr Fox
              </Button>

              {/* Workspace Badge */}
              {tenant?.workspace_title && (
                <Badge variant="purple" size="sm" className="hidden lg:inline-flex">
                  {tenant.workspace_title}
                </Badge>
              )}
            </div>
          </header>

          {/* Master Content Area */}
          <main className="flex-1 overflow-y-auto bg-[#060709] p-4 sm:p-6 lg:p-8">
            <div className="max-w-7xl mx-auto space-y-6">
              {children}
            </div>
          </main>
        </div>
      </div>

      {/* Global Spotlight Command Palette */}
      <CommandPalette
        isOpen={commandPaletteOpen}
        onClose={() => setCommandPaletteOpen(false)}
        onOpenMrFox={(prompt) => {
          setMrFoxPrompt(prompt || '');
          setMrFoxOpen(true);
        }}
      />

      {/* Mr Fox Intelligence Launcher & Floating Surface */}
      <MrFoxLauncher
        state="idle"
        onClick={() => setMrFoxOpen(true)}
        unreadInsightsCount={1}
      />

      <MrFoxPanel
        isOpen={mrFoxOpen}
        onClose={() => setMrFoxOpen(false)}
        initialPrompt={mrFoxPrompt}
        contextPage={title || 'Dashboard'}
      />
    </div>
  );
}
