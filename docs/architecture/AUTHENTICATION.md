# JWT Authentication


## Authentication Flow

```
1. Login → Generate access token (1h) + refresh token (7d)
2. Access protected resource with access token
3. Access token expires → Use refresh token to get new access token
4. Logout → Revoke tokens (blacklist)
```

## JWT Structure

```php
{
  "iss": "https://api.example.com",  // Issuer
  "aud": "https://api.example.com",  // Audience
  "iat": 1707696000,                 // Issued at
  "exp": 1707699600,                 // Expiration (1h later)
  "jti": "a3f8b9c2d4e5f6g7",        // JWT ID (unique)
  "uid": 42,                         // User ID
  "role": "admin"                    // User role
}
```

## Token Storage

| Token | Storage | TTL | Revocable |
|-------|---------|-----|-----------|
| Access Token | None (stateless) | 1 hour | Yes (via JTI blacklist) |
| Refresh Token | `refresh_tokens` table | 7 days | Yes (soft delete) |

## Usage

```bash
# Login
curl -X POST /api/v1/auth/login \
  -d '{"email":"user@example.com","password":"pass"}'

# Use access token
curl -X GET /api/v1/users \
  -H "Authorization: Bearer ACCESS_TOKEN"

# Refresh
curl -X POST /api/v1/auth/refresh \
  -d '{"refresh_token":"REFRESH_TOKEN"}'

# Revoke
curl -X POST /api/v1/auth/revoke \
  -H "Authorization: Bearer ACCESS_TOKEN"
```

## Refresh Token Validation

- `POST /api/v1/auth/refresh` and `POST /api/v1/auth/revoke` validate `refresh_token` with `valid_token[64]`.
- Invalid token format is treated as request validation error before business logic.
