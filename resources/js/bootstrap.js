import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

/**
 * Read the CSRF token from the meta tag and set it on axios.
 * Called on page load AND after every Inertia navigation.
 */
function syncCsrfToken() {
    const tokenEl = document.head.querySelector('meta[name="csrf-token"]');
    if (tokenEl) {
        const token = tokenEl.content;
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        // Also set the cookie that Inertia reads for X-XSRF-TOKEN
        // Laravel sets XSRF-TOKEN cookie automatically; axios reads it if withCredentials=true
    } else {
        console.warn('[IEC] CSRF meta tag not found — 419 errors will occur on form submissions.');
    }
}

// Set immediately on page load
syncCsrfToken();

// Re-sync after every Inertia SPA navigation (token can go stale)
document.addEventListener('inertia:finish', syncCsrfToken);
document.addEventListener('inertia:navigate', syncCsrfToken);