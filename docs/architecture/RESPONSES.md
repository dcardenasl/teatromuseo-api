# API Response Format

The API follows a strict, predictable response format. All business endpoints return JSON wrapped in a standard structure, orchestrated via the `ApiResult` pipeline.

---

## The Response Pipeline

The path from Service to HTTP Response is standardized to ensure consistency across all domains.

1. **Service**: Returns a DTO, Entity, or `OperationResult`.
2. **Controller**: Delegates the result to `ApiResponse::fromResult()`.
3. **ApiResponse**: Normalizes the input into an **`ApiResult`** Value Object.
4. **ApiResult**: Encapsulates the final `body` (array) and `status` (int).
5. **Controller**: Renders the JSON and sets the HTTP status code.

---

## Automatic Normalization

`ApiResponse::fromResult()` is the intelligent factory that handles data transformation:
- **DTOs**: Recursively converted to arrays via `convertDataToArrays()`.
- **Pagination**: `PaginatedResponseDTO` is detected and wrapped in the canonical `data` + `meta` envelope.
- **Operations**: `OperationResult` states are mapped to semantic codes (200, 201, 202).
- **Booleans**: Mapped to success structures or `ApiResponse::deleted()`.

---

## Global Error Handling

Exceptions are automatically caught by `ApiController::handleException()` and processed by the **`ExceptionFormatter`**.

- **Production**: Sensitive debug info is stripped. A generic `Api.serverError` message is returned.
- **Development/Testing**: Full class names, file paths, line numbers, and stack traces are included in the JSON `errors` key.
- **Semantic Codes**: If an exception implements `HasStatusCode`, its code is respected.

---

## Core Structure

```json
{
  "status": "success",
  "message": "Optional human-readable message",
  "data": { /* Main payload */ },
  "meta": { /* Metadata, e.g., pagination */ }
}
```

---

## Contract Examples

### Standard Success (200 OK)
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "John"
  }
}
```

### Error (4xx/5xx)
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "email": "Email is already registered"
  },
  "code": 422
}
```

## Exceptions to This Contract

- Operational health endpoints use a flat monitoring payload.
- File downloads or streams return raw binary data.
