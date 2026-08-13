import React from 'react';
import { clsx } from 'clsx';
import { Check } from 'lucide-react';

export interface CheckboxProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: React.ReactNode;
  description?: string;
}

export const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(({
  label,
  description,
  className,
  checked,
  disabled,
  id,
  ...props
}, ref) => {
  const checkboxId = id || (typeof label === 'string' ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

  return (
    <label
      htmlFor={checkboxId}
      className={clsx(
        'inline-flex items-start gap-2.5 select-none cursor-pointer group',
        disabled && 'opacity-50 cursor-not-allowed'
      )}
    >
      <div className="relative flex items-center justify-center mt-0.5">
        <input
          ref={ref}
          type="checkbox"
          id={checkboxId}
          checked={checked}
          disabled={disabled}
          className="sr-only peer"
          {...props}
        />
        <div
          className={clsx(
            'w-4 h-4 rounded border spring-transition flex items-center justify-center',
            'peer-focus-visible:ring-2 peer-focus-visible:ring-violet-500/50',
            checked
              ? 'bg-violet-600 border-violet-500 text-white shadow-sm shadow-violet-950/50'
              : 'bg-[#12161E] border-white/15 group-hover:border-white/30'
          )}
        >
          {checked && <Check className="w-3 h-3 stroke-[3]" />}
        </div>
      </div>

      {(label || description) && (
        <div className="space-y-0.5">
          {label && <span className="text-sm font-medium text-gray-200 block">{label}</span>}
          {description && <p className="text-xs text-gray-400">{description}</p>}
        </div>
      )}
    </label>
  );
});

Checkbox.displayName = 'Checkbox';

export interface SwitchProps {
  checked: boolean;
  onChange: (checked: boolean) => void;
  label?: React.ReactNode;
  description?: string;
  disabled?: boolean;
}

export const Switch: React.FC<SwitchProps> = ({
  checked,
  onChange,
  label,
  description,
  disabled = false,
}) => {
  return (
    <label
      className={clsx(
        'inline-flex items-center justify-between gap-4 select-none cursor-pointer w-full group',
        disabled && 'opacity-50 cursor-not-allowed'
      )}
    >
      {(label || description) && (
        <div className="space-y-0.5 pr-2">
          {label && <span className="text-sm font-medium text-gray-200 block">{label}</span>}
          {description && <p className="text-xs text-gray-400">{description}</p>}
        </div>
      )}

      <button
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => !disabled && onChange(!checked)}
        className={clsx(
          'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent spring-transition focus-ring',
          checked ? 'bg-violet-600' : 'bg-white/10'
        )}
      >
        <span
          aria-hidden="true"
          className={clsx(
            'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-md transform spring-transition',
            checked ? 'translate-x-5' : 'translate-x-0'
          )}
        />
      </button>
    </label>
  );
};
