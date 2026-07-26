#!/usr/bin/env bash
# CI4 API Starter — project bootstrapper.
# Intended to be run via: bash <(curl -fsSL https://…/install.sh)
# Creates a new project directory, clones the template, and configures it.

set -euo pipefail

TEMPLATE_REPO_URL="${TEMPLATE_REPO_URL:-https://github.com/dcardenasl/ci4-api-starter.git}"
TEMPLATE_BRANCH="${TEMPLATE_BRANCH:-main}"
INSTALL_DIR=""

# Cleanup partial install on unexpected exit
_cleanup_on_error() {
  local exit_code=$?
  if [ $exit_code -ne 0 ] && [ -n "$INSTALL_DIR" ] && [ -d "$INSTALL_DIR" ]; then
    print_warn "Installation failed (exit $exit_code). Removing partial directory: $INSTALL_DIR"
    rm -rf "$INSTALL_DIR"
  fi
}
trap '_cleanup_on_error' EXIT

# ---------------------------------------------------------------------------
# Minimal helpers — needed BEFORE the clone (setup.sh does not exist yet).
# After the clone, scripts/setup.sh is sourced and redefines these cleanly.
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

# MySQL detection — also needed pre-clone so the user is asked about Docker
# before the long clone + install phase. setup.sh preserves MYSQL_MODE via :-
MYSQL_MODE="local"
DOCKER_CONTAINER=""
DETECTED_DOCKER_PORT=""

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

  printf "Running containers:\n"
  local mysql_containers
  mysql_containers=$(docker ps --format "{{.Names}}" 2>/dev/null | while read -r name; do
    if docker inspect "$name" --format='{{.Config.Image}}' 2>/dev/null | grep -qi mysql; then
      printf "  %s\n" "$name"
    fi
  done) || true

  if [ -n "$mysql_containers" ]; then
    printf "%s\n" "$mysql_containers"
  else
    printf "  (none found with 'mysql' in image name)\n"
  fi

  local default_container
  default_container=$(printf "%s" "$mysql_containers" | head -1 | tr -d ' ') || true
  if [ -n "$default_container" ]; then
    DOCKER_CONTAINER="$(ask_with_default "Docker container name with MySQL" "$default_container")"
  else
    DOCKER_CONTAINER="$(ask_required "Docker container name with MySQL")"
  fi

  if ! docker inspect "$DOCKER_CONTAINER" >/dev/null 2>&1; then
    print_error "Container '$DOCKER_CONTAINER' not found or not running."
    exit 1
  fi

  # Detect mapped port so the DB_PORT default is accurate.
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

# ---------------------------------------------------------------------------
# PRE-CLONE: requirements + collect all user input upfront
# (so installation can run unattended after this phase)
# ---------------------------------------------------------------------------

LOG_FILE="$(pwd)/install.log"

print_header "CI4 Project Bootstrapper"
printf "Template repo: %s (%s)\n" "$TEMPLATE_REPO_URL" "$TEMPLATE_BRANCH"

print_header "Checking requirements"
require_cmd git
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
print_ok "Dependencies found (git, php, composer)"

print_header "Project"
PROJECT_NAME_RAW="$(ask_required "Project name")"
PROJECT_NAME="$(slugify "$PROJECT_NAME_RAW")"
PROJECT_DESCRIPTION="$(ask_required "Project description")"

if [ -z "$PROJECT_NAME" ]; then
  print_error "Project name produced an empty folder slug."
  exit 1
fi
if [ -e "$PROJECT_NAME" ]; then
  print_error "Target folder '$PROJECT_NAME' already exists."
  exit 1
fi

print_header "Database"
DB_HOST="$(ask_with_default "MySQL host" "localhost")"
# Use detected Docker port as default if available (set after detect_mysql_mode)
default_db_port="3306"
[ -n "$DETECTED_DOCKER_PORT" ] && default_db_port="$DETECTED_DOCKER_PORT"
DB_PORT="$(ask_with_default "MySQL port" "$default_db_port")"
DB_USER="$(ask_with_default "MySQL user" "root")"
read -r -s -p "MySQL password (can be empty): " DB_PASS
printf "\n"
SUGGESTED_DB_NAME="$(printf "%s" "$PROJECT_NAME" | tr '-' '_')"
DB_NAME="$(ask_with_default "Database name" "$SUGGESTED_DB_NAME")"
TEST_DB_NAME="$(ask_with_default "Test database name" "${DB_NAME}_test")"
validate_db_name "$DB_NAME"
validate_db_name "$TEST_DB_NAME"
if [ "$DB_NAME" = "$TEST_DB_NAME" ]; then
  print_error "Database name and test database name must be different."
  exit 1
fi

print_header "Superadmin"
SUPERADMIN_EMAIL="$(ask_with_default "Email" "admin@example.com")"
SUPERADMIN_PASSWORD="$(ask_hidden "Password (min 8 chars)")"
while [ "${#SUPERADMIN_PASSWORD}" -lt 8 ]; do
  print_warn "Password must be at least 8 characters. Try again." >&2
  SUPERADMIN_PASSWORD="$(ask_hidden "Password (min 8 chars)")"
done
SUPERADMIN_FIRST_NAME="$(ask_with_default "First name" "Admin")"
SUPERADMIN_LAST_NAME="$(ask_with_default "Last name" "User")"

# ---------------------------------------------------------------------------
# Start logging now — all interactive prompts are done
# ---------------------------------------------------------------------------

printf "Install log: %s\n" "$LOG_FILE"
if [ "${CI4_FORCE_LOG_TO_FILE:-false}" = "true" ]; then
  exec >"$LOG_FILE" 2>&1
else
  exec > >(tee -a "$LOG_FILE") 2>&1
fi

# ---------------------------------------------------------------------------
# Clone
# ---------------------------------------------------------------------------

print_header "Checking connectivity"
_template_host=$(printf "%s" "$TEMPLATE_REPO_URL" | sed -E 's|https?://([^/:]+).*|\1|')
if ! curl -fsSL --max-time 8 --head "https://$_template_host" >/dev/null 2>&1; then
  print_warn "Cannot reach '$_template_host' — clone may fail if you are offline."
else
  print_ok "Network reachable ($_template_host)"
fi
unset _template_host

print_header "Cloning template"
INSTALL_DIR="$(pwd)/$PROJECT_NAME"
# 'timeout' is a Linux coreutils command; macOS ships without it.
# Use gtimeout (brew install coreutils) when available, otherwise run without a timeout.
if command -v timeout >/dev/null 2>&1; then
  _GIT_TIMEOUT="timeout 300"
elif command -v gtimeout >/dev/null 2>&1; then
  _GIT_TIMEOUT="gtimeout 300"
else
  _GIT_TIMEOUT=""
fi
$_GIT_TIMEOUT git clone --depth=1 --branch "$TEMPLATE_BRANCH" "$TEMPLATE_REPO_URL" "$PROJECT_NAME" \
  || { print_error "git clone failed. Check your connection and try again."; exit 1; }
unset _GIT_TIMEOUT
cd "$PROJECT_NAME"
print_ok "Project cloned into $PROJECT_NAME"

# ---------------------------------------------------------------------------
# POST-CLONE: source shared library — step functions live here
# MYSQL_MODE and DOCKER_CONTAINER are already set; setup.sh preserves them.
# ---------------------------------------------------------------------------

# shellcheck source=scripts/setup.sh
source scripts/setup.sh

print_header "Setting project metadata"
php scripts/set_project_meta.php --name "$PROJECT_NAME_RAW" --description "$PROJECT_DESCRIPTION"
print_ok "Project metadata updated"

ci4_install_deps
ci4_configure_env
ci4_prepare_databases
ci4_verify_database
ci4_run_migrations

print_header "Bootstrapping superadmin"
php spark users:bootstrap-superadmin \
  --email "$SUPERADMIN_EMAIL" \
  --password "$SUPERADMIN_PASSWORD" \
  --first-name "$SUPERADMIN_FIRST_NAME" \
  --last-name "$SUPERADMIN_LAST_NAME"
print_ok "Superadmin created/updated"

ci4_generate_swagger

# ---------------------------------------------------------------------------
# Git reset
# ---------------------------------------------------------------------------

print_header "Git history"
read -r -p "Reset git history for this new project? (y/N): " RESET_GIT
RESET_GIT="$(trim "$RESET_GIT")"
case "$RESET_GIT" in
  [Yy])
    rm -rf .git
    git init >/dev/null
    if ! git check-ignore -q .env 2>/dev/null; then
      print_error ".env is not listed in .gitignore — refusing to commit to avoid leaking credentials."
      print_warn "Add '.env' to .gitignore, then run: git add . && git commit"
      exit 1
    fi
    git add .
    if git commit -m "chore: initial commit from ci4-api-starter template" >/dev/null 2>&1; then
      print_ok "Git repository reset with initial commit"
    else
      print_warn "Git initialized but commit failed — configure git user.name/email and commit manually."
    fi
    ;;
  *)
    print_warn "Keeping template git history."
    ;;
esac

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------

print_header "Done"
printf "Project ready at: %s\n\n" "$(pwd)"
printf "  cd %s\n" "$PROJECT_NAME"
printf "  php spark serve\n\n"
printf "Install log: %s\n" "$LOG_FILE"
