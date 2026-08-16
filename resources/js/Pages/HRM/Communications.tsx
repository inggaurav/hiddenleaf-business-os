import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Badge } from '@/Components/UI/Badge';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { Input } from '@/Components/UI/Input';
import { Select } from '@/Components/UI/Select';
import { SectionHeader } from '@/Components/UI/SectionHeader';

export default function Communications({ announcements = [], policies = [], canManage = false, acknowledgedPolicyIds = [] }: any) {
  const [announcement, setAnnouncement] = useState({ title: '', content: '', starts_on: '', ends_on: '', status: 'published' });
  const [policy, setPolicy] = useState({ title: '', version: '1.0', content: '', effective_on: '', requires_acknowledgement: true, status: 'active' });
  return <AppShell title="HR Communications" breadcrumbs={[{ label: 'HRM' }, { label: 'Communications & Policies' }]}><div className="space-y-6">
    <SectionHeader title="Announcements, Events & Company Policies" description="Publish workforce announcements and controlled policy versions with acknowledgement tracking." />
    {canManage && <div className="grid xl:grid-cols-2 gap-5">
      <Card level={0} className="p-5"><h3 className="font-bold mb-4">Publish Announcement</h3><form onSubmit={event => { event.preventDefault(); router.post('/hrm/announcements', announcement); }} className="grid sm:grid-cols-2 gap-3"><Input label="Title" value={announcement.title} onChange={event => setAnnouncement({ ...announcement, title: event.target.value })} required /><Select label="Status" value={announcement.status} onChange={event => setAnnouncement({ ...announcement, status: event.target.value })}><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></Select><Input label="Starts On" type="date" value={announcement.starts_on} onChange={event => setAnnouncement({ ...announcement, starts_on: event.target.value })} /><Input label="Ends On" type="date" value={announcement.ends_on} onChange={event => setAnnouncement({ ...announcement, ends_on: event.target.value })} /><div className="sm:col-span-2"><Input label="Content" value={announcement.content} onChange={event => setAnnouncement({ ...announcement, content: event.target.value })} required /></div><Button type="submit" variant="primary">Publish</Button></form></Card>
      <Card level={0} className="p-5"><h3 className="font-bold mb-4">Create Company Policy</h3><form onSubmit={event => { event.preventDefault(); router.post('/hrm/policies', policy); }} className="grid sm:grid-cols-2 gap-3"><Input label="Title" value={policy.title} onChange={event => setPolicy({ ...policy, title: event.target.value })} required /><Input label="Version" value={policy.version} onChange={event => setPolicy({ ...policy, version: event.target.value })} required /><Input label="Effective On" type="date" value={policy.effective_on} onChange={event => setPolicy({ ...policy, effective_on: event.target.value })} /><Select label="Status" value={policy.status} onChange={event => setPolicy({ ...policy, status: event.target.value })}><option value="draft">Draft</option><option value="active">Active</option><option value="archived">Archived</option></Select><div className="sm:col-span-2"><Input label="Policy Content" value={policy.content} onChange={event => setPolicy({ ...policy, content: event.target.value })} required /></div><label className="flex items-center gap-2 text-xs"><input type="checkbox" checked={policy.requires_acknowledgement} onChange={event => setPolicy({ ...policy, requires_acknowledgement: event.target.checked })} /> Require acknowledgement</label><Button type="submit" variant="primary">Save Policy</Button></form></Card>
    </div>}
    <div className="grid xl:grid-cols-2 gap-5"><RecordList title="Announcements" records={announcements} /><RecordList title="Company Policies" records={policies} policy acknowledgedPolicyIds={acknowledgedPolicyIds} /></div>
  </div></AppShell>;
}

function RecordList({ title, records, policy = false, acknowledgedPolicyIds = [] }: any) {
  return <Card level={0} className="p-5"><h3 className="font-bold mb-4">{title}</h3><div className="space-y-3">{records.map((record: any) => {
    const acknowledged = acknowledgedPolicyIds.includes(record.id);
    return <div key={record.id} className="p-3 rounded-xl border border-[var(--border-subtle)]"><div className="flex justify-between gap-3"><p className="font-semibold text-sm">{record.title}</p><Badge size="sm" variant="neutral">{record.status}</Badge></div><p className="text-xs text-[var(--text-tertiary)] mt-2 whitespace-pre-wrap">{record.content}</p>{policy && record.requires_acknowledgement && <div className="mt-3"><Button size="sm" variant={acknowledged ? 'secondary' : 'primary'} disabled={acknowledged} onClick={() => router.post(`/hrm/policies/${record.id}/acknowledge`)}>{acknowledged ? 'Acknowledged' : 'Acknowledge Policy'}</Button></div>}</div>;
  })}{!records.length && <p className="text-xs text-[var(--text-tertiary)]">No records yet.</p>}</div></Card>;
}
