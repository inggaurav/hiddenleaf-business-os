import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp, router } from '@inertiajs/react';

let currentBrandName = window.document.querySelector('meta[name="brand-name"]')?.getAttribute('content')
    || window.document.getElementsByTagName('title')[0]?.innerText
    || 'HiddenLeaf BusinessOS';

router.on('navigate', (event) => {
    const pageProps = event.detail.page.props as any;
    const resolvedBrand = pageProps?.tenant?.brand_name
        || pageProps?.branding?.brand_name
        || window.document.querySelector('meta[name="brand-name"]')?.getAttribute('content');
    if (resolvedBrand) {
        currentBrandName = resolvedBrand;
    }
});

createInertiaApp({
    title: (title) => {
        const brand = currentBrandName || 'HiddenLeaf BusinessOS';
        if (!title) return brand;
        if (title.includes(brand)) return title;
        return `${title} — ${brand}`;
    },
    resolve: (name: string) => {
        const pages = import.meta.glob<{ default: React.ComponentType }>('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Inertia page component not found: ${name}`);
        }

        return page.default;
    },
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#8b5cf6',
    },
});
