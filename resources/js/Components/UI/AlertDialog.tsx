import React, { useEffect, useRef } from 'react';
import { AlertTriangle, Trash2, X } from 'lucide-react';
import { Button } from './Button';
import { IconButton } from './IconButton';

export interface AlertDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title?: string;
  description?: string;
  entityName?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'danger' | 'warning' | 'primary';
  loading?: boolean;
}

export const AlertDialog: React.FC<AlertDialogProps> = ({
  isOpen,
  onClose,
  onConfirm,
  title = 'Confirm Destructive Action',
  description = 'This action cannot be undone and will permanently remove this record from your workspace tenant.',
  entityName,
  confirmLabel = 'Delete Record',
  cancelLabel = 'Cancel',
  variant = 'danger',
  loading = false,
}) => {
  const dialogRef = useRef<HTMLDivElement>(null);
  const cancelBtnRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (isOpen) {
      setTimeout(() => cancelBtnRef.current?.focus(), 100);
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="alert-dialog-title"
      aria-describedby="alert-dialog-desc"
      className="fixed inset-0 z-50 overflow-y-auto"
    >
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/75 backdrop-blur-sm spring-transition animate-in fade-in duration-150"
        onClick={onClose}
      />

      {/* Dialog Frame */}
      <div className="flex min-h-full items-center justify-center p-4 text-center">
        <div
          ref={dialogRef}
          className="relative w-full max-w-md transform overflow-hidden rounded-2xl glass-dropdown p-6 text-left align-middle shadow-2xl border border-rose-500/30 spring-transition animate-in zoom-in-95 duration-200"
        >
          <div className="flex items-start gap-4">
            <div className="w-10 h-10 rounded-xl bg-rose-500/20 border border-rose-500/30 flex items-center justify-center text-rose-400 flex-shrink-0">
              <AlertTriangle className="w-5 h-5" />
            </div>

            <div className="space-y-2 flex-1">
              <h3 id="alert-dialog-title" className="text-base font-bold text-white tracking-tight">
                {title}
              </h3>
              <p id="alert-dialog-desc" className="text-xs text-gray-300 leading-relaxed">
                {description}
              </p>
              {entityName && (
                <div className="p-2 rounded-lg bg-black/40 border border-white/10 font-mono text-xs text-rose-300 break-all select-all">
                  Target: {entityName}
                </div>
              )}
            </div>
          </div>

          <div className="mt-6 flex items-center justify-end gap-3">
            <Button
              ref={cancelBtnRef}
              type="button"
              variant="ghost"
              size="md"
              onClick={onClose}
              disabled={loading}
            >
              {cancelLabel}
            </Button>
            <Button
              type="button"
              variant={variant === 'danger' ? 'danger' : 'primary'}
              size="md"
              loading={loading}
              onClick={() => {
                onConfirm();
                onClose();
              }}
              icon={<Trash2 className="w-4 h-4" />}
            >
              {confirmLabel}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
};
