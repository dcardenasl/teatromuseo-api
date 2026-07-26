# Verificación de correo

La verificación de correo usa un token asociado al usuario y expira después de un tiempo.

Archivos clave:
- `app/Services/VerificationService.php`
- `app/Controllers/Api/V1/VerificationController.php`
- `app/Database/Migrations/2026-01-28-014712_CreateUsersTable.php`

Variables de entorno:
- `AUTH_REQUIRE_EMAIL_VERIFICATION`

Notas:
- Campos del usuario: `email_verified_at`, `email_verification_token`, `verification_token_expires`.
- Endpoints:
  - `GET /api/v1/auth/verify-email` (token en query)
  - `POST /api/v1/auth/verify-email` (token en body/form)
  - `POST /api/v1/auth/resend-verification` (ruta protegida)
- Cuando está desactivado, el inicio de sesión y las rutas protegidas no exigen verificación.
