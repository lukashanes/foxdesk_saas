#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "$ROOT_DIR/bin/ios-release-env.sh"
EVIDENCE_DIR="$ROOT_DIR/tmp/ios-next-actions"
REPORT="$EVIDENCE_DIR/latest.md"
EXTERNAL_REPORT="$ROOT_DIR/tmp/ios-external-gates/latest.md"
ARCHIVE_REPORT="$ROOT_DIR/tmp/ios-archive-preflight/latest.md"
MVP_REPORT="$ROOT_DIR/tmp/ios-mvp-local-audit/latest.md"
SCREENSHOT_MANIFEST="$ROOT_DIR/tmp/ios-app-store-screenshots/manifest.md"

mkdir -p "$EVIDENCE_DIR"

status_from_report() {
  local label="$1"
  if [[ ! -f "$EXTERNAL_REPORT" ]]; then
    printf 'unknown'
    return
  fi

  awk -F'|' -v label="$label" '
    $2 ~ label {
      gsub(/^ +| +$/, "", $3)
      print $3
      found=1
      exit
    }
    END {
      if (!found) print "unknown"
    }
  ' "$EXTERNAL_REPORT"
}

bool_status() {
  local value="$1"
  if [[ "$value" == "ready" ]]; then
    printf 'ready'
  else
    printf 'todo'
  fi
}

(cd "$ROOT_DIR" && npm run ios:external:gates >/dev/null)

app_store_record_status="$(status_from_report "App Store Connect record")"
developer_status="$(status_from_report "Apple Developer bundle and push capability")"
business_status="$(status_from_report "Apple Business organization verification")"
app_review_notes_status="$(status_from_report "App Review notes template")"
demo_creds_status="$(status_from_report "Demo reviewer account credentials")"
api_smoke_status="$(status_from_report "Live mobile API smoke credentials")"
write_smoke_status="$(status_from_report "Opt-in write smoke")"
apns_status="$(status_from_report "Physical iPhone APNs token")"
screenshots_status="$(status_from_report "Populated App Store screenshots")"
privacy_status="$(status_from_report "App Store privacy review")"

cat > "$REPORT" <<REPORT
# FoxDesk iOS Next Actions

- Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
- Scope: remaining steps before internal TestFlight/App Store submission
- Local MVP audit: $([[ -f "$MVP_REPORT" ]] && printf 'present' || printf 'missing')
- Archive preflight: $([[ -f "$ARCHIVE_REPORT" ]] && printf 'present' || printf 'missing')
- External gate source: tmp/ios-external-gates/latest.md

## Status Snapshot

| Area | Status |
| --- | --- |
| Apple Business organization verification | $business_status |
| App Store Connect app record | $app_store_record_status |
| Apple Developer bundle + Push Notifications | $developer_status |
| App Review notes template | $app_review_notes_status |
| Demo reviewer credentials | $demo_creds_status |
| Live mobile API smoke credentials | $api_smoke_status |
| Opt-in write smoke | $write_smoke_status |
| Physical iPhone APNs token | $apns_status |
| Populated screenshots | $screenshots_status |
| App Store privacy review | $privacy_status |

## Apple Business Note

Apple Business verification for \`Aenze s.r.o.\` is recorded and ready. It is
company identity evidence only. Continue with the App Store Connect app record
and the Apple Developer bundle ID / Push Notifications setup before TestFlight.

## Safe Local Release Env

Use one ignored local env file for App Review credentials, live-smoke
credentials, APNs token, and Apple readiness flags:

\`\`\`bash
npm run ios:release:init
# edit .env.ios-release locally; never commit it
npm run ios:release:env
\`\`\`

The env check writes \`tmp/ios-release-env/latest.md\` and never prints secret
values. The iOS gate scripts auto-load \`.env.ios-release\` when present.

## Ordered Actions

1. **Create App Store Connect app record** ($(bool_status "$app_store_record_status"))
   - Open https://appstoreconnect.apple.com/apps
   - Use bundle id \`net.foxdesk.ios\`, name \`FoxDesk\`, SKU \`foxdesk-ios\`.
   - Follow \`docs/IOS_APP_STORE_CONNECT_STEPS.md\`.
   - After this is done, set \`APP_STORE_CONNECT_APP_RECORD_READY=1\` in
     \`.env.ios-release\` and run \`npm run ios:release:env\`.

2. **Confirm Apple Developer App ID and Push Notifications** ($(bool_status "$developer_status"))
   - Open https://developer.apple.com/account/resources/identifiers/list
   - Confirm explicit App ID \`net.foxdesk.ios\` under \`Aenze s.r.o.\`.
   - Enable \`Push Notifications\`.
   - Follow \`docs/IOS_APPLE_DEVELOPER_STEPS.md\`.
   - After this is done, set \`APPLE_DEVELOPER_BUNDLE_READY=1\` in
     \`.env.ios-release\` and run \`npm run ios:release:env\`.

3. **Prepare App Review demo account** ($(bool_status "$demo_creds_status"))
   - Use a disposable or curated FoxDesk Cloud workspace.
   - Role: agent or workspace admin.
   - It must include open, waiting, done, comment, attachment, and client-context data.
   - Follow \`docs/IOS_DEMO_REVIEWER_ACCOUNT.md\`.
   - Keep committed docs generic; paste real credentials only into App Store
     Connect review notes or the local ignored \`.env.ios-release\` file.
   - This is not ready until the credentials are present and
     \`npm run ios:demo:check -- --require-credentials --json\` passes.

4. **Verify demo account credentials** ($(bool_status "$demo_creds_status"))
   - Do not commit real passwords.
   - Fill \`FOXDESK_IOS_DEMO_EMAIL\` and \`FOXDESK_IOS_DEMO_PASSWORD\` in
     \`.env.ios-release\`, then run:

     \`\`\`bash
     npm run ios:demo:check -- --require-credentials --json
     \`\`\`

5. **Run live mobile API smoke** ($(bool_status "$api_smoke_status"))
   - Use staging or a disposable production workspace. Fill
     \`FOXDESK_IOS_SMOKE_EMAIL\` and \`FOXDESK_IOS_SMOKE_PASSWORD\` in
     \`.env.ios-release\`, then run:

     \`\`\`bash
     npm run ios:api:smoke -- --require-credentials --json
     \`\`\`

6. **Run one opt-in write smoke** ($(bool_status "$write_smoke_status"))
   - This creates one internal smoke ticket, timed internal comment, and small
     attachment. Set \`FOXDESK_IOS_SMOKE_WRITE=1\` in \`.env.ios-release\`,
     then run:

     \`\`\`bash
     npm run ios:api:smoke -- --require-credentials --json
     \`\`\`

7. **Run physical iPhone APNs smoke** ($(bool_status "$apns_status"))
   - Install a debug/staging build on a real iPhone.
   - Open Account -> Push diagnostics and copy the APNs token.
   - Set \`APNS_TEST_DEVICE_TOKEN\` in \`.env.ios-release\`, then run:

     \`\`\`bash
     npm run ios:apns:smoke -- --send --environment=production
     \`\`\`

8. **Review screenshots** ($(bool_status "$screenshots_status"))
   - Review \`tmp/ios-app-store-screenshots/manifest.md\`.
   - Upload only screenshots without real customer data, tokens, provider internals, billing, platform admin, or self-hosted setup.

9. **Review App Store privacy answers** ($(bool_status "$privacy_status"))
   - Review \`docs/IOS_APP_PRIVACY_ANSWERS.md\` as a human/operator.
   - After review, set \`APP_STORE_PRIVACY_REVIEWED=1\` in
     \`.env.ios-release\`.

## Final Gate

Run only after every action above is ready:

\`\`\`bash
npm run ios:release:env
npm run ios:submission:gate
\`\`\`

REPORT

if [[ -f "$SCREENSHOT_MANIFEST" ]]; then
  cat >> "$REPORT" <<REPORT

## Screenshot Evidence

- Manifest: tmp/ios-app-store-screenshots/manifest.md

REPORT
fi

printf '[ios:next] Wrote tmp/ios-next-actions/latest.md\n'
