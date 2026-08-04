# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **WebP upload support** — added `webp` to the documented file types accepted by the shared
  upload configuration.
- **Public-site cache permissions** — added `system.public-cache.read` and
  `system.public-cache.invalidate` to the RBAC bootstrap roles for secured cache monitoring
  and invalidation from administrative clients.

- **Legacy migration slices A–C** — migration services now cover courses, teachers, works,
  companies, videos, news, publications, institutional sliders, festival galleries, and the
  Anímate festival, including the associated dry-run/apply mappings and assets.
- **Legacy course media and staff enrichment** — course covers can be reconciled from gallery
  images, and staff entries now receive hover portraits during migration.
- **Cached file picker manifest** — added a cached manifest endpoint for efficient file-picker
  loading in administrative clients.

- **`LegacyMigration` library + `legacy:dry-run` command** — dry-run engine for the legacy data
  migration (SQL dump reader, asset resolver, slice A analyzer, migration control tables). No
  destructive writes yet.
- **`legacy:dry-run` / `legacy:apply` slice C support** — `LegacySliceCAnalyzer` analyzes
  Museum/Press/Foundation legacy tables (`sn_expo`, `sn_noticias`, `sn_editorial`, `sn_prensa`,
  `sn_administracion`, `sn_upa`, `sn_funcionarios`, `sn_museo`), and both commands now accept
  `--slice C`.
- **`legacy:dry-run` / `legacy:apply` slice D support** — `LegacySliceDAnalyzer` analyzes
  `sn_contact_message`/`sn_contact_status`, and `LegacyApplyService::applyContactMessages()`
  migrates the 157 legacy contact messages into cms-domain's `cms_form_submissions` via the new
  `POST /api/v1/cms/submissions/import` endpoint, preserving original `created_at`/`status`.
- **`LegacyApplyService`** — CMS entries and blocks created during migration now get a
  translation per active CMS language instead of Spanish only, and entries are deduplicated by
  slug across collections to avoid duplicate creation on re-runs.
- **`DomainFileUsageClient`** — `FileService::destroy()`/`forceDestroy()` now check file usage
  across the CMS, catalog, and event domains (not just the Hub's own `file_references`) before
  deleting, and broadcast cache invalidation to those domains after a successful delete/replace.
- **`LegacyApplyService`** — `sn_compania.director_compania` now maps into `compania_ficha.director`
  during migration instead of being dropped silently.
- **`LegacyApplyService::applyHomeSliderSlides()`** — migrates the 5 visible `sn_slider`
  ("Index" category) legacy home banners into the home page's existing `hero_slider` block as
  `slide_banner` children.
- **`LegacyApplyService::mapLegacySliderLink()`** — translates legacy `teatromuseo.cl` absolute
  URLs in `sn_slider.link` into internal `teatromuseo-web` paths (`/cartelera`, `/cursos`,
  etc.) instead of pointing the new site's own home banner at the old production domain.
- **`legacy:dry-run` / `legacy:apply` slice C** — now also migrates the Anímate festival
  (`sn_obra` where `url=animate`) into the `festivales` collection as its own entry, alongside
  the existing `sn_upa` festivals.

### Fixed

- **Public file URL resolution** — file responses now derive public URLs from storage paths
  consistently instead of relying on incomplete or environment-specific URL fields.

- **Legacy migration reconciliation** — later apply runs now repair missing work/event covers and
  event gallery references instead of leaving partially migrated media behind.
- **Legacy course identity and slugs** — separated `sn_cursos` from `sn_escuela` when coincidental
  IDs do not represent the same record, preserved base titles when supplement titles duplicate
  them, and hardened current-course slug generation.
- **Canonical TeatroEscuela target** — legacy course mappings and applied entries now target the
  `teatroescuela` CMS collection and use its canonical public route.

- **`Config\Database`** — replaced the leftover starter-kit placeholder database name with the
  project's actual default (`teatromuseo_api`).
- **`LegacyHttpDomainClient`** — fixed a `curlrequest` service call that passed `null` where an
  array config was expected, silently breaking the shared-instance flag.
- **`LegacyHttpDomainClient::upload()`** — built multipart uploads in Guzzle's list-of-`{name,
  contents, filename}` shape, but CI4's `CURLRequest` passes `config['multipart']` straight to
  `CURLOPT_POSTFIELDS`, which expects a flat `field => value` array with `CURLFile` for the file
  field. Every asset upload during `legacy:apply` failed with "No file was uploaded".
- **`LegacyApplyService::assetFile()`** — a single Hub-rejected upload (oversized file,
  unsupported mime type) aborted the entire `legacy:apply` slice instead of being recorded as an
  issue and letting the rest of the run continue.
- **`LegacyApplyService::applyNoticias()`** — filled a manual, redundant `rich_text` block with
  the article body while leaving the `noticias` template's required, auto-created primary
  `rich_text` block empty in every language.
- **`PermissionUpdateRequestDTO`, `RoleUpdateRequestDTO`, `UserUpdateRequestDTO`** — update
  requests can now explicitly clear a nullable field to `null` instead of silently dropping it
  (the DTOs previously used `array_filter($v !== null)`, which made it impossible to clear a
  nullable column via `PUT`/`PATCH`).
