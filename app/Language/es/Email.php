<?php

declare(strict_types=1);

/**
 * Contenido de correos electrónicos (Español)
 */
return [
    // Correo de verificación
    'verification' => [
        'subject'     => 'Verifica tu Correo Electrónico',
        'title'       => 'Verifica tu Correo',
        'welcome'     => '¡Bienvenido a {0}!',
        'greeting'    => '¡Hola, {0}!',
        'intro'       => 'Gracias por registrarte. Para completar tu registro, verifica tu correo electrónico. Tu cuenta se activará después de la aprobación del administrador.',
        'buttonText'  => 'Verificar Correo',
        'linkIntro'   => 'O copia y pega este enlace en tu navegador:',
        'expiration'  => 'Este enlace expira el {0}',
        'footer'      => 'Si no creaste una cuenta, puedes ignorar este correo de forma segura.',
        'autoMessage' => 'Este es un mensaje automático, por favor no respondas.',
        'copyright'   => 'Todos los derechos reservados.',
    ],

    // Correo de restablecimiento de contraseña
    'passwordReset' => [
        'subject'        => 'Restablecer tu Contraseña',
        'title'          => 'Solicitud de Restablecimiento de Contraseña',
        'greeting'       => '¡Hola!',
        'intro'          => 'Estás recibiendo este correo porque recibimos una solicitud de restablecimiento de contraseña para tu cuenta.',
        'buttonText'     => 'Restablecer Contraseña',
        'linkIntro'      => 'O copia y pega este enlace en tu navegador:',
        'expiration'     => 'Este enlace de restablecimiento expira en {0}',
        'securityTitle'  => 'Aviso de Seguridad',
        'securityNotice' => 'Si no solicitaste un restablecimiento de contraseña, ignora este correo o contacta a soporte si tienes preocupaciones sobre la seguridad de tu cuenta.',
        'autoMessage'    => 'Este es un mensaje automático, por favor no respondas.',
        'copyright'      => 'Todos los derechos reservados.',
    ],

    // Correo de invitación
    'invitation' => [
        'subject'     => 'Has sido invitado',
        'title'       => 'Invitación de Cuenta',
        'greeting'    => '¡Hola, {0}!',
        'intro'       => 'Un administrador creó una cuenta para ti. Por favor establece tu contraseña para activar el acceso.',
        'googleOption' => 'También puedes iniciar sesión directamente con Google usando este mismo correo.',
        'buttonText'  => 'Establecer Contraseña',
        'linkIntro'   => 'O copia y pega este enlace en tu navegador:',
        'expiration'  => 'Este enlace expira en {0}',
        'autoMessage' => 'Este es un mensaje automático, por favor no respondas.',
        'copyright'   => 'Todos los derechos reservados.',
    ],

    // Correo de cuenta aprobada
    'accountApproved' => [
        'subject'     => 'Tu cuenta fue aprobada',
        'title'       => 'Cuenta Aprobada',
        'greeting'    => '¡Hola, {0}!',
        'intro'       => 'Tu cuenta ha sido aprobada. Ya puedes iniciar sesión.',
        'buttonText'  => 'Ir al Login',
        'linkIntro'   => 'O copia y pega este enlace en tu navegador:',
        'autoMessage' => 'Este es un mensaje automático, por favor no respondas.',
        'copyright'   => 'Todos los derechos reservados.',
    ],

    // Correo de Google pendiente de aprobación
    'pendingApprovalGoogle' => [
        'subject'     => 'Tu cuenta está pendiente de aprobación',
        'title'       => 'Cuenta Pendiente de Aprobación',
        'greeting'    => '¡Hola, {0}!',
        'intro'       => 'Recibimos tu inicio de sesión con Google. Tu cuenta está pendiente de aprobación por un administrador.',
        'nextStep'    => 'Recibirás otro correo en cuanto tu cuenta sea aprobada.',
        'autoMessage' => 'Este es un mensaje automático, por favor no respondas.',
        'copyright'   => 'Todos los derechos reservados.',
    ],
];
