# Architecture Overview

This project is a Laravel 12 API that coordinates customer and administrative workflows for e-commerce operations, while delegating stock checks and reservations to a separate Go gRPC microservice.

## Modules

### 1. Laravel API

- Entry point: `app/Http/Controllers/Api/*`
- Authentication: JWT via `tymon/jwt-auth`
- Authorization: custom `RoleMiddleware`
- Data access: Eloquent models and migrations
- API response formatting: `App\Helpers\ApiResponse`

### 2. Inventory microservice

- Runtime: Go
- Transport: gRPC
- Persistence: dedicated MySQL database
- Responsibilities: stock lookup, reservation, release, initialization

### 3. Shared contract

- Contract file: `protos/inventory.proto`
- Generated PHP client: `app/Grpc/Inventory/*`

## Dependency Diagram

```text
Client
  |
  v
Laravel API
  |  HTTP/JSON
  +--> MySQL (users, orders, products, notifications)
  |  gRPC client
  v
Inventory Service (Go)
  |  MySQL (inventory table + reservation audit)
  +--> gRPC on :50051
```

## Responsibilities

- Laravel API owns user identity, order lifecycle, notifications, and catalog operations.
- Inventory Service owns stock availability and atomic reservation logic.
- The main app does not access the inventory database directly.
