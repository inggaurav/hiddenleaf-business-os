import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from './UI/Card';
import { SectionHeader } from './UI/SectionHeader';

export default function DataPanel({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  const props = usePage<any>().props;
  const data = Object.entries(props).filter(
    ([key]) => !['auth', 'tenant', 'flash', 'errors', 'ziggy'].includes(key)
  );

  return (
    <AppShell title={title}>
      <div className="space-y-6">
        <SectionHeader title={title} description={description} />

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          {data.map(([key, value]) => (
            <Card key={key} level={0} className="space-y-3">
              <h3 className="text-xs font-bold text-white uppercase tracking-wider text-purple-300">
                {key.replace(/_/g, ' ')}
              </h3>
              {Array.isArray(value) ? (
                <div className="space-y-2">
                  {value.length ? (
                    value.map((item: any, index) => (
                      <div
                        key={item?.id ?? index}
                        className="p-3 rounded-lg bg-white/[0.03] border border-white/5 text-xs text-gray-200"
                      >
                        {typeof item === 'object'
                          ? item.name ?? item.title ?? item.subject ?? JSON.stringify(item)
                          : String(item)}
                      </div>
                    ))
                  ) : (
                    <p className="text-xs text-gray-500">No records available.</p>
                  )}
                </div>
              ) : (
                <pre className="p-3 rounded-lg bg-black/40 border border-white/5 overflow-auto text-xs text-gray-300 font-mono">
                  {JSON.stringify(value, null, 2)}
                </pre>
              )}
            </Card>
          ))}
        </div>
      </div>
    </AppShell>
  );
}
