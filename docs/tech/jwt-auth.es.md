# Autenticación JWT

Los tokens de acceso JWT se emiten al iniciar sesión y son validados por un filtro de solicitud. El sistema utiliza una arquitectura de gestión de tokens inmutable y orientada a dominios.

Componentes Clave:
- **`app/Services/Tokens/JwtService.php`**: Orquestador inmutable `readonly` para la codificación/decodificación de tokens.
- **`app/Services/Tokens/TokenRevocationService.php`**: Gestiona la lista negra de tokens y el caché de revocación.
- **`app/Filters/JwtAuthFilter.php`**: Intercepta las solicitudes para validar los tokens y establecer el contexto de seguridad inicial.

Variables de Entorno:
- `JWT_SECRET_KEY`: Secreto de mínimo 32 caracteres.
- `JWT_ACCESS_TOKEN_TTL`: Expiración del token de acceso en segundos.
- `JWT_REVOCATION_CACHE_TTL`: Duración del caché de rendimiento para tokens revocados.

Flujo Estándar:
1. Se esperan los tokens en el encabezado `Authorization: Bearer <token>`.
2. El `JwtAuthFilter` extrae y valida el token.
3. Si es válido, comprueba si el claim `jti` está en la lista negra a través del `TokenRevocationService`.
4. Si está autorizado, puebla el `SecurityContext` para su propagación automática.
