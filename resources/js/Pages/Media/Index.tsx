import React, { useState } from 'react';
import { usePage, useForm, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Card } from '@/Components/UI/Card';
import { Button } from '@/Components/UI/Button';
import { Input } from '@/Components/UI/Input';
import { Badge } from '@/Components/UI/Badge';
import { Modal } from '@/Components/UI/Modal';
import { SectionHeader } from '@/Components/UI/SectionHeader';
import { Image, Upload, File, Trash2, Download, Copy, Check } from 'lucide-react';

export default function MediaIndex() {
  const { files = [] } = usePage<any>().props;
  const [modalOpen, setModalOpen] = useState(false);
  const [copiedId, setCopiedId] = useState<number | null>(null);

  const { data, setData, post, processing, reset, errors } = useForm({
    file: null as File | null,
  });

  const handleUpload = (e: React.FormEvent) => {
    e.preventDefault();
    post('/media', {
      onSuccess: () => {
        setModalOpen(false);
        reset();
      },
    });
  };

  const handleDelete = (id: number) => {
    if (confirm('Delete this media asset?')) {
      router.delete(`/media/${id}`);
    }
  };

  const handleCopyLink = (fileObj: any) => {
    const url = `${window.location.origin}/storage/${fileObj.path || fileObj.name}`;
    navigator.clipboard.writeText(url);
    setCopiedId(fileObj.id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  return (
    <AppShell title="Media Library">
      <div className="space-y-6">
        <SectionHeader
          title="Media Library & Assets"
          description="Centralized cloud storage for brand assets, documents, invoices, receipts, and product attachments."
          badge={<Badge variant="purple" size="sm">Cloud S3</Badge>}
          actions={
            <Button
              variant="primary"
              size="sm"
              icon={<Upload className="w-4 h-4" />}
              onClick={() => setModalOpen(true)}
            >
              Upload Asset
            </Button>
          }
        />

        {files.length > 0 ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {files.map((file: any) => {
              const isImage = file.mime_type?.startsWith('image/') || /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(file.name);
              return (
                <Card key={file.id} level={0} className="flex flex-col justify-between group overflow-hidden p-3">
                  <div className="rounded-lg bg-black/40 border border-white/10 h-36 flex items-center justify-center overflow-hidden mb-3 relative">
                    {isImage ? (
                      <img
                        src={`/storage/${file.path || file.name}`}
                        alt={file.name}
                        className="w-full h-full object-cover group-hover:scale-105 spring-transition"
                        onError={(e) => {
                          (e.target as any).style.display = 'none';
                        }}
                      />
                    ) : (
                      <File className="w-10 h-10 text-gray-500" />
                    )}
                  </div>

                  <div className="space-y-1">
                    <p className="text-xs font-semibold text-white truncate" title={file.name}>
                      {file.name}
                    </p>
                    <div className="flex items-center justify-between text-[11px] text-gray-400">
                      <span>{file.size ? `${Math.round(file.size / 1024)} KB` : 'Asset'}</span>
                      <span>{new Date(file.created_at || Date.now()).toLocaleDateString()}</span>
                    </div>
                  </div>

                  <div className="pt-3 mt-3 border-t border-white/10 flex items-center justify-between">
                    <Button
                      variant="ghost"
                      size="sm"
                      icon={copiedId === file.id ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                      onClick={() => handleCopyLink(file)}
                    >
                      {copiedId === file.id ? 'Copied' : 'Link'}
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="text-rose-400 hover:text-rose-300"
                      icon={<Trash2 className="w-3.5 h-3.5" />}
                      onClick={() => handleDelete(file.id)}
                    >
                      Delete
                    </Button>
                  </div>
                </Card>
              );
            })}
          </div>
        ) : (
          <Card level={0} className="p-12 text-center">
            <div className="w-12 h-12 rounded-2xl bg-white/[0.04] border border-white/10 text-gray-400 flex items-center justify-center mx-auto mb-3 shadow-inner">
              <Image className="w-6 h-6" />
            </div>
            <h3 className="text-base font-semibold text-white">No media files uploaded</h3>
            <p className="text-xs text-gray-400 mt-1 mb-4">Upload logos, invoice PDFs, contracts, and images.</p>
            <Button variant="primary" size="sm" icon={<Upload className="w-4 h-4" />} onClick={() => setModalOpen(true)}>
              Upload File
            </Button>
          </Card>
        )}

        {/* Upload File Modal */}
        <Modal
          isOpen={modalOpen}
          onClose={() => setModalOpen(false)}
          title="Upload Asset"
          description="Attach documents or images to your workspace media library."
        >
          <form onSubmit={handleUpload} className="space-y-4">
            <div className="p-6 rounded-xl border border-dashed border-white/20 hover:border-violet-500/50 bg-white/[0.02] text-center cursor-pointer">
              <input
                type="file"
                onChange={(e) => setData('file', e.target.files?.[0] || null)}
                className="w-full text-xs text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-violet-600 file:text-white"
                required
              />
              {errors.file && <p className="text-xs text-rose-400 mt-2">{errors.file}</p>}
            </div>

            <div className="pt-4 flex items-center justify-end gap-2">
              <Button type="button" variant="ghost" onClick={() => setModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" variant="primary" loading={processing} icon={<Upload className="w-4 h-4" />}>
                Upload Asset
              </Button>
            </div>
          </form>
        </Modal>
      </div>
    </AppShell>
  );
}
