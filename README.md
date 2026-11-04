# E-commerce API

This project is a multi-service e-commerce API built with Laravel and a Go-based gRPC inventory service. The application separates user-facing commerce workflows from inventory reservations and uses JWT for auth, MySQL for persistence, and Docker Compose for local orchestration.

## Tech stack

- Laravel 12 + PHP 8.3
- MySQL 8 for the core application database
- Go inventory service with gRPC
- MySQL 8 for inventory persistence
- JWT via `tymon/jwt-auth`
- Docker Compose for local development

## Architecture overview

- Main API handles authentication, products, categories, orders, and notifications.
- Inventory service owns the stock ledger and reservation logic.
- The API does not read or write inventory tables directly.
- Inventory operations use the protobuf contract in `protos/inventory.proto`.

See [docs/architecture.md](docs/architecture.md) and [docs/grpc.md](docs/grpc.md) for the detailed design.

## Quick start

```bash
docker compose up -d
php artisan test
```

## Common commands

```bash
docker compose build app inventory-service
docker compose up -d
docker compose ps
docker compose logs -f app inventory-service
php artisan route:list
php artisan test
```

## API overview

- Auth endpoints: `/api/v1/auth/*`
- Products: `/api/v1/products` and `/api/v1/admin/products`
- Orders: `/api/v1/orders`, `/api/v1/my-orders`, `/api/v1/admin/orders`
- Notifications: `/api/v1/notifications`

See [docs/api.md](docs/api.md) for the full inventory.

## Security and operational notes

- JWT auth is enforced on protected routes.
- Customer/admin separation is enforced by middleware.
- Order ownership checks prevent customer IDOR access.
- Security headers are added to responses.

See [docs/security.md](docs/security.md) and [docs/docker.md](docs/docker.md).

## Verification

The current project test suite passes with:

```bash
php artisan test
```

This project is actively validated using the Laravel test suite and Docker Compose checks.
