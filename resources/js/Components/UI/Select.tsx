import React from 'react';
import { clsx } from 'clsx';
import { ChevronDown, AlertCircle } from 'lucide-react';

export interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  error?: string;
  hint?: string;
  options?: Array<{ value: string | number; label: string; disabled?: boolean }>;
}

export const Select = React.forwardRef<HTMLSelectElement, SelectProps>(({
  label,
  error,
  hint,
  options,
  children,
  className,
  id,
  ...props
}, ref) => {
  const selectId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

  return (
    <div className="w-full space-y-1.5">
      {label && (
        <label
          htmlFor={selectId}
          className="block text-xs font-medium tracking-wide text-gray-300 select-none"
        >
          {label}
        </label>
      )}

      <div className="relative">
        <select
          ref={ref}
          id={selectId}
          className={clsx(
            'w-full appearance-none bg-[#12161E]/90 text-gray-100 text-sm rounded-lg border pl-3.5 pr-10 py-2 shadow-inner spring-transition focus-ring disabled:opacity-50 disabled:cursor-not-allowed',
            error
              ? 'border-rose-500/60 focus:border-rose-500 text-rose-100'
              : 'border-white/10 hover:border-white/20 focus:border-violet-500/80 focus:bg-[#151A24]',
            className
          )}
          {...props}
        >
          {options
            ? options.map((opt) => (
                <option key={opt.value} value={opt.value} disabled={opt.disabled} className="bg-[#12161E] text-gray-100">
                  {opt.label}
                </option>
              ))
            : children}
        </select>

        <div className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
          <ChevronDown className="w-4 h-4" />
        </div>
      </div>

      {error ? (
        <p className="text-xs text-rose-400 flex items-center gap-1 font-medium">{error}</p>
      ) : hint ? (
        <p className="text-xs text-gray-400">{hint}</p>
      ) : null}
    </div>
  );
});

Select.displayName = 'Select';
