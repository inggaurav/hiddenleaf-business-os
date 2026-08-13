import React, { useState } from 'react';
import { usePage, useForm, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Badge, StatusBadge } from '@/Components/UI/Badge';
import { Textarea } from '@/Components/UI/Textarea';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { ArrowLeft, Send, Headphones, CheckCircle2 } from 'lucide-react';

export default function HelpdeskShow() {
  const { ticket, replies = [] } = usePage<any>().props;

  const { data, setData, post, processing, reset } = useForm({
    description: '',
  });

  const handleReply = (e: React.FormEvent) => {
    e.preventDefault();
    post(`/helpdesk-tickets/${ticket.id}/reply`, {
      onSuccess: () => reset(),
    });
  };

  return (
    <AppShell title={`Ticket #${ticket?.ticket_id || ticket?.id}`}>
      <div className="max-w-4xl mx-auto space-y-6">
        <SectionHeader
          title={`Ticket #${ticket?.ticket_id || ticket?.id}: ${ticket?.title}`}
          description={`Reported by ${ticket?.name} (${ticket?.email})`}
          badge={<StatusBadge status={ticket?.status || 'Open'} />}
          actions={
            <Link href="/helpdesk-tickets">
              <Button variant="ghost" size="sm" icon={<ArrowLeft className="w-4 h-4" />}>
                Back to Queue
              </Button>
            </Link>
          }
        />

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {/* Main Thread */}
          <div className="md:col-span-2 space-y-4">
            {/* Original Problem Card */}
            <Card level={0} className="space-y-3">
              <div className="flex items-center justify-between text-xs text-gray-400 pb-2 border-b border-white/10">
                <span className="font-semibold text-white">{ticket?.name}</span>
                <span>{new Date(ticket?.created_at).toLocaleString()}</span>
              </div>
              <p className="text-sm text-gray-200 leading-relaxed whitespace-pre-wrap">
                {ticket?.description}
              </p>
            </Card>

            {/* Replies List */}
            {replies.map((rep: any) => (
              <Card key={rep.id} level={0} className="space-y-3 bg-purple-950/10 border-purple-500/20">
                <div className="flex items-center justify-between text-xs text-purple-300 pb-2 border-b border-purple-500/20">
                  <span className="font-semibold">{rep.user?.name || 'Support Agent'}</span>
                  <span>{new Date(rep.created_at).toLocaleString()}</span>
                </div>
                <p className="text-sm text-gray-200 leading-relaxed whitespace-pre-wrap">
                  {rep.description}
                </p>
              </Card>
            ))}

            {/* Reply Input Form */}
            <form onSubmit={handleReply}>
              <Card level={1} className="space-y-3">
                <Textarea
                  label="Post Agent Reply"
                  placeholder="Type your response to the customer..."
                  rows={3}
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  required
                />
                <div className="flex items-center justify-end">
                  <Button
                    type="submit"
                    variant="primary"
                    size="sm"
                    loading={processing}
                    icon={<Send className="w-3.5 h-3.5" />}
                  >
                    Send Reply
                  </Button>
                </div>
              </Card>
            </form>
          </div>

          {/* Ticket Metadata Sidebar */}
          <div className="space-y-4">
            <Card level={0} className="space-y-3 text-xs">
              <h4 className="font-bold uppercase text-gray-400 tracking-wider">Ticket Info</h4>
              <div>
                <span className="text-gray-400 block">Category</span>
                <Badge variant="purple" size="sm">{ticket?.category?.name || 'General'}</Badge>
              </div>
              <div>
                <span className="text-gray-400 block">Requester Email</span>
                <span className="text-white font-mono">{ticket?.email}</span>
              </div>
              <div>
                <span className="text-gray-400 block">Status</span>
                <StatusBadge status={ticket?.status || 'Open'} />
              </div>
              <div>
                <span className="text-gray-400 block">Opened At</span>
                <span className="text-gray-300">{new Date(ticket?.created_at).toLocaleDateString()}</span>
              </div>
            </Card>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
