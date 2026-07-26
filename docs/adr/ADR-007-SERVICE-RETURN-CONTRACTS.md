# ADR-007: Service Return Contracts (DTO vs OperationResult)

## Status
Accepted

## Context

Service return types must stay predictable to avoid leaking HTTP concerns and to keep controller normalization simple and stable.

## Decision

1. Read/query operations return DTOs (`DataTransferObjectInterface`).
2. Command-style operations return `OperationResult` when outcome semantics matter (success/accepted/error + message/errors).
3. CRUD contract remains explicit:
- `index/show/store/update` -> DTO
- `destroy` -> `bool`
4. Response shaping remains centralized in `ApiController` + `ApiResponse::fromResult`.

## Consequences

### Positive
- Predictable service contracts and simpler API normalization pipeline.
- Better compatibility for generated modules and architecture tests.
- Reduced accidental coupling between business logic and transport semantics.

### Trade-offs
- Requires discipline when introducing new command-like methods.
- Some flows need explicit conversion between entity/model output and DTO/OperationResult.
