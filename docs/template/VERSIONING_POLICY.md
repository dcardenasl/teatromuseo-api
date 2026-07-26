# Template Versioning Policy

This policy governs architecture and internal contract changes in this template.

## 1. Versioning Intent

1. HTTP external contract stability is prioritized for consumers.
2. Internal architecture contracts may evolve to reduce technical debt.
3. Any internal breaking change must be explicit and documented in release notes.

## 2. Change Classes

1. **Patch**: bug fix or non-breaking refactor.
2. **Minor**: additive capabilities, backward-compatible defaults.
3. **Major (internal)**: breaking internal contracts (services/interfaces/scaffold output) with migration notes.
4. **Major (external)**: breaking API contract changes (HTTP payloads/status/routes) — avoid unless explicitly approved.

## 3. Mandatory Disclosure

Every PR affecting contracts must include:

1. Affected files/interfaces.
2. Breaking impact (if any).
3. Migration actions for downstream projects.
4. Tests/guardrails updated.

## 4. Enforcement

1. `composer quality` must pass.
2. Architecture tests in `tests/Unit/Architecture` must pass.
3. Documentation EN/ES parity must pass.
