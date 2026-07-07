# FoxDesk iOS Operator Checklist

This checklist contains only the external steps needed after local iOS technical
readiness is green. The native app scope is the FoxDesk Cloud agent/admin work
companion: tickets, comments, time, attachments, search, notifications, and
basic client context. Billing, platform admin, reports, and full settings stay
on the web.

## Current Local State

As of 2026-07-07 04:35 UTC:

- `npm run ios:mvp:audit` passes.
- `npm run ios:beta:gate` passes locally and writes
  `tmp/ios-beta-readiness/latest.md`.
- Xcode simulator tests pass, including regressions that clear the local
  session even when APNs unregister or server logout fails, reset APNs
  diagnostic state after sign-out, and clear any pending push/deep-link ticket
  navigation so the next signed-in account cannot inherit it.
- Simulator launch smoke passes and writes
  `tmp/ios-smoke/foxdesk-login.png` plus `tmp/ios-smoke/latest.md`.
- App Store screenshots exist in `tmp/ios-app-store-screenshots`.
- External gate evidence is written to `tmp/ios-external-gates/latest.md`.
- Apple Business organization verification is done for `Aenze s.r.o.`
  (reported by the operator from Apple Business email confirmation and
  re-confirmed on 2026-07-07).

Apple Business verification is useful for company identity and Apple Business
features, but it does not replace the App Store Connect app record, Apple
Developer signing setup, bundle ID ownership, or push notification capability
setup needed for TestFlight/App Store release.

## Operator Steps

### 1. App Store Connect

Create the App Store Connect record:

- Bundle ID: `net.foxdesk.ios`
- App name: `FoxDesk`
- SKU: `foxdesk-ios`
- Primary language: English
- Category: Business
- Privacy Policy URL: `https://foxdesk.net/index.php?page=legal&type=privacy`
- Support URL: `https://foxdesk.net/#support`

Use the detailed field-by-field runbook:

```text
docs/IOS_APP_STORE_CONNECT_STEPS.md
```

When done, the final gate will need:

```bash
APP_STORE_CONNECT_APP_RECORD_READY=1
```

### 2. Apple Developer Bundle And Push

Use the detailed Apple Developer runbook:

```text
docs/IOS_APPLE_DEVELOPER_STEPS.md
```

In Apple Developer / Certificates, Identifiers & Profiles, confirm:

- Bundle ID exists: `net.foxdesk.ios`
- Team/organization: `Aenze s.r.o.`
- Push Notifications capability is enabled.

When done, the final gate will need:

```bash
APPLE_DEVELOPER_BUNDLE_READY=1
```

### 3. Demo Reviewer Account

Use the detailed reviewer account runbook:

```text
docs/IOS_DEMO_REVIEWER_ACCOUNT.md
```

Create or choose one FoxDesk Cloud workspace user:

- role: agent or workspace admin
- at least one open ticket
- at least one waiting ticket
- at least one done ticket
- at least one ticket with comments and attachment
- permission to add replies, internal notes, time entries, and attachments

Create or choose the App Review demo account, then keep the real credentials in
App Store Connect review notes and in the local ignored `.env.ios-release`
file. Do not commit the real password into `docs/IOS_APP_STORE_SUBMISSION.md`.

Use these fields:

- Email
- Password
- 2FA/backup code, if enabled

Verify that the reviewer account is populated and usable:

```bash
npm run ios:release:env
npm run ios:demo:check -- --require-credentials --json
```

The check writes redacted proof to
`tmp/ios-demo-account-check/latest-live-demo-account.json`. The external gate
stays `needs verification` until that file exists and is passing.

Put `FOXDESK_IOS_DEMO_EMAIL` and `FOXDESK_IOS_DEMO_PASSWORD` in the ignored
local `.env.ios-release` file. If the account uses 2FA, also set
`FOXDESK_IOS_DEMO_2FA_CODE`.

### 4. Live Mobile API Smoke

Use a staging workspace when possible. If you must use production, use only a
disposable production workspace and set
`FOXDESK_IOS_ALLOW_PRODUCTION_WRITE_SMOKE=1` for that run. Put
`FOXDESK_IOS_SMOKE_EMAIL`, `FOXDESK_IOS_SMOKE_PASSWORD`, and
`FOXDESK_IOS_SMOKE_BASE_URL` in the ignored local `.env.ios-release` file.

Run read-only smoke first:

```bash
npm run ios:api:smoke -- --require-credentials --json
```

This writes `tmp/ios-api-smoke/latest-live-read-only.json`.

Then run exactly one write smoke on a safe workspace:

```bash
FOXDESK_IOS_SMOKE_WRITE=1 npm run ios:api:smoke -- --require-credentials --json
```

The write smoke must create one internal smoke ticket, add one timed internal
comment, upload one small attachment, download that attachment through the
authorized URL, reload ticket detail, and suppress notifications. It writes
`tmp/ios-api-smoke/latest-live-write.json`.

### 5. Physical iPhone APNs Smoke

Install a debug or staging build on a physical iPhone.

In the app:

1. Sign in.
2. Open Account.
3. Enable notifications.
4. Open Push diagnostics.
5. Copy the APNs token.

Then run:

```bash
npm run ios:release:env
npm run ios:apns:smoke -- --send --environment=production
```

Put `APNS_TEST_DEVICE_TOKEN` in the ignored local `.env.ios-release` file.
The live send writes `tmp/ios-apns-smoke/latest-send.json`.

Pass criteria:

- the iPhone receives the push,
- tapping it opens the matching ticket,
- the live notification payload contains a valid `ticket_id`,
- the safe APNs dry-run validates every first-release ticket push type:
  `new_ticket`, `new_comment`, `assigned_to_you`, `mentioned`,
  `ticket_updated`, `status_changed`, `priority_changed`, and
  `due_date_reminder`.

The physical-device smoke sends one real notification for the selected
`--type`. Do not spam the device with every notification type; the dry-run
evidence covers payload shape for the full first-release event set, while the
live send proves Apple delivery and tap-to-ticket routing.

### 6. Screenshot Review

Review images in:

```bash
tmp/ios-app-store-screenshots
```

Confirm screenshots do not contain private customer data, tokens, provider
internals, billing, platform admin, or self-hosted setup screens.

### 7. Archive Preflight

Before opening Xcode Organizer or uploading any build, run:

```bash
npm run ios:archive:preflight
```

Pass criteria:

- `tmp/ios-archive-preflight/latest.md` exists,
- Production bundle id is `net.foxdesk.ios`,
- Production API base is `https://app.foxdesk.net/index.php`,
- Production APNs environment is `production`,
- AppIcon and `PrivacyInfo.xcprivacy` are present.

### 8. Privacy Review

Review App Store privacy answers in:

```text
docs/IOS_APP_PRIVACY_ANSWERS.md
```

Then confirm the summary in `docs/IOS_APP_STORE_SUBMISSION.md`.

When reviewed, the final gate will need:

```bash
APP_STORE_PRIVACY_REVIEWED=1
```

## Final Gate

Before handing the release to another Mac or agent, generate the one-file
release packet:

```bash
npm run ios:release:packet
```

It writes `tmp/ios-release-packet/latest.md` and collects the evidence paths,
archive command, strict gate command, and remaining human blockers.

Prepare a local env file for the strict gate:

```bash
npm run ios:release:init
# edit .env.ios-release with real operator values
```

The initializer uses `.env.ios-release.example` as the committed template and
preserves an existing local `.env.ios-release`.

Do not commit `.env.ios-release`. It can contain demo passwords, smoke-test
passwords, and a physical-device APNs token. The committed example file exists
only to keep the final gate repeatable across Macs and agents. The iOS gate
scripts auto-load this local file when it exists. To use a different local
path, set `FOXDESK_IOS_RELEASE_ENV_FILE=/path/to/file`.

Check the local env status without printing secrets:

```bash
npm run ios:release:env
```

Only after every item above is complete:

```bash
npm run ios:release:env
npm run ios:submission:gate
```

Do not call the iOS app ready for TestFlight or App Store submission until this
strict gate passes.
