<?php

declare(strict_types=1);

/**
 * Cadenas de autenticación (Español)
 */
return [
    // Mensajes JWT
    'headerMissing'           => 'Falta el encabezado de autorización',
    'invalidFormat'           => 'Formato de encabezado de autorización inválido',
    'invalidToken'            => 'Token inválido o expirado',
    'tokenRevoked'            => 'El token ha sido revocado',
    'appKeyMissing'           => 'Se requiere el encabezado X-App-Key',
    'appKeyInvalid'           => 'X-App-Key inválida o inactiva',
    'emailNotVerified'        => 'El correo electrónico no está verificado',
    'accountPendingApproval'  => 'La cuenta está pendiente de aprobación por un administrador',
    'accountSetupRequired'    => 'La cuenta requiere configuración inicial. Revisa tu correo de invitación para definir tu contraseña.',
    'registrationPendingApproval' => 'Registro recibido. Verifica tu correo y espera la aprobación del administrador.',
    'registrationPendingApprovalNoVerification' => 'Registro recibido. Espera la aprobación del administrador.',
    'googleTokenRequired'     => 'El token de Google es obligatorio',
    'googleInvalidToken'      => 'Token de Google inválido',
    'googleEmailNotVerified'  => 'El email de la cuenta de Google no está verificado',
    'googleProviderMismatch'  => 'Este email está vinculado a otro proveedor de acceso',
    'googleProviderIdentityMismatch' => 'La identidad de Google no coincide con la cuenta vinculada',
    'googleUserMissing' => 'El usuario desapareció después de la actualización de Google',
    'googleRegistrationPendingApproval' => 'Inicio de sesión con Google recibido. Tu cuenta está pendiente de aprobación por un administrador.',
    'googleClientNotConfigured' => 'La autenticación con Google no está configurada',
    'googleLibraryUnavailable' => 'La librería de autenticación de Google no está disponible',

    // Mensajes de autenticación
    'authRequired'            => 'Autenticación requerida',
    'insufficientPermissions' => 'Permisos insuficientes',
    'unauthorized'            => 'Acceso no autorizado',
    'passwordResetSuccess'    => 'La contraseña ha sido restablecida exitosamente',

    // Límite de velocidad
    'rateLimitExceeded'       => 'Límite de solicitudes excedido. Por favor, intente más tarde.',
    'tooManyRequests'         => 'Demasiadas solicitudes. Máximo {0} solicitudes por {1} segundos permitidas.',
    'tooManyLoginAttempts'    => 'Demasiados intentos de autenticación. Máximo {0} intentos por {1} minutos. Por favor, intente más tarde.',
];
