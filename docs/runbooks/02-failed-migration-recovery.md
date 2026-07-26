# Runbook 02 — Recover from a failed migration

**Severity:** High (production may be in mixed-schema state) | **ETA:** 5–60 minutes depending on the migration | **Audit:** B11.2

## When to use

- `php spark migrate` exited non-zero on a production deploy.
- A k8s deploy job pod is in `Error` status with the migration command in its logs.
- A user reports queries failing with "unknown column" or "table doesn't exist" right after a deploy.

## Critical first action — stop new deploys

```bash
# Kubernetes
kubectl scale deployment/ci4-api-starter --replicas=0   # stop new traffic
# OR pin to the last good image tag if pods are healthy mixed with broken ones

# Disable CI auto-deploy temporarily
gh workflow disable cd.yml -R dcardenasl/ci4-api-starter
```

A second `migrate` attempt from CI/CD while you investigate makes things worse.

## Diagnose

### Step 1 — Inspect the migration log

```bash
# CI4 records every applied migration. The last row is what we know ran;
# the migration after that is the suspect.
mysql -e "
  SELECT version, class, group, namespace, time, batch
  FROM migrations
  ORDER BY id DESC
  LIMIT 5;
" "$DB_NAME"
```

Compare with `app/Database/Migrations/` to identify what was intended next.

### Step 2 — Identify what actually got applied

Two failure modes:

- **DDL failure** (e.g. CREATE TABLE failed mid-way). DDL is non-transactional in MySQL — schema changes from earlier statements in the same migration may have committed. Inspect with `SHOW TABLES`, `DESCRIBE <table>`, `SHOW INDEX FROM <table>`.
- **Data backfill failure** (e.g. `2026-05-03-100004_MigrateMembershipRolesToUserRoles`). These migrations wrap their work in `transStart()`/`transComplete()` — if the body throws, the transaction rolls back cleanly. Confirm by counting rows in the affected tables.

### Step 3 — Pick the recovery path

| Symptom | Path |
|---|---|
| Migration row absent + no schema artifacts | **Re-run** — the migration was rolled back cleanly. |
| Migration row absent + partial schema artifacts | **Manual cleanup** then re-run. Drop the partial table/column before retrying. |
| Migration row PRESENT + schema clearly broken | **Down + up.** The migration's `down()` should reverse the change. After it succeeds, fix the migration code and re-run. |
| `down()` doesn't exist or fails | **Manual repair.** Apply the missing schema by hand from the migration's intended end state, then `INSERT INTO migrations` to mark it done. |

## Recovery

### Path A: Clean re-run

```bash
php spark migrate
mysql -e "SELECT class FROM migrations ORDER BY id DESC LIMIT 3;" "$DB_NAME"
# Confirm the failing migration is now in the table.
```

### Path B: Manual cleanup, then re-run

```bash
mysql "$DB_NAME"
> -- Inspect what got created
> SHOW CREATE TABLE the_partial_table;
> -- Drop / un-add as needed
> DROP TABLE the_partial_table;
> -- Repeat for any indexes / foreign keys
EXIT;

php spark migrate
```

### Path C: Down then up

```bash
php spark migrate:rollback -b $(mysql -Nse "SELECT MAX(batch) FROM migrations;" "$DB_NAME")
# Inspect: the rolled-back migration should be GONE from the migrations table.
mysql -e "SELECT class FROM migrations ORDER BY id DESC LIMIT 3;" "$DB_NAME"

# Now fix the migration code (if it had a bug), redeploy the binary,
# and run forward.
php spark migrate
```

### Path D: Manual repair (last resort)

Only when `down()` is unavailable / unsafe. **Document every SQL statement you run** so the post-mortem can capture what state production was in.

```bash
mysql "$DB_NAME"
> -- Apply the migration's intended end state by hand.
> ALTER TABLE users ADD COLUMN ... ;

> -- Mark the migration as done so CI4 doesn't re-attempt.
> INSERT INTO migrations (version, class, group, namespace, time, batch)
> VALUES ('2026-05-03-100007', 'App\\Database\\Migrations\\DropMembershipsTables', 'default', 'App', UNIX_TIMESTAMP(), <next_batch>);
EXIT;
```

## Restore traffic

```bash
# Verify the schema matches the binary running in `latest`.
php spark migrate:status

# Bring traffic back.
kubectl scale deployment/ci4-api-starter --replicas=$DESIRED_REPLICAS
gh workflow enable cd.yml -R dcardenasl/ci4-api-starter
```

## Post-mortem checklist

- [ ] Why did the migration fail? Locking conflict? FK-against-non-existent-target? Bad SQL?
- [ ] Was the failure reproducible on staging? If not, what differed?
- [ ] Add a test that would have caught it (likely an `Integration` test that runs the migration against a fresh DB).
- [ ] If the migration was non-idempotent and the recovery required hand SQL, file an issue: "harden migration X with explicit `dropIfExists` / `addColumn-if-not-present` checks."
- [ ] Update this runbook with anything new.
