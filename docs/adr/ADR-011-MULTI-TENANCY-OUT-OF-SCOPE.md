# ADR-011: Multi-tenancy is out of scope for v1.x

## Status
Accepted (audit B11.1, 2026-05-07)

## Context

Several projects derived from this kit have asked the same question: "can it host multiple tenants in a single deployment?" The answer has been "yes, with surgery." The audit (May 2026, finding F31) flagged that the kit doesn't say so explicitly — the silence reads as "we do support it" until a tenant scoping bug bites.

This ADR makes the position explicit so future contributors don't try to retrofit half a multi-tenant model and so future projects can pick the kit knowing what shape they're getting.

## Decision

**v1.x is single-tenant.** The data model, the authorization model, and the request lifecycle assume one organization per deployment.

Specifically:

- `users.email` has a single global unique index. Two tenants with the same admin email cannot coexist.
- `applications` exists in the schema (`code = 'self'` is the seeded row) but it is **not** a tenant scope — it's a permission-scoping concept that lets a single tenant host multiple registered apps (admin, mobile, third-party integrations) sharing the same user pool. Renaming it would invite the wrong assumption.
- `permissions.application_id` scopes permission codes per application, not per tenant.
- `BaseAuditableModel` has no tenant column; queries do not filter by tenant.
- Audit log entries do not record tenant context.
- File uploads default to a per-user scope (`FILES_USER_SCOPED=true`); they have no tenant scope at all.

If a project needs multi-tenancy, the supported path is to **fork** the kit (or generate a project from it via `new-project.sh`) and add a tenant column where appropriate. We do not promise a non-breaking upgrade path back from a tenanted fork.

## Consequences

### Positive

- Every layer stays simpler. No tenant-resolution middleware, no hidden global scopes on Eloquent-style models, no "did I forget the tenant filter on this query" review burden.
- The seeder, the bootstrap-superadmin command, the OpenAPI examples — all stay readable by someone seeing the kit for the first time.
- Audit log queries don't require an extra `WHERE tenant_id = ?` clause.

### Negative

- Teams that need multi-tenancy must do real work themselves. The kit gives them a clean starting point but not a shortcut.
- A future v2 that adds tenancy will be a major version bump with breaking schema migrations. Not a problem until it happens.

### Neutral

- The `applications` table can be repurposed as a tenant table by a determined fork, but this requires also adding a `tenant_id` to `users`, `audit_logs`, `files`, and probably `roles`/`role_permissions`. We don't recommend this — start fresh.

## What "fork properly" looks like (when needed)

For teams that decide they need it:

1. Add `tenant_id INT UNSIGNED NOT NULL` to `users`, `audit_logs`, `files`, and any other domain table.
2. Replace the global unique on `users.email` with `(tenant_id, email)`.
3. Introduce a `TenantContext` static (mirror of `ContextHolder`) populated from the JWT `tenant_id` claim.
4. Inject a `BaseAuditableModel::applyBaseCriteria()` override that scopes every query by `tenant_id`.
5. Update `EffectivePermissionsResolver` to filter by tenant, not just application.
6. Update `RbacBootstrapSeeder` to seed per-tenant roles or accept that roles are global.

That's roughly 2–3 weeks of careful work, plus its own test surface. Far cheaper if planned upfront than retrofitted.

## Pointers

- Audit finding F31 (May 2026) — "Multi-tenancy fuera de alcance pero no documentado explícitamente como single-tenant kit".
- README for derived projects should restate this position. Updated alongside this ADR.
