# Pre-Deployment Security Checklist

This checklist must be completed before deploying the application to production.

## Environment Configuration

- [ ] `CI_ENVIRONMENT=production` set in `.env` or server environment
- [ ] `app.forceGlobalSecureRequests=true` enabled in `.env` (forces HTTPS)
- [ ] `DBDebug=false` verified in `app/Config/Database.php` (automatically disabled when `ENVIRONMENT=production`)
- [ ] `JWT_SECRET_KEY` is unique and at least 64 characters (not example value)
- [ ] `encryption.key` is unique (not example value from .env.example)
- [ ] Database credentials changed from defaults (`root:root`)
- [ ] First superadmin account has been created with `php spark users:bootstrap-superadmin`

## Security Settings

- [ ] `CORS_ALLOWED_ORIGINS` configured with specific domains (no wildcards like `*`)
- [ ] `AUTH_RATE_LIMIT_REQUESTS` set to 5 or less (prevents brute force attacks)
- [ ] `AUTH_RATE_LIMIT_WINDOW` appropriate for your use case (default: 900 seconds / 15 minutes)
- [ ] `RATE_LIMIT_REQUESTS` configured for general API endpoints (default: 60 per minute)
- [ ] File permissions on `writable/` directory set to 755 (or 775 if web server needs write access)
- [ ] `.env` file permissions set to 600 (readable only by owner)

## Database

- [ ] All migrations executed successfully (`php spark migrate`)
- [ ] Production database has proper backup strategy
- [ ] Database user has minimal required privileges (not root)
- [ ] Database connection uses SSL/TLS if available

## Testing

- [ ] All tests pass: `vendor/bin/phpunit`
- [ ] No security vulnerabilities: `composer audit`
- [ ] Static analysis passes: `vendor/bin/phpstan analyse --level=6 app`
- [ ] Code style check passes: `composer cs-check`

## Optional but Recommended

- [ ] Email service configured (SMTP or provider API)
- [ ] File storage configured (S3 or equivalent for production)
- [ ] Redis or Memcached configured for caching
- [ ] Log monitoring/aggregation setup (e.g., Sentry, Loggly)
- [ ] Health check endpoint `/health` monitored
- [ ] SSL/TLS certificate installed and valid
- [ ] Security headers verified (check `/health` response headers)

## Post-Deployment Verification

- [ ] Health check endpoint returns 200: `GET /health`
- [ ] Can register new user: `POST /api/v1/auth/register`
- [ ] Can login: `POST /api/v1/auth/login`
- [ ] Protected endpoints require authentication
- [ ] Rate limiting works (test with multiple rapid requests)
- [ ] HTTPS redirect works (if `forceGlobalSecureRequests=true`)
- [ ] CORS headers present for configured origins
- [ ] Logs are being written to `writable/logs/`

## Rollback Plan

If deployment fails or critical issues are discovered:

1. Revert to previous version via your deployment tool
2. Restore database from last known good backup (if schema changed)
3. Verify health check endpoint returns 200
4. Check logs for errors: `tail -f writable/logs/log-*.log`

## Secret Rotation Schedule

Set up recurring reminders to rotate secrets:

- [ ] JWT secrets: Every 90 days
- [ ] Database passwords: Every 180 days
- [ ] Encryption keys: Only on security breach (user sessions will be invalidated)

See **Secret Rotation** section in `README.md` for rotation procedures.

---

**Last Review Date:** _________________
**Reviewed By:** _________________
**Deployment Approved:** [ ] Yes [ ] No
