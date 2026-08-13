import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import { Button } from '@ui/Button';

export default function Dashboard() {
  const { auth, tenant } = usePage<any>().props;

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-8">
      <div className="max-w-4xl mx-auto space-y-6">
        <header className="flex justify-between items-center bg-slate-900 border border-slate-800 rounded-xl p-6">
          <div>
            <h1 className="text-2xl font-bold">HiddenLeaf BusinessOS Dashboard</h1>
            <p className="text-sm text-slate-400 mt-1">Welcome back, {auth.user?.name}</p>
          </div>
          <Link href="/logout" method="post" as="button" className="text-xs text-rose-400 hover:underline">
            Log Out
          </Link>
        </header>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-emerald-400 mb-2">Active Workspace Context</h2>
            <p className="text-lg font-bold">{tenant?.workspace_title || 'Main Operations'}</p>
            <p className="text-xs text-slate-400 mt-1">Organization ID: #{tenant?.organization_id}</p>
          </div>

          <div className="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-emerald-400 mb-2">Quick Navigation</h2>
            <div className="flex space-x-3 mt-3">
              <Link href="/workspaces"><Button size="sm" variant="secondary">Workspaces</Button></Link>
              <Link href="/roles"><Button size="sm" variant="secondary">Roles & Permissions</Button></Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
