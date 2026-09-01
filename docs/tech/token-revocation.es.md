# Revocación de tokens

Los access tokens individuales pueden revocarse vía blacklist de JTI. La
revocación global de una cuenta usa una versión de sesión por usuario para
invalidar inmediatamente los JWT ya emitidos.

Archivos clave:
- `app/Services/Tokens/TokenRevocationService.php`
- `app/Models/TokenBlacklistModel.php`
- `app/Database/Migrations/2026-01-29-205223_CreateTokenBlacklistTable.php`
- `app/Filters/JwtAuthFilter.php`
- `app/Database/Migrations/2026-08-12-130000_HardenTokenLifecycle.php`

Variables de entorno:
- `JWT_REVOCATION_CHECK`

Notas:
- Los JTIs revocados se guardan en `token_blacklist` y solo se cachean
  resultados positivos de la blacklist.
- El filtro JWT valida revocación cuando `JWT_REVOCATION_CHECK=true`.
- `revoke-all` revoca los refresh tokens activos e incrementa
  `users.auth_token_version` en una sola transacción.
- El filtro JWT y `/auth/introspect` rechazan tokens de usuario cuyo
  `token_version` no coincide con la versión vigente.
