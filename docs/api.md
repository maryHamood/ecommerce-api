# API Inventory

## Authentication

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/verify-otp`
- `POST /api/v1/auth/resend-otp`
- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

## Products

- `GET /api/v1/products` (authenticated)
- `GET /api/v1/products/{product}` (authenticated)
- `POST /api/v1/admin/products` (admin)
- `PUT /api/v1/admin/products/{product}` (admin)
- `DELETE /api/v1/admin/products/{product}` (admin)

## Orders

- `POST /api/v1/orders` (customer)
- `GET /api/v1/my-orders` (customer)
- `GET /api/v1/orders/{id}` (customer, owner-only)
- `GET /api/v1/admin/orders` (admin)
- `PATCH /api/v1/admin/orders/{order}/status` (admin)

## Categories

- `GET /api/v1/categories` (authenticated)
- `POST /api/v1/admin/categories` (admin)

## Notifications

- `GET /api/v1/notifications` (authenticated user only)

## Security notes

- JWT is required for every protected route.
- Role middleware restricts customer/admin scopes.
- Only owners are allowed to view their own orders.
- Status changes are admin-only and validated server-side.
