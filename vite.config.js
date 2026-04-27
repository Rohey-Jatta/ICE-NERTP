import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
    build: {
        chunkSizeWarningLimit: 2000,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // Vendor splitting — keeps app code separate from stable library code
                    // so browsers cache libraries independently of app changes
                    if (id.includes('node_modules/react-dom') || id.includes('node_modules/react/')) {
                        return 'react-vendor';
                    }
                    if (id.includes('node_modules/@inertiajs')) {
                        return 'inertia-vendor';
                    }
                    if (id.includes('node_modules/leaflet') || id.includes('node_modules/react-leaflet')) {
                        return 'map-vendor';
                    }
                    if (
                        id.includes('node_modules/chart.js') ||
                        id.includes('node_modules/react-chartjs-2') ||
                        id.includes('node_modules/recharts') ||
                        id.includes('node_modules/d3')
                    ) {
                        return 'chart-vendor';
                    }
                    if (id.includes('node_modules/')) {
                        return 'vendor';
                    }
                },
            },
        },
    },
    server: {
        hmr: {
            overlay: false,
        },
    },
    optimizeDeps: {
        include: ['react', 'react-dom', '@inertiajs/react', 'axios'],
    },
});
