<?php

declare(strict_types=1);

/**
 * Cadenas de idioma para API Keys (Español)
 */
return [
    // General
    'notFound'    => 'Clave de API no encontrada',
    'idRequired'  => 'El ID de la clave de API es obligatorio',

    // Validación
    'nameRequired'   => 'El nombre de la clave de API es obligatorio',
    'fieldRequired'  => 'Se debe proporcionar al menos un campo para actualizar',
    'validation' => [
        'name' => [
            'maxLength' => 'El nombre no puede exceder {0} caracteres',
        ],
        'keyPrefix' => [
            'required' => 'El prefijo de la clave es obligatorio',
            'maxLength' => 'El prefijo de la clave no puede exceder {0} caracteres',
        ],
        'keyHash' => [
            'required' => 'El hash de la clave es obligatorio',
            'maxLength' => 'El hash de la clave no puede exceder {0} caracteres',
        ],
        'rateLimitRequests' => [
            'integer' => 'El límite de solicitudes debe ser un entero',
            'greaterThan' => 'El límite de solicitudes debe ser mayor que 0',
        ],
        'rateLimitWindow' => [
            'integer' => 'La ventana de límite debe ser un entero',
            'greaterThan' => 'La ventana de límite debe ser mayor que 0',
        ],
        'userRateLimit' => [
            'integer' => 'El límite por usuario debe ser un entero',
            'greaterThan' => 'El límite por usuario debe ser mayor que 0',
        ],
        'ipRateLimit' => [
            'integer' => 'El límite por IP debe ser un entero',
            'greaterThan' => 'El límite por IP debe ser mayor que 0',
        ],
    ],

    // Mensajes de éxito
    'createdSuccess' => 'Clave de API creada correctamente. Guárdala en un lugar seguro — no se mostrará de nuevo.',
    'deletedSuccess' => 'Clave de API eliminada correctamente',

    // Mensajes de error
    'createError'   => 'Error al crear la clave de API',
    'deleteError'   => 'Error al eliminar la clave de API',
    'retrieveError' => 'Error al recuperar la clave de API recién creada',

    // Auth / rate limiting
    'invalidKey'   => 'La clave de API proporcionada es inválida o está inactiva',
    'unauthorized' => 'No autorizado',
];
