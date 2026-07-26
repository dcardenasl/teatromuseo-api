<?php

declare(strict_types=1);

/**
 * Cadenas de idioma relacionadas con usuarios (Español)
 */
return [
    // Mensajes generales
    'notFound'        => 'Usuario no encontrado',
    'idRequired'      => 'El ID del usuario es obligatorio',
    'emailRequired'   => 'El email es obligatorio',
    'passwordRequired' => 'La contraseña es obligatoria',
    'adminPasswordForbidden' => 'Los administradores no pueden definir contraseñas al crear usuarios. Se enviará un correo de invitación.',
    'fieldRequired'   => 'Se requiere al menos un campo (email, nombre, apellido, contraseña, rol)',

    // Mensajes de éxito
    'deletedSuccess'  => 'Usuario eliminado correctamente',
    'invitationSent'  => 'Invitación enviada correctamente',
    'approvedSuccess' => 'Usuario aprobado correctamente',
    'alreadyApproved' => 'El usuario ya está aprobado',
    'cannotApproveInvited' => 'Los usuarios invitados ya están aprobados y deben completar su contraseña desde el enlace de invitación.',
    'invalidApprovalState' => 'No se puede aprobar al usuario desde su estado actual.',
    'adminCannotManagePrivileged' => 'Los administradores solo pueden gestionar usuarios con rol user.',
    'adminCannotAssignPrivilegedRole' => 'Los administradores no pueden asignar roles admin o superadmin.',

    // Mensajes de error
    'deleteError'     => 'Error al eliminar el usuario',
    'createError'     => 'Error al crear el usuario',

    // Autenticación
    'auth' => [
        'invalidCredentials' => 'Credenciales inválidas',
        'loginSuccess'       => 'Inicio de sesión exitoso',
        'registerSuccess'    => 'Usuario registrado exitosamente',
        'notAuthenticated'   => 'Usuario no autenticado',
        'credentialsRequired' => 'Se requiere email y contraseña',
    ],
];
