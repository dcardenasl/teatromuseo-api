# Guías de Testing

Guía técnica rápida para mantener la calidad de pruebas en el repositorio.

## Reglas

1. Unit tests para lógica aislada de servicios y DTOs.
2. Feature tests para contrato HTTP final.
3. Integration tests cuando hay interacción real con DB.
4. Mocks obligatorios para dependencias externas en unit tests.

## Reglas de arquitectura a validar

1. Controladores delgados (`handleRequest` + DTOs).
2. Servicios sin lógica HTTP.
3. Contratos de retorno consistentes (`DTO`/`OperationResult`).

## Comandos

```bash
php spark tests:prepare-db
vendor/bin/phpunit --configuration=phpunit.xml --no-coverage --testdox
composer quality
```

## Referencia

Versión base en inglés: `TESTING_GUIDELINES.md`.
