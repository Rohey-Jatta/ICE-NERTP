/**
 * apiClient.js — single entry point for all fetch()-based API calls.
 *
 * Guarantees:
 *  - CSRF token always attached
 *  - Network failures never fail silently (thrown as AppError)
 *  - Non-2xx responses are normalized into AppError with a human message
 *  - Callers get { message, code, fields, status } consistently, whether
 *    the backend used ApiResponse::error(), Laravel's default validation
 *    error shape, or returned nothing parseable at all.
 */

export class AppError extends Error {
    constructor(message, code = 'ERROR', fields = null, status = 0) {
        super(message);
        this.name = 'AppError';
        this.code = code;
        this.fields = fields;
        this.status = status;
    }
}

function friendlyMessageForStatus(status) {
    switch (true) {
        case status === 0:
            return 'Unable to connect to the server. Please check your internet connection and try again.';
        case status === 401:
            return 'You need to login to continue.';
        case status === 403:
            return 'You don\'t have permission to perform this action. Please contact your administrator.';
        case status === 404:
            return 'The requested resource could not be found.';
        case status === 409:
            return 'This action could not be completed because of a conflicting change. Please refresh and try again.';
        case status === 419:
            return 'Your session has expired. Please refresh the page and try again.';
        case status === 422:
            return 'Some of the information provided is invalid. Please check the form and try again.';
        case status === 429:
            return 'Too many attempts. Please wait a moment and try again.';
        case status >= 500:
            return 'Something went wrong on our end. Please try again, and contact support if the problem continues.';
        default:
            return 'The request could not be completed.';
    }
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || null;
}

/**
 * apiFetch(url, options)
 * Same signature as fetch(), but throws AppError on any failure and
 * returns the parsed JSON body on success.
 */
export async function apiFetch(url, options = {}) {
    const isFormData = options.body instanceof FormData;
    const csrf = getCsrfToken();

    const headers = {
        Accept: 'application/json',
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        ...(options.headers || {}),
    };

    let response;
    try {
        response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers,
        });
    } catch (networkError) {
        // fetch() itself threw — DNS failure, offline, CORS, etc.
        throw new AppError(friendlyMessageForStatus(0), 'NETWORK_ERROR', null, 0);
    }

    let body = null;
    try {
        body = await response.json();
    } catch {
        body = null; // empty body / non-JSON response (e.g. some 500s, redirects)
    }

    if (!response.ok) {
        const message =
            body?.message ||
            (body?.errors ? Object.values(body.errors).flat()[0] : null) ||
            friendlyMessageForStatus(response.status);

        const code =
            body?.error_code ||
            body?.code ||
            (response.status === 422 ? 'VALIDATION_ERROR' : `HTTP_${response.status}`);

        const fields = body?.fields || body?.errors || null;

        throw new AppError(message, code, fields, response.status);
    }

    return body ?? {};
}

/** Convenience helpers */
export const apiGet = (url, options = {}) => apiFetch(url, { ...options, method: 'GET' });
export const apiPost = (url, data, options = {}) =>
    apiFetch(url, {
        ...options,
        method: 'POST',
        body: data instanceof FormData ? data : JSON.stringify(data ?? {}),
    });
export const apiPut = (url, data, options = {}) =>
    apiFetch(url, {
        ...options,
        method: 'PUT',
        body: data instanceof FormData ? data : JSON.stringify(data ?? {}),
    });
export const apiDelete = (url, options = {}) => apiFetch(url, { ...options, method: 'DELETE' });