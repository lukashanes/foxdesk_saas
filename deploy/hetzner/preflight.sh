#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."

failures=0

check_ok() {
  printf 'OK   %s\n' "$1"
}

check_fail() {
  printf 'FAIL %s\n' "$1" >&2
  failures=$((failures + 1))
}

check_warn() {
  printf 'WARN %s\n' "$1" >&2
}

require_file() {
  local file="$1"
  if [[ -f "$file" ]]; then
    check_ok "Found $file"
  else
    check_fail "Missing $file"
  fi
}

env_value() {
  local key="$1"
  awk -F= -v key="$key" '$1 == key { sub(/^[^=]*=/, ""); print; exit }' .env.production 2>/dev/null || true
}

require_env() {
  local key="$1"
  local value
  value="$(env_value "$key")"
  if [[ -z "$value" ]]; then
    check_fail "Missing .env.production value: $key"
    return
  fi
  if [[ "$value" == replace_with_* || "$value" == *_replace || "$value" == "sk_live_replace" || "$value" == "whsec_replace" || "$value" == "price_replace" ]]; then
    check_fail "Placeholder value still set: $key"
    return
  fi
  check_ok "Configured $key"
}

require_https_env() {
  local key="$1"
  local value
  value="$(env_value "$key")"
  require_env "$key"
  if [[ -n "$value" && "$value" != https://* ]]; then
    check_fail "$key must be a https URL"
    return
  fi
  if [[ "$value" == *localhost* || "$value" == *127.0.0.1* || "$value" == *"[::1]"* ]]; then
    check_fail "$key must not point at a local URL"
    return
  fi
}

require_absolute_env() {
  local key="$1"
  local value
  value="$(env_value "$key")"
  require_env "$key"
  if [[ -n "$value" && "$value" != /* ]]; then
    check_fail "$key must be an absolute path"
  fi
}

require_file .env.production
require_file config.php
require_file docker-compose.prod.yml
require_file docker/prod/Dockerfile
require_file docker/caddy/Caddyfile

if command -v docker >/dev/null 2>&1; then
  check_ok "Docker CLI available"
else
  check_fail "Docker CLI is not installed or not in PATH"
fi

if docker compose version >/dev/null 2>&1; then
  check_ok "Docker Compose plugin available"
else
  check_fail "Docker Compose plugin is not available"
fi

if command -v npm >/dev/null 2>&1; then
  check_ok "npm available for deployment evidence"
else
  check_fail "npm is required for deployment evidence. Run bootstrap/setup and npm ci."
fi

if [[ -d node_modules ]]; then
  check_ok "Node dependencies installed"
else
  check_fail "Missing node_modules. Run npm ci before production deploy."
fi

if [[ -x node_modules/.bin/playwright ]]; then
  check_ok "Playwright available for production smoke"
else
  check_fail "Missing Playwright binary. Run npm ci && npx playwright install --with-deps chromium."
fi

if [[ -f .env.production ]]; then
  required_env=(
    APP_HOST
    APP_URL
    PROD_BASE_URL
    PROD_PUBLIC_URL
    DB_NAME
    DB_USER
    DB_PASS
    DB_ROOT_PASS
    SECRET_KEY
    MAIL_PROVIDER
    CLOUDFLARE_ACCOUNT_ID
    CLOUDFLARE_EMAIL_API_TOKEN
    CLOUDFLARE_EMAIL_FROM
    CLOUDFLARE_EMAIL_REPLY_TO
    STORAGE_DRIVER
    R2_BUCKET
    R2_ENDPOINT
    R2_ACCESS_KEY_ID
    R2_SECRET_ACCESS_KEY
    FOXDESK_BACKUP_DIR
    FOXDESK_RESTORE_EVIDENCE_PATH
    FOXDESK_DEPLOY_EVIDENCE_DIR
    FOXDESK_MONITORING_HEALTH_URL
    FOXDESK_MONITORING_ALERT_EMAIL
  )

  for key in "${required_env[@]}"; do
    require_env "$key"
  done

  for key in APP_URL PROD_BASE_URL PROD_PUBLIC_URL FOXDESK_MONITORING_HEALTH_URL; do
    require_https_env "$key"
  done

  for key in FOXDESK_BACKUP_DIR FOXDESK_RESTORE_EVIDENCE_PATH FOXDESK_DEPLOY_EVIDENCE_DIR; do
    require_absolute_env "$key"
  done

  if [[ "$(env_value BILLING_ENABLED)" == "true" ]]; then
    require_env STRIPE_SECRET_KEY
    require_env STRIPE_WEBHOOK_SECRET
    require_env STRIPE_PRICE_CLOUD_BASE
    require_env STRIPE_PRICE_STORAGE_OVERAGE
    require_env STRIPE_STORAGE_METER_EVENT_NAME
  else
    check_warn "BILLING_ENABLED is not true; paid signup will not be live"
  fi

  if [[ "$(env_value MAIL_PROVIDER)" != "cloudflare" ]]; then
    check_fail "MAIL_PROVIDER must be cloudflare for SaaS production"
  fi

  if [[ "$(env_value STORAGE_DRIVER)" != "r2" ]]; then
    check_fail "STORAGE_DRIVER must be r2 for SaaS production"
  fi

  r2_endpoint="$(env_value R2_ENDPOINT)"
  if [[ -n "$r2_endpoint" && "$r2_endpoint" != https://*.r2.cloudflarestorage.com ]]; then
    check_fail "R2_ENDPOINT must use the Cloudflare R2 S3 endpoint"
  fi

  secret_key="$(env_value SECRET_KEY)"
  if [[ -n "$secret_key" && ${#secret_key} -lt 32 ]]; then
    check_fail "SECRET_KEY should be at least 32 characters"
  fi

  if [[ "$(env_value STRIPE_SECRET_KEY)" == sk_test_* ]]; then
    check_fail "STRIPE_SECRET_KEY must be a live key for paid production"
  fi

  if [[ "$(env_value FOXDESK_RESTORE_EVIDENCE_PATH)" != *.json ]]; then
    check_fail "FOXDESK_RESTORE_EVIDENCE_PATH must point to a JSON evidence file"
  fi
fi

if [[ -f config.php ]] && command -v docker >/dev/null 2>&1; then
  if docker run --rm -v "$PWD":/app -w /app php:8.2-cli php -l config.php >/dev/null; then
    check_ok "config.php PHP syntax"
  else
    check_fail "config.php PHP syntax failed"
  fi
fi

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  if FOXDESK_ENV_FILE=.env.production docker compose --env-file .env.production -f docker-compose.prod.yml config >/dev/null; then
    check_ok "docker-compose.prod.yml config"
  else
    check_fail "docker-compose.prod.yml config failed"
  fi
fi

if [[ "$failures" -gt 0 ]]; then
  echo
  echo "Preflight failed with $failures issue(s)." >&2
  exit 1
fi

echo
echo "Preflight passed. You can run deploy/hetzner/deploy.sh."
