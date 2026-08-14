import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Printer, Edit, Building } from 'lucide-react';

export default function PurchaseInvoiceShow() {
  const { invoice } = usePage<any>().props;

  return (
    <AppShell title={`Purchase #${invoice?.invoice_id || invoice?.id}`}>
      <div className="max-w-4xl mx-auto space-y-6">
        <SectionHeader
          title={`Vendor Purchase Invoice #${invoice?.invoice_id || invoice?.id}`}
          description={`Issued by ${invoice?.vendor?.name || invoice?.vendor_id || 'Supplier'}`}
          badge={<StatusBadge status={invoice?.status || 'Draft'} />}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/purchase-invoices">
                <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                  Back
                </Button>
              </Link>
              <Link href={`/purchase-invoices/${invoice?.id}/edit`}>
                <Button variant="secondary" size="sm" icon={<Edit className="w-4 h-4" />}>
                  Edit
                </Button>
              </Link>
              <Button
                variant="primary"
                size="sm"
                icon={<Printer className="w-4 h-4" />}
                onClick={() => window.print()}
              >
                Print
              </Button>
            </div>
          }
        />

        <Card level={1} className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/10">
            <div>
              <span className="text-xs uppercase font-bold text-gray-400">Purchase Reference</span>
              <h2 className="text-2xl font-bold text-white font-mono mt-0.5">#{invoice?.invoice_id || invoice?.id}</h2>
              <span className="text-xs text-gray-400">
                Invoice Date: {invoice?.issue_date ? new Date(invoice.issue_date).toLocaleDateString() : 'Today'}
              </span>
            </div>
            <div className="text-left sm:text-right">
              <span className="text-xs uppercase font-bold text-gray-400">Payable Amount</span>
              <p className="text-3xl font-extrabold text-emerald-400 tabular-nums">
                ${parseFloat(invoice?.total_amount || 0).toFixed(2)}
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
            <div>
              <span className="text-gray-400 block">Vendor</span>
              <span className="text-white font-semibold">{invoice?.vendor?.name || invoice?.vendor_id || 'Global Supplier'}</span>
            </div>
            <div>
              <span className="text-gray-400 block">Receiving Facility</span>
              <span className="text-white font-semibold">{invoice?.warehouse?.name ?? '—'}</span>
            </div>
            <div>
              <span className="text-gray-400 block">Payment Status</span>
              <StatusBadge status={invoice?.status || 'Draft'} />
            </div>
          </div>
        </Card>
      </div>
    </AppShell>
  );
}
