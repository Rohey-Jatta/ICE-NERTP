import '../css/app.css'
import { createInertiaApp } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createRoot } from 'react-dom/client'

const appName = import.meta.env.VITE_APP_NAME || 'IEC NERTP'

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },

    /*
     * Inertia's built-in progress bar uses NProgress — pure DOM manipulation,
     * no React state, no re-renders, zero impact on navigation speed.
     *
     * delay: 0   → bar appears instantly on every click (no 250ms wait)
     * color      → matches the app's indigo brand colour
     * showSpinner: false → spinner adds a second DOM mutation per frame; skip it
     */
    progress: {
        delay: 0,
        color: '#4f46e5',
        includeCSS: false,
        showSpinner: false,
    },
})
