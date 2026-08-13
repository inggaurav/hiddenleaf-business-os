import React from 'react';
import { clsx } from 'clsx';
import { AlertCircle } from 'lucide-react';

export interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string;
  error?: string;
  hint?: string;
}

export const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(({
  label,
  error,
  hint,
  className,
  id,
  rows = 3,
  ...props
}, ref) => {
  const textareaId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

  return (
    <div className="w-full space-y-1.5">
      {label && (
        <label
          htmlFor={textareaId}
          className="block text-xs font-medium tracking-wide text-gray-300 select-none"
        >
          {label}
        </label>
      )}

      <textarea
        ref={ref}
        id={textareaId}
        rows={rows}
        className={clsx(
          'w-full bg-[#12161E]/90 text-gray-100 text-sm rounded-lg border px-3.5 py-2.5 shadow-inner placeholder:text-gray-500 spring-transition focus-ring disabled:opacity-50 disabled:cursor-not-allowed',
          error
            ? 'border-rose-500/60 focus:border-rose-500 text-rose-100'
            : 'border-white/10 hover:border-white/20 focus:border-violet-500/80 focus:bg-[#151A24]',
          className
        )}
        {...props}
      />

      {error ? (
        <p className="text-xs text-rose-400 flex items-center gap-1 font-medium">{error}</p>
      ) : hint ? (
        <p className="text-xs text-gray-400">{hint}</p>
      ) : null}
    </div>
  );
});

Textarea.displayName = 'Textarea';
