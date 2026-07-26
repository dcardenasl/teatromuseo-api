# Architecture Drift Guide

## Purpose
Explain how to interpret and remediate failures reported by `composer arch-drift` or the CI guardrails.

## Guardrails covered
- Controller pipeline (`ApiController` conventions, DTOs, route coverage).
- Service purity (`ServicePurityConventionsTest`, avoids `env()`/`Config\Services` in services).
- Feature flags (`FeatureToggleFilter`, `FeatureFlags` config).
- CRUD contracts and `OperationResult`.

## Running the check
1. Run `composer arch-drift`.
2. The script executes architecture tests + i18n parity docs.
3. If it fails, note the specific violation (test output includes file path + reason).

## Common failures & fixes
- **Missing DTO handleRequest snippet**: ensure controller uses `handleRequest(..., RequestDTO::class)` instead of re-implementing request parsing.
- **Service purity violation**: move `env()`/`Config\Services` calls into `Config/Services`, pass values via constructor.
- **Route coverage missing**: register the controller route in `app/Config/Routes.php`.
- **Feature toggle policy flagged**: ensure `featureToggle:...` applied to route and `FeatureFlags` config toggles exist.

## Escalation/practice
1. Add regression tests (unit/feature) covering the scenario once fixed.
2. Update `docs/architecture/README.md` or relevant ADR if the architecture decision has changed.
3. Reference this guide in PRs touching controllers/services/filters to show compliance.
