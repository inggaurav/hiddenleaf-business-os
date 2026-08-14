import React from 'react';

interface IconButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  label: string;
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  size?: 'sm' | 'md' | 'lg';
  children: React.ReactNode;
  className?: string;
}

export const IconButton: React.FC<IconButtonProps> = ({
  label,
  variant = 'ghost',
  size = 'md',
  children,
  className = '',
  ...props
}) => {
  const sizeClasses = {
    sm: 'p-1.5 rounded-lg',
    md: 'p-2 rounded-xl',
    lg: 'p-2.5 rounded-xl',
  };

  const variantClasses = {
    primary: 'bg-purple-600 hover:bg-purple-500 text-white shadow-md',
    secondary: 'bg-[var(--surface-1)] hover:bg-[var(--surface-2)] text-[var(--text-secondary)] border border-[var(--border-subtle)]',
    ghost: 'hover:bg-white/[0.06] text-[var(--text-secondary)] hover:text-[var(--text-primary)]',
    danger: 'hover:bg-rose-500/10 text-[var(--text-secondary)] hover:text-rose-400',
  };

  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      className={`inline-flex items-center justify-center spring-transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed ${sizeClasses[size]} ${variantClasses[variant]} ${className}`}
      {...props}
    >
      {children}
    </button>
  );
};
