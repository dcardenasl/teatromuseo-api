# CORS

CORS is handled by a dedicated filter that processes preflight requests and adds headers.

Key files:
- `app/Filters/CorsFilter.php`
- `app/Config/Cors.php`
- `app/Config/Filters.php`

Environment variables:
- `CORS_ALLOWED_ORIGINS`

Notes:
- Origins are validated against `CORS_ALLOWED_ORIGINS`.
- In production, if no origins are configured, it defaults to the app URL.
