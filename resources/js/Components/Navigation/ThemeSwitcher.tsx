import React, { useEffect, useState } from 'react';
import { Sun, Moon, Laptop } from 'lucide-react';

type ThemeMode = 'system' | 'dark' | 'light';

export const ThemeSwitcher: React.FC = () => {
  const [theme, setTheme] = useState<ThemeMode>('dark');

  useEffect(() => {
    const saved = (localStorage.getItem('hl_theme') as ThemeMode) || 'dark';
    setTheme(saved);
    applyTheme(saved);

    // Runtime OS listener for system theme
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const handleChange = () => {
      const current = localStorage.getItem('hl_theme') as ThemeMode;
      if (current === 'system') {
        applyTheme('system');
      }
    };

    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, []);

  const applyTheme = (mode: ThemeMode) => {
    const root = document.documentElement;
    if (mode === 'system') {
      const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (systemDark) {
        root.classList.add('dark');
        root.classList.remove('light');
      } else {
        root.classList.add('light');
        root.classList.remove('dark');
      }
    } else if (mode === 'light') {
      root.classList.add('light');
      root.classList.remove('dark');
    } else {
      root.classList.add('dark');
      root.classList.remove('light');
    }
  };

  const handleSelect = (mode: ThemeMode) => {
    setTheme(mode);
    localStorage.setItem('hl_theme', mode);
    applyTheme(mode);
  };

  return (
    <div
      role="radiogroup"
      aria-label="Color Theme Switcher"
      className="flex items-center p-1 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)]"
    >
      <button
        type="button"
        role="radio"
        aria-checked={theme === 'dark'}
        onClick={() => handleSelect('dark')}
        className={`p-1.5 rounded-lg text-xs spring-transition cursor-pointer ${
          theme === 'dark' 
            ? 'bg-purple-600/30 text-purple-300 font-semibold shadow-sm' 
            : 'text-[var(--text-tertiary)] hover:text-[var(--text-primary)]'
        }`}
        title="Dark Mode"
        aria-label="Dark Mode"
      >
        <Moon className="w-3.5 h-3.5" />
      </button>

      <button
        type="button"
        role="radio"
        aria-checked={theme === 'light'}
        onClick={() => handleSelect('light')}
        className={`p-1.5 rounded-lg text-xs spring-transition cursor-pointer ${
          theme === 'light' 
            ? 'bg-purple-600/30 text-purple-300 font-semibold shadow-sm' 
            : 'text-[var(--text-tertiary)] hover:text-[var(--text-primary)]'
        }`}
        title="Light Mode"
        aria-label="Light Mode"
      >
        <Sun className="w-3.5 h-3.5" />
      </button>

      <button
        type="button"
        role="radio"
        aria-checked={theme === 'system'}
        onClick={() => handleSelect('system')}
        className={`p-1.5 rounded-lg text-xs spring-transition cursor-pointer ${
          theme === 'system' 
            ? 'bg-purple-600/30 text-purple-300 font-semibold shadow-sm' 
            : 'text-[var(--text-tertiary)] hover:text-[var(--text-primary)]'
        }`}
        title="System Preference"
        aria-label="System Preference"
      >
        <Laptop className="w-3.5 h-3.5" />
      </button>
    </div>
  );
};
