# Architecture Decision Records (ADR)

This document explains the architectural decisions made in this project.

## 1. Domain-Driven Service Layer

**Decision:** Services are organized into functional domains to prevent "God Classes" and improve maintainability.

### Directory Structure
- `app/Services/{Domain}/`: Primary orchestrators.
- `app/Services/{Domain}/Support/`: Specialized logic (Mappers, Handlers, Managers).
- `app/Interfaces/{Domain}/`: Strict contracts for each domain.

### Domains defined:
- **Auth**: Login, Registration, OAuth, and Invitations.
- **Tokens**: JWT creation, Refresh Token rotation, and Revocation.
- **Users**: Identity management and RBAC.
- **Files**: Storage orchestration and file processing.
- **System**: Infrastructure (Audit, Email, Metrics).
- **Core**: Shared base classes.

## 2. Service Composition Pattern

**Decision:** Large services must be decomposed into specialized, single-responsibility components injected via constructor.

### Patterns in use:
- **Handlers**: Encapsulate complex multi-step processes (e.g., `GoogleAuthHandler`, `MultipartProcessor`).
- **Guards**: Centralize security and account policy assertions (e.g., `UserRoleGuard`, `UserAccountGuard`).
- **Mappers**: Handle transformation of entities to specific output arrays (e.g., `AuthUserMapper`).
- **Managers**: Orchestrate cross-service sessions or complex state (e.g., `SessionManager`).

## 3. Immutable Infrastructure (PHP 8.2+)

**Decision:** All Services and DTOs should be `readonly class` whenever possible.

**Benefits:**
- Prevents side effects during request execution.
- Ensures strict dependency injection via constructor.
- Improves static analysis (PHPStan).

## 4. Response Orchestration

**Decision:** The path from Service to HTTP Response is standardized via `ApiResult`.

### Flow:
`Service Method -> Returns DTO/OperationResult -> ApiController delegates to ApiResponse::fromResult() -> Returns ApiResult -> Controller renders JSON.`

- **`ApiResult`**: A Value Object carrying the `body` and `status`.
- **`ExceptionFormatter`**: Environment-aware transformation of exceptions into `ApiResult`.

## 5. DTO-First Development (The "Shield" Pattern)

**Decision:** Validation and Context Enrichment happen at the edge.

- **Self-Validation**: Handled in `BaseRequestDTO::__construct`.
- **Context Enrichment**: `ApiController` injects `userId` and `userRole` into DTO payloads via `withSecurityContext(...)` before DTO instantiation.
- **Immutability**: All DTOs are `readonly`.

---

## Summary of Modern Engineering Standards

1. **Dependency Injection**: No static service calls inside business logic. Use `Config/Services.php`.
2. **Domain Isolation**: Services from one domain should interact with others via Interfaces.
3. **Atomic Operations**: Use `HandlesTransactions` for any state change.
4. **Zero-Trust Input**: Every input must be a DTO.
5. **Observability**: Every state change is logged via `AuditService`.
