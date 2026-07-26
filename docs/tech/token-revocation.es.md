# Revocación de tokens

Los access tokens pueden revocarse vía blacklist de JTI.

Archivos clave:
- `app/Services/Tokens/TokenRevocationService.php`
- `app/Models/TokenBlacklistModel.php`
- `app/Database/Migrations/2026-01-29-205223_CreateTokenBlacklistTable.php`
- `app/Filters/JwtAuthFilter.php`

Variables de entorno:
- `JWT_REVOCATION_CHECK`

Notas:
- Los JTIs revocados se guardan en `token_blacklist`.
- El filtro JWT valida revocación cuando `JWT_REVOCATION_CHECK=true`.
