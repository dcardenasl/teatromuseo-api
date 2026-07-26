# OpenAPI y Swagger

La documentación OpenAPI se genera desde anotaciones y se escribe en `public/swagger.json`.

Archivos clave:
- `app/Config/OpenApi.php`
- `app/Documentation/`
- `public/swagger.json`

Generar docs:
- `php spark swagger:generate`

Notas:
- Swagger UI puede servirse con el ejemplo de Docker en `README.es.md`.
- La colección de Postman se deriva de `public/swagger.json`.
  Importa el archivo en Postman y genera una colección para tu proyecto.
  Las variables pueden vivir a nivel de colección (por ejemplo, `baseUrl`, `accessToken`).
