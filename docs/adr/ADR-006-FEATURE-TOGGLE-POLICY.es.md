# ADR-006: Política de Feature Toggles en el Borde HTTP

## Estado
Aceptado

## Contexto

Las validaciones de disponibilidad de features (metrics/monitoring) estaban dispersas en controladores, generando comportamiento inconsistente y lógica duplicada.

## Decisión

1. Los feature toggles se aplican en el borde HTTP mediante filtros.
2. Se introduce una configuración tipada (`Config\FeatureFlags`) para centralizar switches.
3. Se usa `FeatureToggleFilter` con argumentos de ruta (`featureToggle:metrics`, `featureToggle:monitoring`).
4. Los controllers quedan enfocados en orquestación y delegación de negocio.

## Consecuencias

### Positivas
- Comportamiento uniforme 503 para features deshabilitadas.
- Política centralizada y mejor control operacional.
- Menos lógica duplicada en controllers.

### Trade-offs
- Más artefactos de filtro/configuración a mantener.
- Definiciones de rutas ligeramente más verbosas.
