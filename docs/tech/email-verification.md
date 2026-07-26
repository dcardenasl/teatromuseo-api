# Email Verification

Email verification uses a token stored on the user record and expires after a fixed window.

Key files:
- `app/Services/VerificationService.php`
- `app/Controllers/Api/V1/VerificationController.php`
- `app/Database/Migrations/2026-01-28-014712_CreateUsersTable.php`

Environment variables:
- `AUTH_REQUIRE_EMAIL_VERIFICATION`

Notes:
- User fields: `email_verified_at`, `email_verification_token`, `verification_token_expires`.
- Endpoints:
  - `GET /api/v1/auth/verify-email` (token in query)
  - `POST /api/v1/auth/verify-email` (token in body/form)
  - `POST /api/v1/auth/resend-verification` (protected route)
- When disabled, login and protected routes do not require verification.
