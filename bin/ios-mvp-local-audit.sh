#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
EVIDENCE_DIR="$ROOT_DIR/tmp/ios-mvp-local-audit"
EVIDENCE_REPORT="$EVIDENCE_DIR/latest.md"
SCREENSHOT_DIR="$ROOT_DIR/tmp/ios-app-store-screenshots"

mkdir -p "$EVIDENCE_DIR"

cat > "$EVIDENCE_REPORT" <<REPORT
# FoxDesk iOS MVP Local Audit

- Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
- Git revision: $(git -C "$ROOT_DIR" rev-parse --short HEAD 2>/dev/null || printf 'unknown')
- Product scope: native agent/admin iPhone app for FoxDesk Cloud

## Checks

REPORT

log() {
  printf '[ios:mvp:audit] %s\n' "$1"
  printf -- '- %s\n' "$1" >> "$EVIDENCE_REPORT"
}

run_check() {
  local label="$1"
  shift

  log "$label"
  (cd "$ROOT_DIR" && "$@")
  printf -- '  - Result: passed\n' >> "$EVIDENCE_REPORT"
}

record_status() {
  local label="$1"
  local status="$2"
  local detail="$3"

  printf '[ios:mvp:audit] %s: %s\n' "$label" "$status"
  printf -- '- %s: %s' "$label" "$status" >> "$EVIDENCE_REPORT"
  if [[ -n "$detail" ]]; then
    printf ' — %s' "$detail" >> "$EVIDENCE_REPORT"
  fi
  printf '\n' >> "$EVIDENCE_REPORT"
}

trap 'status=$?; if [[ "$status" -ne 0 ]]; then printf "\n## Result\n\nFailed with exit code %s.\n" "$status" >> "$EVIDENCE_REPORT"; printf "[ios:mvp:audit] Evidence report: %s\n" "$EVIDENCE_REPORT"; fi' EXIT

run_check "Mobile API contract" ./bin/run-php.sh tests/mobile-api-contract-test.php
run_check "Mobile API v1 routing contract" ./bin/run-php.sh tests/mobile-api-v1-routing-contract-test.php
run_check "iOS MVP endpoint matrix contract" ./bin/run-php.sh tests/ios-mvp-endpoint-matrix-contract-test.php
run_check "Native app API freeze contract" ./bin/run-php.sh tests/native-app-api-freeze-contract-test.php
run_check "App home contract" ./bin/run-php.sh tests/app-home-contract-test.php
run_check "iOS MVP scope contract" ./bin/run-php.sh tests/ios-mvp-scope-contract-test.php
run_check "iOS MVP traceability contract" ./bin/run-php.sh tests/ios-mvp-traceability-contract-test.php
run_check "TestFlight preflight" npm run ios:testflight:preflight

cat >> "$EVIDENCE_REPORT" <<'REPORT'

## MVP Requirement Evidence

| Requirement | Evidence |
| --- | --- |
| Sign in to `app.foxdesk.net` | `LoginView`, `AppSession`, `KeychainTokenStore`, mobile login/2FA/refresh/me endpoints |
| Work dashboard | `DashboardView`, worked-time and queue sections, `GET /api/mobile/v1/work` |
| My tickets and queues | `TicketsView`, Mine/New/Waiting/Done/All tabs, `GET /api/mobile/v1/tickets` |
| Ticket detail | `TicketDetailView`, activity, timer, attachments, actions, `GET /api/mobile/v1/tickets/{id}` |
| Public reply and internal note | `CommentComposerSection`, `POST /api/mobile/v1/tickets/{id}/comments` |
| Comment with time | exact/manual time controls, `POST /api/mobile/v1/tickets/{id}/comment-with-time` |
| Photos, files, previews | `CameraCaptureView`, attachment upload/metadata, image preview/download |
| Push notifications | device-token registration, APNs payloads, `PushNavigationRouter` |
| Global search | `SearchView`, `GET /api/mobile/v1/search` |
| Client context | `ClientContextView`, `/api/mobile/v1/clients/{id}`, demo account client-context check |
| Offline and speed fallback | saved dashboard/ticket caches, per-ticket reply drafts, attachment retry state |

REPORT

cat >> "$EVIDENCE_REPORT" <<'REPORT'

## Evidence Status

REPORT

screenshot_count=0
if [[ -d "$SCREENSHOT_DIR" ]]; then
  screenshot_count=$(find "$SCREENSHOT_DIR" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpg' \) | wc -l | tr -d ' ')
fi

if [[ -f "$SCREENSHOT_DIR/manifest.md" && "$screenshot_count" -ge 8 ]]; then
  record_status "Populated screenshots" "ready" "$screenshot_count images plus manifest.md in tmp/ios-app-store-screenshots"
else
  record_status "Populated screenshots" "missing" "run npm run ios:screenshots before App Store upload"
fi

if [[ -f "$ROOT_DIR/tmp/ios-beta-readiness/latest.md" ]]; then
  record_status "Beta readiness report" "present" "tmp/ios-beta-readiness/latest.md"
else
  record_status "Beta readiness report" "missing" "run npm run ios:beta:gate for full local build evidence"
fi

cat >> "$EVIDENCE_REPORT" <<'REPORT'

## Next External Gates

- App Store Connect app record for `net.foxdesk.ios`
- Apple Developer explicit App ID `net.foxdesk.ios` with Push Notifications enabled
- demo reviewer account credentials verified with `npm run ios:demo:check -- --require-credentials --json`
- live mobile API smoke credentials
- opt-in write smoke on staging or disposable workspace
- physical iPhone APNs smoke with `APNS_TEST_DEVICE_TOKEN`
- human review/upload of populated screenshots
- human review of App Store privacy answers

REPORT

log "Evidence report: tmp/ios-mvp-local-audit/latest.md"
