# Password Reset

Password reset uses a dedicated token table and centralized auth validation rules.

Key files:
- `app/Services/PasswordResetService.php`
- `app/Controllers/Api/V1/PasswordResetController.php`
- `app/Models/PasswordResetModel.php`
- `app/Validations/AuthValidation.php`
- `app/Database/Migrations/2026-01-29-200145_CreatePasswordResetsTable.php`

Endpoints:
- `POST /api/v1/auth/forgot-password`
- `GET /api/v1/auth/validate-reset-token`
- `POST /api/v1/auth/reset-password`

Validation actions used:
- `auth:forgot_password`
- `auth:password_reset_validate_token`
- `auth:password_reset`

Behavior summary:
- Missing/invalid input now returns `ValidationException` (HTTP 422).
- Well-formed but unknown/expired reset token returns `NotFoundException` (HTTP 404).
- Password policy is enforced via `strong_password` rule (not manual checks in service).

Notes:
- Tokens are stored in `password_resets` with email and timestamp.
- Expired tokens are periodically cleaned with `cleanExpired(60)`.
- Emails are queued via `EmailService::queueTemplate()`.
