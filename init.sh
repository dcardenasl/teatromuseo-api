#!/usr/bin/env bash
# CodeIgniter 4 API Starter — environment initializer.
# Run this script from the project root after cloning the repo.
# Usage: ./init.sh [--skip-deps] [--skip-db] [--skip-server]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/setup.sh
source "$SCRIPT_DIR/scripts/setup.sh"

# Auto-detect Docker container if not explicitly provided and available
if [ -z "${CI4_DOCKER_CONTAINER:-}" ] && command -v docker >/dev/null 2>&1; then
  # Try to auto-detect the first MySQL container
  _auto_docker_container=$(docker ps --format "{{.Names}}" 2>/dev/null | while read -r name; do
    if docker inspect "$name" --format='{{.Config.Image}}' 2>/dev/null | grep -qi mysql; then
      echo "$name"
      break
    fi
  done | head -1)
  if [ -n "$_auto_docker_container" ]; then
    export CI4_DOCKER_CONTAINER="$_auto_docker_container"
  fi
fi

# ---------------------------------------------------------------------------
# Flags
# ---------------------------------------------------------------------------

SKIP_DEPS=false
SKIP_DB=false
SKIP_SERVER=false
DOCKER_CONTAINER_ARG=""

while [ $# -gt 0 ]; do
  case $1 in
    --skip-deps)   SKIP_DEPS=true; shift ;;
    --skip-db)     SKIP_DB=true; shift ;;
    --skip-server) SKIP_SERVER=true; shift ;;
    --docker-container)
      DOCKER_CONTAINER_ARG="$2"
      shift 2
      ;;
    --help)
      printf "Usage: ./init.sh [OPTIONS]\n\n"
      printf "Options:\n"
      printf "  --skip-deps           Skip composer install\n"
      printf "  --skip-db             Skip database creation and migrations\n"
      printf "  --skip-server         Do not offer to start the development server\n"
      printf "  --docker-container    Specify Docker container name for MySQL\n"
      printf "  --help                Show this help message\n"
      exit 0
      ;;
    *)
      print_error "Unknown option: $1"
      exit 1
      ;;
  esac
done

# If Docker container was passed as an argument, use it
if [ -n "$DOCKER_CONTAINER_ARG" ]; then
  export CI4_DOCKER_CONTAINER="$DOCKER_CONTAINER_ARG"
fi

LOG_FILE="$(pwd)/init.log"
if [ "${CI4_FORCE_LOG_TO_FILE:-false}" = "true" ]; then
  exec >"$LOG_FILE" 2>&1
else
  exec > >(tee -a "$LOG_FILE") 2>&1
fi
printf "Init log: %s\n" "$LOG_FILE"

print_header "CI4 Project — Environment Setup"

# ---------------------------------------------------------------------------
# Requirements
# ---------------------------------------------------------------------------

print_header "Checking requirements"
require_cmd php
require_cmd composer
detect_mysql_mode

if ! php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
  print_error "PHP 8.2+ is required (found: $(php -r 'echo PHP_VERSION;'))."
  printf "  macOS:  brew install php@8.2\n"
  printf "  Ubuntu: sudo apt install php8.2\n"
  printf "  See: https://www.php.net/downloads\n"
  exit 1
fi
print_ok "Dependencies found (php, composer)"

# ---------------------------------------------------------------------------
# Database credentials
# ---------------------------------------------------------------------------

DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_USER="root"
DB_PASS=""
DB_NAME="ci4_website_builder_api"
TEST_DB_NAME="ci4_website_builder_api_test"

# Use detected Docker port as default if available
[ -n "$DETECTED_DOCKER_PORT" ] && DB_PORT="$DETECTED_DOCKER_PORT"

# Override defaults from env vars (non-interactive / CI mode).
# When CI4_DB_HOST is exported (e.g. by new-project.sh), all DB prompts are skipped.
if [ -n "${CI4_DB_HOST:-}" ]; then
  DB_HOST="${CI4_DB_HOST}"
  DB_PORT="${CI4_DB_PORT:-$DB_PORT}"
  DB_USER="${CI4_DB_USER:-$DB_USER}"
  DB_PASS="${CI4_DB_PASS:-}"
  DB_NAME="${CI4_DB_NAME:-$DB_NAME}"
  TEST_DB_NAME="${CI4_TEST_DB_NAME:-$TEST_DB_NAME}"
elif [ "$SKIP_DB" = false ]; then
  print_header "Database configuration"
  DB_HOST="$(ask_with_default "MySQL host" "$DB_HOST")"
  DB_PORT="$(ask_with_default "MySQL port" "$DB_PORT")"
  DB_USER="$(ask_with_default "MySQL user" "$DB_USER")"
  read -r -s -p "MySQL password (can be empty): " DB_PASS
  printf "\n"
  DB_NAME="$(ask_with_default "Database name" "$DB_NAME")"
  TEST_DB_NAME="$(ask_with_default "Test database name" "$TEST_DB_NAME")"
  validate_db_name "$DB_NAME"
  validate_db_name "$TEST_DB_NAME"
fi

# ---------------------------------------------------------------------------
# Steps
# ---------------------------------------------------------------------------

if [ "$SKIP_DEPS" = false ]; then
  ci4_install_deps
fi

print_header "Environment configuration"
if [ -f ".env" ]; then
  if [ -n "${CI4_OVERWRITE_ENV:-}" ]; then
    OVERWRITE_ENV="$(trim "${CI4_OVERWRITE_ENV}")"
  elif [ -n "${CI4_DB_HOST:-}" ]; then
    # Non-interactive mode: env vars are set, so don't overwrite (keep existing .env)
    OVERWRITE_ENV="n"
    print_warn ".env already exists. Keeping it (non-interactive mode detected)."
  else
    # Interactive mode: ask user
    print_warn ".env already exists."
    read -r -p "Overwrite? (y/N): " OVERWRITE_ENV
    OVERWRITE_ENV="$(trim "$OVERWRITE_ENV")"
  fi
  case "$OVERWRITE_ENV" in
    [Yy]) ci4_configure_env ;;
    *)    print_warn "Keeping existing .env — skipping key generation." ;;
  esac
else
  ci4_configure_env
fi

print_header "Validating environment"
if ! php spark env:check; then
  print_error "Environment validation failed. Fix .env before continuing."
  exit 1
fi

if [ "$SKIP_DB" = false ]; then
  ci4_prepare_databases
  ci4_verify_database
  ci4_run_migrations
  ci4_seed_rbac
fi

print_header "Validating ci4-api-core service wiring"
php spark core:check || { print_error "Service wiring incomplete. Fix app/Config/Services.php before continuing."; exit 1; }

ci4_generate_swagger

# ---------------------------------------------------------------------------
# Superadmin (optional)
# ---------------------------------------------------------------------------

if [ -n "${CI4_SA_EMAIL:-}" ]; then
  print_header "Superadmin"
  php spark users:bootstrap-superadmin \
    --email "${CI4_SA_EMAIL}" \
    --password "${CI4_SA_PASSWORD:-}" \
    --first-name "${CI4_SA_FIRST_NAME:-Super}" \
    --last-name "${CI4_SA_LAST_NAME:-Admin}"
  print_ok "Superadmin created/updated"
  export CI4_SA_EMAIL
  export CI4_SA_PASSWORD
else
  read -r -p "Bootstrap a superadmin account? (y/N): " BOOTSTRAP_SA
  BOOTSTRAP_SA="$(trim "$BOOTSTRAP_SA")"
  case "$BOOTSTRAP_SA" in
    [Yy])
      print_header "Superadmin"
      SA_EMAIL="$(ask_with_default "Email" "admin@example.com")"
      SA_PASSWORD="$(ask_hidden "Password (min 8 chars)")"
      while [ "${#SA_PASSWORD}" -lt 8 ]; do
        print_warn "Password must be at least 8 characters. Try again." >&2
        SA_PASSWORD="$(ask_hidden "Password (min 8 chars)")"
      done
      SA_FIRST_NAME="$(ask_with_default "First name" "Admin")"
      SA_LAST_NAME="$(ask_with_default "Last name" "User")"
      php spark users:bootstrap-superadmin \
        --email "$SA_EMAIL" \
        --password "$SA_PASSWORD" \
        --first-name "$SA_FIRST_NAME" \
        --last-name "$SA_LAST_NAME"
      print_ok "Superadmin created/updated"
      export CI4_SA_EMAIL="$SA_EMAIL"
      export CI4_SA_PASSWORD="$SA_PASSWORD"
      ;;
  esac
fi

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------

print_header "Done"
printf "Project ready at: %s\n" "$(pwd)"

if [ "$SKIP_SERVER" = false ]; then
  if [ -n "${CI4_START_SERVER:-}" ]; then
    START_SERVER="$(trim "${CI4_START_SERVER}")"
  else
    read -r -p "Start development server now? (y/N): " START_SERVER
    START_SERVER="$(trim "$START_SERVER")"
  fi
  case "$START_SERVER" in
    [Yy])
      print_header "Starting development server"
      printf "Server at http://localhost:8180 — press Ctrl+C to stop.\n\n"
      php spark serve --port 8180
      ;;
    *)
      printf "Start server: php spark serve --port 8180\n"
      ;;
  esac
fi
