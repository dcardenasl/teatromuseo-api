# Audit Operations Runbook

Operational guide for audit retention and critical security alerts.

## 1. Retention Cleanup (`audit:clean`)

Command:

```bash
php spark audit:clean [days]
```

- `days` is optional.
- If omitted, it uses `AUDIT_RETENTION_DAYS` (default `90`).

Examples:

```bash
php spark audit:clean
php spark audit:clean 90
php spark audit:clean 180
```

### Recommended cron (daily at 02:15)

```cron
15 2 * * * cd /path/to/ci4-api-starter && /usr/bin/php spark audit:clean >> /var/log/ci4/audit-clean.log 2>&1
```

### Recommended cron (hourly in high-volume environments)

```cron
0 * * * * cd /path/to/ci4-api-starter && /usr/bin/php spark audit:clean >> /var/log/ci4/audit-clean.log 2>&1
```

## 2. Critical Alert Rules

Monitor these events from `audit_logs`:

1. `authorization_denied_role` or `authorization_denied_resource`
- Severity: `critical`
- Trigger: `>= 5` events from same `user_id` or IP in 10 minutes.

2. `api_key_auth_failed`
- Severity: `critical`
- Trigger: `>= 10` events from same key prefix or IP in 10 minutes.

3. `api_key_rate_limit_exceeded`
- Severity: `warning`
- Trigger: sustained growth for same key/IP for 15 minutes.

4. `revoked_token_reuse_detected`
- Severity: `critical`
- Trigger: any single event.

5. `login_failure` / `password_reset_token_invalid` / `email_verification_failed`
- Severity: `warning`
- Trigger: anomalous spike per account or IP in 15 minutes.

## 3. Triage Checklist (First 15 Minutes)

1. Confirm alert validity:
- Check `request_id`, `created_at`, `action`, `result`, `severity`, `ip_address`, `user_id`.

2. Identify blast radius:
- Count affected users/resources.
- Identify if activity is isolated or distributed.

3. Validate active threat:
- Review current events for same IP, account, key prefix, token `jti`.

## 4. Containment Actions

1. For API key abuse:
- Disable API key (`is_active = 0`).
- Rotate and reissue key.

2. For token abuse:
- Revoke current token.
- Revoke all user tokens when needed.

3. For account abuse:
- Force password reset.
- Temporarily block account if policy requires it.

4. For role abuse attempts:
- Verify admin session source and MFA posture.

## 5. Evidence & Forensics

Preserve:

1. Relevant `audit_logs` rows (`request_id`, IP, actor, metadata).
2. Matching request logs and application logs.
3. Timeline with UTC timestamps.

## 6. Incident Closure Template

1. Summary:
- What happened and when.

2. Scope:
- Impacted users/resources and duration.

3. Root cause:
- Credential leak, abuse, configuration issue, etc.

4. Actions taken:
- Revocations, blocks, rotations, policy updates.

5. Follow-ups:
- Tests added, thresholds tuned, runbook improvements.
