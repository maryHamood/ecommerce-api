'use strict';

const tokenKey = 'store-test-ui-token';
const element = (id) => document.getElementById(id);
let currentUser = null;
let productsPage = 1;
let adminPage = 1;

function feedback(message, isError = false) {
    element('feedback').textContent = message;
    element('feedback').classList.toggle('error', isError);
}

function clearSession() {
    sessionStorage.removeItem(tokenKey);
    currentUser = null;
    element('identity').textContent = 'Logged out';
    element('authenticated').hidden = true;
    element('logout').hidden = true;
    element('login-form').hidden = false;
    ['products', 'orders', 'admin-orders', 'notifications'].forEach((id) => element(id).replaceChildren());
}

async function api(url, method = 'GET', body = null, authenticated = true) {
    const headers = { Accept: 'application/json' };
    if (body !== null) headers['Content-Type'] = 'application/json';
    const token = sessionStorage.getItem(tokenKey);
    if (authenticated && token) headers.Authorization = 'Bearer ' + token;
    let response;
    try {
        response = await fetch(url, {
            method,
            headers,
            body: body === null ? null : JSON.stringify(body),
            credentials: 'omit',
        });
    } catch (error) {
        throw new Error('Network error (no HTTP status): ' + error.message);
    }
    const raw = await response.text();
    let payload;
    try { payload = JSON.parse(raw); } catch { payload = { message: raw || response.statusText }; }
    if (!response.ok) {
        if (response.status === 401 && authenticated) clearSession();
        const details = payload.errors ? '\n' + JSON.stringify(payload.errors, null, 2) : '';
        throw new Error('HTTP ' + response.status + ': ' + (payload.message || response.statusText) + details);
    }
    return payload;
}

async function run(action) {
    try { await action(); } catch (error) { feedback(error.message, true); }
}

function rows(id, items, columns) {
    const target = element(id);
    target.replaceChildren();
    if (!items.length) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = columns.length;
        cell.textContent = 'No records.';
        row.append(cell);
        target.append(row);
    }
    items.forEach((item) => {
        const row = document.createElement('tr');
        columns.forEach((column) => {
            const cell = document.createElement('td');
            cell.textContent = String(column(item) ?? '');
            row.append(cell);
        });
        target.append(row);
    });
}

function pagination(prefix, data) {
    element(prefix + '-page').textContent = 'Page ' + data.current_page + ' of ' + data.last_page;
    element(prefix + '-previous').disabled = data.current_page <= 1;
    element(prefix + '-next').disabled = data.current_page >= data.last_page;
}

async function loadProducts() {
    const result = await api('/api/v1/products?page=' + productsPage);
    rows('products', result.data.data, [(p) => p.id, (p) => p.name, (p) => p.price]);
    pagination('products', result.data);
}

async function loadOrders() {
    const result = await api('/api/v1/my-orders');
    rows('orders', result.data, [(o) => o.id, (o) => o.total, (o) => o.status]);
}

async function loadAdminOrders() {
    const result = await api('/api/v1/admin/orders?page=' + adminPage);
    rows('admin-orders', result.data.data, [(o) => o.id, (o) => o.user?.name || o.user_id, (o) => o.total, (o) => o.status]);
    pagination('admin', result.data);
}

async function loadNotifications() {
    const result = await api('/api/v1/notifications');
    const list = element('notifications');
    list.replaceChildren();
    if (!result.length) {
        const empty = document.createElement('li');
        empty.textContent = 'No notifications.';
        list.append(empty);
    }
    result.forEach((notification) => {
        const item = document.createElement('li');
        item.textContent = '#' + notification.id + ' | ' + notification.message + ' | ' + notification.createdAt;
        list.append(item);
    });
}

async function showSession() {
    const result = await api('/api/v1/auth/me');
    currentUser = result.data;
    productsPage = 1;
    adminPage = 1;
    element('identity').textContent = currentUser.name + ' | ' + currentUser.phone + ' | role: ' + currentUser.role;
    element('login-form').hidden = true;
    element('logout').hidden = false;
    element('authenticated').hidden = false;
    element('customer').hidden = currentUser.role !== 'customer';
    element('admin').hidden = currentUser.role !== 'admin';
    await loadProducts();
    if (currentUser.role === 'customer') await loadOrders();
    if (currentUser.role === 'admin') await loadAdminOrders();
    await loadNotifications();
    feedback('Logged in as ' + currentUser.name + '.');
}

element('login-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        const result = await api('/api/v1/auth/login', 'POST', {
            phone: element('phone').value,
            password: element('password').value,
        }, false);
        sessionStorage.setItem(tokenKey, result.data.token);
        element('password').value = '';
        await showSession();
    });
});

element('logout').addEventListener('click', () => run(async () => {
    try {
        await api('/api/v1/auth/logout', 'POST');
        feedback('Logged out.');
    } finally { clearSession(); }
}));

element('order-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        const result = await api('/api/v1/orders', 'POST', {
            items: [{ product_id: Number(element('product-id').value), quantity: Number(element('quantity').value) }],
        });
        await loadOrders();
        feedback('HTTP 201: Order #' + result.data.id + ' created.');
    });
});

element('status-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        const id = Number(element('admin-order-id').value);
        const result = await api('/api/v1/admin/orders/' + id + '/status', 'PATCH', { status: element('status').value });
        await loadAdminOrders();
        feedback('HTTP 200: Order #' + result.data.id + ' is now ' + result.data.status + '.');
    });
});

element('products-refresh').addEventListener('click', () => run(loadProducts));
element('orders-refresh').addEventListener('click', () => run(loadOrders));
element('admin-refresh').addEventListener('click', () => run(loadAdminOrders));
element('notifications-refresh').addEventListener('click', () => run(loadNotifications));
element('products-previous').addEventListener('click', () => run(async () => { productsPage--; await loadProducts(); }));
element('products-next').addEventListener('click', () => run(async () => { productsPage++; await loadProducts(); }));
element('admin-previous').addEventListener('click', () => run(async () => { adminPage--; await loadAdminOrders(); }));
element('admin-next').addEventListener('click', () => run(async () => { adminPage++; await loadAdminOrders(); }));

if (sessionStorage.getItem(tokenKey)) run(showSession);
