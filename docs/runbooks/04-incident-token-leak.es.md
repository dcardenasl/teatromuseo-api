# Runbook 04 — Respuesta a incidente: leak de JWT o refresh token

**Severidad:** Crítica | **ETA:** 15 min para contención del blast radius, horas para audit completo | **Auditoría:** B11.2

## Cuándo usar

- Un usuario reporta que su cuenta hizo cosas que no hizo.
- Un JWT específico o su `jti` aparece donde no debería (logs, error reports, screenshot, repo público).
- Entradas anómalas en `audit_logs`: mismo `user_id`, dos IPs distintas en el mismo minuto.

## Fase 1 — Contención (objetivo: 15 min)

### Paso 1 — Revocar el/los token(s)

Si tienes el JWT o su claim `jti`:

```bash
# Decodear el token para sacar su `jti`.
echo "$TOKEN" | cut -d. -f2 | base64 -d 2>/dev/null | jq -r .jti

# Revocar.
mysql "$DB_NAME" -e "
  INSERT INTO token_revocations (jti, user_id, revoked_at, reason)
  VALUES ('<jti>', <user_id>, NOW(), 'incident-response: leaked token');
"
```

La próxima request con este JWT fallará en el chequeo de revocación de `JwtAuthFilter`. El `Config\Api::$jwtRevocationCacheTtl` (default 60s) implica hasta 60s de gracia; para incidente caliente, bustear el caché:

```bash
php spark cache:clear
```

Si solo tienes el `user_id` (no el contenido del token filtrado):

```bash
# Revocar TODOS los refresh tokens del usuario. Será forzado a re-autenticarse.
mysql "$DB_NAME" -e "
  UPDATE refresh_tokens
  SET revoked_at = NOW(), revoked_reason = 'incident-response: blanket revoke'
  WHERE user_id = <user_id> AND revoked_at IS NULL;
"
# Los access tokens en flight expiran solos por JWT_ACCESS_TOKEN_TTL
# (default 1h). Para invalidación completa en <60s, además rotar el
# secret de firma JWT (ver runbook 01).
```

### Paso 2 — Suspender al usuario (si se sospecha compromiso de cuenta)

```bash
mysql "$DB_NAME" -e "
  UPDATE users
  SET status = 'suspended', suspended_at = NOW(), suspended_reason = 'incident-response'
  WHERE id = <user_id>;
"
```

`UserAccountGuard::assertCanAuthenticate()` rechaza usuarios suspended en cada login; combinado con revocar tokens deja la cuenta totalmente locked-out.

### Paso 3 — Capturar audit trail

```bash
# Snapshotear audit_logs relevantes a archivo para que el resto del incidente
# ocurra sin perturbar los datos.
mysql "$DB_NAME" -e "
  SELECT * FROM audit_logs
  WHERE user_id = <user_id>
    AND created_at >= NOW() - INTERVAL 7 DAY
  ORDER BY created_at DESC
" > /tmp/incident-<ticket-id>-audit.tsv

# Igual para refresh tokens.
mysql "$DB_NAME" -e "
  SELECT * FROM refresh_tokens WHERE user_id = <user_id>
" > /tmp/incident-<ticket-id>-refresh-tokens.tsv
```

Mover ambos a un long-term incident store (S3 + KMS basta).

## Fase 2 — Investigación

### Reconstruir la timeline

```sql
SELECT created_at, ip_address, user_agent, event_type, resource, resource_id, metadata
FROM audit_logs
WHERE user_id = <user_id>
  AND created_at >= NOW() - INTERVAL 30 DAY
ORDER BY created_at DESC
LIMIT 500;
```

Buscar:

- IPs que el usuario no usa normalmente.
- Operaciones que el usuario no haría normalmente (asignaciones de rol admin, creación de API keys, deletes de archivos).
- Patrón two-IP-same-minute → señal fuerte de session theft.

### Verificar artefactos downstream

```sql
-- ¿La sesión filtrada creó nuevas API keys?
SELECT id, name, prefix, created_at, revoked_at
FROM api_keys
WHERE created_by_user_id = <user_id>
  AND created_at >= '<incident_window_start>';

-- ¿Modificó usuarios?
SELECT user_id, event_type, created_at
FROM audit_logs
WHERE actor_user_id = <user_id>
  AND event_type IN ('user.create', 'user.update', 'user.role-assign')
  AND created_at >= '<incident_window_start>';

-- ¿Descargó archivos?
SELECT file_id, created_at
FROM audit_logs
WHERE actor_user_id = <user_id>
  AND event_type = 'file.download'
  AND created_at >= '<incident_window_start>';
```

## Fase 3 — Contención de causa raíz

El token filtrado es síntoma; investigar cómo salió.

### Causas comunes y respuestas

| Causa raíz | Acción |
|---|---|
| Token visible en logs HTTP de acceso | Auditar `Config\Logger::$threshold`. Confirmar que `JwtAuthFilter` y `RequestLoggingFilter` redactan `Authorization`. |
| Token en error report (Sentry / similar) | El SDK PHP de Sentry redacta `Authorization` por default; verificar en `MonologHandler`. Agregar `data_scrubber` config si falta. |
| Usuario pegó token a soporte chat / repo público | Educación + este runbook publicado. Sin fix técnico más allá de revocación rápida. |
| Token sobrevivió ventana sospechosa de refresh | Investigar `RefreshTokenService::rotate()` — confirmar que el rotated token revoca al padre atómicamente. |
| Brute-force / credential stuffing llevó a login legítimo | Verificar conteos de `auth_login_failed` en `audit_logs`. Apretar thresholds de `AuthThrottleFilter` si necesario. |

### Decidir si rotar el secret global

Si el secret de firma del token filtrado pudo también haberse filtrado (e.g. el leak fue un `.env`, no solo el JWT resultante), seguir **runbook 01** para rotar `JWT_SECRET_KEY`. Esto force-loguea-out a todos los usuarios, lo cual es aceptable para compromiso confirmado del secret.

## Fase 4 — Recuperación

Una vez contenida la causa raíz:

```bash
# Re-habilitar al usuario (si la suspensión fue preventiva).
mysql "$DB_NAME" -e "
  UPDATE users
  SET status = 'active', suspended_at = NULL, suspended_reason = NULL
  WHERE id = <user_id>;
"
```

Comunicar con el usuario:

- "Detectamos actividad inusual en tu cuenta a las <hora>."
- "Hemos invalidado tus sesiones; por favor inicia sesión de nuevo."
- "Recomendamos cambiar tu contraseña y habilitar 2FA (cuando 2FA esté disponible)."
- **No** revelar detalles internos de la investigación.

## Fase 5 — Post-mortem

Dentro de 5 días hábiles tras contención, post-mortem escrito cubriendo:

1. **Timeline** — cuándo pasó el leak, cuándo lo notamos, cuándo contuvimos.
2. **Causa raíz** — una sola oración.
3. **Daño** — qué hizo el atacante con el acceso. Referenciar audit logs capturados en Fase 1.
4. **Action items** — qué cambiamos para que no se repita. Mínimo: test de regresión para el vector de leak + review de code paths relacionados.
5. **Notice al cliente** — si GDPR / CCPA / regulación sectorial requiere disclosure.

Agregar el post-mortem a `docs/runbooks/incidents/<YYYY-MM-DD>-<short-name>.md`. Aún si redactado, mantener la estructura en archivo hace que los próximos sean más rápidos.
