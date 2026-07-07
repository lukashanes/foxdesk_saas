#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "$ROOT_DIR/bin/ios-release-env.sh"
EVIDENCE_DIR="$ROOT_DIR/tmp/ios-beta-readiness"
EVIDENCE_REPORT="$EVIDENCE_DIR/latest.md"
DEMO_EVIDENCE="$ROOT_DIR/tmp/ios-demo-account-check/latest-live-demo-account.json"

mkdir -p "$EVIDENCE_DIR"
cat > "$EVIDENCE_REPORT" <<REPORT
# FoxDesk iOS Beta Readiness Evidence

- Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
- Git revision: $(git -C "$ROOT_DIR" rev-parse --short HEAD 2>/dev/null || printf 'unknown')
- Bundle identifier: net.foxdesk.ios
- Backend: https://app.foxdesk.net/api/mobile/v1

## Gate Results

REPORT

log() {
  printf '[ios:beta:gate] %s\n' "$1"
  printf -- '- %s\n' "$1" >> "$EVIDENCE_REPORT"
}

run_step() {
  local label="$1"
  shift
  log "$label"
  (cd "$ROOT_DIR" && "$@")
  printf -- '  - Result: passed\n' >> "$EVIDENCE_REPORT"
}

human_gate_status() {
  local label="$1"
  local status="$2"
  local detail="$3"

  printf -- '- %s: %s' "$label" "$status" >> "$EVIDENCE_REPORT"
  if [[ -n "$detail" ]]; then
    printf ' — %s' "$detail" >> "$EVIDENCE_REPORT"
  fi
  printf '\n' >> "$EVIDENCE_REPORT"
}

missing_human_gates=()

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
}

demo_write_ready() {
  local file="$1"

  [[ -f "$file" ]] || return 1
  [[ "$(json_field "$file" ok 2>/dev/null || true)" == "true" ]] || return 1
  [[ "$(json_field "$file" mode 2>/dev/null || true)" == "live-demo-account" ]] || return 1
  node -e '
    const fs = require("node:fs");
    const data = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
    const step = Array.isArray(data.steps)
      ? data.steps.find((row) => row && row.name === "demo-write-comment-with-time")
      : null;
    const hasId = (value) => Number.isInteger(Number(value)) && Number(value) > 0;
    if (!step || step.ok !== true || !hasId(step.comment_id) || !hasId(step.time_entry_id)) {
      process.exit(1);
    }
  ' "$file"
}

mark_human_gate() {
  local label="$1"
  local status="$2"
  local detail="$3"

  human_gate_status "$label" "$status" "$detail"
  if [[ "$status" == "missing" ]]; then
    missing_human_gates+=("$label: $detail")
  fi
}

trap 'status=$?; if [[ "$status" -ne 0 ]]; then printf "\n## Result\n\nFailed with exit code %s.\n" "$status" >> "$EVIDENCE_REPORT"; printf "[ios:beta:gate] Evidence report: %s\n" "$EVIDENCE_REPORT"; fi' EXIT

log "Running local iOS beta readiness gate"
log "Writing evidence report to tmp/ios-beta-readiness/latest.md"

run_step "1/8 iOS MVP gate" npm run ios:gate
run_step "2/8 Production build check" npm run ios:production:check
run_step "3/8 Release compatibility build check" npm run ios:release:check
run_step "4/8 Staging build check" npm run ios:staging:check
run_step "5/8 Simulator launch smoke" npm run ios:sim:smoke
run_step "6/8 TestFlight preflight" npm run ios:testflight:preflight
run_step "7/8 Mobile API safe smoke" npm run ios:api:smoke -- --json
run_step "8/8 APNs dry-run smoke" npm run ios:apns:smoke -- --json

if [[ -n "${FOXDESK_IOS_SMOKE_EMAIL:-}" && -n "${FOXDESK_IOS_SMOKE_PASSWORD:-}" ]]; then
  run_step "Live mobile API read-only smoke with provided credentials" env FOXDESK_IOS_SMOKE_WRITE=0 npm run ios:api:smoke -- --require-credentials --json
else
  log "Skipped live mobile API smoke: set FOXDESK_IOS_SMOKE_EMAIL and FOXDESK_IOS_SMOKE_PASSWORD."
  printf -- '  - Result: skipped\n' >> "$EVIDENCE_REPORT"
fi

if [[ -n "${FOXDESK_IOS_DEMO_EMAIL:-}" && -n "${FOXDESK_IOS_DEMO_PASSWORD:-}" ]]; then
  if [[ "${FOXDESK_IOS_DEMO_WRITE:-}" == "1" ]]; then
    run_step "Demo reviewer account write proof with provided credentials" env FOXDESK_IOS_DEMO_WRITE=1 npm run ios:demo:check -- --require-credentials --json
  else
    run_step "Demo reviewer account check with provided credentials" npm run ios:demo:check -- --require-credentials --json
  fi
else
  log "Skipped demo reviewer account check: set FOXDESK_IOS_DEMO_EMAIL and FOXDESK_IOS_DEMO_PASSWORD."
  printf -- '  - Result: skipped\n' >> "$EVIDENCE_REPORT"
fi

if [[ "${FOXDESK_IOS_SMOKE_WRITE:-}" == "1" && -n "${FOXDESK_IOS_SMOKE_EMAIL:-}" && -n "${FOXDESK_IOS_SMOKE_PASSWORD:-}" ]]; then
  run_step "Opt-in write smoke with provided credentials" npm run ios:api:smoke -- --require-credentials --json
else
  log "Skipped opt-in write smoke: set FOXDESK_IOS_SMOKE_WRITE=1 with smoke credentials."
  printf -- '  - Result: skipped\n' >> "$EVIDENCE_REPORT"
fi

if [[ -n "${APNS_TEST_DEVICE_TOKEN:-}" ]]; then
  run_step "APNs live-send smoke with provided device token" npm run ios:apns:smoke -- --send "--environment=${APNS_TEST_ENVIRONMENT:-production}" --json
else
  log "Skipped APNs live-send smoke: copy a physical-device token from Account → Push diagnostics and set APNS_TEST_DEVICE_TOKEN."
  printf -- '  - Result: skipped\n' >> "$EVIDENCE_REPORT"
fi

cat >> "$EVIDENCE_REPORT" <<'REPORT'

## Human Gates

REPORT

if [[ "${APP_STORE_CONNECT_APP_RECORD_READY:-}" == "1" ]]; then
  mark_human_gate "App Store Connect app record" "ready" "APP_STORE_CONNECT_APP_RECORD_READY=1"
else
  mark_human_gate "App Store Connect app record" "missing" "create the app record for net.foxdesk.ios"
fi

if [[ "${APPLE_DEVELOPER_BUNDLE_READY:-}" == "1" ]]; then
  mark_human_gate "Apple Developer bundle and push capability" "ready" "APPLE_DEVELOPER_BUNDLE_READY=1"
else
  mark_human_gate "Apple Developer bundle and push capability" "missing" "confirm bundle id net.foxdesk.ios exists under Aenze s.r.o. and Push Notifications are enabled"
fi

if [[ "${APPLE_BUSINESS_VERIFIED:-}" == "1" ]]; then
  human_gate_status "Apple Business organization verification" "ready" "APPLE_BUSINESS_VERIFIED=1"
elif [[ -f "$ROOT_DIR/docs/IOS_OPERATOR_CHECKLIST.md" ]] && grep -Fq 'Apple Business organization verification is done' "$ROOT_DIR/docs/IOS_OPERATOR_CHECKLIST.md"; then
  human_gate_status "Apple Business organization verification" "ready" "docs/IOS_OPERATOR_CHECKLIST.md records Aenze s.r.o. as verified"
else
  human_gate_status "Apple Business organization verification" "not recorded" "optional context only; App Store Connect and Developer signing remain the release gates"
fi

if grep -Fq 'Demo reviewer account:' "$ROOT_DIR/docs/IOS_APP_STORE_SUBMISSION.md" && grep -Fq 'docs/IOS_DEMO_REVIEWER_ACCOUNT.md' "$ROOT_DIR/docs/IOS_APP_STORE_SUBMISSION.md"; then
  mark_human_gate "App Review notes template" "ready" "submission packet includes demo account notes and setup runbook"
else
  mark_human_gate "App Review notes template" "missing" "add demo account notes and docs/IOS_DEMO_REVIEWER_ACCOUNT.md link"
fi

if evidence_ready "$DEMO_EVIDENCE" "live-demo-account"; then
  mark_human_gate "Demo reviewer account credentials" "ready" "passing live demo check evidence exists at tmp/ios-demo-account-check/latest-live-demo-account.json"
elif [[ -n "${FOXDESK_IOS_DEMO_EMAIL:-}" && -n "${FOXDESK_IOS_DEMO_PASSWORD:-}" ]]; then
  mark_human_gate "Demo reviewer account credentials" "missing" "demo credentials are set, but no passing live demo evidence exists; run npm run ios:demo:check -- --require-credentials --json"
else
  mark_human_gate "Demo reviewer account credentials" "missing" "set FOXDESK_IOS_DEMO_EMAIL and FOXDESK_IOS_DEMO_PASSWORD, then run npm run ios:demo:check -- --require-credentials --json"
fi

if demo_write_ready "$DEMO_EVIDENCE"; then
  mark_human_gate "Demo reviewer write proof" "ready" "passing demo evidence includes an internal comment-with-time write check"
elif [[ "${FOXDESK_IOS_DEMO_WRITE:-}" == "1" ]]; then
  mark_human_gate "Demo reviewer write proof" "missing" "FOXDESK_IOS_DEMO_WRITE=1 is set, but demo evidence does not include linked comment_id/time_entry_id"
else
  mark_human_gate "Demo reviewer write proof" "missing" "run once with FOXDESK_IOS_DEMO_WRITE=1 to prove the App Review account can add an internal timed comment"
fi

if [[ -n "${FOXDESK_IOS_SMOKE_EMAIL:-}" && -n "${FOXDESK_IOS_SMOKE_PASSWORD:-}" ]]; then
  mark_human_gate "Live mobile API smoke credentials" "ready" "FOXDESK_IOS_SMOKE_EMAIL and FOXDESK_IOS_SMOKE_PASSWORD are set"
else
  mark_human_gate "Live mobile API smoke credentials" "missing" "set FOXDESK_IOS_SMOKE_EMAIL and FOXDESK_IOS_SMOKE_PASSWORD"
fi

if [[ "${FOXDESK_IOS_SMOKE_WRITE:-}" == "1" ]]; then
  mark_human_gate "Opt-in write smoke" "ready" "FOXDESK_IOS_SMOKE_WRITE=1"
else
  mark_human_gate "Opt-in write smoke" "missing" "run once with FOXDESK_IOS_SMOKE_WRITE=1 on staging or a disposable workspace"
fi

if [[ -n "${APNS_TEST_DEVICE_TOKEN:-}" ]]; then
  mark_human_gate "Real-device APNs smoke" "ready" "APNS_TEST_DEVICE_TOKEN is set"
else
  mark_human_gate "Real-device APNs smoke" "missing" "copy a token from Account → Push diagnostics on a physical iPhone"
fi

screenshot_count=0
if [[ -d "$ROOT_DIR/tmp/ios-app-store-screenshots" ]]; then
  screenshot_count=$(find "$ROOT_DIR/tmp/ios-app-store-screenshots" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpg' \) | wc -l | tr -d ' ')
fi

if [[ -f "$ROOT_DIR/tmp/ios-app-store-screenshots/manifest.md" && "$screenshot_count" -ge 8 ]]; then
  mark_human_gate "Populated workspace screenshots" "ready" "tmp/ios-app-store-screenshots contains $screenshot_count images and manifest.md"
else
  mark_human_gate "Populated workspace screenshots" "missing" "run npm run ios:screenshots and review tmp/ios-app-store-screenshots/manifest.md"
fi

if [[ "${APP_STORE_PRIVACY_REVIEWED:-}" == "1" ]]; then
  mark_human_gate "App Store privacy answers" "ready" "APP_STORE_PRIVACY_REVIEWED=1"
else
  mark_human_gate "App Store privacy answers" "missing" "review App Store privacy answers as a human/operator"
fi

printf '[ios:beta:gate] Local readiness complete.\n'
if [[ "${#missing_human_gates[@]}" -eq 0 ]]; then
  printf '[ios:beta:gate] Human gates ready for TestFlight.\n'
else
  printf '[ios:beta:gate] Human gates still required before TestFlight:\n'
  for gate in "${missing_human_gates[@]}"; do
    printf '[ios:beta:gate] - %s\n' "$gate"
  done
fi

log "Evidence report: tmp/ios-beta-readiness/latest.md"
