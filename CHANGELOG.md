# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`LegacyMigration` library + `legacy:dry-run` command** — dry-run engine for the legacy data
  migration (SQL dump reader, asset resolver, slice A analyzer, migration control tables). No
  destructive writes yet.
- **`legacy:dry-run` / `legacy:apply` slice C support** — `LegacySliceCAnalyzer` analyzes
  Museum/Press/Foundation legacy tables (`sn_expo`, `sn_noticias`, `sn_editorial`, `sn_prensa`,
  `sn_administracion`, `sn_upa`, `sn_funcionarios`, `sn_museo`), and both commands now accept
  `--slice C`.
- **`LegacyApplyService`** — CMS entries and blocks created during migration now get a
  translation per active CMS language instead of Spanish only, and entries are deduplicated by
  slug across collections to avoid duplicate creation on re-runs.
- **`DomainFileUsageClient`** — `FileService::destroy()`/`forceDestroy()` now check file usage
  across the CMS, catalog, and event domains (not just the Hub's own `file_references`) before
  deleting, and broadcast cache invalidation to those domains after a successful delete/replace.

### Fixed

- **`Config\Database`** — replaced the leftover starter-kit placeholder database name with the
  project's actual default (`teatromuseo_api`).
- **`LegacyHttpDomainClient`** — fixed a `curlrequest` service call that passed `null` where an
  array config was expected, silently breaking the shared-instance flag.
- **`PermissionUpdateRequestDTO`, `RoleUpdateRequestDTO`, `UserUpdateRequestDTO`** — update
  requests can now explicitly clear a nullable field to `null` instead of silently dropping it
  (the DTOs previously used `array_filter($v !== null)`, which made it impossible to clear a
  nullable column via `PUT`/`PATCH`).
