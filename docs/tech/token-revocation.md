# Token Revocation

Access tokens can be revoked by JTI blacklist checks.

Key files:
- `app/Services/Tokens/TokenRevocationService.php`
- `app/Models/TokenBlacklistModel.php`
- `app/Database/Migrations/2026-01-29-205223_CreateTokenBlacklistTable.php`
- `app/Filters/JwtAuthFilter.php`

Environment variables:
- `JWT_REVOCATION_CHECK`

Notes:
- Revoked JTIs are stored in the `token_blacklist` table.
- The JWT filter checks revocation when `JWT_REVOCATION_CHECK=true`.
