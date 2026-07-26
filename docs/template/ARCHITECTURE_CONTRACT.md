# Architecture Contract — pointer

> **Pinned version:** `dcardenasl/ci4-api-scaffolding` — check `vendor/dcardenasl/ci4-api-scaffolding/docs/ARCHITECTURE_CONTRACT.md` for the version matching your `composer.lock`.
>
> **The authoritative document lives in the `ci4-api-scaffolding` repo, not here.**
>
> - GitHub: https://github.com/dcardenasl/ci4-api-scaffolding/blob/main/docs/ARCHITECTURE_CONTRACT.md
> - In this monorepo (development): `../ci4-api-scaffolding/docs/ARCHITECTURE_CONTRACT.md`

This file used to be a maintained copy of the contract, which led to drift between the two
versions. The drift was identified in the May 2026 audit. To prevent future divergence:

- The package's copy is the single source of truth for module-level architecture rules.
- This stub stays in place so existing links (CLAUDE.md, README.md, etc.) keep working.
- Do **not** edit the package's copy from this repo — change it in `ci4-api-scaffolding`
  and bump the dependency.

Note: Composer's dist tarball strips the `docs/` directory, so the contract file is
**not** vendored into `vendor/dcardenasl/ci4-api-scaffolding/docs/`. Read it from the
GitHub URL above (or from `../ci4-api-scaffolding/docs/` when working in the monorepo).
