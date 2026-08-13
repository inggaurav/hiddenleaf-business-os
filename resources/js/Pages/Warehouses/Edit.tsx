import React from 'react';
import { useForm, Link, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save } from 'lucide-react';

export default function WarehouseEdit() {
  const { warehouse } = usePage<any>().props;

  const { data, setData, put, processing, errors } = useForm({
    name: warehouse?.name || '',
    code: warehouse?.code || '',
    address: warehouse?.address || '',
    city: warehouse?.city || '',
    country: warehouse?.country || '',
    phone: warehouse?.phone || '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/warehouses/${warehouse.id}`);
  };

  return (
    <AppShell title={`Edit Warehouse: ${warehouse?.name}`}>
      <div className="max-w-2xl mx-auto space-y-6">
        <SectionHeader
          title={`Edit ${warehouse?.name}`}
          description="Update warehouse location and facility contact information."
          actions={
            <Link href="/warehouses">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Warehouses
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit}>
          <Card level={0} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="Warehouse Name"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                error={errors.name}
                required
              />
              <Input
                label="Facility Code"
                value={data.code}
                onChange={(e) => setData('code', e.target.value)}
              />
            </div>

            <Textarea
              label="Physical Address"
              value={data.address}
              onChange={(e) => setData('address', e.target.value)}
            />

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Input
                label="City"
                value={data.city}
                onChange={(e) => setData('city', e.target.value)}
              />
              <Input
                label="Country"
                value={data.country}
                onChange={(e) => setData('country', e.target.value)}
              />
              <Input
                label="Phone Contact"
                value={data.phone}
                onChange={(e) => setData('phone', e.target.value)}
              />
            </div>

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/warehouses">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Save Changes
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
