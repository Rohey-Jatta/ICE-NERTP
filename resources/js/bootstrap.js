import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Read CSRF token from the meta tag and attach to every Axios request.
// This fixes 419 errors on mobile and any browser that drops the XSRF-TOKEN cookie.
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.warn('CSRF token meta tag not found. CSRF protection may fail.');
}