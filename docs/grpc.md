# gRPC and Protobuf Contract

The contract is defined in `protos/inventory.proto` and is consumed by the Laravel API using the generated PHP client under `app/Grpc/*`.

## Operations

- `CheckStock`
- `ReserveStock`
- `ReleaseStock`
- `InitInventory`
- `DeleteInventory`

## Message Types

- `StockRequest` / `StockResponse`
- `ReserveRequest` / `OperationResponse`
- `ReleaseRequest` / `OperationResponse`
- `InitRequest` / `OperationResponse`
- `DeleteRequest` / `OperationResponse`

## Inventory Service Responsibilities

- Validate stock availability
- Reserve all items atomically in one transaction
- Roll back on failure
- Release previously reserved stock when the main order write fails

## Notes

The Go service runs independently and owns a separate database from the Laravel app. The PHP app calls it over gRPC rather than reading inventory tables directly.
