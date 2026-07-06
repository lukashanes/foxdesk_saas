#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "$ROOT_DIR/bin/ios-release-env.sh"
SUBMISSION_PACKET="$ROOT_DIR/docs/IOS_APP_STORE_SUBMISSION.md"
SCREENSHOT_DIR="$ROOT_DIR/tmp/ios-app-store-screenshots"
SCREENSHOT_MANIFEST="$SCREENSHOT_DIR/manifest.md"
SCREENSHOT_SCRIPT="$ROOT_DIR/bin/ios-app-store-screenshots.sh"

log() {
  printf '[ios:submission:gate] %s\n' "$1"
}

failures=()

require_env_flag() {
  local name="$1"
  local message="$2"
  if [[ "${!name:-}" != "1" ]]; then
    failures+=("$message")
  fi
}

require_env_value() {
  local name="$1"
  local message="$2"
  if [[ -z "${!name:-}" ]]; then
    failures+=("$message")
  fi
}

log "Running local MVP audit first"
(cd "$ROOT_DIR" && npm run ios:mvp:audit)

log "Running local beta readiness gate"
(cd "$ROOT_DIR" && npm run ios:beta:gate)

log "Running completion audit"
(cd "$ROOT_DIR" && npm run ios:completion:audit)

log "Checking final human and live-smoke gates"

require_env_flag "APP_STORE_CONNECT_APP_RECORD_READY" "Set APP_STORE_CONNECT_APP_RECORD_READY=1 after creating the App Store Connect app record for net.foxdesk.ios."
require_env_flag "APPLE_DEVELOPER_BUNDLE_READY" "Set APPLE_DEVELOPER_BUNDLE_READY=1 after confirming bundle id net.foxdesk.ios exists under Aenze s.r.o. in Apple Developer and Push Notifications are enabled."
require_env_flag "APP_STORE_PRIVACY_REVIEWED" "Set APP_STORE_PRIVACY_REVIEWED=1 after a human reviews App Store privacy answers."
require_env_value "FOXDESK_IOS_DEMO_EMAIL" "Set FOXDESK_IOS_DEMO_EMAIL for the App Review demo account verification."
require_env_value "FOXDESK_IOS_DEMO_PASSWORD" "Set FOXDESK_IOS_DEMO_PASSWORD for the App Review demo account verification."
require_env_value "FOXDESK_IOS_SMOKE_EMAIL" "Set FOXDESK_IOS_SMOKE_EMAIL for the live mobile API smoke."
require_env_value "FOXDESK_IOS_SMOKE_PASSWORD" "Set FOXDESK_IOS_SMOKE_PASSWORD for the live mobile API smoke."
require_env_flag "FOXDESK_IOS_SMOKE_WRITE" "Set FOXDESK_IOS_SMOKE_WRITE=1 and run the write smoke against staging or a disposable workspace."
require_env_value "APNS_TEST_DEVICE_TOKEN" "Set APNS_TEST_DEVICE_TOKEN from Settings → Push diagnostics on a physical iPhone."

if ! grep -Fq 'Demo reviewer account:' "$SUBMISSION_PACKET" || ! grep -Fq 'docs/IOS_DEMO_REVIEWER_ACCOUNT.md' "$SUBMISSION_PACKET"; then
  failures+=("Keep demo reviewer account instructions and docs/IOS_DEMO_REVIEWER_ACCOUNT.md linked in docs/IOS_APP_STORE_SUBMISSION.md.")
fi

if [[ ! -d "$SCREENSHOT_DIR" ]]; then
  failures+=("Create $SCREENSHOT_DIR by running npm run ios:screenshots, then review the populated App Store screenshots.")
else
  screenshot_count="$(find "$SCREENSHOT_DIR" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpg' -o -name '*.jpeg' \) | wc -l | tr -d ' ')"
  if [[ "$screenshot_count" -lt 8 ]]; then
    failures+=("Run npm run ios:screenshots and keep at least 8 populated screenshots in tmp/ios-app-store-screenshots; found ${screenshot_count}.")
  fi
  if [[ ! -f "$SCREENSHOT_MANIFEST" ]]; then
    failures+=("Missing screenshot manifest. Run npm run ios:screenshots and review tmp/ios-app-store-screenshots/manifest.md.")
  fi
fi

if [[ ! -x "$SCREENSHOT_SCRIPT" ]]; then
  failures+=("Missing executable screenshot generator: bin/ios-app-store-screenshots.sh.")
fi

if [[ "${#failures[@]}" -gt 0 ]]; then
  log "Not ready for submission. Missing:"
  for failure in "${failures[@]}"; do
    printf '[ios:submission:gate] - %s\n' "$failure" >&2
  done
  log "Local technical readiness is still available in tmp/ios-beta-readiness/latest.md"
  exit 2
fi

log "Running required App Review demo account check"
(cd "$ROOT_DIR" && npm run ios:demo:check -- --require-credentials --json)

log "Running required live mobile API smoke"
(cd "$ROOT_DIR" && npm run ios:api:smoke -- --require-credentials --json)

log "Running required opt-in write smoke"
(cd "$ROOT_DIR" && FOXDESK_IOS_SMOKE_WRITE=1 npm run ios:api:smoke -- --require-credentials --json)

log "Running required real-device APNs smoke"
(cd "$ROOT_DIR" && npm run ios:apns:smoke -- --send "--environment=${APNS_TEST_ENVIRONMENT:-production}" --json)

log "OK: iOS submission gate passed"
