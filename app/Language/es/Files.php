<?php

declare(strict_types=1);

/**
 * Cadenas de gestión de archivos (Español)
 */
return [
    // Mensajes de éxito
    'upload_success'      => 'Archivo subido exitosamente',
    'delete_success'      => 'Archivo eliminado exitosamente',
    'restore_success'     => 'Archivo restaurado exitosamente',
    'force_delete_success' => 'Archivo eliminado de forma permanente',

    // Mensajes de error
    'file_required'       => 'El archivo es requerido',
    'invalid_file_object'  => 'Objeto de archivo inválido',
    'upload_failed'       => 'Error al subir el archivo: {0}',
    'file_too_large'       => 'El tamaño del archivo excede el máximo permitido',
    'invalid_file_type'    => 'Tipo de archivo no permitido',
    'file_mime_mismatch'   => 'El contenido del archivo no coincide con su extensión',
    'storage_failed'      => 'Error al almacenar el archivo',
    'file_not_found'       => 'Archivo no encontrado o acceso denegado',
    'id_required'         => 'El ID del archivo es obligatorio',
    'unauthorized'       => 'No está autorizado para acceder a este archivo',
    'save_failed'         => 'Error al guardar los metadatos del archivo',
    'malware_detected'    => 'Se detectó malware o virus en el archivo subido',
    'temp_file_creation_failed' => 'No se pudo crear un archivo temporal para el escaneo de virus',
    'virus_scan_read_error' => 'No se puede leer el archivo para el escaneo de virus',
    'virus_scan_unavailable' => 'El escaneo de virus está habilitado, pero no hay ningún escáner configurado',

    // Mensajes de solicitud
    'invalid_request'     => 'Solicitud inválida',
    'storage_error'       => 'Error de almacenamiento',
    'notFound'           => 'No encontrado',
    'useDeleteWithContext' => 'Use delete() con FileGetRequestDTO para forzar la validación de propiedad',
    'upload' => [
        'noFile' => 'No se subió ningún archivo o el archivo no es válido',
    ],

    // Papelera / soft-delete
    'already_trashed'  => 'El archivo ya está en la papelera',
    'not_trashed'      => 'El archivo no está en la papelera',
    'bulk_ids_required' => 'Se requiere una lista no vacía de IDs de archivos',
    'bulk_item_failed' => 'La operación falló para este archivo',

    // Referencias de archivos
    'in_use' => 'No es posible eliminar: este archivo es referenciado por {0} recurso(s). Desvincularlo primero.',

    // Generación de variantes
    'not_an_image'       => 'La generación de variantes solo está disponible para archivos de imagen.',
    'regenerate_success' => 'Variantes regeneradas exitosamente.',

    // Actualización de metadatos
    'metadata_no_fields'    => 'Se debe proporcionar al menos un campo de metadatos.',
    'metadata_update_success' => 'Metadatos del archivo actualizados exitosamente.',

    // Reemplazo
    'replace_success' => 'Archivo reemplazado exitosamente.',

    // Hash de stream
    'hash_stream_invalid' => 'Se esperaba un stream legible para el hash del archivo.',
    'hash_stream_failed' => 'Error al hacer hash del archivo subido.',
];
