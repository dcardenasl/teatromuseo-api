# ADR-010: Optional RFC 7807 Problem Details for error responses

## Status
Accepted (audit B7.4, 2026-05-06)

## Context

The kit ships a default error envelope:

```json
{ "status": "error", "message": "...", "errors": { ... }, "code": 422 }
```

It works, all consumer apps recognize it, and `ApiResponse::error()` is well-tested. But it's not the contract that downstream tooling expects:

- **API gateways** (Cloudflare API Shield, AWS API Gateway, Kong, Apigee) parse `type` and `status` from RFC 7807 bodies to categorize errors and route problem-class metrics.
- **OpenAPI / Swagger code generators** template against `ProblemDetails` shapes when `application/problem+json` is declared in `produces`.
- **Enterprise integration contracts** frequently mandate RFC 7807 by name.

We don't want to break every existing consumer of the default envelope, but we want to be answer "yes" when an RFC-7807 client asks for it.

## Decision

1. **Add `ApiResponse::problemDetails()` as an additive builder.** Pure builder — no I/O, no opinions. Constructs a body that conforms to RFC 7807:

   ```json
   {
     "type": "https://example.com/errors/validation-failed",
     "title": "Validation failed",
     "status": 422,
     "detail": "...",
     "instance": "/api/v1/users",
     "errors": { "email": "required" }
   }
   ```

   - `type` defaults to `"about:blank"` per RFC 7807 §4.2 when no specific URI is supplied. Callers SHOULD provide a stable URI (typically the docs page that explains the error class) so clients can recognize and branch on it.
   - `errors` is an additive non-RFC field that preserves the per-field validation map. RFC 7807 explicitly allows extension members (§3.2), so this is compliant.

2. **Add `ApiResponse::negotiateError()` as the opt-in entry point.** Takes an `Accept` header value and returns either:
   - The legacy shape (`status` / `message` / `errors` / `code`) under `Content-Type: application/json`, OR
   - The 7807 shape under `Content-Type: application/problem+json`,

   based on whether the Accept header explicitly mentions `application/problem+json`. Returns a small `{body, content_type}` array so the calling controller can set the correct `Content-Type` header on the response.

3. **No globals, no implicit content-negotiation in the framework.** Existing call sites stay on `error()`. Controllers (or future filters) opt in deliberately by switching to `negotiateError()` when they want the dual-format behavior. This keeps the blast radius of the change zero for current consumers.

4. **`clientPrefersProblemJson()` helper** for when the calling code wants to make routing decisions itself (e.g. setting Content-Type before constructing the body via a different code path).

## Consequences

### Positive
- RFC-7807-aware integrators get a first-class contract by sending `Accept: application/problem+json`.
- Existing internal consumers (the admin starter, CLI tools, dev fixtures) see no change.
- Future controllers can adopt `negotiateError()` selectively — high-stakes endpoints first.
- `problemDetails()` is also useful by itself (e.g. for explicitly-7807 endpoints that don't need negotiation).

### Negative
- Two error shapes increase the testing surface: every error path that adopts negotiation needs one test for each shape.
- `clientPrefersProblemJson()` is a minimal q-aware parser, NOT a full RFC 7231 §5.3.2 implementation. Pathological Accept headers (e.g. `application/problem+json;q=0`) are ignored — the parser flips on any non-empty mention. Practical clients don't send such headers; if a real one ever surfaces, the parser is small enough to upgrade.

### Neutral
- The 7807 spec mandates `Content-Type: application/problem+json` on responses with that body shape. The opt-in caller is responsible for setting that header — `negotiateError()` only returns the body and the recommended content type as a hint.

## Implementation pointers

- Builder + negotiator: `vendor/dcardenasl/ci4-api-core/src/Http/ApiResponse.php` — methods `problemDetails`, `negotiateError`, `clientPrefersProblemJson`.
- Tests: `tests/Unit/Libraries/ApiResponseTest.php` — 6 cases covering the new builders.

## Future work

- Extend `ApiController::handleRequest()` (or a thin wrapper) to opt every controller into negotiation by default. Out of scope for v0.x of this policy — the per-route opt-in keeps the blast radius small until we see real consumer demand.
- Introduce a stable URI scheme for `type` (e.g. `https://api.example.com/errors/validation-failed`) and a docs page per type that documents the contract. Best done alongside the v2 cut.
- Adopt `application/problem+xml` if any consumer materially asks for XML. Not on the radar.
