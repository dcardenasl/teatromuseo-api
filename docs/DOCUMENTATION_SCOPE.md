# Documentation Scope Matrix

This matrix defines where each type of documentation belongs to avoid duplication.

## Canonical Sources

1. `docs/architecture/*`: Architecture contracts, invariants, layer patterns, request lifecycle.
2. `docs/tech/*`: Operational and subsystem implementation details (config, runtime behavior, troubleshooting).
3. `docs/template/*`: Governance contracts and quality gates for template adopters.
4. `docs/adr/*`: Architecture decision records.
5. `docs/AGENT_QUICK_REFERENCE.md`: Command and workflow cheat sheet.
6. `docs/DOCUMENTATION_SCOPE.md`: This scope matrix.

## Authoring Rules

1. Do not duplicate full technical implementations in `docs/tech/*` when `docs/architecture/*` already covers the rule.
2. For each new topic, define one canonical file and keep other files as concise references.
3. Keep EN/ES parity for all active docs in `docs/`.

## Practical Mapping

1. Queue worker configuration/troubleshooting: `docs/tech/QUEUE.md`.
2. Rate limiting internals and headers: `docs/tech/rate-limiting.md`.
3. API testing strategy and constraints: `docs/architecture/TESTING.md`.
4. API testing practical rules/checklists: `docs/tech/TESTING_GUIDELINES.md`.
