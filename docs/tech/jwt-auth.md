# JWT Authentication

JWT access tokens are issued on login and validated by a request filter. The system uses an immutable, domain-driven token management architecture.

Key Components:
- **`app/Services/Tokens/JwtService.php`**: Immutable `readonly` orchestrator for token encoding/decoding.
- **`app/Services/Tokens/TokenRevocationService.php`**: Manages the JTI blacklist and account-wide revocation.
- **`app/Services/Tokens/TokenVersionService.php`**: Atomically manages each user's session version.
- **`app/Filters/JwtAuthFilter.php`**: Intercepts requests to validate tokens and establish the initial security context.

Environment Variables:
- `JWT_SECRET_KEY`: Minimum 32-character secret.
- `JWT_ACCESS_TOKEN_TTL`: Access token expiration in seconds.

Standard Workflow:
1. Tokens are expected in the `Authorization: Bearer <token>` header.
2. The `JwtAuthFilter` extracts and validates the token.
3. If valid, it checks if the `jti` claim is blacklisted via `TokenRevocationService`.
4. User tokens also carry `token_version`; it must equal `users.auth_token_version`.
5. If authorized, it populates the `SecurityContext` for automatic downstream propagation.

`revoke-all`, password-reset reactivation and refresh-token reuse increment
`users.auth_token_version`. This invalidates every already-issued user JWT on
the next request, without waiting for its `exp` claim. Service tokens do not
carry a user version and remain governed by their own short TTL and JTI.
