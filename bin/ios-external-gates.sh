#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "$ROOT_DIR/bin/ios-release-env.sh"
EVIDENCE_DIR="$ROOT_DIR/tmp/ios-external-gates"
EVIDENCE_REPORT="$EVIDENCE_DIR/latest.md"
DEMO_EVIDENCE="$ROOT_DIR/tmp/ios-demo-account-check/latest-live-demo-account.json"
API_READ_EVIDENCE="$ROOT_DIR/tmp/ios-api-smoke/latest-live-read-only.json"
API_WRITE_EVIDENCE="$ROOT_DIR/tmp/ios-api-smoke/latest-live-write.json"
APNS_SEND_EVIDENCE="$ROOT_DIR/tmp/ios-apns-smoke/latest-send.json"
SUBMISSION_PACKET="$ROOT_DIR/docs/IOS_APP_STORE_SUBMISSION.md"
OPERATOR_CHECKLIST="$ROOT_DIR/docs/IOS_OPERATOR_CHECKLIST.md"
SCREENSHOT_DIR="$ROOT_DIR/tmp/ios-app-store-screenshots"
SCREENSHOT_MANIFEST="$SCREENSHOT_DIR/manifest.md"

mkdir -p "$EVIDENCE_DIR"

log() {
  printf '[ios:external:gates] %s\n' "$1"
}

gate_status() {
  local label="$1"
  local status="$2"
  local detail="$3"

  printf '| %s | %s | %s |\n' "$label" "$status" "$detail" >> "$EVIDENCE_REPORT"
  printf '[ios:external:gates] %s: %s — %s\n' "$label" "$status" "$detail"
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
}

screenshot_count=0
if [[ -d "$SCREENSHOT_DIR" ]]; then
  screenshot_count="$(find "$SCREENSHOT_DIR" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpg' -o -name '*.jpeg' \) | wc -l | tr -d ' ')"
fi

cat > "$EVIDENCE_REPORT" <<REPORT
# FoxDesk iOS External Gates

- Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
- Git revision: $(git -C "$ROOT_DIR" rev-parse --short HEAD 2>/dev/null || printf 'unknown')
- Scope: human/operator and live-service gates before TestFlight/App Store submission

| Gate | Status | Next action |
| --- | --- | --- |
REPORT

if [[ "${APP_STORE_CONNECT_APP_RECORD_READY:-}" == "1" ]]; then
  gate_status "App Store Connect record" "ready" "Operator set APP_STORE_CONNECT_APP_RECORD_READY=1."
else
  gate_status "App Store Connect record" "missing" "Create the App Store Connect app record for bundle id net.foxdesk.ios, then set APP_STORE_CONNECT_APP_RECORD_READY=1."
fi

if [[ "${APPLE_DEVELOPER_BUNDLE_READY:-}" == "1" ]]; then
  gate_status "Apple Developer bundle and push capability" "ready" "Operator set APPLE_DEVELOPER_BUNDLE_READY=1."
else
  gate_status "Apple Developer bundle and push capability" "missing" "Confirm bundle id net.foxdesk.ios exists under Aenze s.r.o. in Apple Developer and Push Notifications are enabled, then set APPLE_DEVELOPER_BUNDLE_READY=1."
fi

if [[ "${APPLE_BUSINESS_VERIFIED:-}" == "1" ]]; then
  gate_status "Apple Business organization verification" "ready" "Operator set APPLE_BUSINESS_VERIFIED=1."
elif [[ -f "$OPERATOR_CHECKLIST" ]] && grep -Fq 'Apple Business organization verification is done' "$OPERATOR_CHECKLIST"; then
  gate_status "Apple Business organization verification" "ready" "docs/IOS_OPERATOR_CHECKLIST.md records Aenze s.r.o. as verified."
else
  gate_status "Apple Business organization verification" "not recorded" "Optional context only: Apple Business verification is helpful, but App Store Connect and Developer signing remain the release gates."
fi

if grep -Fq 'Demo reviewer account:' "$SUBMISSION_PACKET" && grep -Fq 'docs/IOS_DEMO_REVIEWER_ACCOUNT.md' "$SUBMISSION_PACKET"; then
  gate_status "App Review notes template" "ready" "Submission packet includes the demo account section and links the demo setup runbook."
else
  gate_status "App Review notes template" "missing" "Add the demo account section and docs/IOS_DEMO_REVIEWER_ACCOUNT.md link to docs/IOS_APP_STORE_SUBMISSION.md."
fi

if evidence_ready "$DEMO_EVIDENCE" "live-demo-account"; then
  gate_status "Demo reviewer account credentials" "ready" "Passing live demo check evidence exists at tmp/ios-demo-account-check/latest-live-demo-account.json."
elif [[ -n "${FOXDESK_IOS_DEMO_EMAIL:-}" && -n "${FOXDESK_IOS_DEMO_PASSWORD:-}" ]]; then
  gate_status "Demo reviewer account credentials" "needs verification" "FOXDESK_IOS_DEMO_EMAIL and FOXDESK_IOS_DEMO_PASSWORD are set, but tmp/ios-demo-account-check/latest-live-demo-account.json is not passing; run npm run ios:demo:check -- --require-credentials --json."
else
  gate_status "Demo reviewer account credentials" "missing" "Set FOXDESK_IOS_DEMO_EMAIL and FOXDESK_IOS_DEMO_PASSWORD, then run npm run ios:demo:check -- --require-credentials --json."
fi

if evidence_ready "$API_READ_EVIDENCE" "live-read-only"; then
  gate_status "Live mobile API smoke credentials" "ready" "Passing read-only live API smoke evidence exists at tmp/ios-api-smoke/latest-live-read-only.json."
elif [[ -n "${FOXDESK_IOS_SMOKE_EMAIL:-}" && -n "${FOXDESK_IOS_SMOKE_PASSWORD:-}" ]]; then
  gate_status "Live mobile API smoke credentials" "needs verification" "FOXDESK_IOS_SMOKE_EMAIL and FOXDESK_IOS_SMOKE_PASSWORD are set, but tmp/ios-api-smoke/latest-live-read-only.json is not passing; run npm run ios:api:smoke -- --require-credentials --json."
else
  gate_status "Live mobile API smoke credentials" "missing" "Set FOXDESK_IOS_SMOKE_EMAIL and FOXDESK_IOS_SMOKE_PASSWORD for a staging or disposable workspace agent."
fi

if evidence_ready "$API_WRITE_EVIDENCE" "live-write"; then
  gate_status "Opt-in write smoke" "ready" "Passing write smoke evidence exists at tmp/ios-api-smoke/latest-live-write.json."
elif [[ "${FOXDESK_IOS_SMOKE_WRITE:-}" == "1" ]]; then
  gate_status "Opt-in write smoke" "needs verification" "FOXDESK_IOS_SMOKE_WRITE=1 is set, but tmp/ios-api-smoke/latest-live-write.json is not passing; rerun npm run ios:api:smoke -- --require-credentials --json."
else
  gate_status "Opt-in write smoke" "missing" "Run the write smoke once with FOXDESK_IOS_SMOKE_WRITE=1 on staging or a disposable workspace."
fi

if evidence_ready "$APNS_SEND_EVIDENCE" "send" && [[ "$(json_field "$APNS_SEND_EVIDENCE" sent 2>/dev/null || true)" == "true" ]]; then
  gate_status "Physical iPhone APNs token" "ready" "Passing live APNs send evidence exists at tmp/ios-apns-smoke/latest-send.json."
elif [[ -n "${APNS_TEST_DEVICE_TOKEN:-}" ]]; then
  gate_status "Physical iPhone APNs token" "needs verification" "APNS_TEST_DEVICE_TOKEN is set, but tmp/ios-apns-smoke/latest-send.json is not passing; run npm run ios:apns:smoke -- --send --environment=production."
else
  gate_status "Physical iPhone APNs token" "missing" "Install a debug/staging build on a physical iPhone, open Account -> Push diagnostics, copy the token, and set APNS_TEST_DEVICE_TOKEN."
fi

if [[ -f "$SCREENSHOT_MANIFEST" && "$screenshot_count" -ge 8 ]]; then
  gate_status "Populated App Store screenshots" "ready" "Found $screenshot_count screenshots and manifest.md in tmp/ios-app-store-screenshots."
else
  gate_status "Populated App Store screenshots" "missing" "Run npm run ios:screenshots and review at least 8 populated screenshots plus manifest.md."
fi

if [[ "${APP_STORE_PRIVACY_REVIEWED:-}" == "1" ]]; then
  gate_status "App Store privacy review" "ready" "Operator set APP_STORE_PRIVACY_REVIEWED=1."
else
  gate_status "App Store privacy review" "missing" "Review docs/IOS_APP_PRIVACY_ANSWERS.md as an operator, then set APP_STORE_PRIVACY_REVIEWED=1."
fi

cat >> "$EVIDENCE_REPORT" <<'REPORT'

## Final Command

When every gate above is ready, run:

```bash
npm run ios:release:init
npm run ios:release:env
npm run ios:submission:gate
```
REPORT

log "Evidence report: tmp/ios-external-gates/latest.md"
