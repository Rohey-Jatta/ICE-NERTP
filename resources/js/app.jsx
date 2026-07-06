import './bootstrap'
import '../css/app.css'
import { createInertiaApp, router } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createRoot } from 'react-dom/client'
import { NotificationProvider, useGlobalNotify } from '@/Contexts/NotificationContext'

const appName = import.meta.env.VITE_APP_NAME || 'IEC NERTP'

/**
 * InertiaErrorBridge — catches failures Inertia itself surfaces (network
 * drops mid-navigation, unparseable/500 responses, CSRF/session expiry on
 * a full-page visit) that never reach a page component's own try/catch.
 * Without this, those failures were previously invisible: the URL might
 * change or the page might just... not update.
 */
function InertiaErrorBridge({ children }) {
    const { notify } = useGlobalNotify()

    // 'invalid' fires when Inertia gets a non-Inertia response it can't
    // process (e.g. a raw 500 HTML page, or a session/CSRF failure that
    // produced a redirect Inertia doesn't understand).
    router.on('invalid', (event) => {
        const status = event?.detail?.response?.status
        if (status === 419) {
            notify.warning('Your session has expired. Please refresh the page and try again.')
        } else if (status === 401) {
            notify.warning('You need to login to continue.')
        } else if (status === 403) {
            notify.error('You don\'t have permission to perform this action.')
        } else {
            notify.error('Something went wrong loading this page. Please try again.')
        }
    })

    // 'exception' fires on unexpected JS/network exceptions during a visit.
    router.on('exception', (event) => {
        notify.error('Unable to connect to the server. Please check your internet connection and try again.')
        // Prevent the default console-noise-only behavior from being the
        // ONLY signal — we still let Inertia log it for developers, but
        // the user now sees something too.
    })

    return children
}

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),

    setup({ el, App, props }) {
        createRoot(el).render(
            <NotificationProvider>
                <InertiaErrorBridge>
                    <App {...props} />
                </InertiaErrorBridge>
            </NotificationProvider>
        )
    },
})