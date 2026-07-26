# Matriz de Alcance de Documentación

Esta matriz define dónde pertenece cada tipo de documentación para evitar duplicaciones.

## Fuentes Canónicas

1. `docs/architecture/*`: Contratos de arquitectura, invariantes, patrones de capas, ciclo de vida de requests.
2. `docs/tech/*`: Detalles operativos e implementación de subsistemas (config, runtime, troubleshooting).
3. `docs/template/*`: Contratos de gobierno y quality gates para usuarios del template.
4. `docs/adr/*`: Registros de decisiones de arquitectura.
5. `docs/AGENT_QUICK_REFERENCE.es.md`: Hoja de comandos y flujos de trabajo.
6. `docs/DOCUMENTATION_SCOPE.es.md`: Esta matriz de alcance.

## Reglas de Autoría

1. No duplicar implementaciones técnicas completas en `docs/tech/*` si `docs/architecture/*` ya cubre la regla.
2. Para cada tema nuevo, define un archivo canónico y mantiene el resto como referencias concisas.
3. Mantener paridad EN/ES para toda la documentación activa en `docs/`.

## Mapeo Práctico

1. Configuración y troubleshooting de queues: `docs/tech/QUEUE.es.md`.
2. Internals y headers de rate limiting: `docs/tech/rate-limiting.es.md`.
3. Estrategia y constraints de testing API: `docs/architecture/TESTING.es.md`.
4. Reglas prácticas/checklists de testing API: `docs/tech/TESTING_GUIDELINES.es.md`.
