import React, { useEffect, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';

type Counter = { id: number; name: string; counter_number: string; warehouse_id: number | null };
type Warehouse = { id: number; name: string };
type Discount = { id: number; name: string; type: string; value: string };
type Product = { id: number; name: string; sku?: string; barcode?: string; type: 'product' | 'service'; sale_price: string; tax_rate: string; stock: string };
type CartLine = Product & { quantity: string };

export default function Create({ counters, warehouses, discounts, checkout_token }: { counters: Counter[]; warehouses: Warehouse[]; discounts: Discount[]; checkout_token: string }) {
  const [counterId, setCounterId] = useState(String(counters[0]?.id ?? ''));
  const selectedCounter = counters.find((counter) => String(counter.id) === counterId);
  const [warehouseId, setWarehouseId] = useState(String(selectedCounter?.warehouse_id ?? warehouses[0]?.id ?? ''));
  const [query, setQuery] = useState('');
  const [products, setProducts] = useState<Product[]>([]);
  const [cart, setCart] = useState<CartLine[]>([]);
  const [discountId, setDiscountId] = useState('');
  const [paymentMethod, setPaymentMethod] = useState<'cash' | 'card' | 'bank'>('cash');
  const [paymentReference, setPaymentReference] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (selectedCounter?.warehouse_id) setWarehouseId(String(selectedCounter.warehouse_id));
  }, [counterId]);

  useEffect(() => {
    if (!warehouseId) return;
    const timer = window.setTimeout(async () => {
      const params = new URLSearchParams({ warehouse_id: warehouseId });
      if (query.trim()) params.set('query', query.trim());
      const response = await fetch(`/pos/products?${params.toString()}`, { headers: { Accept: 'application/json' } });
      if (response.ok) setProducts(await response.json());
    }, 180);
    return () => window.clearTimeout(timer);
  }, [warehouseId, query]);

  const add = (product: Product) => setCart((current) => {
    const existing = current.find((line) => line.id === product.id);
    return existing
      ? current.map((line) => line.id === product.id ? { ...line, quantity: (Number(line.quantity) + 1).toFixed(4) } : line)
      : [...current, { ...product, quantity: '1.0000' }];
  });

  const subtotal = useMemo(() => cart.reduce((sum, line) => sum + Number(line.sale_price) * Number(line.quantity || 0), 0), [cart]);

  const checkout = () => {
    if (!counterId || !warehouseId || cart.length === 0 || submitting) return;
    setSubmitting(true);
    router.post('/pos/store', {
      billing_counter_id: Number(counterId),
      warehouse_id: Number(warehouseId),
      discount_id: discountId ? Number(discountId) : null,
      payment_method: paymentMethod,
      payment_reference: paymentReference || null,
      idempotency_key: checkout_token,
      items: cart.map((line) => ({ product_id: line.id, quantity: line.quantity })),
    }, { preserveScroll: true, onFinish: () => setSubmitting(false) });
  };

  return <AppShell><Head title="POS Checkout"/><div className="mx-auto max-w-7xl space-y-6 pb-12">
    <div><h1 className="text-2xl font-bold text-[var(--text-primary)]">Point of Sale</h1><p className="mt-1 text-xs text-[var(--text-secondary)]">Fast checkout with tenant-scoped inventory, idempotent submission and accounting posting.</p></div>
    <Card level={0} className="p-4"><div className="grid gap-3 md:grid-cols-4">
      <select className="input-shell" value={counterId} onChange={(e) => setCounterId(e.target.value)}><option value="">Billing counter</option>{counters.map((c) => <option key={c.id} value={c.id}>{c.counter_number} · {c.name}</option>)}</select>
      <select className="input-shell" value={warehouseId} onChange={(e) => setWarehouseId(e.target.value)} disabled={Boolean(selectedCounter?.warehouse_id)}><option value="">Warehouse</option>{warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}</select>
      <select className="input-shell" value={discountId} onChange={(e) => setDiscountId(e.target.value)}><option value="">No discount</option>{discounts.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}</select>
      <input className="input-shell" autoFocus value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Scan barcode, SKU or search product" />
    </div></Card>
    <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
      <Card level={0} className="p-4"><div className="mb-3 text-sm font-semibold text-[var(--text-primary)]">Catalog</div><div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">{products.map((p) => <button key={p.id} type="button" onClick={() => add(p)} className="rounded-xl border border-[var(--border-subtle)] p-3 text-left transition hover:bg-white/5"><div className="font-medium text-[var(--text-primary)]">{p.name}</div><div className="mt-1 text-xs text-[var(--text-secondary)]">{p.sku || 'No SKU'} · {p.type}</div><div className="mt-3 flex justify-between text-xs"><span>{Number(p.sale_price).toFixed(2)}</span><span>{p.type === 'product' ? `Stock ${p.stock}` : 'Non-stock'}</span></div></button>)}</div></Card>
      <Card level={0} className="p-4"><div className="mb-3 text-sm font-semibold text-[var(--text-primary)]">Current sale</div><div className="space-y-2">{cart.length === 0 && <div className="py-8 text-center text-sm text-[var(--text-secondary)]">Add a product or service to begin.</div>}{cart.map((line) => <div key={line.id} className="flex items-center gap-3 rounded-xl border border-[var(--border-subtle)] p-3"><div className="min-w-0 flex-1"><div className="truncate text-sm text-[var(--text-primary)]">{line.name}</div><div className="text-xs text-[var(--text-secondary)]">{Number(line.sale_price).toFixed(2)} each</div></div><input type="number" min="0.0001" step="0.0001" value={line.quantity} onChange={(e) => setCart((current) => current.map((item) => item.id === line.id ? { ...item, quantity: e.target.value } : item))} className="input-shell w-24"/><button type="button" className="text-xs text-red-400" onClick={() => setCart((current) => current.filter((item) => item.id !== line.id))}>Remove</button></div>)}</div>
        <div className="mt-5 space-y-3 border-t border-[var(--border-subtle)] pt-4"><div className="flex justify-between text-sm"><span className="text-[var(--text-secondary)]">Pre-tax / pre-discount estimate</span><strong>{subtotal.toFixed(2)}</strong></div><select className="input-shell w-full" value={paymentMethod} onChange={(e) => setPaymentMethod(e.target.value as 'cash' | 'card' | 'bank')}><option value="cash">Cash</option><option value="card">Card</option><option value="bank">Bank / digital</option></select>{paymentMethod !== 'cash' && <input className="input-shell w-full" value={paymentReference} onChange={(e) => setPaymentReference(e.target.value)} placeholder="Payment reference"/>}<p className="text-[11px] text-[var(--text-secondary)]">Final price, tax, discount and stock availability are recalculated by the server.</p><Button type="button" variant="primary" className="w-full" disabled={submitting || cart.length === 0 || !counterId || !warehouseId} onClick={checkout}>{submitting ? 'Processing…' : 'Complete checkout'}</Button></div>
      </Card>
    </div>
  </div></AppShell>;
}
