const API_BASE = '/api/v1';
const TOKEN_KEY = 'ecommerce_api_token';
const USER_KEY = 'ecommerce_api_user';

export function getStoredToken() {
    return localStorage.getItem(TOKEN_KEY) || '';
}

export function setStoredToken(token) {
    if (!token) {
        localStorage.removeItem(TOKEN_KEY);
        return;
    }

    localStorage.setItem(TOKEN_KEY, token);
}

export function getStoredUser() {
    const rawUser = localStorage.getItem(USER_KEY);
    if (!rawUser) return null;

    try {
        return JSON.parse(rawUser);
    } catch (error) {
        return null;
    }
}

export function setStoredUser(user) {
    if (!user) {
        localStorage.removeItem(USER_KEY);
        return;
    }

    localStorage.setItem(USER_KEY, JSON.stringify(user));
}

export function clearSession() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
}

export function buildApiUrl(path) {
    return `${API_BASE}${path}`;
}

export async function apiRequest(path, options = {}) {
    const token = getStoredToken();
    const headers = new Headers(options.headers || {});

    if (!(options.body instanceof FormData)) {
        headers.set('Accept', 'application/json');
        if (options.body !== undefined && options.body !== null) {
            headers.set('Content-Type', 'application/json');
        }
    }

    if (token) {
        headers.set('Authorization', `Bearer ${token}`);
    }

    const response = await fetch(buildApiUrl(path), {
        method: options.method || 'GET',
        headers,
        body: options.body instanceof FormData ? options.body : options.body !== undefined && options.body !== null ? JSON.stringify(options.body) : undefined,
        credentials: 'same-origin',
    });

    const text = await response.text();
    let payload = null;
    if (text) {
        try {
            payload = JSON.parse(text);
        } catch (error) {
            payload = { message: text };
        }
    }

    if (!response.ok) {
        const message = payload?.message || 'Request failed.';
        const errors = payload?.errors || null;

        if (response.status === 401 && token) {
            clearSession();
        }

        const error = new Error(message);
        error.status = response.status;
        error.errors = errors;
        throw error;
    }

    return payload;
}

export function getCurrentUser() {
    return getStoredUser();
}

export function isAuthenticated() {
    return !!getStoredToken() && !!getStoredUser();
}

export function isAdminUser() {
    return getCurrentUser()?.role === 'admin';
}
