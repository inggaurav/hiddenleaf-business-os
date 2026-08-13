import React from 'react';
import { clsx } from 'clsx';
import { Loader2 } from 'lucide-react';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'intelligence' | 'outline';
  size?: 'sm' | 'md' | 'lg';
  loading?: boolean;
  icon?: React.ReactNode;
  iconPosition?: 'left' | 'right';
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(({
  children,
  className,
  variant = 'primary',
  size = 'md',
  loading = false,
  disabled = false,
  icon,
  iconPosition = 'left',
  ...props
}, ref) => {
  const baseStyles = 'inline-flex items-center justify-center font-medium rounded-lg spring-transition focus-ring disabled:opacity-50 disabled:cursor-not-allowed select-none cursor-pointer active:scale-[0.98]';

  const sizeStyles = {
    sm: 'text-xs px-2.5 py-1.5 gap-1.5',
    md: 'text-sm px-3.5 py-2 gap-2',
    lg: 'text-base px-5 py-2.5 gap-2.5 rounded-xl',
  };

  const variantStyles = {
    primary: 'bg-gradient-to-b from-violet-600 to-violet-700 text-white hover:from-violet-500 hover:to-violet-600 border border-violet-500/30 shadow-sm shadow-violet-950/50',
    secondary: 'bg-white/[0.06] hover:bg-white/[0.10] text-gray-200 border border-white/10 hover:border-white/20',
    outline: 'bg-transparent hover:bg-white/[0.05] text-gray-300 border border-white/15 hover:border-white/30',
    ghost: 'bg-transparent hover:bg-white/[0.07] text-gray-300 hover:text-white',
    danger: 'bg-rose-600/90 hover:bg-rose-600 text-white border border-rose-500/30 shadow-sm shadow-rose-950/50',
    intelligence: 'bg-gradient-to-r from-purple-600 via-indigo-600 to-cyan-600 text-white hover:brightness-110 border border-purple-400/30 shadow-md shadow-purple-950/50',
  };

  return (
    <button
      ref={ref}
      disabled={disabled || loading}
      className={clsx(baseStyles, sizeStyles[size], variantStyles[variant], className)}
      {...props}
    >
      {loading ? (
        <Loader2 className="w-4 h-4 animate-spin text-current" />
      ) : (
        icon && iconPosition === 'left' && <span className="flex-shrink-0">{icon}</span>
      )}
      <span>{children}</span>
      {!loading && icon && iconPosition === 'right' && <span className="flex-shrink-0">{icon}</span>}
    </button>
  );
});

Button.displayName = 'Button';
