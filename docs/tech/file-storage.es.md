# Almacenamiento de Archivos

La gestión de archivos sigue una arquitectura descompuesta para manejar múltiples tipos de entrada y drivers de almacenamiento sin fisuras.

Componentes Clave:
- **`app/Services/Files/FileService.php`**: Orquesta el almacenamiento y la persistencia en base de datos. Expone el método unificado `destroy` para limpieza atómica.
- **`app/Interfaces/Files/FileRepositoryInterface.php`**: Estandariza la recuperación y persistencia de metadatos.
- **`app/Libraries/Files/MultipartProcessor.php`**: Maneja las cargas de archivos HTTP estándar.
- **`app/Libraries/Files/Base64Processor.php`**: Decodifica y valida Data URIs y Base64 puro.
- **`app/Libraries/Files/StorageKeyGenerator.php`**: Genera claves opacas y resistentes a colisiones para los archivos persistidos.
- **`app/Support/Files/ProcessedFile.php`**: Value Object estandarizado para transferencias basadas en streams.

La base de datos conserva `original_name` tal como llega desde el cliente y guarda la clave física por separado en `stored_name`/`path`. Esa clave física es opaca, particionada por fecha y derivada de un prefijo corto del hash de contenido más aleatoriedad, así que no depende del nombre original.

Drivers de Almacenamiento (`app/Libraries/Storage/`):
- **LocalDriver**: Almacena archivos en `writable/uploads/`.
- **S3Driver**: Se integra con AWS S3 usando flysystem.

Variables de Entorno:
- `FILE_STORAGE_DRIVER`: `local` o `s3`.
- `FILE_MAX_SIZE`: Límite en bytes.
- `FILE_ALLOWED_TYPES`: Extensiones separadas por comas (ej. `jpg,png,pdf`).

Validación:
Todas las operaciones de archivos utilizan validación basada en DTOs. Los procesadores garantizan que los archivos sean estructuralmente sólidos y seguros antes de que el `FileService` intente la persistencia.

## Autorización

La autorización es explícita por acción y está centralizada en
`FilePolicyService`; ya no existe un flag de bypass de propiedad enviado por el
caller.

- `files.read` permite leer (`view`, `download` y `view_usages`). Cuando
  `FILE_ALLOW_PRIVILEGED_READ_BYPASS=true`, puede omitir la propiedad solamente
  para esas acciones de lectura.
- `files.write` permite subir y modificar archivos propios.
- `files.admin` permite modificar archivos de cualquier usuario.
- `force-delete` usa la misma regla de propietario/escritura o administración
  para archivos ajenos, y la ruta exige además `files.write` como gate grueso.

`delete`, `restore`, `replace`, `update_metadata` y `regenerate_variants` nunca
interpretan `files.read` como permiso de escritura ni como bypass de propiedad.
Los intentos denegados quedan en el audit log con códigos específicos como
`unauthorized_file_delete`, `unauthorized_file_replace` y
`unauthorized_file_update_metadata`.
