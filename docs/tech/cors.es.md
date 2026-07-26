# CORS (Compartición entre orígenes)

El filtro `CorsFilter` atiende preflight y agrega las cabeceras necesarias.

Archivos clave:
- `app/Filters/CorsFilter.php`
- `app/Config/Cors.php`
- `app/Config/Filters.php`

Variables de entorno:
- `CORS_ALLOWED_ORIGINS`

Notas:
- Los orígenes se validan contra `CORS_ALLOWED_ORIGINS`.
- En producción, si no hay orígenes configurados, se usa la URL de la app.
