# File Storage

File management follows a decomposed architecture to handle multiple input types and storage drivers seamlessly.

Key Components:
- **`app/Services/Files/FileService.php`**: Orchestrates storage and database persistence. Owns the soft-delete / restore / force-delete lifecycle (see below).
- **`app/Interfaces/Files/FileRepositoryInterface.php`**: Standardizes metadata retrieval and persistence; adds `findIncludingTrashed()` and `purge()` for trash-aware reads/writes.
- **`app/Libraries/Files/MultipartProcessor.php`**: Handles standard HTTP file uploads.
- **`app/Libraries/Files/Base64Processor.php`**: Decodes and validates Data URIs and raw Base64.
- **`app/Libraries/Files/StorageKeyGenerator.php`**: Generates opaque, collision-resistant storage keys for persisted files.
- **`app/Support/Files/ProcessedFile.php`**: Standardized value object for stream-based transfers.

The database keeps the user-facing `original_name` intact and stores the physical object key separately as `stored_name`/`path`. The physical key is opaque, date-partitioned, and derived from a short content hash plus randomness so it does not depend on the original filename.

Storage Drivers (`app/Libraries/Storage/`):
- **LocalDriver**: Stores files in `writable/uploads/`.
- **S3Driver**: Integrates with AWS S3 using flysystem.

Environment Variables:
- `FILE_STORAGE_DRIVER`: `local` or `s3`.
- `FILE_MAX_SIZE`: Limit in bytes.
- `FILE_ALLOWED_TYPES`: Comma-separated extensions (e.g., `jpg,png,pdf`).
- `FILE_DEFAULT_VISIBILITY`: Default visibility stored with uploads when a caller does not provide one.
- `FILE_ALLOWED_VISIBILITY`: Comma-separated allow-list for accepted visibility values.
- `FILE_USER_SCOPED_FILES`: `false` exposes all files to authenticated readers; `true` restores owner scoping.
- `FILE_ALLOW_PRIVILEGED_READ_BYPASS`: only relevant when `FILE_USER_SCOPED_FILES=true`. Defaults to
  `true` — a caller holding `files.read` can view/download files they don't own, bypassing the
  per-user scoping. This is intentional for a CMS where staff routinely need to read files uploaded
  by other users (see `FilePolicyService::canBypassOwnershipForRead()`), not an oversight. Set to
  `false` if a deployment needs strict per-owner isolation even for privileged roles.
- `FILE_ALLOW_PUBLIC_VISIBILITY`: `true` allows trusted callers to persist public uploads.

Validation:
All file operations use DTO-based validation. The processors ensure that files are structurally sound and safe before the `FileService` attempts persistence.

---

## Soft Delete & Trash

Files use CI4's native soft-delete mechanism. The `files` table carries two
trash-related columns (migration `2026-05-17-045115_AddSoftDeleteToFilesTable`):

- `deleted_at` — nullable `DATETIME`. `null` = live file; non-null = in trash.
- `deleted_by_user_id` — nullable `INT UNSIGNED`. Records who trashed it; cleared on
  restore. No FK because SQLite (used by the test harness) does not support adding
  FKs to existing tables — integrity is enforced at the service layer.

`FileModel::$useSoftDeletes = true` so `find()`, listings, and `paginateCriteria()`
exclude trashed rows by default.

### Lifecycle

| Action | Endpoint | DB effect | Storage effect |
|---|---|---|---|
| Trash | `DELETE /api/v1/files/{id}` | sets `deleted_at`, `deleted_by_user_id` | **preserved** — bytes still on disk |
| Restore | `POST /api/v1/files/{id}/restore` | clears `deleted_at`, `deleted_by_user_id` | n/a |
| Force delete | `DELETE /api/v1/files/{id}/force` | row purged | bytes removed from storage |

Calling `DELETE /files/{id}` on an already-trashed file returns **404** — the file is
already invisible to default queries (intentional REST semantics).
`POST /restore` and `DELETE /force` on a non-trashed file return **400**.

### Listing the trash

The `GET /api/v1/files` endpoint accepts a `trashed` query parameter:

- `trashed=without` (default) — only live files.
- `trashed=only` — only trashed files (trash bin view).
- `trashed=with` — both, useful for admin tools that show everything.

Anything else falls back to `without`.

### Bulk endpoints

For trash UI multi-select:

- `POST /api/v1/files/bulk-delete` `{ "ids": ["1", "2", "3"] }` — bulk trash.
- `POST /api/v1/files/bulk-restore` — bulk restore.
- `POST /api/v1/files/bulk-force-delete` — bulk permanent delete.

Each returns a per-item outcome so partial successes are reportable to the UI:

```json
{
  "status": "success",
  "data": [
    { "id": 1, "ok": true },
    { "id": 2, "ok": true },
    { "id": 999, "ok": false, "error": "File not found" }
  ]
}
```

**Note on `ids` typing.** Send ids as **strings**, not integers, in the JSON body.
CI4's global `InvalidChars` filter recurses into the request body and calls
`mb_check_encoding()` on each leaf; raw integers trigger a `TypeError`. The DTO
casts strings back to `int` internally. The admin's `FileApiService::bulk*` already
stringifies. Tracked as `SEÑAL-API-001` in `TASKS.md`.

### Authorization

Authorization is action-based and centralized in `FilePolicyService`; there is no
caller-supplied ownership-bypass flag.

- `files.read` permits read actions (`view`, `download`, and `view_usages`). When
  `FILE_ALLOW_PRIVILEGED_READ_BYPASS=true`, it may bypass ownership for those
  read actions only.
- `files.write` permits uploads and mutations of files owned by the caller.
- `files.admin` permits mutations of files owned by any user.
- `force-delete` follows the same owner/write or cross-owner/admin policy, while
  the route still requires `files.write` as a coarse permission gate.

`delete`, `restore`, `replace`, `update_metadata`, and `regenerate_variants`
never treat `files.read` as a write or ownership bypass. Denied attempts are
written to the audit log with action-specific codes such as
`unauthorized_file_delete`, `unauthorized_file_replace`, and
`unauthorized_file_update_metadata`.
