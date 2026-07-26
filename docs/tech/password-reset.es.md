# Restablecimiento de contraseña

El restablecimiento de contraseña usa una tabla de tokens dedicada y reglas centralizadas de validación de auth.

Archivos clave:
- `app/Services/PasswordResetService.php`
- `app/Controllers/Api/V1/PasswordResetController.php`
- `app/Models/PasswordResetModel.php`
- `app/Validations/AuthValidation.php`
- `app/Database/Migrations/2026-01-29-200145_CreatePasswordResetsTable.php`

Endpoints:
- `POST /api/v1/auth/forgot-password`
- `GET /api/v1/auth/validate-reset-token`
- `POST /api/v1/auth/reset-password`

Acciones de validación usadas:
- `auth:forgot_password`
- `auth:password_reset_validate_token`
- `auth:password_reset`

Resumen de comportamiento:
- Input faltante/ inválido ahora responde `ValidationException` (HTTP 422).
- Token bien formado pero inexistente/expirado responde `NotFoundException` (HTTP 404).
- La política de contraseña se aplica con la regla `strong_password` (sin validación manual en el servicio).

Notas:
- Los tokens se guardan en `password_resets` con email y timestamp.
- Los tokens expirados se limpian con `cleanExpired(60)`.
- Los emails se encolan vía `EmailService::queueTemplate()`.
