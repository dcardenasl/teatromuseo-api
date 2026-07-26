# OpenAPI and Swagger

OpenAPI documentation is generated from annotations and written to `public/swagger.json`.

Key files:
- `app/Config/OpenApi.php`
- `app/Documentation/`
- `public/swagger.json`

Generate docs:
- `php spark swagger:generate`

Notes:
- Swagger UI can be served using the Docker example in `README.md`.
- Postman collection is derived from `public/swagger.json`.
  Import the file in Postman and generate a collection for your project.
  Variables can live at the collection level (e.g., `baseUrl`, `accessToken`).
