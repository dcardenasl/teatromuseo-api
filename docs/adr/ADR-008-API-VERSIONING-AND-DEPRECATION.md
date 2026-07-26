# ADR-008: API Versioning and Deprecation Policy

## Status
Accepted (audit B7.2, 2026-05-06)

## Context

The API has shipped with a `/api/v1/` URL prefix since day one, but no formal contract about:

- How long v1 is supported.
- When (and how) clients learn that v1 is moving to "deprecated" or "sunset" status.
- How to discover the current/successor version programmatically.

Without this, clients integrating against v1 have no way to plan migration. CDNs and API gateways (Cloudflare, AWS API Gateway, Kong) likewise can't auto-route based on version lifecycle.

## Decision

1. **Version metadata lives in `Config\Api::$apiVersions`.** A single source of truth, env-aware (deployments can override per-environment if needed). Each version key holds:
   - `status`: `'current' | 'deprecated' | 'sunset'`
   - `deprecated_at`: ISO 8601 date the version entered deprecation, or `null`
   - `sunset_at`: ISO 8601 date the version stops accepting traffic, or `null`
   - `successor`: the version that replaces this one (e.g. `'v2'`), or `null`

2. **Per-response signaling via `DeprecationHeadersFilter`.** The filter runs in `globals.after`, inspects the request path for `/api/<version>/`, and emits:
   - `Deprecation: <ISO 8601 date>` (IETF draft / aligns with the RFC 8594 family).
   - `Sunset: <ISO 8601 date>` (RFC 8594).
   - `Link: </api/<successor>>; rel="successor-version"` (RFC 5988) when the successor is set.

3. **Bulk discovery via `GET /api/versions`** (no version prefix — meta endpoint). Returns:
   ```json
   {
     "current": "v1",
     "versions": [
       { "version": "v1", "status": "current", "deprecated_at": null, "sunset_at": null, "successor": null }
     ]
   }
   ```
   Public, unauthenticated. Stable contract that clients can poll without authenticating, and that automated tooling can consume to render compatibility matrices.

4. **Lifecycle SLA defaults** (these go into deployment runbooks, not the code):
   - **Active support:** 18 months from version GA.
   - **Deprecation notice:** 6 months minimum before sunset.
   - **Sunset:** the version is removed entirely; requests return `410 Gone`.

5. **Breaking changes go to a new version, never v1.** Once v1 is GA (post-1.0.0 of this kit), no incompatible change to existing v1 endpoints. Additive changes (new fields, new optional query params) are fine within v1.

## Consequences

### Positive
- Clients can plan migrations from CI/CD by polling `/api/versions` weekly.
- API gateways can route requests to the live version automatically.
- Sunset enforcement is operationally clear (`410 Gone` after the date).
- Future v2 GA does not break v1 traffic mid-flight.

### Negative
- Adds a required-fill row in `Config\Api::$apiVersions` whenever a new version is cut.
- Maintenance burden: an actually deprecated version still requires keeping the `v1/*` route group + tests alive until sunset.
- Clients that ignore Deprecation/Sunset headers get no second chance once 410 starts.

### Neutral
- No automatic enforcement of the "successor must be `v(n+1)`" rule — that's policy, not constraint. The schema allows skipping versions if a deployment intentionally rebrands.

## Implementation pointers

- **Config:** `app/Config/Api.php` — `$apiVersions` array.
- **Filter:** `vendor/dcardenasl/ci4-api-core/src/Http/Filters/DeprecationHeadersFilter.php` — registered as alias `deprecationheaders` and wired in `globals.after` (after `secureheaders`, before `requestLogging`).
- **Endpoint:** `app/Config/Routes.php` — closure for `GET /api/versions` reading from `Config\Api`.
- **Tests:** `tests/Unit/Filters/DeprecationHeadersFilterTest.php` (filter behavior matrix), `tests/Feature/Controllers/ApiVersionsEndpointTest.php` (endpoint contract).

## Future work

- When v1 enters deprecation, write the "cut a new API version" runbook (B11.2, not yet authored — see `docs/runbooks/` for the existing numbered runbooks) to walk through: route group duplication → migration of code paths → CHANGELOG note → headers update → sunset removal.
- Consider exposing a `Warning` header as well (RFC 7234 §5.5) for richer free-text deprecation reasons. Out of scope for v0.x of this policy.
