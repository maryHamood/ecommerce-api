# Docker Guide

## Start

```bash
docker compose up -d
```

## Stop

```bash
docker compose down
```

## Rebuild

```bash
docker compose build app inventory-service
```

## Inspect services

```bash
docker compose ps
docker compose logs -f app inventory-service
```

## Validate health

```bash
curl http://localhost:8000/up
```

## Database access

- Main app MySQL: `localhost:3307`
- Inventory MySQL: `localhost:3306` (inside Docker network) or `inventory-db:3306` for service-to-service communication

The compose file defines a dedicated network for both services and their databases.
