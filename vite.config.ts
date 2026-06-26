import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

// When running inside the Docker dev container we need Vite to listen on all
// interfaces and tell the HMR client (running in the browser on the host) to
// connect back via localhost. Polling is used so file changes on the mounted
// volume are picked up reliably.
const inDocker = process.env.VITE_DOCKER === 'true';

export default defineConfig({
    server: inDocker
        ? {
              host: '0.0.0.0',
              port: 5173,
              strictPort: true,
              cors: true,
              hmr: {
                  host: process.env.VITE_HMR_HOST || 'localhost',
              },
              watch: {
                  usePolling: true,
                  interval: 300,
              },
          }
        : undefined,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
});
