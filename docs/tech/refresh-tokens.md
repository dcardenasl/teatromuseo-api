# Refresh Tokens

Refresh tokens are stored in the database and rotated on use.

Key files:
- `app/Services/Tokens/RefreshTokenService.php`
- `app/Models/RefreshTokenModel.php`
- `app/Validations/TokenValidation.php`
- `app/Database/Migrations/2026-01-29-205207_CreateRefreshTokensTable.php`

Environment variables:
- `JWT_REFRESH_TOKEN_TTL`

Validation:
- Actions `token:refresh` and `token:revoke` require `refresh_token` with rule `valid_token[64]`.
- Invalid token format is treated as request validation error.

Notes:
- Tokens live in the `refresh_tokens` table.
- Refresh uses a DB transaction and row lock to avoid race conditions.
- Revoked tokens are marked with `revoked_at`.
