import React from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Printer, Edit, DollarSign, Building } from 'lucide-react';

export default function SalesInvoiceShow() {
  const { invoice } = usePage<any>().props;

  return (
    <AppShell title={`Invoice #${invoice?.invoice_id || invoice?.id}`}>
      <div className="max-w-4xl mx-auto space-y-6">
        <SectionHeader
          title={`Sales Invoice #${invoice?.invoice_id || invoice?.id}`}
          description={`Issued to ${invoice?.customer?.name || invoice?.customer_name || 'Client'}`}
          badge={<StatusBadge status={invoice?.status || 'Draft'} />}
          actions={
            <div className="flex items-center gap-2">
              <Link href="/sales-invoices">
                <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                  Back
                </Button>
              </Link>
              <Link href={`/sales-invoices/${invoice?.id}/edit`}>
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
                Print Invoice
              </Button>
            </div>
          }
        />

        <Card level={1} className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/10">
            <div>
              <span className="text-xs uppercase font-bold text-gray-400">Invoice Reference</span>
              <h2 className="text-2xl font-bold text-white font-mono mt-0.5">#{invoice?.invoice_id || invoice?.id}</h2>
              <span className="text-xs text-gray-400">
                Issue Date: {invoice?.issue_date ? new Date(invoice.issue_date).toLocaleDateString() : 'Today'}
              </span>
            </div>
            <div className="text-left sm:text-right">
              <span className="text-xs uppercase font-bold text-gray-400">Total Valuation</span>
              <p className="text-3xl font-extrabold text-violet-400 tabular-nums">
                ${parseFloat(invoice?.total_amount || 0).toFixed(2)}
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div>
              <span className="text-gray-400 block">Customer</span>
              <span className="text-white font-semibold">{invoice?.customer?.name ?? (invoice?.customer_id ? `Customer #${invoice.customer_id}` : '—')}</span>
            </div>
            <div>
              <span className="text-gray-400 block">Fulfillment Hub</span>
              <span className="text-white font-semibold">{invoice?.warehouse?.name ?? '—'}</span>
            </div>
            <div>
              <span className="text-gray-400 block">Due Date</span>
              <span className="text-white font-semibold">
                {invoice?.due_date ? new Date(invoice.due_date).toLocaleDateString() : 'Net 30'}
              </span>
            </div>
            <div>
              <span className="text-gray-400 block">Payment Status</span>
              <StatusBadge status={invoice?.status || 'Draft'} />
            </div>
          </div>

          {/* Items Table */}
          <div className="pt-4 border-t border-white/10">
            <h4 className="text-xs uppercase font-bold text-gray-400 mb-3">Itemized Breakdown</h4>
            <div className="rounded-xl border border-white/10 overflow-hidden">
              <table className="w-full text-left text-xs">
                <thead className="bg-white/[0.03] text-gray-400 uppercase border-b border-white/10">
                  <tr>
                    <th className="py-2.5 px-4">Description</th>
                    <th className="py-2.5 px-4 text-right">Quantity</th>
                    <th className="py-2.5 px-4 text-right">Price</th>
                    <th className="py-2.5 px-4 text-right">Total</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/[0.06] text-gray-200">
                  {invoice?.items && invoice.items.length > 0 ? (
                    invoice.items.map((it: any, i: number) => (
                      <tr key={i}>
                        <td className="py-3 px-4 font-medium text-white">{it.description || it.product_name}</td>
                        <td className="py-3 px-4 text-right tabular-nums">{it.quantity || 1}</td>
                        <td className="py-3 px-4 text-right tabular-nums">${parseFloat(it.unit_price || 0).toFixed(2)}</td>
                        <td className="py-3 px-4 text-right font-bold text-white tabular-nums">
                          ${((it.quantity || 1) * (it.unit_price || 0)).toFixed(2)}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td className="py-3 px-4 font-medium text-white">Commercial Line Services</td>
                      <td className="py-3 px-4 text-right tabular-nums">1</td>
                      <td className="py-3 px-4 text-right tabular-nums">${parseFloat(invoice?.total_amount || 0).toFixed(2)}</td>
                      <td className="py-3 px-4 text-right font-bold text-white tabular-nums">
                        ${parseFloat(invoice?.total_amount || 0).toFixed(2)}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </Card>
      </div>
    </AppShell>
  );
}
