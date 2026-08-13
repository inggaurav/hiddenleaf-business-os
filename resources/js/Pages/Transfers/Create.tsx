import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save, ArrowRightLeft } from 'lucide-react';

export default function TransferCreate() {
  const { warehouses = [] } = usePage<any>().props;

  const { data, setData, post, processing, errors } = useForm({
    from_warehouse_id: warehouses[0]?.id || '',
    to_warehouse_id: warehouses[1]?.id || warehouses[0]?.id || '',
    product_id: 1,
    quantity: 10,
    date: new Date().toISOString().split('T')[0],
    notes: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/transfers');
  };

  return (
    <AppShell title="Transfer Stock">
      <div className="max-w-2xl mx-auto space-y-6">
        <SectionHeader
          title="Initiate Stock Movement"
          description="Transfer physical stock units between source and destination warehouses."
          actions={
            <Link href="/transfers">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Transfers
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Select
                label="Origin Facility (From)"
                value={data.from_warehouse_id}
                onChange={(e) => setData('from_warehouse_id', e.target.value)}
                required
              >
                {warehouses.map((w: any) => (
                  <option key={w.id} value={w.id}>
                    {w.name}
                  </option>
                ))}
              </Select>

              <Select
                label="Destination Facility (To)"
                value={data.to_warehouse_id}
                onChange={(e) => setData('to_warehouse_id', e.target.value)}
                required
              >
                {warehouses.map((w: any) => (
                  <option key={w.id} value={w.id}>
                    {w.name}
                  </option>
                ))}
              </Select>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Transfer Quantity (Units)"
                type="number"
                min={1}
                value={data.quantity}
                onChange={(e) => setData('quantity', parseInt(e.target.value) || 1)}
                error={errors.quantity}
                required
              />
              <Input
                label="Transfer Date"
                type="date"
                value={data.date}
                onChange={(e) => setData('date', e.target.value)}
                required
              />
            </div>

            <Textarea
              label="Logistics & Dispatch Notes"
              placeholder="Carrier details, tracking numbers, special transit handling..."
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
            />

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/transfers">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<ArrowRightLeft className="w-4 h-4" />}>
                Dispatch Transfer
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
