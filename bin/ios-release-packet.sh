#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ROOT_DIR="$ROOT"
source "$ROOT/bin/ios-release-env.sh"
OUT_DIR="$ROOT/tmp/ios-release-packet"
OUT="$OUT_DIR/latest.md"
REPORT_PATH="tmp/ios-release-packet/latest.md"

mkdir -p "$OUT_DIR"

refresh_report() {
  local name="$1"
  local command="$2"

  echo "Refreshing $name..."
  (cd "$ROOT" && eval "$command" >/dev/null)
}

refresh_report "external gate snapshot" "npm run ios:external:gates"
refresh_report "next-action checklist" "npm run ios:next"
refresh_report "completion audit" "npm run ios:completion:audit"

timestamp="$(date -u '+%Y-%m-%d %H:%M:%S UTC')"
external_gates="$ROOT/tmp/ios-external-gates/latest.md"
gate_snapshot="$(awk '
  /^\| Gate \| Status \| Next action \|/ { in_table = 1 }
  in_table { print }
  in_table && /^$/ { exit }
' "$external_gates" 2>/dev/null || true)"

cat > "$OUT" <<MD
# FoxDesk iOS Release Packet

Generated: $timestamp

This packet is the operator handoff for getting the native FoxDesk iOS app from
local readiness to TestFlight/App Store upload. It does not sign, archive, or
upload the app. It collects the current evidence paths, the exact final gates,
and the human actions still needed.

## Product Scope

- Native SwiftUI app for existing FoxDesk Cloud agents and workspace admins.
- Bundle identifier: \`net.foxdesk.ios\`
- Production backend: \`https://app.foxdesk.net/api/mobile/v1\`
- First release includes tickets, comments, time, attachments, push, search,
  notifications, and basic client context.
- Billing, platform admin, reports, self-hosted setup, and public pricing stay
  on the web.

## Current Gate Snapshot

${gate_snapshot:-See \`tmp/ios-external-gates/latest.md\` for the current external gate status.}

## Local Evidence Files

- Beta readiness: \`tmp/ios-beta-readiness/latest.md\`
- MVP audit: \`tmp/ios-mvp-local-audit/latest.md\`
- Simulator smoke: \`tmp/ios-smoke/latest.md\`
- Archive preflight: \`tmp/ios-archive-preflight/latest.md\`
- External gates: \`tmp/ios-external-gates/latest.md\`
- Next actions: \`tmp/ios-next-actions/latest.md\`
- Completion audit: \`tmp/ios-completion-audit/latest.md\`
- Demo account preflight: \`tmp/ios-demo-account-check/latest-preflight.json\`
- Demo account live evidence: \`tmp/ios-demo-account-check/latest-live-demo-account.json\`
- Mobile API smoke preflight: \`tmp/ios-api-smoke/latest-preflight.json\`
- Mobile API read live evidence: \`tmp/ios-api-smoke/latest-live-read-only.json\`
- Mobile API write live evidence: \`tmp/ios-api-smoke/latest-live-write.json\`
- APNs dry-run evidence: \`tmp/ios-apns-smoke/latest-dry-run.json\`
- APNs live send evidence: \`tmp/ios-apns-smoke/latest-send.json\`
- App Store screenshots: \`tmp/ios-app-store-screenshots/manifest.md\`
- App Store submission packet: \`docs/IOS_APP_STORE_SUBMISSION.md\`
- App Store Connect runbook: \`docs/IOS_APP_STORE_CONNECT_STEPS.md\`
- Apple Developer runbook: \`docs/IOS_APPLE_DEVELOPER_STEPS.md\`
- TestFlight runbook: \`docs/IOS_TESTFLIGHT_RUNBOOK.md\`

## Commands To Run Before Upload

Create a local, ignored env file for the final gate:

\`\`\`bash
npm run ios:release:init
# edit .env.ios-release with the operator-only values
\`\`\`

The initializer uses \`.env.ios-release.example\` as the committed template and
does not overwrite an existing local env file.

Never commit \`.env.ios-release\`; it can contain App Review credentials,
smoke-test passwords, and a physical-device APNs token. The iOS gate scripts
auto-load it when present. To use a different local path, set
\`FOXDESK_IOS_RELEASE_ENV_FILE=/path/to/file\`.

\`\`\`bash
npm run ios:release:env
npm run ios:mvp:audit
npm run ios:sim:smoke
npm run ios:beta:gate
npm run ios:completion:audit
npm run ios:metadata:check
npm run ios:archive:preflight
npm run ios:apns:smoke -- --json
\`\`\`

The strict submission gate must pass before calling the release ready. Keep
operator-only values in the ignored \`.env.ios-release\` file, then run:

\`\`\`bash
npm run ios:release:env
npm run ios:submission:gate
\`\`\`

## Upload Guard

Do not upload a build to App Store Connect, distribute it through TestFlight, or
call the iOS release ready until \`npm run ios:submission:gate\` passes. A
successful archive proves only that Xcode can build the app. It does not prove
the App Store Connect record, Apple Developer Push capability, demo reviewer
account, live mobile API smoke, opt-in write smoke, physical-device APNs smoke,
screenshots, or privacy review. The APNs dry-run proves all first-release push
payload shapes; the physical-device APNs smoke proves Apple delivery to one
real iPhone.

## Archive Command

Run this only after Apple Developer signing and App Store Connect are ready:

\`\`\`bash
cd ios/FoxDesk
xcodebuild \\
  -project FoxDesk.xcodeproj \\
  -scheme FoxDesk \\
  -configuration Production \\
  -destination 'generic/platform=iOS' \\
  -archivePath ../../tmp/ios-archive/FoxDesk.xcarchive \\
  archive
\`\`\`

Then validate and distribute the archive through Xcode Organizer or the
approved App Store Connect upload flow.

## Human Gates

These are intentionally outside local automation:

- App Store Connect app record for \`net.foxdesk.ios\`
- Apple Developer bundle id and Push Notifications capability
- Demo reviewer account and populated workspace
- Live mobile API smoke credentials
- One opt-in write smoke on staging or disposable production workspace
- Physical iPhone APNs token and live APNs smoke
- Screenshot human review/upload
- App Store privacy answer review

## Handoff Rule

If the next agent or Mac needs context, start with this packet, then read
\`docs/IOS_HANDOFF.md\`, \`tmp/ios-next-actions/latest.md\`, and
\`docs/IOS_OPERATOR_CHECKLIST.md\`.
MD

echo "Wrote $REPORT_PATH"
