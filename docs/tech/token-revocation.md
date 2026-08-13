# Token Revocation

Individual access tokens can be revoked by JTI blacklist checks. Account-wide
revocation uses the per-user session version so already-issued JWTs are
invalidated immediately.

Key files:
- `app/Services/Tokens/TokenRevocationService.php`
- `app/Models/TokenBlacklistModel.php`
- `app/Database/Migrations/2026-01-29-205223_CreateTokenBlacklistTable.php`
- `app/Filters/JwtAuthFilter.php`
- `app/Database/Migrations/2026-08-12-130000_HardenTokenLifecycle.php`

Environment variables:
- `JWT_REVOCATION_CHECK`

Notes:
- Revoked JTIs are stored in the `token_blacklist` table and only positive
  blacklist results are cached.
- The JWT filter checks revocation when `JWT_REVOCATION_CHECK=true`.
- `revoke-all` revokes active refresh tokens and increments
  `users.auth_token_version` in one transaction.
- The JWT filter and `/auth/introspect` reject user tokens whose
  `token_version` does not match the current user version.
