import React from 'react';
import { clsx } from 'clsx';
import { AlertCircle, X } from 'lucide-react';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  hint?: string;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
  onClear?: () => void;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(({
  label,
  error,
  hint,
  leftIcon,
  rightIcon,
  onClear,
  className,
  value,
  disabled,
  id,
  ...props
}, ref) => {
  const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

  return (
    <div className="w-full space-y-1.5">
      {label && (
        <label
          htmlFor={inputId}
          className="block text-xs font-medium tracking-wide text-gray-300 select-none"
        >
          {label}
        </label>
      )}

      <div className="relative flex items-center">
        {leftIcon && (
          <div className="absolute left-3 flex items-center pointer-events-none text-gray-400">
            {leftIcon}
          </div>
        )}

        <input
          ref={ref}
          id={inputId}
          value={value}
          disabled={disabled}
          className={clsx(
            'w-full bg-[#12161E]/90 text-gray-100 text-sm rounded-lg border placeholder:text-gray-500 spring-transition focus-ring disabled:opacity-50 disabled:cursor-not-allowed',
            leftIcon ? 'pl-9' : 'pl-3.5',
            rightIcon || onClear || error ? 'pr-9' : 'pr-3.5',
            'py-2 shadow-inner',
            error
              ? 'border-rose-500/60 focus:border-rose-500 text-rose-100'
              : 'border-white/10 hover:border-white/20 focus:border-violet-500/80 focus:bg-[#151A24]',
            className
          )}
          {...props}
        />

        <div className="absolute right-3 flex items-center space-x-1.5">
          {onClear && value && !disabled && (
            <button
              type="button"
              onClick={onClear}
              className="text-gray-500 hover:text-gray-300 p-0.5 rounded-full hover:bg-white/10"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          )}

          {error ? (
            <AlertCircle className="w-4 h-4 text-rose-400 flex-shrink-0" />
          ) : (
            rightIcon && <div className="text-gray-400 flex-shrink-0">{rightIcon}</div>
          )}
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

Input.displayName = 'Input';
