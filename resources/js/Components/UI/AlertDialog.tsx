import React, { useEffect, useRef } from 'react';
import { AlertTriangle, Info, AlertCircle, CheckCircle2 } from 'lucide-react';
import { Button } from './Button';

export interface AlertDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  description: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'danger' | 'warning' | 'info' | 'success';
  loading?: boolean;
  entityName?: string;
}

export const AlertDialog: React.FC<AlertDialogProps> = ({
  isOpen,
  onClose,
  onConfirm,
  title,
  description,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  variant = 'danger',
  loading = false,
  entityName,
}) => {
  const triggerRef = useRef<HTMLElement | null>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const cancelBtnRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (isOpen) {
      triggerRef.current = document.activeElement as HTMLElement;
      const mainEl = document.querySelector('main') || document.getElementById('app');
      if (mainEl) {
        mainEl.setAttribute('aria-hidden', 'true');
        (mainEl as any).inert = true;
      }
      // Focus safe element (Cancel button) on open
      setTimeout(() => cancelBtnRef.current?.focus(), 50);
    } else {
      const mainEl = document.querySelector('main') || document.getElementById('app');
      if (mainEl) {
        mainEl.removeAttribute('aria-hidden');
        (mainEl as any).inert = false;
      }
      if (triggerRef.current) {
        triggerRef.current.focus();
      }
    }
  }, [isOpen]);

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      onClose();
      return;
    }

    if (e.key === 'Tab' && dialogRef.current) {
      const focusable = dialogRef.current.querySelectorAll<HTMLElement>(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      if (!focusable.length) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  };

  if (!isOpen) return null;

  const iconMap = {
    danger: <AlertCircle className="w-6 h-6 text-rose-400" />,
    warning: <AlertTriangle className="w-6 h-6 text-amber-400" />,
    info: <Info className="w-6 h-6 text-indigo-400" />,
    success: <CheckCircle2 className="w-6 h-6 text-emerald-400" />,
  };

  const confirmVariantMap = {
    danger: 'danger' as const,
    warning: 'primary' as const,
    info: 'primary' as const,
    success: 'primary' as const,
  };

  return (
    <div
      className="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 flex items-center justify-center"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="alert-dialog-title"
      aria-describedby="alert-dialog-desc"
      onKeyDown={handleKeyDown}
    >
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/70 backdrop-blur-sm spring-transition"
        onClick={onClose}
      />

      {/* Modal Surface */}
      <div
        ref={dialogRef}
        className="relative w-full max-w-md bg-[var(--surface-1)] border border-[var(--border-medium)] rounded-2xl p-6 shadow-2xl z-10 space-y-5 spring-transition"
      >
        <div className="flex items-start gap-4">
          <div className="p-3 rounded-xl bg-[var(--surface-2)] border border-[var(--border-subtle)] flex-shrink-0">
            {iconMap[variant]}
          </div>

          <div className="space-y-1.5 min-w-0">
            <h2 id="alert-dialog-title" className="text-base font-bold text-[var(--text-primary)] tracking-tight">
              {title}
            </h2>
            <p id="alert-dialog-desc" className="text-xs sm:text-sm text-[var(--text-secondary)] leading-relaxed">
              {description}
            </p>
          </div>
        </div>

        <div className="flex items-center justify-end gap-3 pt-3 border-t border-[var(--border-subtle)]">
          <Button
            ref={cancelBtnRef}
            variant="secondary"
            size="sm"
            onClick={onClose}
            disabled={loading}
          >
            {cancelLabel}
          </Button>

          <Button
            variant={confirmVariantMap[variant]}
            size="sm"
            onClick={onConfirm}
            loading={loading}
          >
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
};
