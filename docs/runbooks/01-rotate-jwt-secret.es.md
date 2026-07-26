# Runbook 01 — Rotar el secret de firma JWT

**Severidad:** Alta (compromiso de signing key) | **ETA:** ~15 minutos (con breve ventana de auth interrumpida) | **Auditoría:** B11.2

## Cuándo usar

- Una laptop con `.env` se perdió / comprometió.
- Una copia de `JWT_SECRET_KEY` se filtró a una superficie no-prod (logs, screenshot, hilo de soporte de terceros).
- Rotación anual de rutina (recomendado).

## Pre-flight

```bash
# 1. Confirmar quórum de admins. Tras rotar, todo JWT activo se invalida;
#    los usuarios re-loguean en frío.
mysql -e "SELECT COUNT(*) FROM users WHERE status='active';" "$DB_NAME"

# 2. Generar el secret candidato. Tratar el output como sensible —
#    NO loggear, NO pegar a chat.
NEW_SECRET="$(openssl rand -base64 64 | tr -d '\n')"
echo "Length: ${#NEW_SECRET}"   # debe ser >= 64 bytes
```

## Procedimiento

### Paso 1 — Stagear el nuevo secret

Actualizar el `.env` de producción (o el equivalente en k8s `Secret` / Vault):

```dotenv
JWT_SECRET_KEY="$NEW_SECRET"
```

> **No deployar todavía.** El siguiente paso valida que el valor está bien formado antes de que llegue tráfico.

### Paso 2 — Validar

```bash
# En el pod de staging / un container one-off, correr env:check con el nuevo secret.
JWT_SECRET_KEY="$NEW_SECRET" php spark env:check --strict
```

Esperar: "All required environment variables are present and well-formed."

Si el comando rechaza (length < 64, substring placeholder, etc.), regenerar y reintentar.

### Paso 3 — Rolar el deployment

```bash
# Kubernetes
kubectl rollout restart deployment/ci4-api-starter

# systemd
systemctl reload php-fpm

# Docker compose
docker compose up -d --force-recreate api
```

### Paso 4 — Verificar que auth funciona con el nuevo secret

```bash
# Un login debe tener éxito y retornar JWT firmado con el nuevo secret.
curl -sX POST "$API_URL/api/v1/auth/login" \
  -H 'Content-Type: application/json' \
  -d '{"email":"smoketest@admin","password":"...."}' | jq -r .data.access_token
```

### Paso 5 — Comunicar

- Todos los usuarios activos serán force-logout en su próxima request (JWT viejo falla validación de firma → 401 → admin redirige a /login).
- Postear nota breve en canal de operadores: "JWT secret rotado a las &lt;timestamp&gt;. Usuarios pueden necesitar re-login."
- **No** publicar la razón de rotación si fue un leak — eso es respuesta-a-incidente, manejado aparte.

## Rollback

Si el Paso 4 revela que el nuevo secret está mal (typo, encoding):

1. Restaurar el valor previo desde el secret store (Vault, AWS SM, k8s `Secret` revision).
2. Re-rolar: `kubectl rollout restart` o equivalente.
3. Investigar la causa del bad-secret antes de reintentar.

**Nunca** mantener ambos secrets viejo + nuevo activos en paralelo — el JWT layer no soporta rotación basada en `kid` en v1.x; secrets superpuestos significa que gana quien firmó al final.

## Checklist post-mortem (solo si se rotó por leak)

- [ ] Identificar cómo se filtró el secret (logs / screenshot / chat / repo).
- [ ] Si fue repo-leaked: reescribir historia git (`git filter-repo`) o rotar de nuevo asumiendo que el valor filtrado está en el clone de alguien.
- [ ] Auditar `audit_logs` por eventos `auth.login` anómalos en la ventana del leak.
- [ ] Considerar si refresh tokens emitidos durante la ventana del leak deben revocarse (tabla `token_revocations`).
- [ ] Actualizar `.env.example` placeholder si el leak reveló un default engañoso.
