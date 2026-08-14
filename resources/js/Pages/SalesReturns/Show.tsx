import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Printer } from 'lucide-react';

export default function SalesReturnShow() {
  const { salesReturn } = usePage<any>().props;

  return (
    <AppShell title={`Credit Note #${salesReturn?.return_id || salesReturn?.id}`}>
      <div className="max-w-3xl mx-auto space-y-6">
        <SectionHeader
          title={`Credit Note #${salesReturn?.return_id || salesReturn?.id}`}
          description={`Customer: ${salesReturn?.customer?.name || salesReturn?.customer_id || 'Client'}`}
          badge={<StatusBadge status={salesReturn?.status || 'Processed'} />}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/sales-returns">
                <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                  Back
                </Button>
              </Link>
              <Button
                variant="primary"
                size="sm"
                icon={<Printer className="w-4 h-4" />}
                onClick={() => window.print()}
              >
                Print Credit Note
              </Button>
            </div>
          }
        />

        <Card level={1} className="space-y-6">
          <div className="flex items-center justify-between pb-4 border-b border-white/10">
            <div>
              <span className="text-xs uppercase font-bold text-gray-400">Credit Reference</span>
              <h2 className="text-2xl font-bold text-white font-mono mt-0.5">#{salesReturn?.return_id || salesReturn?.id}</h2>
            </div>
            <div className="text-right">
              <span className="text-xs uppercase font-bold text-gray-400">Credit Valuation</span>
              <p className="text-2xl font-bold text-amber-400 tabular-nums">
                ${parseFloat(salesReturn?.total_amount || 0).toFixed(2)}
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4 text-xs">
            <div>
              <span className="text-gray-400 block">Customer</span>
              <span className="text-white font-semibold">{salesReturn?.customer?.name || salesReturn?.customer_id || 'Client Corp'}</span>
            </div>
            <div>
              <span className="text-gray-400 block">Linked Invoice</span>
              <span className="text-white font-semibold">#{salesReturn?.sales_invoice_id || '—'}</span>
            </div>
          </div>

          {salesReturn?.reason && (
            <div className="pt-4 border-t border-white/10 space-y-1 text-xs">
              <span className="text-gray-400 font-semibold">Reason for Credit:</span>
              <p className="text-gray-200">{salesReturn.reason}</p>
            </div>
          )}
        </Card>
      </div>
    </AppShell>
  );
}
