import React, { useState, useEffect } from 'react';
import { Sun, Moon, Laptop } from 'lucide-react';
import { clsx } from 'clsx';

export type ThemeMode = 'system' | 'dark' | 'light';

export const ThemeSwitcher: React.FC<{ className?: string }> = ({ className }) => {
  const [theme, setTheme] = useState<ThemeMode>('dark');

  useEffect(() => {
    const saved = localStorage.getItem('hl_theme') as ThemeMode | null;
    if (saved) {
      setTheme(saved);
      applyTheme(saved);
    } else {
      applyTheme('dark');
    }
  }, []);

  const applyTheme = (mode: ThemeMode) => {
    if (mode === 'system') {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
    } else {
      document.documentElement.setAttribute('data-theme', mode);
    }
  };

  const handleSelect = (mode: ThemeMode) => {
    setTheme(mode);
    localStorage.setItem('hl_theme', mode);
    applyTheme(mode);
  };

  return (
    <div className={clsx('flex items-center p-0.5 rounded-lg bg-white/[0.04] border border-white/10 text-xs', className)}>
      <button
        type="button"
        title="Light Theme"
        onClick={() => handleSelect('light')}
        className={clsx(
          'p-1.5 rounded-md spring-transition cursor-pointer',
          theme === 'light' ? 'bg-purple-600 text-white shadow' : 'text-gray-400 hover:text-white'
        )}
      >
        <Sun className="w-3.5 h-3.5" />
      </button>

      <button
        type="button"
        title="Dark Theme"
        onClick={() => handleSelect('dark')}
        className={clsx(
          'p-1.5 rounded-md spring-transition cursor-pointer',
          theme === 'dark' ? 'bg-purple-600 text-white shadow' : 'text-gray-400 hover:text-white'
        )}
      >
        <Moon className="w-3.5 h-3.5" />
      </button>

      <button
        type="button"
        title="System Match"
        onClick={() => handleSelect('system')}
        className={clsx(
          'p-1.5 rounded-md spring-transition cursor-pointer',
          theme === 'system' ? 'bg-purple-600 text-white shadow' : 'text-gray-400 hover:text-white'
        )}
      >
        <Laptop className="w-3.5 h-3.5" />
      </button>
    </div>
  );
};
