# Contributing to ci4-api-starter

## Overview

This is the REST API template of the ci4-starter-kit. Changes here become the baseline for every project generated with `new-project.sh`.

**Do not use this repo for application work** — always work in the generated copy.

## Development Setup

```bash
git clone https://github.com/dcardenasl/ci4-api-starter.git
cd ci4-api-starter
composer install
cp .env.example .env
# Edit .env: set DB credentials, JWT_SECRET_KEY, encryption.key
php spark migrate
php spark serve   # http://localhost:8080
```

## Branching Strategy

- `main` — stable, tagged releases only. No direct commits — PRs only.
- `dev` — integration branch for the next release.
- Feature branches: `feat/description`, `fix/description`, `docs/description`

Always branch off `dev`, not `main`.

## Commit Conventions

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add retry logic to token refresh
fix: correct TypeMapper for bool fields
docs: update scaffolding examples
chore: upgrade PHPUnit to v11
```

## Quality Gates

Before opening a PR, all checks must pass:

```bash
composer quality   # PHPStan + PHP-CS-Fixer check + PHPUnit + Swagger validation
```

Fix style automatically with:

```bash
composer cs-fix
```

## Versioning

This project uses [Semantic Versioning](https://semver.org/):

- **MAJOR** — breaking changes to the generated project structure, DTOs, or API contracts
- **MINOR** — new features or non-breaking additions (new endpoints, new scaffolding capabilities)
- **PATCH** — bug fixes, documentation updates, dependency bumps

## Release Process

Releases are always cut from `main`. Since `main` only accepts merges via PR, the changelog update **must happen on `dev` as the last commit before opening the PR**.

### Step-by-step

1. **On `dev`, prepare the release commit:**

   a. In `CHANGELOG.md`, rename `[Unreleased]` to `[x.y.z] — YYYY-MM-DD` and add a fresh empty `[Unreleased]` section above it. Update the footer comparison links.

   b. Commit:
   ```bash
   git commit -m "chore: release vx.y.z"
   ```

2. **Open the PR `dev → main`.**

3. **After the PR is merged, tag `main`:**
   ```bash
   git checkout main
   git pull origin main
   git tag vx.y.z
   git push origin vx.y.z
   ```

> **Never tag on `dev`** — tags mark stable releases and belong on `main` after the merge.

## Pull Request Checklist

- [ ] Branch is off `dev`
- [ ] All quality checks pass (`composer quality`)
- [ ] `CHANGELOG.md` updated under `[Unreleased]` (or promoted to a version if this is a release PR)
- [ ] No sensitive data (credentials, tokens, `.env`) committed
- [ ] `CLAUDE.md` updated if architecture patterns or commands changed
- [ ] New scaffolding behavior validated with `php spark module:check`

## Reporting Issues

Open an issue in this repository. Include:
- PHP and CodeIgniter 4 version
- Steps to reproduce
- Expected vs actual behaviour
- Error messages or logs (redact any credentials)
