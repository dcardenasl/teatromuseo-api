#!/usr/bin/env bash
#
# First-run bootstrap for the ci4-website-builder API Docker container.
#
# Idempotent: every step is safe to re-run. The container can be started,
# stopped, and rebuilt without manual setup between runs.
#
#   1. Ensure /var/www/html/.env exists (copy from .env.example if missing).
#   2. Generate JWT_SECRET_KEY and encryption.key if absent or placeholder.
#   3. Run database migrations (CI4's migrator skips already-applied ones).
#   4. Seed the RBAC bootstrap (the seeder itself is idempotent).
#   5. Hand off to the original command (apache2-foreground).
#
set -euo pipefail

APP_ROOT="/var/www/html"
ENV_FILE="${APP_ROOT}/.env"
ENV_STORE_DIR="${APP_ROOT}/.env-store"

cd "${APP_ROOT}"

# --- 1. Ensure .env exists ------------------------------------------------
# Prefer a previously-persisted copy from the api-env volume so secrets
# survive `docker compose down` without -v. Fall back to .env.example.
if [ ! -f "${ENV_FILE}" ]; then
  if [ -f "${ENV_STORE_DIR}/.env" ]; then
    cp "${ENV_STORE_DIR}/.env" "${ENV_FILE}"
  elif [ -f "${APP_ROOT}/.env.example" ]; then
    cp "${APP_ROOT}/.env.example" "${ENV_FILE}"
  else
    echo "FATAL: neither .env nor .env.example present in ${APP_ROOT}" >&2
    exit 1
  fi
fi

# --- 2. Generate secrets if missing or placeholder ------------------------
upsert_env_key() {
  local key="$1"
  local value="$2"
  # Escape forward slashes and ampersands for sed replacement.
  local escaped
  escaped=$(printf '%s' "${value}" | sed -e 's/[\/&]/\\&/g')

  if grep -qE "^${key}[[:space:]]*=" "${ENV_FILE}"; then
    sed -i.bak -E "s/^${key}[[:space:]]*=.*$/${key} = \"${escaped}\"/" "${ENV_FILE}"
    rm -f "${ENV_FILE}.bak"
  else
    printf '\n%s = "%s"\n' "${key}" "${value}" >> "${ENV_FILE}"
  fi
}

needs_secret() {
  local key="$1"
  local current
  current=$(grep -E "^${key}[[:space:]]*=" "${ENV_FILE}" | head -n1 | sed -E "s/^${key}[[:space:]]*=[[:space:]]*//;s/^\"//;s/\"$//" || true)
  if [ -z "${current}" ] || echo "${current}" | grep -qE '(change-me|your-secret|CHANGE_THIS|placeholder)'; then
    return 0
  fi
  return 1
}

if needs_secret 'JWT_SECRET_KEY'; then
  upsert_env_key 'JWT_SECRET_KEY' "$(openssl rand -base64 64 | tr -d '\n')"
fi

if needs_secret 'encryption.key'; then
  upsert_env_key 'encryption.key' "hex2bin:$(openssl rand -hex 32)"
fi

# Pin the Docker-managed values into .env. CI4 reads .env before OS env, so
# anything left at the .env.example defaults (e.g. database.default.hostname =
# localhost) would shadow the compose `environment:` overrides.
upsert_env_key 'database.default.hostname' "${DB_HOST:-db}"
upsert_env_key 'database.default.port'     "${DB_PORT:-3306}"
upsert_env_key 'database.default.database' "${MYSQL_DATABASE:-ci4_website_builder_api}"
upsert_env_key 'database.default.username' "${MYSQL_USER:-ci4_user}"
upsert_env_key 'database.default.password' "${MYSQL_PASSWORD:-ci4_dev_password}"
upsert_env_key 'database.default.DBDriver' 'MySQLi'

if [ -n "${APP_BASE_URL:-}" ]; then
  upsert_env_key 'app.baseURL' "${APP_BASE_URL}"
fi

if [ -n "${CI_ENVIRONMENT:-}" ]; then
  upsert_env_key 'CI_ENVIRONMENT' "${CI_ENVIRONMENT}"
fi

# --- 3. Persist .env to the named volume so it survives container removal -
mkdir -p "${ENV_STORE_DIR}"
cp "${ENV_FILE}" "${ENV_STORE_DIR}/.env"

# Apache runs as www-data; ensure .env and writable/ are readable/writable by it.
chown -R www-data:www-data "${ENV_FILE}" "${ENV_STORE_DIR}" "${APP_ROOT}/writable" 2>/dev/null || true

# --- 4. Wait for DB then migrate + seed ----------------------------------
echo "[entrypoint] Waiting for database..."
for i in $(seq 1 30); do
  if php -r '
    $h = getenv("DB_HOST") ?: "db";
    $u = getenv("MYSQL_USER") ?: "ci4_user";
    $p = getenv("MYSQL_PASSWORD") ?: "";
    $d = getenv("MYSQL_DATABASE") ?: "ci4_website_builder_api";
    @$c = mysqli_connect($h, $u, $p, $d);
    exit($c ? 0 : 1);
  '; then
    echo "[entrypoint] Database is ready."
    break
  fi
  if [ "${i}" = "30" ]; then
    echo "[entrypoint] FATAL: database unreachable after 30 attempts" >&2
    exit 1
  fi
  sleep 2
done

echo "[entrypoint] Running migrations..."
php spark migrate --all || true

echo "[entrypoint] Seeding RBAC bootstrap (idempotent)..."
php spark db:seed RbacBootstrapSeeder || true

# --- 5. Hand off ---------------------------------------------------------
exec "$@"
