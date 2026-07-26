# ADR-009: Idempotency-Key support for state-changing requests

## Status
Accepted (audit B7.3, 2026-05-06)

## Context

State-changing HTTP requests (POST / PUT / PATCH / DELETE) suffer from a class of distributed-systems failures that are easy to ignore at low traffic and devastating at scale:

- A network blip between client and server. The client retries. **The server processed the original request successfully** but the client never received the 2xx. Now there are two of whatever resource was created.
- A SaaS integration (payments, HR feeds, mobile push delivery) cannot tolerate duplicate writes. The industry-standard mitigation, used by Stripe, AWS, GitHub, and others, is the `Idempotency-Key` request header.

Before this ADR, no API endpoint in this kit honored the header. A retry created duplicates silently.

## Decision

1. **Introduce an opt-in `IdempotencyFilter`** registered as alias `idempotency`. Routes that opt in get the contract; routes that don't are unaffected. **Not** wired into globals — a read-only endpoint never needs idempotency, and adding it everywhere would inflate the cache table needlessly.

2. **Storage in `idempotency_keys` table** (migration `2026-05-06-100000_CreateIdempotencyKeysTable`):
   - `idempotency_key` VARCHAR(64) PK — client-provided.
   - `actor_id` INT NULL — identifies the authenticated subject (NULL for anonymous / service tokens).
   - `endpoint` VARCHAR(255) — `METHOD path`, e.g. `POST /api/v1/users`.
   - `request_hash` CHAR(64) — SHA-256 of the request body.
   - `response_status`, `response_headers` (JSON), `response_body` — what we replay.
   - `expires_at` DATETIME — TTL = 24 hours (configurable in the filter).
   - Indexes on `expires_at` (for cleanup) and `(actor_id, endpoint)` (for the lookup path).

3. **Behavior matrix:**

   | Scenario | Filter response |
   |---|---|
   | No `Idempotency-Key` header on a method we honor | Pass-through (no extra DB work). |
   | Method not in {POST, PUT, PATCH, DELETE} | Pass-through. |
   | Header present, format invalid (length / charset) | `400` with `Validation.invalidIdempotencyKey`. |
   | Header valid, no cache hit | Forward to handler. On 2xx response, persist row in `after()`. |
   | Header valid, cache hit, same body hash | Replay cached `(status, headers, body)` + `Idempotent-Replay: true`. |
   | Header valid, cache hit, different body hash | `409 Conflict` with `Idempotency-Mismatch: true`. |
   | Handler returns 4xx/5xx | Pending row is **not** persisted (the client may legitimately retry against a different outcome). |

4. **Key format constraint:** `[A-Za-z0-9._:+\-]{8,64}`. Wide enough for UUIDv4, ULIDs, KSUID, and Stripe-style `sk_live_...` strings. Hard cap at 64 characters to keep the column tight and prevent storage abuse. Rejecting too-short keys (< 8) protects against accidentally-empty templates.

5. **Body hashing strategy:** SHA-256 of the raw request body. We hash AFTER `before()` reads the body, so it sees the bytes the client actually sent. Headers are explicitly NOT part of the hash — clients legitimately retry with a refreshed `Authorization` header on token rotation, and we want to replay anyway.

6. **Persistence happens in `after()`** to record the actual response. A small race window exists (two concurrent calls with the same key both miss the cache, both forward to the handler, both try to insert). The insert is wrapped in `try/catch` so the loser silently drops the duplicate-key error; the winner's response body wins. This is acceptable because:
   - The two responses are equivalent (same body hash).
   - Subsequent retries by either client will see the persisted row and replay it.

7. **In-flight state between `before()` and `after()`** is held in a `private static ?array $pending`. This is safe because PHP-FPM serves one request per worker process at a time. CI4 instantiates filters fresh per phase (`new $className()` in both `before` and `after`), so instance state is unreliable. The static carries the (key, hash, endpoint, actor) tuple across phases of the same request.

8. **Opt-in convention:** routes apply the filter via `['filter' => 'idempotency']` in their definition. The route owner consciously decides which mutations are idempotency-safe (most are; some, like fund transfers, are explicitly not — those should reject calls without the header outright, which is a future enhancement not in v1.0).

## Consequences

### Positive
- Network-retry safety for any opted-in route at the cost of one extra row + lookup per write.
- Industry-standard contract that integrators (mobile apps, third-party services) recognize from Stripe / AWS / GitHub.
- The replay path is cheap (single PK lookup, one row read) so opted-in endpoints stay fast under retry storms.
- Body-hash mismatch surfaces a real client bug (key reuse with different payload) instead of corrupting state.

### Negative
- Adds storage proportional to write traffic over 24h. At ~1 KB / row and 100 writes / second sustained, that's ~8.6M rows / day = ~8 GB / day. The cleanup job is mandatory at scale.
- The static `$pending` carries state across phases, which assumes single-threaded request handling. If we move to a Swoole / RoadRunner / Octane-style multi-request worker, this needs revisiting.
- Routes that need strict body-hash semantics on multipart uploads (where the same logical request can serialize differently) will see false-positive 409s. Multipart-heavy endpoints should opt out.

### Neutral
- Tests must reset `IdempotencyFilter::flushPending()` between calls in the same test method.

## Implementation pointers

- Migration: `app/Database/Migrations/2026-05-06-100000_CreateIdempotencyKeysTable.php`.
- Filter: `vendor/dcardenasl/ci4-api-core/src/Http/Filters/IdempotencyFilter.php`. Alias `idempotency` in `Config\Filters::$aliases`.
- Tests: `tests/Feature/Filters/IdempotencyFilterTest.php` — covers the full behavior matrix above, 6 cases.

## Future work

- **Cleanup job** (`php spark idempotency:gc`) deleting `WHERE expires_at < NOW()`. Cron-trigger every hour.
- **Per-route TTL configuration** (some endpoints want 5 minutes, some want 7 days). Pass via filter argument: `['filter' => 'idempotency:300']` for 5-minute TTL.
- **`Idempotency-Key` required** mode for high-stakes endpoints (e.g. money movement). A 422 if the header is missing on opt-in routes.
- Replace static `$pending` with a request-scoped service container slot when CI4 ships proper per-request DI (or sooner if we adopt Octane).
