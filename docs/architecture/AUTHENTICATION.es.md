# Autenticación JWT


## Flujo de Autenticación

```
1. Login → Generar access token (1h) + refresh token (7d)
2. Acceder recurso protegido con access token
3. Access token expira → Usar refresh token para obtener nuevo access token
4. Logout → Revocar tokens (blacklist)
```

## Estructura JWT

```php
{
  "iss": "https://api.example.com",  // Issuer
  "aud": "https://api.example.com",  // Audience
  "iat": 1707696000,                 // Issued at
  "exp": 1707699600,                 // Expiration (1h después)
  "jti": "a3f8b9c2d4e5f6g7",        // JWT ID (único)
  "uid": 42,                         // User ID
  "role": "admin"                    // User role
}
```

## Almacenamiento de Tokens

| Token | Almacenamiento | TTL | Revocable |
|-------|---------|-----|-----------|
| Access Token | Ninguno (stateless) | 1 hora | Sí (via blacklist JTI) |
| Refresh Token | tabla `refresh_tokens` | 7 días | Sí (soft delete) |

## Uso

```bash
# Login
curl -X POST /api/v1/auth/login \
  -d '{"email":"user@example.com","password":"pass"}'

# Usar access token
curl -X GET /api/v1/users \
  -H "Authorization: Bearer ACCESS_TOKEN"

# Refrescar
curl -X POST /api/v1/auth/refresh \
  -d '{"refresh_token":"REFRESH_TOKEN"}'

# Revocar
curl -X POST /api/v1/auth/revoke \
  -H "Authorization: Bearer ACCESS_TOKEN"
```

## Validacion de Refresh Token

- `POST /api/v1/auth/refresh` y `POST /api/v1/auth/revoke` validan `refresh_token` con `valid_token[64]`.
- Un formato invalido del token se trata como error de validacion del request antes de la logica de negocio.

**Ver `../ARCHITECTURE.md` sección 15 para implementación completa de JWT.**
