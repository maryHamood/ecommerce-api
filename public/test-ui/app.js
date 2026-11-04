'use strict';

const tokenKey = 'store-test-ui-token';
const statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
const element = (id) => document.getElementById(id);

let currentUser = null;
let productsPage = 1;
let adminPage = 1;
let categories = [];
let draft = [];
let pendingRegistration = null;
let editingProductId = null;

function feedback(message, isError = false) {
    element('feedback').textContent = message;
    element('feedback').classList.toggle('error', isError);
}

function clearSession() {
    sessionStorage.removeItem(tokenKey);
    currentUser = null;
    draft = [];
    element('identity').textContent = 'Logged out';
    element('authenticated').hidden = true;
    element('logout').hidden = true;
    element('guest').hidden = false;
    ['products', 'orders', 'admin-orders', 'notifications', 'draft'].forEach((id) => element(id).replaceChildren());
    ['product-details-section', 'edit-section', 'order-details', 'race-result'].forEach((id) => { element(id).hidden = true; });
}

// Sends one request and returns the status with the parsed body, without throwing on HTTP errors.
async function send(url, method = 'GET', body = null, authenticated = true) {
    const headers = { Accept: 'application/json' };
    const isForm = body instanceof FormData;
    if (body !== null && !isForm) headers['Content-Type'] = 'application/json';
    const token = sessionStorage.getItem(tokenKey);
    if (authenticated && token) headers.Authorization = 'Bearer ' + token;
    let response;
    try {
        response = await fetch(url, {
            method,
            headers,
            body: body === null || isForm ? body : JSON.stringify(body),
            credentials: 'omit',
        });
    } catch (error) {
        throw new Error('Network error (no HTTP status): ' + error.message);
    }
    const raw = await response.text();
    let payload;
    try { payload = JSON.parse(raw); } catch { payload = { message: raw || response.statusText }; }
    return { ok: response.ok, status: response.status, statusText: response.statusText, payload };
}

async function api(url, method = 'GET', body = null, authenticated = true) {
    const result = await send(url, method, body, authenticated);
    if (!result.ok) {
        if (result.status === 401 && authenticated) clearSession();
        const details = result.payload.errors ? '\n' + JSON.stringify(result.payload.errors, null, 2) : '';
        throw new Error('HTTP ' + result.status + ': ' + (result.payload.message || result.statusText) + details);
    }
    return result.payload;
}

async function run(action) {
    try { await action(); } catch (error) { feedback(error.message, true); }
}

function button(label, action) {
    const item = document.createElement('button');
    item.type = 'button';
    item.textContent = label;
    item.addEventListener('click', () => run(action));
    return item;
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
            const value = column(item);
            if (value instanceof Node) cell.append(value);
            else if (Array.isArray(value)) cell.append(...value);
            else cell.textContent = String(value ?? '');
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

function photo(url) {
    if (!url) return '';
    const image = document.createElement('img');
    image.src = url;
    image.alt = 'Product';
    image.className = 'thumb';
    return image;
}

function itemsText(order) {
    return (order.items || [])
        .map((item) => (item.product?.name || 'Product ' + item.product_id) + ' x' + item.quantity)
        .join(', ');
}

function categoryName(id) {
    return categories.find((category) => category.id === id)?.name || id;
}

function fillCategorySelect(id, firstOption) {
    const select = element(id);
    const selected = select.value;
    select.replaceChildren();
    if (firstOption !== null) select.append(new Option(firstOption, ''));
    categories.forEach((category) => select.append(new Option(category.name, category.id)));
    select.value = selected;
}

async function loadCategories() {
    const result = await api('/api/v1/categories');
    categories = result.data;
    fillCategorySelect('category-filter', 'All categories');
    fillCategorySelect('product-category', null);
    fillCategorySelect('edit-category', null);
}

function productActions(product) {
    const actions = [button('Details', () => showProduct(product.id))];
    if (currentUser.role === 'customer') {
        actions.push(button('Add to order', async () => addToDraft(product)));
    }
    if (currentUser.role === 'admin') {
        actions.push(button('Edit', async () => startEdit(product)));
        actions.push(button('Delete', () => deleteProduct(product)));
    }
    return actions;
}

async function loadProducts() {
    const category = element('category-filter').value;
    const query = 'page=' + productsPage + (category ? '&category_id=' + encodeURIComponent(category) : '');
    const result = await api('/api/v1/products?' + query);
    rows('products', result.data.data, [
        (p) => p.id,
        (p) => photo(p.photo),
        (p) => p.name,
        (p) => p.price,
        (p) => categoryName(p.category_id),
        productActions,
    ]);
    pagination('products', result.data);
}

async function showProduct(id) {
    const result = await api('/api/v1/products/' + id);
    const product = result.data;
    element('product-details').textContent = [
        'ID: ' + product.id,
        'Name: ' + product.name,
        'Price: ' + product.price,
        'Category: ' + categoryName(product.category_id),
        'Available stock (from inventory service): ' + product.amount,
    ].join('\n');
    element('product-photo').hidden = !product.photo;
    if (product.photo) element('product-photo').src = product.photo;
    else element('product-photo').removeAttribute('src');
    element('product-details-section').hidden = false;
    feedback('HTTP 200: Product #' + product.id + ' loaded.');
}

function renderDraft() {
    const target = element('draft');
    target.replaceChildren();
    if (!draft.length) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 4;
        cell.textContent = 'No products added.';
        row.append(cell);
        target.append(row);
        return;
    }
    draft.forEach((item) => {
        const row = document.createElement('tr');
        const quantity = document.createElement('input');
        quantity.type = 'number';
        quantity.min = '1';
        quantity.step = '1';
        quantity.value = item.quantity;
        quantity.addEventListener('change', () => { item.quantity = Number(quantity.value); });
        const remove = button('Remove', async () => {
            draft = draft.filter((entry) => entry !== item);
            renderDraft();
        });
        [item.product_id, item.name].forEach((value) => {
            const cell = document.createElement('td');
            cell.textContent = String(value);
            row.append(cell);
        });
        [quantity, remove].forEach((node) => {
            const cell = document.createElement('td');
            cell.append(node);
            row.append(cell);
        });
        target.append(row);
    });
}

function addToDraft(product) {
    const existing = draft.find((item) => item.product_id === product.id);
    if (existing) existing.quantity += 1;
    else draft.push({ product_id: product.id, name: product.name, quantity: 1 });
    renderDraft();
    feedback(product.name + ' added to the order.');
}

async function loadOrders() {
    const result = await api('/api/v1/my-orders');
    rows('orders', result.data, [
        (o) => o.id,
        (o) => o.total,
        (o) => o.status,
        itemsText,
        (o) => button('View', () => showOrder(o.id)),
    ]);
}

async function showOrder(id) {
    element('order-details').hidden = true;
    const result = await api('/api/v1/orders/' + id);
    const order = result.data;
    element('order-details').textContent = [
        'Order #' + order.id,
        'Status: ' + order.status,
        'Total: ' + order.total,
        'Items: ' + itemsText(order),
    ].join('\n');
    element('order-details').hidden = false;
    feedback('HTTP 200: Order #' + order.id + ' loaded.');
}

function statusControl(order) {
    const select = document.createElement('select');
    statuses.forEach((status) => select.append(new Option(status, status)));
    select.value = order.status;
    return [select, button('Save', () => changeStatus(order.id, select.value))];
}

async function changeStatus(id, status) {
    const result = await api('/api/v1/admin/orders/' + id + '/status', 'PATCH', { status });
    await loadAdminOrders();
    feedback('HTTP 200: Order #' + result.data.id + ' is now ' + result.data.status + '.');
}

async function loadAdminOrders() {
    const result = await api('/api/v1/admin/orders?page=' + adminPage);
    rows('admin-orders', result.data.data, [
        (o) => o.id,
        (o) => o.user?.name || o.user_id,
        (o) => o.total,
        (o) => o.status,
        itemsText,
        statusControl,
    ]);
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

function startEdit(product) {
    editingProductId = product.id;
    element('edit-id').textContent = '#' + product.id;
    element('edit-name').value = product.name;
    element('edit-price').value = product.price;
    element('edit-category').value = product.category_id;
    element('edit-file').value = '';
    element('edit-section').hidden = false;
    element('edit-section').scrollIntoView();
}

async function deleteProduct(product) {
    if (!confirm('Delete product "' + product.name + '"?')) return;
    await api('/api/v1/admin/products/' + product.id, 'DELETE');
    await loadProducts();
    feedback('HTTP 200: Product #' + product.id + ' and its inventory were deleted.');
}

async function showSession() {
    const result = await api('/api/v1/auth/me');
    currentUser = result.data;
    productsPage = 1;
    adminPage = 1;
    element('identity').textContent = currentUser.name + ' | ' + currentUser.phone + ' | role: ' + currentUser.role;
    element('guest').hidden = true;
    element('logout').hidden = false;
    element('authenticated').hidden = false;
    element('customer').hidden = currentUser.role !== 'customer';
    element('admin').hidden = currentUser.role !== 'admin';
    await loadCategories();
    await loadProducts();
    if (currentUser.role === 'customer') {
        renderDraft();
        await loadOrders();
    }
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

element('register-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        const result = await api('/api/v1/auth/register', 'POST', {
            name: element('register-name').value,
            phone: element('register-phone').value,
            password: element('register-password').value,
            password_confirmation: element('register-confirm').value,
        }, false);
        pendingRegistration = { phone: result.data.phone, token: result.data.registration_token };
        element('otp-info').textContent = 'Phone: ' + result.data.phone + ' | code valid for ' + result.data.expires_in + ' seconds';
        element('otp-section').hidden = false;
        element('register-password').value = '';
        element('register-confirm').value = '';
        feedback('HTTP 201: ' + result.message);
    });
});

element('otp-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        const result = await api('/api/v1/auth/verify-otp', 'POST', {
            phone: pendingRegistration.phone,
            code: element('otp-code').value,
            registration_token: pendingRegistration.token,
        }, false);
        sessionStorage.setItem(tokenKey, result.data.token);
        pendingRegistration = null;
        element('otp-code').value = '';
        element('otp-section').hidden = true;
        await showSession();
    });
});

element('otp-resend').addEventListener('click', () => run(async () => {
    const result = await api('/api/v1/auth/resend-otp', 'POST', { phone: pendingRegistration.phone }, false);
    feedback('HTTP 200: ' + result.message);
}));

element('logout').addEventListener('click', () => run(async () => {
    try {
        await api('/api/v1/auth/logout', 'POST');
        feedback('Logged out.');
    } finally { clearSession(); }
}));

element('order-submit').addEventListener('click', () => run(async () => {
    if (!draft.length) throw new Error('Add at least one product to the order.');
    const result = await api('/api/v1/orders', 'POST', {
        items: draft.map((item) => ({ product_id: item.product_id, quantity: item.quantity })),
    });
    draft = [];
    renderDraft();
    await loadOrders();
    feedback('HTTP 201: Order #' + result.data.id + ' created.');
}));

element('order-view-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(() => showOrder(Number(element('order-view-id').value)));
});

element('test-admin-orders').addEventListener('click', () => run(async () => {
    await api('/api/v1/admin/orders');
    feedback('Unexpected: the API allowed this call.', true);
}));

element('test-admin-status').addEventListener('click', () => run(async () => {
    await api('/api/v1/admin/orders/1/status', 'PATCH', { status: 'delivered' });
    feedback('Unexpected: the API allowed this call.', true);
}));

element('race-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        const body = {
            items: [{
                product_id: Number(element('race-product').value),
                quantity: Number(element('race-quantity').value),
            }],
        };
        const count = Number(element('race-count').value);
        const results = await Promise.all(Array.from({ length: count }, () => send('/api/v1/orders', 'POST', body)));
        const created = results.filter((result) => result.status === 201);
        const lines = results.map((result, index) => 'Request ' + (index + 1) + ': HTTP ' + result.status + ' | '
            + (result.payload.message || '') + (result.status === 201 ? ' | order #' + result.payload.data.id : ''));
        element('race-result').textContent = 'Succeeded: ' + created.length + ' of ' + count + '\n' + lines.join('\n');
        element('race-result').hidden = false;
        await loadOrders();
        feedback('Race test finished: ' + created.length + ' of ' + count + ' orders succeeded.');
    });
});

element('category-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        const result = await api('/api/v1/admin/categories', 'POST', { name: element('category-name').value });
        element('category-name').value = '';
        await loadCategories();
        feedback('HTTP 201: Category "' + result.data.name + '" created.');
    });
});

element('product-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        const form = new FormData();
        form.append('name', element('product-name').value);
        form.append('price', element('product-price').value);
        form.append('initial_stock', element('product-stock').value);
        form.append('category_id', element('product-category').value);
        const file = element('product-file').files[0];
        if (file) form.append('photo', file);
        const result = await api('/api/v1/admin/products', 'POST', form);
        element('product-form').reset();
        await loadProducts();
        feedback('HTTP 201: Product #' + result.data.id + ' created.');
    });
});

element('edit-form').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
        // PHP only reads multipart bodies on POST, so the PUT is sent as a POST with _method.
        const form = new FormData();
        form.append('_method', 'PUT');
        form.append('name', element('edit-name').value);
        form.append('price', element('edit-price').value);
        form.append('category_id', element('edit-category').value);
        const file = element('edit-file').files[0];
        if (file) form.append('photo', file);
        const result = await api('/api/v1/admin/products/' + editingProductId, 'POST', form);
        element('edit-section').hidden = true;
        await loadProducts();
        feedback('HTTP 200: Product #' + result.data.id + ' updated.');
    });
});

element('edit-cancel').addEventListener('click', () => { element('edit-section').hidden = true; });
element('category-filter').addEventListener('change', () => run(async () => { productsPage = 1; await loadProducts(); }));
element('products-refresh').addEventListener('click', () => run(loadProducts));
element('orders-refresh').addEventListener('click', () => run(loadOrders));
element('admin-refresh').addEventListener('click', () => run(loadAdminOrders));
element('notifications-refresh').addEventListener('click', () => run(loadNotifications));
element('products-previous').addEventListener('click', () => run(async () => { productsPage--; await loadProducts(); }));
element('products-next').addEventListener('click', () => run(async () => { productsPage++; await loadProducts(); }));
element('admin-previous').addEventListener('click', () => run(async () => { adminPage--; await loadAdminOrders(); }));
element('admin-next').addEventListener('click', () => run(async () => { adminPage++; await loadAdminOrders(); }));

if (sessionStorage.getItem(tokenKey)) run(showSession);
