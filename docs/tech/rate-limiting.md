# Rate Limiting

Rate limiting is enforced through HTTP filters for general and auth-specific routes.

Key files:
- `app/Filters/ThrottleFilter.php`
- `app/Filters/AuthThrottleFilter.php`
- `app/Config/Filters.php`
- `app/Config/Routes.php`

Environment variables:
- `RATE_LIMIT_REQUESTS`
- `RATE_LIMIT_USER_REQUESTS`
- `RATE_LIMIT_WINDOW`
- `AUTH_RATE_LIMIT_REQUESTS`
- `AUTH_RATE_LIMIT_WINDOW`
- `API_KEY_RATE_LIMIT_DEFAULT`
- `API_KEY_USER_RATE_LIMIT_DEFAULT`
- `API_KEY_IP_RATE_LIMIT_DEFAULT`
- `API_KEY_WINDOW_DEFAULT`

Notes:
- `authThrottle` is applied to auth endpoints.
- `throttle` is applied to general API routes.
- Responses include rate limit headers (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`).
- If `X-App-Key` is present, API key limits are applied after key validation.
