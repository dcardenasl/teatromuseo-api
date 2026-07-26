# ADR-004: Gobernanza de Observabilidad

## Estado
Aceptado

## Contexto

El proyecto necesita observabilidad consistente (logs, métricas, trazabilidad de requests) sin acoplar lógica de negocio a detalles de transporte HTTP.

## Decisión

1. Mantener instrumentación transversal en filtros/librerías compartidas.
2. Mantener servicios puros (sin payloads HTTP).
3. Estandarizar respuestas y metadatos desde `ApiController`.
4. Aplicar quality gates y tests de arquitectura como guardrails.

## Consecuencias

1. Mayor consistencia entre módulos y menor deuda técnica.
2. Mejor trazabilidad operativa para auditoría y diagnóstico.
3. Coste inicial mayor en contratos/disciplinas, menor coste de mantenimiento posterior.

## Referencia

Para detalle completo, ver versión fuente en inglés: `ADR-004-OBSERVABILITY-GOVERNANCE.md`.
