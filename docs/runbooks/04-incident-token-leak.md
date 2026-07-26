# Runbook 04 — Incident response: JWT or refresh token leak

**Severity:** Critical | **ETA:** 15 minutes for blast-radius containment, hours for full audit | **Audit:** B11.2

## When to use

- A user reports their account doing things they didn't do.
- A specific JWT or refresh token surfaces in a place it should not be (logs, error reports, screenshot, public repo).
- Anomalous `audit_logs` entries: same `user_id`, two different IPs in the same minute.

## Phase 1 — Containment (target: 15 min)

### Step 1 — Revoke the token(s)

If you have the JWT or its `jti` claim:

```bash
# Decode the token to get its `jti` (JWT ID).
echo "$TOKEN" | cut -d. -f2 | base64 -d 2>/dev/null | jq -r .jti

# Revoke it.
mysql "$DB_NAME" -e "
  INSERT INTO token_revocations (jti, user_id, revoked_at, reason)
  VALUES ('<jti>', <user_id>, NOW(), 'incident-response: leaked token');
"
```

The next request bearing this JWT will fail at `JwtAuthFilter`'s revocation check. The `Config\Api::$jwtRevocationCacheTtl` (default 60s) means up to 60 seconds of grace; for a hot incident, bust the cache:

```bash
# Clear the JWT revocation cache so the new revocation is visible immediately.
php spark cache:clear
```

If you only have the `user_id` (e.g. you don't have the leaked token's contents):

```bash
# Revoke ALL of this user's refresh tokens. They'll be forced to re-authenticate.
mysql "$DB_NAME" -e "
  UPDATE refresh_tokens
  SET revoked_at = NOW(), revoked_reason = 'incident-response: blanket revoke'
  WHERE user_id = <user_id> AND revoked_at IS NULL;
"
# Access tokens still in flight expire on their own JWT_ACCESS_TOKEN_TTL
# (default 1 hour). For complete invalidation in <60s, also rotate the
# JWT signing secret (see runbook 01).
```

### Step 2 — Suspend the user (if account compromise suspected)

```bash
mysql "$DB_NAME" -e "
  UPDATE users
  SET status = 'suspended', suspended_at = NOW(), suspended_reason = 'incident-response'
  WHERE id = <user_id>;
"
```

`UserAccountGuard::assertCanAuthenticate()` rejects suspended users at every login attempt; combined with revoking tokens this fully locks the account out.

### Step 3 — Capture audit trail

```bash
# Snapshot relevant audit_logs to a file so the rest of the incident can
# happen without disturbing the data.
mysql "$DB_NAME" -e "
  SELECT * FROM audit_logs
  WHERE user_id = <user_id>
    AND created_at >= NOW() - INTERVAL 7 DAY
  ORDER BY created_at DESC
" > /tmp/incident-<ticket-id>-audit.tsv

# Same for refresh tokens.
mysql "$DB_NAME" -e "
  SELECT * FROM refresh_tokens
  WHERE user_id = <user_id>
" > /tmp/incident-<ticket-id>-refresh-tokens.tsv
```

Move both to a long-term incident store (S3 bucket + KMS encryption is fine).

## Phase 2 — Investigation

### Reconstruct the timeline

```sql
-- All authenticated actions by the user, with IP and event.
SELECT
  created_at,
  ip_address,
  user_agent,
  event_type,
  resource,
  resource_id,
  metadata
FROM audit_logs
WHERE user_id = <user_id>
  AND created_at >= NOW() - INTERVAL 30 DAY
ORDER BY created_at DESC
LIMIT 500;
```

Look for:

- IPs the user doesn't normally use (compare against historical login locations).
- Operations the user wouldn't normally perform (admin role assignments, API key creation, file deletes).
- Two-IP-same-minute pattern → strong signal of session theft.

### Check for downstream artifacts

```sql
-- Did the leaked session create new API keys?
SELECT id, name, prefix, created_at, revoked_at
FROM api_keys
WHERE created_by_user_id = <user_id>
  AND created_at >= '<incident_window_start>';
-- Revoke any suspicious ones (UPDATE api_keys SET revoked_at = NOW()).

-- Did it modify any users?
SELECT user_id, event_type, created_at
FROM audit_logs
WHERE actor_user_id = <user_id>
  AND event_type IN ('user.create', 'user.update', 'user.role-assign')
  AND created_at >= '<incident_window_start>';

-- Did it download files?
SELECT file_id, created_at
FROM audit_logs
WHERE actor_user_id = <user_id>
  AND event_type = 'file.download'
  AND created_at >= '<incident_window_start>';
```

## Phase 3 — Containment of root cause

The leaked token is a symptom; investigate how it got out.

### Common causes and responses

| Root cause | Action |
|---|---|
| Token visible in HTTP access logs | Audit `Config\Logger::$threshold`. Confirm `JwtAuthFilter` and `RequestLoggingFilter` redact `Authorization`. Tighten if not. |
| Token in error report (Sentry / similar) | Sentry's PHP SDK should redact `Authorization` by default; verify in `MonologHandler`. Add `data_scrubber` config if missing. |
| User pasted token to support chat / public repo | Education + this runbook published. No tech fix beyond rapid revocation. |
| Token survived suspicious refresh window | Investigate `RefreshTokenService::rotate()` — confirm the rotated token revokes its parent atomically. |
| Brute-force / credential stuffing led to legit login | Check `auth_login_failed` counts in `audit_logs`. Tighten `AuthThrottleFilter` thresholds if needed. |

### Decide whether to rotate the global secret

If the leaked token signing secret might also have leaked (e.g. the leak was a `.env` file, not just the resulting JWT), follow **runbook 01** to rotate `JWT_SECRET_KEY`. This force-logs-out every user, which is acceptable for a confirmed secret compromise.

## Phase 4 — Recovery

Once the root cause is contained:

```bash
# Re-enable the user (if suspension was preventive).
mysql "$DB_NAME" -e "
  UPDATE users
  SET status = 'active', suspended_at = NULL, suspended_reason = NULL
  WHERE id = <user_id>;
"
```

Communicate with the user:

- "We detected unusual activity on your account at <time>."
- "We have invalidated your sessions; please log in again."
- "Recommend changing your password and enabling 2FA (when 2FA ships)."
- Do **not** disclose internal investigation details.

## Phase 5 — Post-mortem

Within 5 business days of containment, a written post-mortem covering:

1. **Timeline** — when the leak happened, when we noticed, when we contained.
2. **Root cause** — single sentence.
3. **Damage** — what the attacker did with the access. Reference the audit logs captured in Phase 1.
4. **Action items** — what we change so it doesn't repeat. At minimum: a regression test for the leak vector + a review of related code paths.
5. **Customer notice** — whether GDPR / CCPA / sectoral regulation requires disclosure.

Add the post-mortem to `docs/runbooks/incidents/<YYYY-MM-DD>-<short-name>.md`. Even if redacted, keeping the structure on file makes future ones faster.
