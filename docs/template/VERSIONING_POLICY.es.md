# Política de Versionado del Template

Esta política define cómo versionar cambios de arquitectura y contratos internos del template.

## 1. Intención de versionado

1. Se prioriza la estabilidad del contrato HTTP externo.
2. Los contratos internos pueden evolucionar para reducir deuda técnica.
3. Cualquier breaking interno debe declararse y documentarse en release notes.

## 2. Clases de cambio

1. **Patch**: corrección o refactor sin breaking.
2. **Minor**: capacidades aditivas, compatibles hacia atrás.
3. **Major (interno)**: breaking en contratos internos (services/interfaces/scaffold), con guía de migración.
4. **Major (externo)**: breaking en contrato API HTTP (payload/status/rutas); evitar salvo aprobación explícita.

## 3. Divulgación obligatoria en PR

1. Archivos/interfaces afectados.
2. Impacto breaking (si existe).
3. Acciones de migración para proyectos derivados.
4. Tests/guardrails actualizados.

## 4. Enforcement

1. `composer quality` en verde.
2. Tests de arquitectura en `tests/Unit/Architecture` en verde.
3. Paridad EN/ES de documentación en verde.
