import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { AppToaster } from './components/app-toaster';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'EWMS';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    // The negated pattern keeps a page's co-located *.test.tsx (see
    // pages/auth/login.test.tsx) out of both this glob and the production
    // build — without it, Vite has no way to know a matched module is never
    // actually requested at runtime and bundles it as its own chunk anyway.
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob(['./pages/**/*.tsx', '!./pages/**/*.test.tsx'])),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <App {...props} />
                <AppToaster />
            </>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
