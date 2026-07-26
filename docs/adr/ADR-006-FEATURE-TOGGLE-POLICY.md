# ADR-006: Feature Toggle Policy at HTTP Boundary

## Status
Accepted

## Context

Feature availability checks (for metrics/monitoring) were spread inside controllers, creating inconsistent behavior and duplicated policy logic.

## Decision

1. Feature toggles are enforced at HTTP boundary via filters.
2. Introduce a typed config object (`Config\FeatureFlags`) to centralize feature switches.
3. Use `FeatureToggleFilter` with route-level arguments (`featureToggle:metrics`, `featureToggle:monitoring`).
4. Controllers remain focused on request orchestration and business delegation only.

## Consequences

### Positive
- Uniform 503 behavior for disabled features.
- Centralized toggle policy and easier operational control.
- Less duplicated logic across controllers.

### Trade-offs
- Additional filter/config artifacts to maintain.
- Route definitions become slightly more verbose.
