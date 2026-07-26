# Pagination conventions

Audit B7.5 (2026-05-06): clarifies the `per_page` vs `limit` distinction across the API. The audit originally flagged the difference between `RoleIndexRequestDTO` (`per_page`) and `SlowRequestsQueryRequestDTO` (`limit`) as an inconsistency. After review, the two parameters are **semantically distinct** and should remain different — but the convention needs to be documented so future endpoints don't pick the wrong one.

## The two patterns

### `per_page` — paginated list endpoints

Use `per_page` (with companion `page` parameter) when the endpoint returns a navigable, page-based collection. The response carries pagination metadata so the client can fetch additional pages.

**Request shape:**
```
GET /api/v1/users?page=2&per_page=20
```

**Response shape:**
```json
{
  "status": "success",
  "data": [ ... ],
  "meta": {
    "total": 312,
    "per_page": 20,
    "page": 2,
    "last_page": 16,
    "from": 21,
    "to": 40
  }
}
```

**Used by:** `UserIndexRequestDTO`, `RoleIndexRequestDTO`, `ApplicationIndexRequestDTO`, `PermissionIndexRequestDTO`, `ApiKeyIndexRequestDTO`, `AuditIndexRequestDTO`, `FileIndexRequestDTO`.

**Rules:**
- Default `per_page = 20`.
- Hard ceiling `per_page <= 100` (200 for `applications` only — small dataset).
- `page` is 1-indexed.
- The DTO declares `public int $per_page;` with `is_natural_no_zero|less_than[101]` validation.

### `limit` — top-N cap endpoints

Use `limit` when the endpoint returns "the top N results by some ordering" and pagination is **conceptually wrong** — the consumer cannot ask for "page 2" of the top N. The endpoint is a cap, not a window.

**Request shape:**
```
GET /api/v1/admin/metrics/slow-requests?threshold=500&limit=10
```

**Response shape:**
```json
{
  "status": "success",
  "data": [ ... up to 10 entries ordered by latency desc ... ]
}
```

**Used by:** `SlowRequestsQueryRequestDTO`.

**Rules:**
- Default `limit` reflects the most useful "default top N" for the endpoint (10 for slow requests).
- Hard ceiling `limit <= 100`.
- No `page` parameter; no pagination metadata in the response.

## When to pick which

| Question | Answer |
|---|---|
| "Can a client meaningfully ask for page 2?" | Yes → `per_page`. No → `limit`. |
| "Does the response need a `total` so the client can render `1–20 of 312`?" | Yes → `per_page`. No → `limit`. |
| "Is the upstream collection small / capped by the endpoint design?" | `limit`. |
| "Is the upstream collection arbitrarily large and the client wants to walk it?" | `per_page`. |

## Anti-patterns

- **Don't use `limit`+`offset`.** Offset-based pagination through arbitrarily large collections has performance issues at scale (every request walks `N+offset` rows). For paginated endpoints, prefer `per_page` (offset-based today) and migrate to cursor-based pagination when a real signal arrives (audit open item B3, deferred).
- **Don't add `page` to a `limit` endpoint.** Either the endpoint is a top-N (use `limit` alone) or it's paginated (use `per_page` + `page`). Mixing the two confuses clients and forces the server to walk an arbitrarily deep result set.
- **Don't change a paginated endpoint to use `limit` "for consistency".** That's a breaking change. Stick to `per_page` for paginated endpoints, period.

## Future work

- Introduce a `BaseIndexRequestDTO` that factors out `per_page`, `page`, `search`, `sort`, `filter` from the seven Index DTOs that currently duplicate the same five properties. Ticket: not yet filed; trigger when a sixth Index DTO needs the same shape.
- Migrate offset-based pagination to cursor-based for `audit_logs`, `request_logs`, and `metrics` once they exceed ~1M rows or P95 list latency exceeds the SLO (audit B3, deferred).
