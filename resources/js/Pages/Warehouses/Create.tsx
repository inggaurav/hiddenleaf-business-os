import React from 'react';
import { useForm, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Save } from 'lucide-react';

export default function WarehouseCreate() {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    code: '',
    address: '',
    city: '',
    country: '',
    phone: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/warehouses');
  };

  return (
    <AppShell title="Add Warehouse Facility">
      <div className="max-w-2xl mx-auto space-y-6">
        <SectionHeader
          title="Add Warehouse Facility"
          description="Register a new physical storage hub for stock management."
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
                placeholder="e.g. Central Distribution Hub"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                error={errors.name}
                required
              />
              <Input
                label="Facility Code"
                placeholder="e.g. HUB-01"
                value={data.code}
                onChange={(e) => setData('code', e.target.value)}
                error={errors.code}
              />
            </div>

            <Textarea
              label="Physical Address"
              placeholder="Street address, building / bay number..."
              value={data.address}
              onChange={(e) => setData('address', e.target.value)}
            />

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Input
                label="City"
                placeholder="City"
                value={data.city}
                onChange={(e) => setData('city', e.target.value)}
              />
              <Input
                label="Country"
                placeholder="Country"
                value={data.country}
                onChange={(e) => setData('country', e.target.value)}
              />
              <Input
                label="Phone Contact"
                placeholder="+1 555-0199"
                value={data.phone}
                onChange={(e) => setData('phone', e.target.value)}
              />
            </div>

            <div className="pt-4 flex items-center justify-end gap-2">
              <Link href="/warehouses">
                <Button variant="ghost">Cancel</Button>
              </Link>
              <Button type="submit" variant="primary" loading={processing} icon={<Save className="w-4 h-4" />}>
                Save Warehouse
              </Button>
            </div>
          </Card>
        </form>
      </div>
    </AppShell>
  );
}
