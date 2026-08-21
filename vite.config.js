import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./resources/js/test-setup.ts'],
        globals: true,
        css: false,
        // tests/e2e runs under Playwright's own test runner (npm run
        // test:e2e), not Vitest — its test() isn't the same function.
        exclude: ['node_modules/**', 'tests/e2e/**'],
    },
});
