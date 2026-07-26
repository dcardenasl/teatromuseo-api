# ADR-005: Pureza de Servicios y Límites de Inyección de Dependencias

## Estado
Aceptado

## Contexto

A medida que el template evolucionó, algunas clases de servicio acumularon acoplamiento a runtime (`env()`, `getenv()`, `Config\Services`), debilitando la testabilidad y aumentando el riesgo de architecture drift.

## Decisión

1. `app/Services/*` debe mantenerse agnóstico al runtime:
- sin llamadas a `Config\Services`
- sin acceso a `env()` / `getenv()`
2. La resolución de runtime/configuración se permite solo en bordes:
- `app/Config/Services.php`
- controllers / filters / commands
3. Los servicios reciben valores de runtime mediante inyección por constructor.
4. Se refuerza con guardrails de arquitectura (`ServicePurityConventionsTest`).

## Consecuencias

### Positivas
- Mejor testabilidad y determinismo en la lógica de servicios.
- Separación más clara entre dominio y transporte/runtime.
- Menor deuda técnica a largo plazo en proyectos derivados.

### Trade-offs
- Más wiring en `Config/Services.php`.
- Firmas de constructor más explícitas y extensas.
