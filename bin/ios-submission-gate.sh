#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "$ROOT_DIR/bin/ios-release-env.sh"
SUBMISSION_PACKET="$ROOT_DIR/docs/IOS_APP_STORE_SUBMISSION.md"
SCREENSHOT_DIR="$ROOT_DIR/tmp/ios-app-store-screenshots"
SCREENSHOT_MANIFEST="$SCREENSHOT_DIR/manifest.md"
SCREENSHOT_ACCOUNT="$SCREENSHOT_DIR/account.png"
SCREENSHOT_SCRIPT="$ROOT_DIR/bin/ios-app-store-screenshots.sh"
DEMO_EVIDENCE="$ROOT_DIR/tmp/ios-demo-account-check/latest-live-demo-account.json"
API_READ_EVIDENCE="$ROOT_DIR/tmp/ios-api-smoke/latest-live-read-only.json"
API_WRITE_EVIDENCE="$ROOT_DIR/tmp/ios-api-smoke/latest-live-write.json"
APNS_DRY_EVIDENCE="$ROOT_DIR/tmp/ios-apns-smoke/latest-dry-run.json"
APNS_SEND_EVIDENCE="$ROOT_DIR/tmp/ios-apns-smoke/latest-send.json"

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

json_field() {
  local file="$1"
  local path="$2"

  [[ -f "$file" ]] || return 1
  node -e '
    const fs = require("node:fs");
    const data = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
    let value = data;
    for (const key of process.argv[2].split(".")) {
      if (!value || typeof value !== "object" || !(key in value)) {
        process.exit(3);
      }
      value = value[key];
    }
    if (typeof value === "boolean") {
      process.stdout.write(value ? "true" : "false");
    } else if (value === null || value === undefined) {
      process.stdout.write("");
    } else {
      process.stdout.write(String(value));
    }
  ' "$file" "$path"
}

evidence_ready() {
  local file="$1"
  local mode="$2"

  [[ -f "$file" ]] || return 1
  [[ "$(json_field "$file" ok 2>/dev/null || true)" == "true" ]] || return 1
  [[ "$(json_field "$file" mode 2>/dev/null || true)" == "$mode" ]] || return 1
  if [[ "$mode" == live-* ]]; then
    evidence_targets_production "$file" || return 1
  fi
}

evidence_targets_production() {
  local file="$1"

  node -e '
    const fs = require("node:fs");
    const data = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
    const hostname = new URL(String(data.base_url || "")).hostname.toLowerCase();
    if (hostname !== "app.foxdesk.net") process.exit(1);
  ' "$file"
}

api_write_ready() {
  local file="$1"

  evidence_ready "$file" "live-write" || return 1
  node -e '
    const fs = require("node:fs");
    const data = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
    const steps = Array.isArray(data.steps) ? data.steps : [];
    const required = [
      "create-ticket",
      "comment-with-time",
      "attachment-upload",
      "attachment-metadata",
      "attachment-download",
      "created-ticket-detail",
    ];
    for (const name of required) {
      const step = steps.find((row) => row && row.name === name);
      if (!step || step.ok !== true) process.exit(1);
    }
    const comment = steps.find((row) => row && row.name === "comment-with-time");
    const detail = steps.find((row) => row && row.name === "created-ticket-detail");
    const hasText = (value) => typeof value === "string" && value.trim().length > 0;
    const hasManualTimes = comment
      && hasText(comment.manual_date)
      && hasText(comment.manual_start_time)
      && hasText(comment.manual_end_time)
      && comment.exact_time_returned === true;
    const detailHasExactTime = detail
      && detail.exact_time_visible === true
      && detail.manual_date === comment.manual_date
      && detail.manual_start_time === comment.manual_start_time
      && detail.manual_end_time === comment.manual_end_time;
    if (!hasManualTimes || !detailHasExactTime) process.exit(1);
    const download = steps.find((row) => row && row.name === "attachment-download");
    if (!Number.isInteger(Number(download.bytes)) || Number(download.bytes) <= 0) process.exit(1);
  ' "$file"
}

api_read_ready() {
  local file="$1"

  evidence_ready "$file" "live-read-only"
}

demo_write_ready() {
  local file="$1"

  evidence_ready "$file" "live-demo-account" || return 1
  node -e '
    const fs = require("node:fs");
    const data = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
    const steps = Array.isArray(data.steps) ? data.steps : [];
    const create = steps.find((row) => row && row.name === "demo-write-create-ticket");
    const comment = steps.find((row) => row && row.name === "demo-write-comment-with-time");
    const reload = steps.find((row) => row && row.name === "demo-write-detail-reload");
    const hasId = (value) => Number.isInteger(Number(value)) && Number(value) > 0;
    const hasText = (value) => typeof value === "string" && value.trim().length > 0;
    const hasManualTimes = comment && hasText(comment.manual_date) && hasText(comment.manual_start_time) && hasText(comment.manual_end_time);
    if (
      !create || create.ok !== true || !hasId(create.ticket_id) ||
      !comment || comment.ok !== true || !hasId(comment.comment_id) || !hasId(comment.time_entry_id) ||
      !hasManualTimes ||
      !reload || reload.ok !== true || !hasId(reload.ticket_id) || reload.comment_visible !== true || reload.linked_time_visible !== true
    ) {
      process.exit(1);
    }
  ' "$file"
}

apns_send_ready() {
  local file="$1"

  evidence_ready "$file" "send" || return 1
  [[ "$(json_field "$file" sent 2>/dev/null || true)" == "true" ]] || return 1
}

apns_dry_ready() {
  local file="$1"

  evidence_ready "$file" "dry-run" || return 1
  node -e '
    const fs = require("node:fs");
    const data = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
    const required = [
      "new_ticket",
      "new_comment",
      "assigned_to_you",
      "mentioned",
      "ticket_updated",
      "status_changed",
      "priority_changed",
      "due_date_reminder",
    ];
    const types = Array.isArray(data.validated_types) ? data.validated_types : [];
    for (const type of required) {
      if (!types.includes(type)) process.exit(1);
      const payload = data.validated_payloads && data.validated_payloads[type];
      if (!payload || Number(payload.ticket_id) <= 0 || payload.type !== type) process.exit(1);
    }
  ' "$file"
}

assert_evidence() {
  local label="$1"
  local predicate="$2"
  local file="$3"
  local message="$4"

  if ! "$predicate" "$file"; then
    printf '[ios:submission:gate] %s evidence failed: %s\n' "$label" "$message" >&2
    exit 2
  fi
}

smoke_base_is_production() {
  local value="${FOXDESK_IOS_SMOKE_BASE_URL:-}"
  [[ "$value" == "https://app.foxdesk.net/api/mobile/v1" || "$value" == "https://app.foxdesk.net/index.php" ]]
}

project_setting() {
  local name="$1"
  awk -F': ' -v name="$name" '$1 ~ "^[[:space:]]*" name "$" { print $2; exit }' "$ROOT_DIR/ios/FoxDesk/project.yml"
}

log "Running local MVP audit first"
(cd "$ROOT_DIR" && npm run ios:mvp:audit)

log "Running local beta readiness gate"
(cd "$ROOT_DIR" && npm run ios:beta:gate)

log "Running completion audit"
(cd "$ROOT_DIR" && npm run ios:completion:audit)

log "Running native free-companion readiness gate"
(cd "$ROOT_DIR" && npm run ios:companion:gate)

log "Checking App Store privacy and screenshot assets"
(cd "$ROOT_DIR" && npm run ios:assets:check)

log "Checking that the uploaded archive matches the current iOS source"
(cd "$ROOT_DIR" && npm run ios:upload:evidence)

log "Checking final human and live-smoke gates"

require_env_flag "APP_STORE_CONNECT_APP_RECORD_READY" "Set APP_STORE_CONNECT_APP_RECORD_READY=1 after creating the App Store Connect app record for net.foxdesk.ios."
require_env_flag "APPLE_DEVELOPER_BUNDLE_READY" "Set APPLE_DEVELOPER_BUNDLE_READY=1 after confirming bundle id net.foxdesk.ios exists under Aenze s.r.o. in Apple Developer and Push Notifications are enabled."
require_env_flag "APPLE_BUSINESS_VERIFIED" "Set APPLE_BUSINESS_VERIFIED=1 after confirming Aenze s.r.o. is verified in Apple Business. This records brand/organization readiness but does not replace App Store Connect or Developer signing."
require_env_flag "APP_STORE_REVIEW_INFO_READY" "Set APP_STORE_REVIEW_INFO_READY=1 after saving the reviewer login, contact phone/email, and review notes in App Store Connect."
require_env_flag "APP_STORE_SCREENSHOTS_UPLOADED" "Set APP_STORE_SCREENSHOTS_UPLOADED=1 after uploading and checking the final screenshots in App Store Connect."
require_env_flag "TESTFLIGHT_BUILD_UPLOADED" "Set TESTFLIGHT_BUILD_UPLOADED=1 after a signed FoxDesk build is processed and selectable in App Store Connect/TestFlight."
require_env_flag "APP_STORE_PRIVACY_REVIEWED" "Set APP_STORE_PRIVACY_REVIEWED=1 after a human reviews App Store privacy answers."
require_env_flag "APP_STORE_PRICING_READY" "Set APP_STORE_PRICING_READY=1 after configuring the iOS app download price as Free."
require_env_flag "APP_STORE_AVAILABILITY_READY" "Set APP_STORE_AVAILABILITY_READY=1 after saving the intended App Store countries or regions."
require_env_flag "APP_STORE_CONTENT_RIGHTS_READY" "Set APP_STORE_CONTENT_RIGHTS_READY=1 after answering Content Rights for customer-supplied ticket content."
require_env_flag "APP_STORE_AGREEMENTS_READY" "Set APP_STORE_AGREEMENTS_READY=1 after confirming the free-app Apple Developer Program License Agreement is active and no blocking agreement action remains. Paid Apps, tax, and banking are not otherwise required for this free no-IAP build."
require_env_flag "APP_STORE_UNTESTED_PLATFORMS_DISABLED" "Set APP_STORE_UNTESTED_PLATFORMS_DISABLED=1 after disabling Apple Silicon Mac and Apple Vision Pro availability for this iPhone-only release."
require_env_value "APP_STORE_SELECTED_MARKETING_VERSION" "Set APP_STORE_SELECTED_MARKETING_VERSION to the exact marketing version selected in App Store Connect."
require_env_value "APP_STORE_SELECTED_BUILD_NUMBER" "Set APP_STORE_SELECTED_BUILD_NUMBER to the exact processed build selected in App Store Connect."
if [[ "${APP_STORE_SELECTED_MARKETING_VERSION:-}" != "$(project_setting MARKETING_VERSION)" ]]; then
  failures+=("App Store Connect marketing version must match project.yml ($(project_setting MARKETING_VERSION)); found ${APP_STORE_SELECTED_MARKETING_VERSION:-unset}.")
fi
if [[ "${APP_STORE_SELECTED_BUILD_NUMBER:-}" != "$(project_setting CURRENT_PROJECT_VERSION)" ]]; then
  failures+=("App Store Connect selected build must match project.yml ($(project_setting CURRENT_PROJECT_VERSION)); found ${APP_STORE_SELECTED_BUILD_NUMBER:-unset}.")
fi
require_env_value "FOXDESK_IOS_DEMO_EMAIL" "Set FOXDESK_IOS_DEMO_EMAIL for the App Review demo account verification."
require_env_value "FOXDESK_IOS_DEMO_PASSWORD" "Set FOXDESK_IOS_DEMO_PASSWORD for the App Review demo account verification."
require_env_flag "FOXDESK_IOS_DEMO_WRITE" "Set FOXDESK_IOS_DEMO_WRITE=1 to prove the App Review demo account can create a demo ticket and add a linked internal timed comment without notifying customers."
require_env_value "FOXDESK_IOS_SMOKE_EMAIL" "Set FOXDESK_IOS_SMOKE_EMAIL for the live mobile API smoke."
require_env_value "FOXDESK_IOS_SMOKE_PASSWORD" "Set FOXDESK_IOS_SMOKE_PASSWORD for the live mobile API smoke."
require_env_flag "FOXDESK_IOS_SMOKE_WRITE" "Set FOXDESK_IOS_SMOKE_WRITE=1 and run the write smoke against staging or a disposable workspace."
if [[ "${FOXDESK_IOS_SMOKE_WRITE:-}" == "1" ]] && smoke_base_is_production && [[ "${FOXDESK_IOS_ALLOW_PRODUCTION_WRITE_SMOKE:-}" != "1" ]]; then
  failures+=("Production write smoke needs explicit acknowledgement: set FOXDESK_IOS_ALLOW_PRODUCTION_WRITE_SMOKE=1 only for a disposable production workspace, or use staging.app.foxdesk.net.")
fi
require_env_value "APNS_TEST_DEVICE_TOKEN" "Set APNS_TEST_DEVICE_TOKEN from Account → Push diagnostics on a physical iPhone."

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
  if [[ ! -f "$SCREENSHOT_ACCOUNT" ]]; then
    failures+=("Run npm run ios:screenshots again so the App Store screenshot set includes account.png instead of the old settings.png.")
  elif ! grep -Fq '`account.png`' "$SCREENSHOT_MANIFEST"; then
    failures+=("Screenshot manifest must list account.png. Regenerate with npm run ios:screenshots.")
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
(cd "$ROOT_DIR" && FOXDESK_IOS_DEMO_WRITE=1 npm run ios:demo:check -- --require-credentials --json)
assert_evidence "App Review demo account" demo_write_ready "$DEMO_EVIDENCE" "expected live-demo-account JSON with created ticket, linked timed comment, manual date/start/end, and detail reload proof."

log "Running required live mobile API smoke"
(cd "$ROOT_DIR" && npm run ios:api:smoke -- --require-credentials --json)
assert_evidence "Live mobile API read-only smoke" api_read_ready "$API_READ_EVIDENCE" "expected latest-live-read-only.json with ok=true and mode=live-read-only."

log "Running required opt-in write smoke"
(cd "$ROOT_DIR" && FOXDESK_IOS_SMOKE_WRITE=1 npm run ios:api:smoke -- --require-credentials --json)
assert_evidence "Opt-in mobile API write smoke" api_write_ready "$API_WRITE_EVIDENCE" "expected latest-live-write.json with ticket, timed comment carrying manual date/start/end, attachment upload, metadata, authorized download, and detail reload proof."

log "Checking complete APNs dry-run payload coverage"
(cd "$ROOT_DIR" && npm run ios:apns:smoke -- --json)
assert_evidence "APNs dry-run payload coverage" apns_dry_ready "$APNS_DRY_EVIDENCE" "expected latest-dry-run.json with every required notification type and valid ticket payloads."

log "Running required real-device APNs smoke"
(cd "$ROOT_DIR" && npm run ios:apns:smoke -- --send "--environment=${APNS_TEST_ENVIRONMENT:-production}" --json)
assert_evidence "Real-device APNs smoke" apns_send_ready "$APNS_SEND_EVIDENCE" "expected latest-send.json with ok=true, mode=send, and sent=true."

log "OK: iOS submission gate passed"
