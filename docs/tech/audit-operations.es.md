# Runbook Operativo de Auditoria

Guia operativa para retencion de auditoria y alertas criticas de seguridad.

## 1. Limpieza de Retencion (`audit:clean`)

Comando:

```bash
php spark audit:clean [days]
```

- `days` es opcional.
- Si se omite, usa `AUDIT_RETENTION_DAYS` (por defecto `90`).

Ejemplos:

```bash
php spark audit:clean
php spark audit:clean 90
php spark audit:clean 180
```

### Cron recomendado (diario a las 02:15)

```cron
15 2 * * * cd /path/to/ci4-api-starter && /usr/bin/php spark audit:clean >> /var/log/ci4/audit-clean.log 2>&1
```

### Cron recomendado (cada hora en ambientes de alto volumen)

```cron
0 * * * * cd /path/to/ci4-api-starter && /usr/bin/php spark audit:clean >> /var/log/ci4/audit-clean.log 2>&1
```

## 2. Reglas de Alerta Critica

Monitorear estos eventos desde `audit_logs`:

1. `authorization_denied_role` o `authorization_denied_resource`
- Severidad: `critical`
- Disparo: `>= 5` eventos del mismo `user_id` o IP en 10 minutos.

2. `api_key_auth_failed`
- Severidad: `critical`
- Disparo: `>= 10` eventos del mismo prefijo de key o IP en 10 minutos.

3. `api_key_rate_limit_exceeded`
- Severidad: `warning`
- Disparo: crecimiento sostenido para la misma key/IP por 15 minutos.

4. `revoked_token_reuse_detected`
- Severidad: `critical`
- Disparo: cualquier evento unico.

5. `login_failure` / `password_reset_token_invalid` / `email_verification_failed`
- Severidad: `warning`
- Disparo: pico anomalo por cuenta o IP en 15 minutos.

## 3. Checklist de Triage (Primeros 15 Minutos)

1. Confirmar validez de la alerta:
- Revisar `request_id`, `created_at`, `action`, `result`, `severity`, `ip_address`, `user_id`.

2. Identificar alcance:
- Contar usuarios/recursos impactados.
- Confirmar si la actividad es aislada o distribuida.

3. Validar amenaza activa:
- Revisar eventos actuales para la misma IP, cuenta, prefijo de key o token `jti`.

## 4. Acciones de Contencion

1. Abuso de API key:
- Desactivar API key (`is_active = 0`).
- Rotar y reemitir key.

2. Abuso de token:
- Revocar token actual.
- Revocar todos los tokens del usuario si aplica.

3. Abuso de cuenta:
- Forzar reseteo de password.
- Bloqueo temporal de cuenta segun politica.

4. Intentos de abuso de roles:
- Verificar origen de sesion admin y postura MFA.

## 5. Evidencia y Forense

Preservar:

1. Filas relevantes de `audit_logs` (`request_id`, IP, actor, metadata).
2. Logs de request y logs de aplicacion relacionados.
3. Timeline con timestamps en UTC.

## 6. Plantilla de Cierre de Incidente

1. Resumen:
- Que paso y cuando.

2. Alcance:
- Usuarios/recursos impactados y duracion.

3. Causa raiz:
- Fuga de credenciales, abuso, error de configuracion, etc.

4. Acciones ejecutadas:
- Revocaciones, bloqueos, rotaciones, ajustes de politica.

5. Seguimiento:
- Tests agregados, ajuste de umbrales, mejoras del runbook.
