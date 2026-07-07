#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "$ROOT_DIR/bin/ios-release-env.sh"

OUT_DIR="$ROOT_DIR/tmp/ios-completion-audit"
REPORT="$OUT_DIR/latest.md"
mkdir -p "$OUT_DIR"

git_rev="$(git -C "$ROOT_DIR" rev-parse --short HEAD 2>/dev/null || printf 'unknown')"
beta_report="$ROOT_DIR/tmp/ios-beta-readiness/latest.md"
next_report="$ROOT_DIR/tmp/ios-next-actions/latest.md"
release_env_report="$ROOT_DIR/tmp/ios-release-env/latest.md"
simulator_smoke_report="$ROOT_DIR/tmp/ios-smoke/latest.md"
demo_evidence="$ROOT_DIR/tmp/ios-demo-account-check/latest-live-demo-account.json"
demo_preflight_evidence="$ROOT_DIR/tmp/ios-demo-account-check/latest-preflight.json"
api_read_evidence="$ROOT_DIR/tmp/ios-api-smoke/latest-live-read-only.json"
api_write_evidence="$ROOT_DIR/tmp/ios-api-smoke/latest-live-write.json"
api_preflight_evidence="$ROOT_DIR/tmp/ios-api-smoke/latest-preflight.json"
apns_send_evidence="$ROOT_DIR/tmp/ios-apns-smoke/latest-send.json"

status_from_env_flag() {
  local name="$1"
  if [[ "${!name:-}" == "1" ]]; then
    printf 'ready'
  else
    printf 'missing'
  fi
}

status_from_env_value() {
  local name="$1"
  if [[ -n "${!name:-}" ]]; then
    printf 'ready'
  else
    printf 'missing'
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
    const download = steps.find((row) => row && row.name === "attachment-download");
    if (!Number.isInteger(Number(download.bytes)) || Number(download.bytes) <= 0) process.exit(1);
  ' "$file"
}

demo_write_ready() {
  local file="$1"

  [[ -f "$file" ]] || return 1
  [[ "$(json_field "$file" ok 2>/dev/null || true)" == "true" ]] || return 1
  [[ "$(json_field "$file" mode 2>/dev/null || true)" == "live-demo-account" ]] || return 1
  node -e '
    const fs = require("node:fs");
    const data = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
    const steps = Array.isArray(data.steps) ? data.steps : [];
    const create = steps.find((row) => row && row.name === "demo-write-create-ticket");
    const comment = steps.find((row) => row && row.name === "demo-write-comment-with-time");
    const reload = steps.find((row) => row && row.name === "demo-write-detail-reload");
    const hasId = (value) => Number.isInteger(Number(value)) && Number(value) > 0;
    if (
      !create || create.ok !== true || !hasId(create.ticket_id) ||
      !comment || comment.ok !== true || !hasId(comment.comment_id) || !hasId(comment.time_entry_id) ||
      !reload || reload.ok !== true || !hasId(reload.ticket_id) || reload.comment_visible !== true || reload.linked_time_visible !== true
    ) {
      process.exit(1);
    }
  ' "$file"
}

live_smoke_status="missing"
if evidence_ready "$api_read_evidence" "live-read-only"; then
  live_smoke_status="ready"
elif [[ -n "${FOXDESK_IOS_SMOKE_EMAIL:-}" && -n "${FOXDESK_IOS_SMOKE_PASSWORD:-}" ]]; then
  live_smoke_status="needs verification"
fi

write_smoke_status="missing"
if api_write_ready "$api_write_evidence"; then
  write_smoke_status="ready"
elif [[ "${FOXDESK_IOS_SMOKE_WRITE:-}" == "1" && "$live_smoke_status" != "missing" ]]; then
  write_smoke_status="needs verification"
fi

demo_status="missing"
if evidence_ready "$demo_evidence" "live-demo-account"; then
  demo_status="ready"
elif [[ -n "${FOXDESK_IOS_DEMO_EMAIL:-}" && -n "${FOXDESK_IOS_DEMO_PASSWORD:-}" ]]; then
  demo_status="needs verification"
fi

demo_write_status="missing"
if demo_write_ready "$demo_evidence"; then
  demo_write_status="ready"
elif [[ "${FOXDESK_IOS_DEMO_WRITE:-}" == "1" && "$demo_status" != "missing" ]]; then
  demo_write_status="needs verification"
fi

apns_status="missing"
if evidence_ready "$apns_send_evidence" "send" && [[ "$(json_field "$apns_send_evidence" sent 2>/dev/null || true)" == "true" ]]; then
  apns_status="ready"
elif [[ -n "${APNS_TEST_DEVICE_TOKEN:-}" ]]; then
  apns_status="needs verification"
fi

app_record_status="$(status_from_env_flag APP_STORE_CONNECT_APP_RECORD_READY)"
developer_status="$(status_from_env_flag APPLE_DEVELOPER_BUNDLE_READY)"
privacy_status="$(status_from_env_flag APP_STORE_PRIVACY_REVIEWED)"

screenshots_status="missing"
if [[ -f "$ROOT_DIR/tmp/ios-app-store-screenshots/manifest.md" ]]; then
  screenshot_count="$(find "$ROOT_DIR/tmp/ios-app-store-screenshots" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpg' -o -name '*.jpeg' \) | wc -l | tr -d ' ')"
  if [[ "$screenshot_count" -ge 8 ]]; then
    screenshots_status="ready"
  fi
fi

cat > "$REPORT" <<REPORT
# FoxDesk iOS Completion Audit

- Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
- Git revision: $git_rev
- Product scope: native SwiftUI agent/admin work app for FoxDesk Cloud
- Report path: \`tmp/ios-completion-audit/latest.md\`
- Strict conclusion: **not complete for TestFlight/App Store until live and Apple gates are ready**

This audit separates locally proven MVP implementation from release evidence
that requires Apple systems, a live workspace account, or a physical iPhone.

## MVP Requirement Status

| Requirement | Local implementation evidence | Release evidence still needed |
| --- | --- | --- |
| 1. Sign in to \`app.foxdesk.net\` | Mobile login/refresh/me contracts, \`LoginView\`, \`AppSession\`, Keychain storage, Xcode tests | Demo reviewer and live smoke credentials: $demo_status / $live_smoke_status |
| 2. Work dashboard | \`GET /api/mobile/v1/work\`, \`DashboardView\`, worked-time, queues, recent updates, beta gate | Live smoke against real workspace: $live_smoke_status |
| 3. My tickets | \`TicketsView\`, Mine/New/Waiting/Done/All tabs, mobile tickets contract | Live smoke against real workspace: $live_smoke_status |
| 4. Create ticket from iPhone | \`NewTicketView\`, \`POST /api/mobile/v1/tickets\`, create-ticket options, attachment upload staging, New ticket tab contract | Opt-in write smoke must create and reload a real ticket: $write_smoke_status |
| 5. Ticket detail | \`TicketDetailView\`, activity/timer/attachments/actions, mobile ticket detail contract | Live smoke against real workspace: $live_smoke_status |
| 6. Reply / internal note | Mobile comments endpoint and native composer are implemented | Opt-in write smoke: $write_smoke_status |
| 7. Comment with time | \`comment-with-time\` endpoint, exact/manual time controls, linked time-entry contracts | Opt-in write smoke: $write_smoke_status |
| 8. Basic reply formatting | \`MobileRichTextFormatter\` and Xcode tests preserve paragraphs, lists, bold/italic, and HTML escaping | Covered locally; verify visually during write smoke: $write_smoke_status |
| 9. Attachments and photos | Camera/file picker, upload, preview/download, attachment contracts | Real-device or live smoke attachment upload and authorized download: $write_smoke_status |
| 10. Push notifications | Device-token endpoints, APNs payload dry-run, native routing tests | Apple Developer Push capability: $developer_status; physical iPhone APNs token: $apns_status |
| 11. Global search | \`SearchView\`, mobile search contract, global search tests | Live smoke against real workspace: $live_smoke_status |
| 12. Client context | \`ClientContextView\`, mobile client endpoint, demo-account contract expectations | Demo reviewer account populated with client context: $demo_status |
| 13. Offline/speed fallback | dashboard/list/detail caches, reply drafts, attachment retry state, Xcode tests | No external gate; validate again after live smoke if API payloads change |

## Apple And Submission Gates

| Gate | Status |
| --- | --- |
| Apple Business verification | $(status_from_env_flag APPLE_BUSINESS_VERIFIED) |
| App Store Connect app record | $app_record_status |
| Apple Developer bundle id + Push Notifications | $developer_status |
| App Review demo credentials | $demo_status |
| Demo reviewer write proof | $demo_write_status |
| Live mobile API smoke credentials | $live_smoke_status |
| Opt-in write smoke | $write_smoke_status |
| Physical iPhone APNs token | $apns_status |
| Populated screenshots | $screenshots_status |
| App Store privacy review | $privacy_status |

## Current Evidence Files

- Beta readiness: $(if [[ -f "$beta_report" ]]; then printf 'present'; else printf 'missing'; fi) — \`tmp/ios-beta-readiness/latest.md\`
- Next actions: $(if [[ -f "$next_report" ]]; then printf 'present'; else printf 'missing'; fi) — \`tmp/ios-next-actions/latest.md\`
- Release env check: $(if [[ -f "$release_env_report" ]]; then printf 'present'; else printf 'missing'; fi) — \`tmp/ios-release-env/latest.md\`
- Simulator smoke evidence: $(if [[ -f "$simulator_smoke_report" ]]; then printf 'present'; else printf 'missing'; fi) — \`tmp/ios-smoke/latest.md\`
- Demo account preflight evidence: $(if [[ -f "$demo_preflight_evidence" ]]; then printf 'present'; else printf 'missing'; fi) — \`tmp/ios-demo-account-check/latest-preflight.json\`
- Demo account live evidence: $(if [[ -f "$demo_evidence" ]]; then printf 'present'; else printf 'missing'; fi) — \`tmp/ios-demo-account-check/latest-live-demo-account.json\`
- API smoke preflight evidence: $(if [[ -f "$api_preflight_evidence" ]]; then printf 'present'; else printf 'missing'; fi) — \`tmp/ios-api-smoke/latest-preflight.json\`
- API read live evidence: $(if [[ -f "$api_read_evidence" ]]; then printf 'present'; else printf 'missing'; fi) — \`tmp/ios-api-smoke/latest-live-read-only.json\`
- API write live evidence: $(if [[ -f "$api_write_evidence" ]]; then printf 'present'; else printf 'missing'; fi) — \`tmp/ios-api-smoke/latest-live-write.json\`
- APNs live-send evidence: $(if [[ -f "$apns_send_evidence" ]]; then printf 'present'; else printf 'missing'; fi) — \`tmp/ios-apns-smoke/latest-send.json\`
- MVP traceability: \`docs/IOS_MVP_TRACEABILITY.md\`
- Handoff: \`docs/IOS_HANDOFF.md\`

## Required Before Calling The Goal Complete

1. Create App Store Connect app record for \`net.foxdesk.ios\`.
2. Confirm Apple Developer explicit App ID \`net.foxdesk.ios\` and enable Push Notifications.
3. Verify App Review demo account with \`npm run ios:demo:check -- --require-credentials --json\`.
4. Prove App Review demo write permission by creating a demo ticket and linked timed internal comment with \`FOXDESK_IOS_DEMO_WRITE=1 npm run ios:demo:check -- --require-credentials --json\`.
5. Run live mobile API read smoke with \`npm run ios:api:smoke -- --require-credentials --json\`.
6. Run one opt-in write smoke with \`FOXDESK_IOS_SMOKE_WRITE=1\`; it must prove ticket creation, timed comment, attachment upload, and authorized attachment download.
7. Run physical-device APNs smoke with \`APNS_TEST_DEVICE_TOKEN\`.
8. Human-review App Store screenshots and privacy answers.
9. Run \`npm run ios:submission:gate\` and require it to pass.

REPORT

printf '[ios:completion:audit] Wrote %s\n' "$REPORT"

if [[ "$app_record_status" == "ready" \
  && "$developer_status" == "ready" \
  && "$privacy_status" == "ready" \
  && "$demo_status" == "ready" \
  && "$demo_write_status" == "ready" \
  && "$live_smoke_status" == "ready" \
  && "$write_smoke_status" == "ready" \
  && "$apns_status" == "ready" \
  && "$screenshots_status" == "ready" ]]; then
  printf '[ios:completion:audit] Completion evidence is ready for strict submission gate.\n'
else
  printf '[ios:completion:audit] Completion evidence is incomplete; see report for remaining gates.\n'
fi
