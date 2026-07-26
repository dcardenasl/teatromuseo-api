# Release procedure — ci4-api-starter

This document describes how to publish a new release of `ci4-api-starter`. The repo is versioned **only by git tags** (`composer.json` does not carry a `version` field — it is `type: project`). A tag push on `main` triggers `.github/workflows/release.yml`, which extracts the matching `## [VERSION]` block from `CHANGELOG.md` and creates the corresponding GitHub Release.

## Pre-flight checklist

Before tagging, every item below must be true. Treat any "no" as a blocker.

1. **`dev` is green on CI.** `.github/workflows/ci.yml` is passing on the latest `dev` commit (matrix PHP 8.2 / 8.3 + MySQL 8.0, `composer quality`, coverage gate).
2. **Working tree is clean.** `git status --porcelain` returns nothing on `dev`.
3. **Local quality gate passes.**
   ```bash
   COMPOSER_PROCESS_TIMEOUT=1800 composer quality
   vendor/bin/phpunit
   php spark swagger:generate
   git diff --exit-code public/swagger.json     # zero drift
   ```
4. **`CHANGELOG.md` has a dated `## [X.Y.Z]` section** at the top (under `## [Unreleased]`, which should be empty). The version string in the heading must match the tag you will push (without the `v` prefix — `2.0.0`, not `v2.0.0`).
5. **`composer.json` constraints for first-party packages are published versions, not `dev-main`.** Before tagging:
   ```bash
   grep -E '"dcardenasl/(ci4-api-core|ci4-api-scaffolding)"' composer.json
   ```
   The output must show concrete constraints (e.g. `"^0.4"`), never `"dev-main"`. The workspace's path repository remains in place for local development, but the published constraint is what downstream consumers will resolve via Packagist.
6. **`init.sh` end-to-end smoke** is green on a clean clone (Docker MySQL path):
   ```bash
   cd /tmp && rm -rf api-smoke && git clone --depth 1 -b dev <repo> api-smoke && cd api-smoke
   bash init.sh
   ```
   Verifies `env:check`, migrations, RBAC seeder, swagger generation, and superadmin bootstrap.

For a major release (`X.0.0`), also confirm:

- The `### ⚠️ Breaking Changes` and `### Migration Guide` blocks in the `[X.0.0]` section accurately describe the upgrade path from the previous minor.
- Any ADRs introduced this cycle are listed under `### Added`.
- The `[X.0.0]: …compare/vX-1.Y.Z...vX.0.0` link at the bottom of the `CHANGELOG.md` resolves on GitHub.

## Release steps

The branching model is `dev → main → tag`. Tags are always cut from `main`.

1. **On `dev`, land the release-marker commit.** This commit only finalises `CHANGELOG.md` (rename `[Unreleased]` → `[X.Y.Z] — YYYY-MM-DD`, add a fresh empty `[Unreleased]` on top) and any final `composer.json` constraint swaps. No code changes in this commit.
   ```bash
   git checkout dev
   git pull --ff-only
   # Edit CHANGELOG.md + composer.json
   git add CHANGELOG.md composer.json
   git commit -m "chore: release vX.Y.Z"
   git push origin dev
   ```
2. **Merge `dev` into `main`.** Open a PR and merge fast-forward (or via a merge commit, depending on repo policy). Do not squash — the release marker commit should survive.
   ```bash
   # Via the GitHub UI (preferred) or:
   git checkout main && git pull --ff-only
   git merge --ff-only dev
   git push origin main
   ```
3. **Tag and push.** The tag must be created **from `main`**, not from `dev`. The workflow checks out the tag at the matching commit.
   ```bash
   git checkout main
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```
4. **Watch the workflow.** `.github/workflows/release.yml` will:
   - Check out the tag.
   - Run an inline `awk` over `CHANGELOG.md` to extract the body between `## [X.Y.Z]` and the next `## [` heading.
   - Create the GitHub Release with that body as the release notes. If the release already exists (re-tag scenario), it edits the existing one instead of failing.
5. **Verify the release page.** Open `https://github.com/dcardenasl/ci4-api-starter/releases/tag/vX.Y.Z` and confirm the notes match the `[X.Y.Z]` block of `CHANGELOG.md`. If the workflow extracted an empty body, the most likely cause is that the heading does not match the tag exactly — check for stray trailing spaces or wrong version-string casing.

## Post-release

- Confirm `[Unreleased]` exists on `dev` and is empty so the next cycle has a clean target.
- If downstream consumers (admin starter, kickstart, generated projects) need to bump their constraints to the new version, open the matching PRs.
- Update `TASKS.md` (workspace-level and per-repo) to close any items the release shipped.

## Rollback

A tag push triggers the release workflow exactly once. If the release notes are wrong, **prefer editing the GitHub Release directly** (the workflow re-runs are idempotent on re-tag and will overwrite the notes from `CHANGELOG.md`).

A bad tag can be retracted with:
```bash
git tag -d vX.Y.Z
git push --delete origin vX.Y.Z
```
This is only safe if **no downstream has pulled the tag yet**. Once a tag is consumed by Composer/Packagist, by another repo's CI, or by a contributor's clone, retraction can leave inconsistent state — prefer a follow-up `vX.Y.(Z+1)` patch release with a corrective `CHANGELOG.md` entry.

## Notes specific to this repo

- **`composer.json` first-party constraint swap.** During the v2.0 cycle, `composer.json` was kept on `"dev-main"` for `dcardenasl/ci4-api-core` and `dcardenasl/ci4-api-scaffolding` so the workspace path-repository symlinks would resolve. Before the v2.0.0 tag, those constraints must be flipped to the published Packagist versions. This is the one manual step the release procedure does not automate — it depends on which `ci4-api-core` / `ci4-api-scaffolding` version the v2.0.0 of this starter pins to.
- **Swagger drift.** `composer quality` runs `swagger-validate` (regenerates and `git diff --exit-code`s `public/swagger.json`). A drift here is always a missing `php spark swagger:generate` before commit, never the release procedure itself.
- **Coverage gate.** Currently a soft-fail (`continue-on-error: true`) in CI. Releases are not blocked by coverage drops, but the line-coverage % printed by `coverage:check` should not regress materially between minor versions.
