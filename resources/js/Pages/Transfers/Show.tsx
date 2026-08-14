import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, ArrowRightLeft, Building, Calendar, Printer } from 'lucide-react';

export default function TransferShow() {
  const { transfer } = usePage<any>().props;

  return (
    <AppShell title={`Transfer #${transfer?.transfer_id || transfer?.id}`}>
      <div className="max-w-3xl mx-auto space-y-6">
        <SectionHeader
          title={`Stock Transfer #${transfer?.transfer_id || transfer?.id}`}
          description={`Logged on ${transfer?.date ? new Date(transfer.date).toLocaleDateString() : 'Today'}`}
          badge={<StatusBadge status={transfer?.status || 'Completed'} />}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/transfers">
                <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                  Back to Transfers
                </Button>
              </Link>
              <Button
                variant="secondary"
                size="sm"
                icon={<Printer className="w-4 h-4" />}
                onClick={() => window.print()}
              >
                Print Manifest
              </Button>
            </div>
          }
        />

        <Card level={1} className="space-y-6">
          <div className="flex items-center justify-between p-4 rounded-xl bg-white/[0.02] border border-white/10">
            <div className="text-center sm:text-left">
              <span className="text-xs text-gray-400 block">Origin</span>
              <span className="font-bold text-white text-base">
                {transfer?.from_warehouse?.name ?? '—'}
              </span>
            </div>

            <div className="p-3 rounded-full bg-violet-600/20 text-violet-300 border border-violet-500/30">
              <ArrowRightLeft className="w-5 h-5" />
            </div>

            <div className="text-center sm:text-right">
              <span className="text-xs text-gray-400 block">Destination</span>
              <span className="font-bold text-violet-300 text-base">
                {transfer?.to_warehouse?.name || 'Target Facility'}
              </span>
            </div>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
            <div>
              <span className="text-gray-400 block">Quantity Moved</span>
              <span className="text-lg font-bold text-white tabular-nums">{transfer?.quantity || 1} Units</span>
            </div>
            <div>
              <span className="text-gray-400 block">Transfer Date</span>
              <span className="text-white font-semibold">
                {transfer?.date ? new Date(transfer.date).toLocaleDateString() : 'Today'}
              </span>
            </div>
            <div>
              <span className="text-gray-400 block">Verification Status</span>
              <StatusBadge status={transfer?.status || 'Completed'} />
            </div>
          </div>

          {transfer?.notes && (
            <div className="pt-4 border-t border-white/10 space-y-1 text-xs">
              <span className="text-gray-400 font-semibold">Logistics Notes:</span>
              <p className="text-gray-200">{transfer.notes}</p>
            </div>
          )}
        </Card>
      </div>
    </AppShell>
  );
}
