# Runbook 01 — Rotate the JWT signing secret

**Severity:** High (signing-key compromise) | **ETA:** ~15 minutes (with brief auth-disrupted window) | **Audit:** B11.2

## When to use

- A laptop with `.env` was lost / compromised.
- A copy of `JWT_SECRET_KEY` leaked to a non-prod surface (logs, screenshot, third-party support thread).
- Routine annual rotation (recommended).

## Pre-flight checks

```bash
# 1. Confirm a quorum of admin users exists. After rotation, every active
#    JWT becomes invalid; users will be re-logging-in cold.
mysql -e "SELECT COUNT(*) FROM users WHERE status='active';" "$DB_NAME"

# 2. Generate a candidate new secret. Treat the output as sensitive —
#    do NOT log it, do NOT paste it to chat.
NEW_SECRET="$(openssl rand -base64 64 | tr -d '\n')"
echo "Length: ${#NEW_SECRET}"   # must be >= 64 bytes
```

## Procedure

### Step 1 — Stage the new secret

Update the production `.env` (or the equivalent k8s `Secret` / Vault entry):

```dotenv
JWT_SECRET_KEY="$NEW_SECRET"
```

> **Do not deploy yet.** The next step verifies the value is well-formed before any traffic hits it.

### Step 2 — Validate

```bash
# In the staging pod / a one-off container, run env:check with the new secret.
JWT_SECRET_KEY="$NEW_SECRET" php spark env:check --strict
```

Expect: "All required environment variables are present and well-formed."

If the command refuses (length < 64, placeholder substring, etc.), regenerate and retry.

### Step 3 — Roll the deployment

```bash
# Kubernetes
kubectl rollout restart deployment/ci4-api-starter

# systemd
systemctl reload php-fpm

# Docker compose
docker compose up -d --force-recreate api
```

### Step 4 — Verify auth works on the new secret

```bash
# A login should succeed and return a JWT signed with the new secret.
curl -sX POST "$API_URL/api/v1/auth/login" \
  -H 'Content-Type: application/json' \
  -d '{"email":"smoketest@admin","password":"...."}' | jq -r .data.access_token

# Decode the JWT header to confirm it's HS256 and the kid (if used) matches.
```

### Step 5 — Communicate

- All active users will be force-logged-out on the next request (their old JWT will fail signature validation; the API returns 401 → admin redirects to /login).
- Post a brief notice in the operator channel: "JWT secret rotated at &lt;timestamp&gt;. Users may need to log in again."
- Do **not** publish the rotation reason if it was a leak — that's an incident-response concern handled separately.

## Rollback

If Step 4 reveals the new secret is wrong (typo, encoding issue):

1. Restore the previous value from your secret store (Vault, AWS Secrets Manager, k8s `Secret` revision).
2. Re-roll: `kubectl rollout restart` or equivalent.
3. Investigate the bad-secret cause before retrying.

**Never** keep both old and new secrets active in parallel — the JWT layer doesn't support kid-based rotation in v1.x and overlapping secrets means whoever signed last wins.

## Post-mortem checklist (only if rotating because of a leak)

- [ ] Identify how the secret leaked (logs / screenshot / chat / repo).
- [ ] If repo-leaked: rewrite git history (`git filter-repo`) or rotate again on the assumption that the leaked value is in someone's clone.
- [ ] Audit `audit_logs` for any `auth.login` events that look anomalous in the leak window.
- [ ] Consider whether refresh tokens issued during the leak window need to be revoked (`token_revocations` table).
- [ ] Update `.env.example` placeholder if the leak revealed a misleading default.
