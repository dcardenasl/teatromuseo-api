# ADR-005: Service Purity and Dependency Injection Boundaries

## Status
Accepted

## Context

As the template evolved, service classes accumulated runtime coupling patterns (`env()`, `getenv()`, `Config\Services`), which made unit testing and behavior predictability weaker and increased architecture drift risk.

## Decision

1. `app/Services/*` must remain runtime-agnostic:
- no `Config\Services` calls
- no `env()` / `getenv()` access
2. Runtime/config resolution is allowed at boundaries only:
- `app/Config/Services.php`
- controllers / filters / commands
3. Services receive runtime-derived values via constructor injection.
4. Architecture guardrails enforce this with `ServicePurityConventionsTest`.

## Consequences

### Positive
- Better testability and determinism in service logic.
- Cleaner separation of concerns between domain and transport/runtime.
- Reduced long-term technical debt in downstream projects using this template.

### Trade-offs
- Slightly more wiring in `Config/Services.php`.
- More explicit constructor signatures to maintain.
