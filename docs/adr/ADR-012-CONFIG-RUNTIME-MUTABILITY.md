# ADR-012: Config values resolve at boot, not at runtime

## Status
Accepted (audit B11.1, 2026-05-07)

## Context

`Config\Api` (and similar config classes) read environment variables in their constructor:

```php
$this->jwtAccessTokenTtl = (int) $this->envValue('JWT_ACCESS_TOKEN_TTL', 3600);

private function envValue(string $key, $default = null)
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    return env($key, $default);
}
```

Two patterns to call out:

1. The use of `getenv()` first, `env()` second.
2. Properties are populated at construction time and kept as plain `public` properties (not `readonly`).

The May 2026 audit (finding F32) flagged this as "runtime config mutability via `getenv()`" — `getenv()` reads `$_SERVER`/`$_ENV` which can be mutated mid-request via `putenv()`. The audit asked whether that mutability is intentional.

This ADR resolves the question.

## Decision

**Config values are resolved at boot and treated as immutable for the lifetime of the request.**

- The constructor pattern (read env once, store in property) is the contract. Anyone reading `config('Api')->jwtAccessTokenTtl` at any later point in the request gets the value as it was when the Config singleton was first instantiated.
- `getenv()` vs `env()` is a minor implementation detail of CI4's bootstrap order: `getenv()` works during very-early phases when CI4's `Services::dotenv()` hasn't run yet (pre-boot CLI commands, e.g. `spark`-loaded scripts). `env()` is the same value in the steady state. The pair-with-fallback exists to make the same Config class work in both contexts.
- The `public` (non-`readonly`) property declaration is a CI4 convention so that Config classes can be `injectMock`-ed in tests; it is not an invitation to mutate values from application code.

**Application code MUST NOT call `putenv()` to change config at runtime.** Doing so does not affect already-instantiated singletons and produces subtle bugs where one path sees the old value and another sees the new one.

**Operators MUST treat changes to `.env` (or the systemd `Environment=` directives, k8s `env:` sections, etc.) as a deploy-time event.** A reload (`systemctl reload php-fpm`, pod restart) is required to pick them up.

## Consequences

### Positive

- Reasoning about config is local — read the constructor, you know the shape. No spooky action-at-a-distance.
- Tests can override config cleanly via `Factories::injectMock('config', 'Api', $instance)` (see `DeprecationHeadersFilterTest` for an example) without worrying about `putenv()` racing them.
- Caching consumers that hold a reference to the Config object (e.g. resolvers cached for 60 seconds) don't risk mid-cache config drift.

### Negative

- "Hot-reload config" workflows (changing TTLs without a process restart) are not supported. Operators who want this need to add their own runtime-mutable layer (e.g. a database-backed `RuntimeConfig` repository) on top of static config — not flag it on as an env trick.
- `putenv()` in tests has to be paired with a Config singleton reset, flushed via `Factories::reset('config')` or `Services::resetSingle()`.

### Neutral

- Some Config properties remain non-`readonly` to preserve CI4's `injectMock` ergonomic. Application code that mutates them is doing something wrong; treat it as a code-review smell.

## Pointers

- `app/Config/Api.php` — the constructor pattern this ADR documents.
- `tests/Unit/Filters/DeprecationHeadersFilterTest.php` — example of correct test-time override via `Factories::injectMock`.
- Audit finding F32 (May 2026) — the prompt for this ADR.
