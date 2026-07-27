# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`LegacyMigration` library + `legacy:dry-run` command** — dry-run engine for the legacy data
  migration (SQL dump reader, asset resolver, slice A analyzer, migration control tables). No
  destructive writes yet.

### Fixed

- **`Config\Database`** — replaced the leftover starter-kit placeholder database name with the
  project's actual default (`teatromuseo_api`).
