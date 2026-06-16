import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

const DEVICE_COOKIE_NAME = 'iec_device_id';
const DEVICE_COOKIE_MAX_AGE_DAYS = 365;

function getCookie(name) {
    const cookieString = document.cookie || '';
    return cookieString.split('; ').reduce((value, cookie) => {
        const [key, ...parts] = cookie.split('=');
        return key === name ? decodeURIComponent(parts.join('=')) : value;
    }, null);
}

function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/; samesite=lax`;
}

function generateFingerprint() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `iec-${Math.random().toString(36).slice(2)}-${Date.now().toString(36)}`;
}

function ensureDeviceFingerprint() {
    let fingerprint = getCookie(DEVICE_COOKIE_NAME);
    if (!fingerprint) {
        try {
            fingerprint = window.localStorage.getItem(DEVICE_COOKIE_NAME) || generateFingerprint();
        } catch {
            fingerprint = generateFingerprint();
        }

        setCookie(DEVICE_COOKIE_NAME, fingerprint, DEVICE_COOKIE_MAX_AGE_DAYS);

        try {
            window.localStorage.setItem(DEVICE_COOKIE_NAME, fingerprint);
        } catch {
            // Ignore localStorage failures.
        }
    }

    window.axios.defaults.headers.common['X-DEVICE-ID'] = fingerprint;
    return fingerprint;
}

window.deviceFingerprint = {
    get: () => getCookie(DEVICE_COOKIE_NAME) || (() => {
        try {
            return window.localStorage.getItem(DEVICE_COOKIE_NAME);
        } catch {
            return null;
        }
    })(),
    ensure: ensureDeviceFingerprint,
};

// Ensure a persistent device identifier exists on every page load.
ensureDeviceFingerprint();

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