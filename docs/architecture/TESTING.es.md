# Estrategia de Testing

Este documento resume la estrategia de pruebas para mantener contratos de arquitectura y estabilidad funcional.

## Capas de prueba

1. Unit: lógica de servicios, DTOs, utilidades y traits.
2. Integration: interacción real con modelos/base de datos.
3. Feature: comportamiento HTTP final (status + JSON contract).

## Principios

1. Los servicios se validan contra tipos de retorno DTO/`OperationResult`.
2. Los controladores se validan por contrato de respuesta.
3. Dependencias externas se mockean en unit tests.
4. Las pruebas de arquitectura evitan regresiones de patrones.

## Gates obligatorios

1. `composer cs-check`
2. `composer phpstan`
3. `php scripts/i18n-check.php`
4. `php spark tests:prepare-db`
5. `vendor/bin/phpunit --configuration=phpunit.xml --no-coverage --testdox`

## Comando recomendado

```bash
composer quality
```

## Referencias

1. `docs/template/QUALITY_GATES.md`
2. `tests/Unit/Architecture/`
