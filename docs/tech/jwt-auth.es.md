# Autenticación JWT

Los tokens de acceso JWT se emiten al iniciar sesión y son validados por un filtro de solicitud. El sistema utiliza una arquitectura de gestión de tokens inmutable y orientada a dominios.

Componentes Clave:
- **`app/Services/Tokens/JwtService.php`**: Orquestador inmutable `readonly` para la codificación/decodificación de tokens.
- **`app/Services/Tokens/TokenRevocationService.php`**: Gestiona la lista negra JTI y la revocación global por cuenta.
- **`app/Services/Tokens/TokenVersionService.php`**: Gestiona atómicamente la versión de sesión de cada usuario.
- **`app/Filters/JwtAuthFilter.php`**: Intercepta las solicitudes para validar los tokens y establecer el contexto de seguridad inicial.

Variables de Entorno:
- `JWT_SECRET_KEY`: Secreto de mínimo 32 caracteres.
- `JWT_ACCESS_TOKEN_TTL`: Expiración del token de acceso en segundos.

Flujo Estándar:
1. Se esperan los tokens en el encabezado `Authorization: Bearer <token>`.
2. El `JwtAuthFilter` extrae y valida el token.
3. Si es válido, comprueba si el claim `jti` está en la lista negra a través del `TokenRevocationService`.
4. Los tokens de usuario también llevan `token_version`; debe coincidir con `users.auth_token_version`.
5. Si está autorizado, puebla el `SecurityContext` para su propagación automática.

`revoke-all`, la reactivación por recuperación de contraseña y la reutilización
de un token de refresco incrementan `users.auth_token_version`. Esto invalida
todos los JWT de usuario ya emitidos en la siguiente solicitud, sin esperar su
`exp`. Los tokens de servicio no llevan versión de usuario y siguen gobernados
por su TTL corto y su JTI.
