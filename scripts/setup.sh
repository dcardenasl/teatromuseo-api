#!/usr/bin/env bash
# Shared setup library for install.sh and init.sh.
# Source this file — do not execute it directly.
#
# Step functions expect these globals to be set before calling them:
#   DB_HOST  DB_PORT  DB_USER  DB_PASS  DB_NAME  TEST_DB_NAME

# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

print_header() { printf "\n${BLUE}==> %s${NC}\n" "$1"; }
print_ok()     { printf "${GREEN}OK${NC} %s\n" "$1"; }
print_warn()   { printf "${YELLOW}WARN${NC} %s\n" "$1"; }
print_error()  { printf "${RED}ERROR${NC} %s\n" "$1"; }

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    print_error "Required command not found: $1"
    exit 1
  fi
}

# ---------------------------------------------------------------------------
# Timeout wrapper
# ---------------------------------------------------------------------------
# Run a command with a hard timeout to prevent hangs in CI/CD or non-TTY runs.
# Uses GNU `timeout` (Linux) or `gtimeout` (macOS w/ coreutils). When neither
# is available, runs the command without a timeout and logs a warning.
#
# Usage: run_with_timeout <seconds> <command> [args...]
run_with_timeout() {
  local seconds="$1"
  shift

  local cmd
  if command -v timeout >/dev/null 2>&1; then
    cmd="timeout"
  elif command -v gtimeout >/dev/null 2>&1; then
    cmd="gtimeout"
  else
    print_warn "Neither 'timeout' nor 'gtimeout' available; running without timeout (install coreutils on macOS)."
    "$@"
    return $?
  fi

  "$cmd" "$seconds" "$@"
  local rc=$?
  if [ "$rc" -eq 124 ]; then
    print_error "Command timed out after ${seconds}s: $*"
  fi
  return $rc
}

# ---------------------------------------------------------------------------
# Input helpers
# ---------------------------------------------------------------------------

trim() {
  local value="$1"
  value="${value#"${value%%[![:space:]]*}"}"
  value="${value%"${value##*[![:space:]]}"}"
  printf "%s" "$value"
}

slugify() {
  local input
  input="$(printf "%s" "$1" | tr '[:upper:]' '[:lower:]')"
  input="$(printf "%s" "$input" | sed -E 's/[^a-z0-9._-]+/-/g; s/^-+//; s/-+$//')"
  printf "%s" "$input"
}

ask_with_default() {
  local prompt="$1" default="$2" answer
  read -r -p "$prompt [$default]: " answer
  answer="$(trim "$answer")"
  printf "%s" "${answer:-$default}"
}

ask_required() {
  local prompt="$1" answer=""
  while [ -z "$answer" ]; do
    read -r -p "$prompt: " answer
    answer="$(trim "$answer")"
  done
  printf "%s" "$answer"
}

ask_hidden() {
  local prompt="$1" answer=""
  while [ -z "$answer" ]; do
    read -r -s -p "$prompt: " answer
    printf "\n" >&2
    answer="$(trim "$answer")"
  done
  printf "%s" "$answer"
}

validate_db_name() {
  local name="$1"
  case "$name" in
    ''|*[!A-Za-z0-9_]*)
      print_error "Invalid database name '$name'. Use only letters, numbers, and underscores."
      exit 1
      ;;
  esac
}

# ---------------------------------------------------------------------------
# MySQL mode detection
# Uses :-  defaults so sourcing this file after detect_mysql_mode has already
# run (e.g. in install.sh pre-clone phase) won't reset the chosen mode.
# ---------------------------------------------------------------------------

MYSQL_MODE="${MYSQL_MODE:-local}"
DOCKER_CONTAINER="${DOCKER_CONTAINER:-}"
DETECTED_DOCKER_PORT="${DETECTED_DOCKER_PORT:-}"

detect_mysql_mode() {
  if command -v mysql >/dev/null 2>&1; then
    MYSQL_MODE="local"
    print_ok "MySQL client found (local)"
    return
  fi

  print_warn "mysql CLI not found. Checking for Docker..."

  if ! command -v docker >/dev/null 2>&1; then
    print_warn "Neither mysql CLI nor docker found."
    print_warn "Database creation will be skipped — create DBs manually."
    MYSQL_MODE="skip"
    return
  fi

  # If CI4_DB_HOST is set to a non-localhost value,
  # assume we're using a remote database (not Docker).
  if [ -n "${CI4_DB_HOST:-}" ] && [ "${CI4_DB_HOST}" != "localhost" ] && [ "${CI4_DB_HOST}" != "127.0.0.1" ] && [ "${CI4_DB_HOST}" != "0.0.0.0" ]; then
    MYSQL_MODE="local"  # Assume it's a remote DB connection, not Docker
    print_ok "Using remote database (CI4_DB_HOST set)"
    return
  fi

  # When no explicit container is specified, try to use the first available Docker MySQL container
  if [ -z "${CI4_DOCKER_CONTAINER:-}" ]; then
    # Get list of running containers with MySQL in the image name
    local containers_with_mysql
    containers_with_mysql=$(docker ps 2>/dev/null | grep -i mysql | awk '{print $NF}' | head -1)

    if [ -n "$containers_with_mysql" ]; then
      DOCKER_CONTAINER="$containers_with_mysql"
      MYSQL_MODE="docker"
      print_ok "Auto-detected Docker container: $DOCKER_CONTAINER"
      return
    fi
  fi

  if [ -n "${CI4_DOCKER_CONTAINER:-}" ]; then
    DOCKER_CONTAINER="${CI4_DOCKER_CONTAINER}"
    MYSQL_MODE="docker"
    if docker inspect "$DOCKER_CONTAINER" >/dev/null 2>&1; then
      print_ok "Docker container found: $DOCKER_CONTAINER"
      return
    else
      print_error "Container '$DOCKER_CONTAINER' not found or not running."
      exit 1
    fi
  fi

  # Interactive mode: ask for container name
  printf "Running containers:\n"
  local mysql_containers
  mysql_containers=$(docker ps --format "{{.Names}}" 2>/dev/null | grep -i mysql || true)

  if [ -n "$mysql_containers" ]; then
    printf "%s\n" "$mysql_containers" | sed 's/^/  /'
    local default_container
    default_container=$(printf "%s" "$mysql_containers" | head -1 | tr -d ' ')
    # In non-interactive mode (piped input), automatically use the first container
    if [ ! -t 0 ]; then
      DOCKER_CONTAINER="$default_container"
      print_ok "Selected Docker container (non-interactive mode): $DOCKER_CONTAINER"
    else
      DOCKER_CONTAINER="$(ask_with_default "Docker container name with MySQL" "$default_container")"
    fi
  else
    printf "  (none found with 'mysql' in image name)\n"
    DOCKER_CONTAINER="$(ask_required "Docker container name with MySQL")"
  fi

  if ! docker inspect "$DOCKER_CONTAINER" >/dev/null 2>&1; then
    print_error "Container '$DOCKER_CONTAINER' not found or not running."
    exit 1
  fi

  # Detect mapped port.
  # 'docker port' may return IPv4 (0.0.0.0:PORT) and/or IPv6 (:::PORT) lines;
  # take the last colon-separated field to handle both formats.
  local mapped_port
  mapped_port=$(docker port "$DOCKER_CONTAINER" 3306 2>/dev/null \
    | awk -F: '{print $NF}' | tr -d ' ' | head -1) || true
  if [ -n "$mapped_port" ]; then
    DETECTED_DOCKER_PORT="$mapped_port"
    print_ok "Detected Docker host port: $DETECTED_DOCKER_PORT"
  else
    print_warn "Could not detect Docker host port for MySQL. Using default 3306."
    DETECTED_DOCKER_PORT=""
  fi

  MYSQL_MODE="docker"
  print_ok "Will use docker exec on container: $DOCKER_CONTAINER"
}

run_mysql_sql() {
  local sql="$1"
  local output
  case "$MYSQL_MODE" in
    local)
      local cmd=(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER")
      # Pass password via env var to avoid exposure in process list
      output=$(MYSQL_PWD="$DB_PASS" "${cmd[@]}" -e "$sql" 2>&1) || { printf "%s\n" "$output"; return 1; }
      printf "%s\n" "$output"
      ;;
    docker)
      # Write credentials to a temp file inside the container to avoid
      # exposing the password as a docker exec CLI argument (visible in ps).
      local tmp_cnf container_cnf exit_status
      tmp_cnf=$(mktemp)
      chmod 600 "$tmp_cnf"
      printf '[client]\npassword=%s\n' "$DB_PASS" > "$tmp_cnf"
      container_cnf="/tmp/.mysql_$$.cnf"
      docker cp "$tmp_cnf" "$DOCKER_CONTAINER:$container_cnf" >/dev/null 2>&1 || true
      rm -f "$tmp_cnf"
      output=$(docker exec -i "$DOCKER_CONTAINER" \
        mysql "--defaults-extra-file=$container_cnf" -u"$DB_USER" -e "$sql" 2>&1)
      exit_status=$?
      docker exec "$DOCKER_CONTAINER" rm -f "$container_cnf" >/dev/null 2>&1 || true
      if [ $exit_status -ne 0 ]; then
        printf "%s\n" "$output"
        return 1
      fi
      printf "%s\n" "$output"
      ;;
    skip)
      return 1
      ;;
  esac
}

# ---------------------------------------------------------------------------
# Shared setup steps
# ---------------------------------------------------------------------------

# Install or update Composer dependencies.
ci4_install_deps() {
  print_header "Installing dependencies"
  printf "This may take a few minutes depending on your connection speed...\n"

  # When cloned standalone, path repositories (ci4-api-core, ci4-api-scaffolding) may not exist.
  # If so, remove them from composer.json and use Packagist versions instead.
  if [ ! -d "../ci4-api-core" ]; then
    print_warn "Path repository '../ci4-api-core' not found. Using Packagist version."
    php -r '
      $json = json_decode(file_get_contents("composer.json"), true);
      if (isset($json["repositories"]) && is_array($json["repositories"])) {
        $json["repositories"] = array_values(array_filter(
          $json["repositories"],
          fn($r) => !isset($r["url"]) || strpos($r["url"], "ci4-api") === false
        ));
        if ($json["repositories"] === []) unset($json["repositories"]);
      }
      file_put_contents("composer.json", json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
    ' || print_warn "Could not update composer.json; proceeding anyway."
  fi

  # 'timeout' is a Linux coreutils command; macOS ships without it.
  # Use gtimeout (brew install coreutils) when available, otherwise run without a timeout.
  if command -v timeout >/dev/null 2>&1; then
    _COMPOSER_TIMEOUT="timeout 600"
  elif command -v gtimeout >/dev/null 2>&1; then
    _COMPOSER_TIMEOUT="gtimeout 600"
  else
    _COMPOSER_TIMEOUT=""
  fi
  if [ -d "vendor" ]; then
    print_warn "vendor/ already exists. Running composer update..."
    $_COMPOSER_TIMEOUT composer update --no-interaction \
      || { print_error "composer update timed out or failed."; exit 1; }
  else
    $_COMPOSER_TIMEOUT composer install --no-interaction --prefer-dist \
      || { print_error "composer install timed out or failed."; exit 1; }
  fi
  unset _COMPOSER_TIMEOUT
  print_ok "Composer dependencies installed"
}

# Write .env from .env.example and configure it via bootstrap_env.php.
# Globals required: DB_HOST DB_PORT DB_USER DB_PASS DB_NAME TEST_DB_NAME
ci4_configure_env() {
  print_header "Configuring .env"
  if [ ! -f ".env.example" ]; then
    print_error ".env.example not found. The template may be corrupted or on the wrong branch."
    exit 1
  fi
  cp .env.example .env
  # Generate encryption key inline: CI4's key:generate uses a regex that
  # doesn't match quoted empty values (encryption.key = ""), so it silently
  # skips the write. bootstrap_env.php handles any existing value format.
  local _enc_key="hex2bin:$(php -r 'echo bin2hex(random_bytes(32));')"
  php scripts/bootstrap_env.php \
    --file .env \
    --set "database.default.hostname=${DB_HOST}" \
    --set "database.default.database=${DB_NAME}" \
    --set "database.default.username=${DB_USER}" \
    --set "database.default.password=${DB_PASS}" \
    --set "database.default.port=${DB_PORT}" \
    --set "database.tests.hostname=${DB_HOST}" \
    --set "database.tests.database=${TEST_DB_NAME}" \
    --set "database.tests.username=${DB_USER}" \
    --set "database.tests.password=${DB_PASS}" \
    --set "database.tests.port=${DB_PORT}" \
    --set "encryption.key=${_enc_key}" \
    --generate-jwt
  print_ok ".env configured and keys generated"
}

# Create the main and test databases.
# Globals required: DB_NAME TEST_DB_NAME MYSQL_MODE (+ DB_* for local/docker modes)
ci4_prepare_databases() {
  print_header "Preparing databases"
  local sql="CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`; CREATE DATABASE IF NOT EXISTS \`${TEST_DB_NAME}\`;"
  if [ "$MYSQL_MODE" = "skip" ]; then
    print_warn "Database creation skipped — create them manually:"
    printf "  CREATE DATABASE IF NOT EXISTS \`%s\`;\n" "$DB_NAME"
    printf "  CREATE DATABASE IF NOT EXISTS \`%s\`;\n" "$TEST_DB_NAME"
  elif run_mysql_sql "$sql"; then
    print_ok "Databases ensured: $DB_NAME, $TEST_DB_NAME"
  else
    print_error "Database creation failed. Check your MySQL connection settings and try again."
    exit 1
  fi
}

# Verify that the main database exists before running migrations.
# Globals required: DB_NAME MYSQL_MODE (+ DB_* for local/docker modes)
ci4_verify_database() {
  if [ "$MYSQL_MODE" = "skip" ]; then
    print_warn "Skipping database verification (MySQL mode is 'skip')"
    return 0
  fi

  print_header "Verifying database"
  local check_sql="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}';"
  local result
  result=$(run_mysql_sql "$check_sql" 2>&1)

  if echo "$result" | grep -q "$DB_NAME"; then
    print_ok "Database '$DB_NAME' verified"
  else
    print_error "Database '$DB_NAME' does not exist. Check if ci4_prepare_databases succeeded."
    exit 1
  fi
}

# Run database migrations. Wrapped with a hard timeout so a hung DB connection
# never blocks `init.sh` indefinitely (matters for CI/CD and `new-project.sh`).
ci4_run_migrations() {
  print_header "Running migrations"
  local migrate_timeout="${CI4_MIGRATE_TIMEOUT:-120}"
  if run_with_timeout "$migrate_timeout" php spark migrate; then
    print_ok "Migrations completed"
  else
    print_error "Migrations failed (or timed out after ${migrate_timeout}s). Fix and run 'php spark migrate' manually."
    exit 1
  fi
}

# Seed the RBAC bootstrap data (applications, permissions, roles).
# Idempotent — safe to re-run. Required before bootstrap-superadmin so the
# 'superadmin' role exists when the command attaches it to the new user.
ci4_seed_rbac() {
  print_header "RBAC bootstrap (applications, permissions, roles)"
  local seed_timeout="${CI4_SEED_TIMEOUT:-60}"
  if run_with_timeout "$seed_timeout" php spark db:seed RbacBootstrapSeeder; then
    print_ok "RBAC bootstrap completed"
  else
    print_error "RBAC seeder failed (or timed out after ${seed_timeout}s). Run 'php spark db:seed RbacBootstrapSeeder' manually."
    exit 1
  fi
}

# Generate the OpenAPI / Swagger schema. Non-fatal: a slow generator should
# not block the rest of init.sh, but it must not hang forever either.
ci4_generate_swagger() {
  print_header "Generating OpenAPI schema"
  local swagger_timeout="${CI4_SWAGGER_TIMEOUT:-90}"
  if run_with_timeout "$swagger_timeout" php spark swagger:generate; then
    print_ok "OpenAPI schema generated"
  else
    print_warn "Swagger generation failed or timed out after ${swagger_timeout}s. Run 'php spark swagger:generate' manually."
  fi
}
