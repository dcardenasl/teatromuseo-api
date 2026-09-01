<?php

declare(strict_types=1);

/**
 * Cadenas de gestión de tokens (Español)
 */
return [
    // Tokens de actualización
    'refreshTokenRequired'  => 'El token de actualización es requerido',
    'invalidRefreshToken'   => 'Token de actualización inválido o expirado',
    'refreshTokenRevoked'   => 'Token de actualización revocado exitosamente',
    'tokenNotFound'         => 'Token no encontrado',
    'allTokensRevoked'      => 'Todos los tokens de actualización revocados exitosamente',

    // Revocación de tokens
    'revocationFailed'             => 'Error al revocar el token',
    'tokenRevokedSuccess'          => 'Token revocado exitosamente',
    'allUserTokensRevoked'         => 'Todos los tokens del usuario revocados exitosamente',
    'authorizationHeaderRequired'  => 'El encabezado de autorización es obligatorio',
    'invalidAuthorizationFormat'   => 'Formato de encabezado de autorización inválido. Se esperaba: Bearer <token>',
    'invalidToken'                 => 'Token inválido',
    'tokenDecodeFailed'            => 'No se pudo decodificar el token',
    'missingRequiredClaims'        => 'El token no contiene los campos requeridos (jti, exp)',

    // Configuración
    'issuerRequired'        => 'El emisor JWT (baseURL) es requerido. Normalmente se configura mediante app.baseURL en .env',

    // General
    'invalidRequest'        => 'Solicitud inválida',
    'notFound'              => 'No encontrado',
    'userNotFound'          => 'Usuario no encontrado',
    'accessTokenVersionUserNotFound' => 'No se puede resolver la versión del token de acceso para un usuario desconocido.',
    'accessTokenVersionInvalidUserId' => 'El identificador de usuario debe ser positivo.',
    'accessTokenVersionIncrementFailed' => 'No se puede incrementar la versión del token de acceso para un usuario desconocido.',
];
