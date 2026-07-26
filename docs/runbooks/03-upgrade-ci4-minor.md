# Runbook 03 — Upgrade CodeIgniter 4 to a new minor version

**Severity:** Low (planned change) | **ETA:** 30–90 minutes | **Audit:** B11.2

## When to use

- A new CI4 minor (`4.6.x`, `4.7.x`, ...) shipped a feature you want, or addresses a CVE.
- Periodic dependency hygiene: at most one minor behind for security-supported windows.

Not for **major** upgrades (5.x). Those need their own playbook with API audits.

## Pre-flight

```bash
# 1. Read the upstream changelog & migration guide.
#    https://github.com/codeigniter4/CodeIgniter4/blob/develop/CHANGELOG.md
#    https://codeigniter.com/user_guide/installation/upgrading.html

# 2. Note the CHANGELOG entries that touch areas the kit uses heavily:
#    - HTTP\Filters
#    - Validation
#    - Session\Handlers
#    - Database\BaseBuilder
#    - Test\FeatureTestTrait

# 3. Check the kit's pinning surface.
grep -A1 '"codeigniter4/framework"' composer.json
# Expected: "^4.5"  (or whatever the current floor is)
```

## Procedure

### Step 1 — Branch and bump

```bash
git switch -c chore/ci4-upgrade-4-6
composer require "codeigniter4/framework:^4.6" --update-with-dependencies
git diff composer.lock | head -50
```

### Step 2 — Run the gate locally

```bash
composer quality   # phpstan + cs-check + tests + arch-drift
```

Common breakages and fixes:

| Symptom | Likely cause | Fix |
|---|---|---|
| PHPStan errors on `getMethod()` return type | CI4 changed an HTTP type | Update type hints in `app/HTTP/ApiRequest.php` if needed. |
| `composer audit` flags a transitive dep | CI4 bumped a vendored library | Inspect with `composer why <package>`; usually safe. |
| Validation error keys renamed | `Validation` core revamped | Run `composer i18n-check` — it'll flag missing keys; copy from the upstream `system/Language/en/Validation.php`. |
| Filter `before()` signature changed | Rare across minors but possible | Update each `App\Filters\*Filter` to match the new interface. |
| Session driver constants moved | Namespace shuffle | Update `App\Config\Session` imports (audit B10.3 already references the post-shuffle layout). |

### Step 3 — Fix what breaks, re-run quality

Loop until `composer quality` is green. **Do not silence PHPStan errors** to make the upgrade pass; either fix the cause or move to the baseline if it's a known framework noise pattern.

### Step 4 — Smoke test against a fresh install

```bash
# In a scratch directory:
bash <(curl -fsSL https://raw.githubusercontent.com/dcardenasl/ci4-starter-kit/main/new-project.sh)
# Confirm the generated project boots, migrates, and serves /health.
```

### Step 5 — Update CHANGELOG and CLAUDE.md

```markdown
## [Unreleased]

### Changed
- **CodeIgniter framework bumped to ^4.6** — picks up <upstream highlight>.
  No application-level breaking changes; PHPStan baseline unchanged.
```

Update the version pin reference in `CLAUDE.md` if it mentions `^4.5` explicitly.

### Step 6 — PR

```bash
gh pr create \
  --base dev \
  --title "chore(deps): bump codeigniter4/framework to ^4.6" \
  --body 'See CHANGELOG.md [Unreleased] entry. Tested manually with `new-project.sh`.'
```

## Rollback

If a regression surfaces post-merge:

```bash
git revert <merge-commit-sha>
composer install
composer quality
```

The lockfile pin makes this clean. The migration table is unaffected (CI4 minors don't change the schema of `migrations`).

## Post-mortem checklist (only on regression)

- [ ] What exactly broke? File path + symptom.
- [ ] Was it caught by `composer quality` or only in production?
- [ ] If only in production: what test would catch it next time?
- [ ] Does the fix belong in `app/` or in a kit-level workaround?
