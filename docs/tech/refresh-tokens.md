# Refresh Tokens

Refresh tokens are stored hashed in the database and rotated on use. Every
login starts a refresh-token family; every rotation creates a child row and
marks its parent as rotated.

Key files:
- `app/Services/Tokens/RefreshTokenService.php`
- `app/Models/RefreshTokenModel.php`
- `app/Validations/TokenValidation.php`
- `app/Database/Migrations/2026-01-29-205207_CreateRefreshTokensTable.php`
- `app/Database/Migrations/2026-08-12-130000_HardenTokenLifecycle.php`

Environment variables:
- `JWT_REFRESH_TOKEN_TTL`

Validation:
- Actions `token:refresh` and `token:revoke` require `refresh_token` with rule `valid_token[64]`.
- Invalid token format is treated as request validation error.

Notes:
- Tokens live in the `refresh_tokens` table.
- Refresh uses a DB transaction and row lock to avoid race conditions.
- `family_id` and `parent_id` preserve the rotation lineage; the raw token is
  never persisted.
- Revoked tokens are marked with `revoked_at` and a closed-set
  `revoked_reason`.
- Reusing a token whose reason is `rotated` is treated as a compromise signal:
  all active refresh tokens for the user are revoked, the user session version
  is incremented, a critical audit event is written, and the caller receives a
  generic `401`.
- Negative revocation lookups are not cached, so a new revocation cannot be
  hidden behind a stale negative cache entry.
