#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ROOT_DIR="$ROOT"
if [[ -f "$ROOT/.env.ios-release.example" ]]; then
  set -a
  source "$ROOT/.env.ios-release.example"
  set +a
fi
source "$ROOT/bin/ios-release-env.sh"

OUT_DIR="$ROOT/tmp/ios-release-env"
OUT="$OUT_DIR/latest.md"
REPORT_PATH="tmp/ios-release-env/latest.md"
STRICT=0

if [[ "${1:-}" == "--strict" ]]; then
  STRICT=1
fi

mkdir -p "$OUT_DIR"

env_file="${FOXDESK_IOS_RELEASE_ENV_FILE:-$ROOT/.env.ios-release}"
missing=0

mark() {
  local ok="$1"
  local label="$2"
  local detail="${3:-}"

  if [[ "$ok" == "1" ]]; then
    printf -- "- OK: %s%s\n" "$label" "${detail:+ — $detail}" >> "$OUT"
  else
    printf -- "- MISSING: %s%s\n" "$label" "${detail:+ — $detail}" >> "$OUT"
    missing=1
  fi
}

value_is_one() {
  local name="$1"
  [[ "${!name:-}" == "1" ]]
}

value_present() {
  local name="$1"
  [[ -n "${!name:-}" ]]
}

file_mode() {
  if stat -f "%Lp" "$env_file" >/dev/null 2>&1; then
    stat -f "%Lp" "$env_file"
  else
    stat -c "%a" "$env_file"
  fi
}

owner_only_mode() {
  local mode="$1"
  [[ "$mode" == *00 ]]
}

timestamp="$(date -u '+%Y-%m-%d %H:%M:%S UTC')"

cat > "$OUT" <<MD
# FoxDesk iOS Release Env Check

Generated: $timestamp

This report checks whether the local ignored iOS release environment is ready.
It never prints secret values.

## Local Env File

Path: \`$env_file\`

MD

if [[ -f "$env_file" ]]; then
  mode="$(file_mode)"
  mark 1 "Local env file exists"
  if owner_only_mode "$mode"; then
    mark 1 "Local env file permissions" "mode $mode"
  else
    mark 0 "Local env file permissions" "run: chmod 600 .env.ios-release"
  fi
else
  mark 0 "Local env file exists" "run: npm run ios:release:init"
fi

cat >> "$OUT" <<MD

## Apple / App Store Gates

MD

for flag in \
  APP_STORE_CONNECT_APP_RECORD_READY \
  APPLE_DEVELOPER_BUNDLE_READY \
  APPLE_BUSINESS_VERIFIED \
  APP_STORE_PRIVACY_REVIEWED; do
  if value_is_one "$flag"; then
    mark 1 "$flag"
  else
    mark 0 "$flag" "set to 1 only after the operator step is complete"
  fi
done

cat >> "$OUT" <<MD

## Demo Reviewer Account

MD

if value_present FOXDESK_IOS_DEMO_EMAIL && value_present FOXDESK_IOS_DEMO_PASSWORD; then
  mark 1 "Demo reviewer credentials"
else
  mark 0 "Demo reviewer credentials" "FOXDESK_IOS_DEMO_EMAIL and FOXDESK_IOS_DEMO_PASSWORD"
fi

cat >> "$OUT" <<MD

## Live Mobile API Smoke

MD

smoke_base="${FOXDESK_IOS_SMOKE_BASE_URL:-}"
if [[ "$smoke_base" == "https://app.foxdesk.net/api/mobile/v1" || "$smoke_base" == *"staging"* ]]; then
  mark 1 "Smoke base URL"
else
  mark 0 "Smoke base URL" "use production or a staging/disposable workspace"
fi

if value_present FOXDESK_IOS_SMOKE_EMAIL && value_present FOXDESK_IOS_SMOKE_PASSWORD; then
  mark 1 "Smoke account credentials"
else
  mark 0 "Smoke account credentials" "FOXDESK_IOS_SMOKE_EMAIL and FOXDESK_IOS_SMOKE_PASSWORD"
fi

if value_is_one FOXDESK_IOS_SMOKE_WRITE; then
  mark 1 "Opt-in write smoke"
else
  mark 0 "Opt-in write smoke" "set FOXDESK_IOS_SMOKE_WRITE=1 only for a safe disposable workspace"
fi

cat >> "$OUT" <<MD

## Physical iPhone APNs Smoke

MD

if value_present APNS_TEST_DEVICE_TOKEN; then
  mark 1 "APNs test device token"
else
  mark 0 "APNs test device token" "copy from iOS app Settings -> Push diagnostics"
fi

if [[ "${APNS_TEST_ENVIRONMENT:-}" == "production" || "${APNS_TEST_ENVIRONMENT:-}" == "sandbox" ]]; then
  mark 1 "APNs environment"
else
  mark 0 "APNs environment" "use production or sandbox"
fi

cat >> "$OUT" <<MD

## Usage

\`\`\`bash
npm run ios:release:init
# edit .env.ios-release with operator-only values
npm run ios:release:env
npm run ios:submission:gate
\`\`\`

MD

if [[ "$missing" == "0" ]]; then
  echo "Status: ready" >> "$OUT"
else
  echo "Status: incomplete" >> "$OUT"
fi

echo "[ios:release:env] Evidence report: $REPORT_PATH"

if [[ "$STRICT" == "1" && "$missing" != "0" ]]; then
  exit 2
fi
