# JWT Authentication

JWT access tokens are issued on login and validated by a request filter. The system uses an immutable, domain-driven token management architecture.

Key Components:
- **`app/Services/Tokens/JwtService.php`**: Immutable `readonly` orchestrator for token encoding/decoding.
- **`app/Services/Tokens/TokenRevocationService.php`**: Manages the token blacklist and revocation cache.
- **`app/Filters/JwtAuthFilter.php`**: Intercepts requests to validate tokens and establish the initial security context.

Environment Variables:
- `JWT_SECRET_KEY`: Minimum 32-character secret.
- `JWT_ACCESS_TOKEN_TTL`: Access token expiration in seconds.
- `JWT_REVOCATION_CACHE_TTL`: Performance cache duration for revoked tokens.

Standard Workflow:
1. Tokens are expected in the `Authorization: Bearer <token>` header.
2. The `JwtAuthFilter` extracts and validates the token.
3. If valid, it checks if the `jti` claim is blacklisted via `TokenRevocationService`.
4. If authorized, it populates the `SecurityContext` for automatic downstream propagation.
