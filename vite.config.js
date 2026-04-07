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
        // Increase chunk size warning limit — the app is large
        chunkSizeWarningLimit: 2000,
        rollupOptions: {
            output: {
                // Split vendor code from app code for better caching
                manualChunks: {
                    'react-vendor': ['react', 'react-dom'],
                    'inertia-vendor': ['@inertiajs/react'],
                    'chart-vendor': ['chart.js', 'react-chartjs-2', 'recharts'],
                    'map-vendor': ['leaflet', 'react-leaflet'],
                },
            },
        },
    },
    // Faster dev server
    server: {
        hmr: {
            overlay: false, // Don't show error overlay — it causes re-renders
        },
    },
    optimizeDeps: {
        include: ['react', 'react-dom', '@inertiajs/react', 'axios'],
    },
});