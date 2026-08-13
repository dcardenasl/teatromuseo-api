# Tokens de refresco

Los tokens de refresco se almacenan hasheados en base de datos y se rotan al
usarse. Cada login inicia una familia de refresh tokens; cada rotación crea un
hijo y marca su padre como rotado.

Archivos clave:
- `app/Services/Tokens/RefreshTokenService.php`
- `app/Models/RefreshTokenModel.php`
- `app/Validations/TokenValidation.php`
- `app/Database/Migrations/2026-01-29-205207_CreateRefreshTokensTable.php`
- `app/Database/Migrations/2026-08-12-130000_HardenTokenLifecycle.php`

Variables de entorno:
- `JWT_REFRESH_TOKEN_TTL`

Validación:
- Las acciones `token:refresh` y `token:revoke` requieren `refresh_token` con la regla `valid_token[64]`.
- Un formato inválido del token se trata como error de validación de la solicitud.

Notas:
- Los tokens viven en la tabla `refresh_tokens`.
- El refresh usa transacción y bloqueo para evitar carreras.
- `family_id` y `parent_id` conservan la línea de rotación; el token crudo
  nunca se persiste.
- Los tokens revocados se marcan con `revoked_at` y un `revoked_reason` de
  conjunto cerrado.
- Reutilizar un token cuyo motivo sea `rotated` se trata como señal de
  compromiso: se revocan todos los refresh tokens activos del usuario, se
  incrementa la versión de sesión, se registra una auditoría crítica y el
  cliente recibe un `401` genérico.
- Las búsquedas negativas de revocación no se cachean, por lo que una
  revocación nueva no queda oculta por una entrada negativa obsoleta.
