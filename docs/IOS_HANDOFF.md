# FoxDesk iOS Handoff

## Purpose

This is the handoff for continuing the native FoxDesk iOS app on another Mac or
by another agent. The first iOS release is a SwiftUI work companion for FoxDesk
Cloud agents and workspace admins. It is not a web wrapper, platform admin,
billing surface, public customer portal, or self-hosted setup app.

## Current Product Scope

The iOS MVP covers:

- sign in to `app.foxdesk.net`
- Dashboard/Work overview
- ticket queues
- ticket detail
- new ticket
- public replies
- internal notes
- comment-with-time
- active timers
- camera/photo/file attachments
- attachment preview/download
- notifications inbox
- APNs registration and ticket deep links
- global search
- basic client context
- lightweight account/settings/logout

Billing, pricing, Stripe, platform administration, reports, and full workspace
settings stay on the web for the first release.

## Latest Verified State

As of 2026-07-06 21:44 UTC, commit `b47f2de` is the current iOS handoff
baseline. The local beta gate passed end-to-end:

- `npm run ios:beta:gate`
- `npm run ios:release:env`
- `npm run ios:next`

The beta gate verified the iOS MVP gate, Xcode tests, Production build, Release
compatibility build, Staging build, simulator launch smoke, TestFlight
preflight, mobile API safe smoke preflight, and APNs dry-run. Evidence:
`tmp/ios-beta-readiness/latest.md`.

Apple Business verification for `Aenze s.r.o.` is ready. The remaining release
gates are operator/live-service gates: App Store Connect app record, Apple
Developer bundle + Push Notifications, demo reviewer credentials, live mobile
API smoke credentials, opt-in write smoke, physical iPhone APNs token, and App
Store privacy review. Evidence and ordered steps: `tmp/ios-next-actions/latest.md`.

## Key Paths

- iOS project: `ios/FoxDesk`
- app target: `FoxDesk`
- shared framework: `FoxDeskKit`
- tests: `FoxDeskKitTests` and `FoxDeskTests`
- bundle identifier: `net.foxdesk.ios`
- mobile API docs: `docs/NATIVE_APP_API.md`
- iOS MVP plan: `docs/IOS_APP_PLAN.md`
- MVP traceability: `docs/IOS_MVP_TRACEABILITY.md`
- launch plan: `docs/IOS_APP_LAUNCH_PLAN.md`
- TestFlight runbook: `docs/IOS_TESTFLIGHT_RUNBOOK.md`
- operator checklist: `docs/IOS_OPERATOR_CHECKLIST.md`
- App Store packet: `docs/IOS_APP_STORE_SUBMISSION.md`
- App Store Connect metadata: `docs/IOS_APP_STORE_CONNECT_METADATA.md`
- App Store Connect steps: `docs/IOS_APP_STORE_CONNECT_STEPS.md`
- Apple Developer steps: `docs/IOS_APPLE_DEVELOPER_STEPS.md`
- App Store privacy answers: `docs/IOS_APP_PRIVACY_ANSWERS.md`
- demo reviewer account steps: `docs/IOS_DEMO_REVIEWER_ACCOUNT.md`
- quick MVP audit report: `tmp/ios-mvp-local-audit/latest.md`
- beta evidence report: `tmp/ios-beta-readiness/latest.md`
- external gate report: `tmp/ios-external-gates/latest.md`
- next-actions report: `tmp/ios-next-actions/latest.md`
- release packet: `tmp/ios-release-packet/latest.md`
- simulator screenshot evidence: `tmp/ios-smoke/foxdesk-login.png`
- App Store screenshot folder: `tmp/ios-app-store-screenshots`
- App Store screenshot generator: `npm run ios:screenshots`
- archive preflight report: `tmp/ios-archive-preflight/latest.md`

## Source Of Truth Commands

Run the local beta readiness gate first:

```bash
npm run ios:mvp:audit
npm run ios:beta:gate
```

The quick MVP audit checks the mobile API contracts, iOS scope contract,
traceability contract, TestFlight preflight, screenshot evidence, and writes
`tmp/ios-mvp-local-audit/latest.md`. The beta gate then runs the MVP gate,
Production build check, Release compatibility build check, simulator launch
smoke, Staging build check, TestFlight preflight, safe mobile API smoke, and
APNs dry-run smoke. It also writes `tmp/ios-beta-readiness/latest.md`.

The MVP gate includes `tests/ios-mvp-scope-contract-test.php`. That contract
allows backend tenant/access-state data in models, but blocks visible iOS UI
for checkout, billing portal, pricing, upgrade, platform admin, self-hosted
setup, SMTP/IMAP, Cloudflare, and R2. If it fails, the app is drifting outside
the first-release agent/admin companion scope.

The native Search tab intentionally shows only tickets, clients, and contacts
for the first release. Backend report/search contracts can exist for future
admin slices, but the iOS MVP must not surface report results.

Run the strict final gate before App Store/TestFlight handoff:

```bash
npm run ios:release:env
npm run ios:submission:gate
```

The strict gate must fail until all human and live-smoke evidence is present.
Do not call the iOS release complete unless this command passes.

To inspect only human/operator and live-service gates without rerunning builds:

```bash
npm run ios:external:gates
```

This writes `tmp/ios-external-gates/latest.md` and reports the App Store
Connect app record, Apple Developer bundle/push capability, demo reviewer
account, live smoke credentials, opt-in write smoke, physical iPhone APNs
token, screenshots, and privacy review status.

For a shorter operator-facing checklist that turns those gates into ordered
actions, run:

```bash
npm run ios:next
```

This writes `tmp/ios-next-actions/latest.md`.

For a single handoff packet that collects the current evidence paths, strict
gate command, archive command, and human blockers, run:

```bash
npm run ios:release:packet
```

This refreshes `ios:external:gates` and `ios:next`, then writes
`tmp/ios-release-packet/latest.md`.

## Human Gates Still Needed

These cannot be completed from code alone:

- App Store Connect app record for `net.foxdesk.ios`
- Apple Developer bundle id `net.foxdesk.ios` confirmed under `Aenze s.r.o.`
  with Push Notifications enabled
- demo reviewer account filled in `docs/IOS_APP_STORE_SUBMISSION.md`
- demo reviewer account verified with `npm run ios:demo:check`
- live mobile API smoke credentials
- opt-in write smoke on staging or a disposable workspace
- physical iPhone APNs smoke using `APNS_TEST_DEVICE_TOKEN`
- human review/upload of the generated App Store screenshots in
  `tmp/ios-app-store-screenshots`
- human review of App Store privacy answers

## Live Smoke Environment

Use these only against staging or a disposable workspace unless an operator has
approved production writes:

Keep these operator-only values in the ignored local `.env.ios-release` file:
`APP_STORE_CONNECT_APP_RECORD_READY`, `APPLE_DEVELOPER_BUNDLE_READY`,
`APP_STORE_PRIVACY_REVIEWED`, `FOXDESK_IOS_SMOKE_EMAIL`,
`FOXDESK_IOS_SMOKE_PASSWORD`, `FOXDESK_IOS_SMOKE_2FA_CODE`,
`FOXDESK_IOS_SMOKE_BASE_URL`, `FOXDESK_IOS_SMOKE_WRITE`,
`FOXDESK_IOS_DEMO_EMAIL`, `FOXDESK_IOS_DEMO_PASSWORD`,
`FOXDESK_IOS_DEMO_2FA_CODE`, and `APNS_TEST_DEVICE_TOKEN`.

The write smoke creates one internal smoke ticket, adds a timed internal
comment, uploads one small attachment, verifies ticket detail, and uses
`skip_notification: true`.

The demo reviewer check signs in through the mobile API and verifies that the
review workspace has open, waiting, and done tickets plus at least one ticket
with comments and an attachment:

```bash
npm run ios:demo:check -- --require-credentials --json
```

New-ticket attachments are staged locally until the ticket has an id. If the
ticket is created but one upload fails, keep the created ticket id and retry the
remaining uploads against that ticket. Do not create a second ticket during
attachment retry, and do not re-upload files that already succeeded before the
failure.

## What To Check Before Editing

1. Inspect the current tree first:

   ```bash
   git status --short -- ios docs/IOS_*.md docs/NATIVE_APP_API.md bin/ios-* bin/test-apns-push.php package.json tests/mobile-api-contract-test.php
   ```

2. Do not remove untracked iOS files unless you created them in the current
   task and know they are obsolete.
3. Keep the first release scoped to agent/admin work. Do not add billing,
   platform, self-hosted setup, or public pricing surfaces to iOS.
4. After any iOS/API gate change, run:

   ```bash
   ./bin/run-php.sh tests/mobile-api-contract-test.php
   npm run ios:beta:gate
   ```

5. If a change touches SwiftUI, run the generated Xcode tests through the iOS
   gate rather than trusting static searches.

## Current Expected State

The correct current pre-submission state is:

- `npm run ios:beta:gate` passes locally.
- `npm run ios:archive:preflight` passes locally and verifies the Production
  archive settings before any manual App Store Connect upload.
- `npm run ios:release:packet` writes a single operator handoff packet for the
  next Mac or agent.
- `npm run ios:submission:gate` fails with exit code `2` until human gates are
  present.
- `tmp/ios-beta-readiness/latest.md` lists local gates as passed and human
  gates as missing or ready based on local evidence.
- Populated screenshots can be regenerated with `npm run ios:screenshots`; if
  `tmp/ios-app-store-screenshots/manifest.md` exists with at least 8 images, the
  beta gate should mark screenshots as ready for human review/upload.

This is intentional. A failing strict submission gate is safer than a false
App Store-ready claim.

## Last Verified Local Evidence

As of 2026-07-06 16:21 UTC, after recording Apple Business verification and
re-running the native simulator suite:

- `./bin/run-php.sh tests/mobile-api-contract-test.php` passed.
- `./bin/run-php.sh tests/native-app-api-freeze-contract-test.php` passed.
- Xcode simulator tests passed: 47/47.
  - Build log:
    `/Users/mac/Library/Developer/XcodeBuildMCP/workspaces/FoxDesk-a94a2094071c/logs/test_sim_2026-07-06T16-21-07-968Z_pid79619_88a6e00e.log`
  - Result bundle:
    `/Users/mac/Library/Developer/XcodeBuildMCP/workspaces/FoxDesk-a94a2094071c/result-bundles/test_sim_2026-07-06T16-21-07-968Z_pid79619_e7156eb7.xcresult`
- `npm run ios:mvp:audit` passed and wrote
  `tmp/ios-mvp-local-audit/latest.md`.
- `npm run ios:external:gates` passed as a status report and wrote
  `tmp/ios-external-gates/latest.md`.
- `npm run ios:metadata:check` passed through the TestFlight preflight and
  verifies `docs/IOS_APP_STORE_CONNECT_METADATA.md`.
- `npm run ios:demo:check -- --json` is wired into the release docs and runs as
  a safe preflight without credentials; it needs operator credentials for the
  live App Review account verification.
- `docs/IOS_APP_STORE_CONNECT_STEPS.md` now contains the field-by-field App
  Store Connect app record runbook for bundle id `net.foxdesk.ios`.
- Apple Business organization verification is recorded as ready for
  `Aenze s.r.o.`.
- `npm run ios:beta:gate` passed locally and wrote
  `tmp/ios-beta-readiness/latest.md`. Live mobile smoke, write smoke, and live
  APNs send were intentionally skipped because they require operator-provided
  credentials and a physical-device APNs token.
- As of 2026-07-06 16:56 UTC, `bin/ios-demo-account-check.js` is wired into
  `package.json`, TestFlight preflight, runbooks, and the mobile API contract.
  Verified commands:
  - `node --check bin/ios-demo-account-check.js`
  - `npm run ios:demo:check -- --json`
  - `npm run ios:testflight:preflight`
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
  - `npm run ios:mvp:audit`
- As of 2026-07-06 17:08 UTC, after tightening the strict submission gate to
  require the live demo reviewer account check, Xcode simulator tests passed:
  47/47.
  - Build log:
    `/Users/mac/Library/Developer/XcodeBuildMCP/workspaces/FoxDesk-a94a2094071c/logs/test_sim_2026-07-06T17-08-19-637Z_pid79619_e9bc3f78.log`
  - Result bundle:
    `/Users/mac/Library/Developer/XcodeBuildMCP/workspaces/FoxDesk-a94a2094071c/result-bundles/test_sim_2026-07-06T17-08-19-637Z_pid79619_d849e3b6.xcresult`
- As of 2026-07-06 17:15 UTC, after adding Terms and Request account deletion
  to the debug-only App Store screenshot Settings fixture, Xcode simulator
  tests passed: 47/47.
  - Build log:
    `/Users/mac/Library/Developer/XcodeBuildMCP/workspaces/FoxDesk-a94a2094071c/logs/test_sim_2026-07-06T17-15-01-942Z_pid79619_5c6417e0.log`
  - Result bundle:
    `/Users/mac/Library/Developer/XcodeBuildMCP/workspaces/FoxDesk-a94a2094071c/result-bundles/test_sim_2026-07-06T17-15-01-943Z_pid79619_501baee2.xcresult`
- As of 2026-07-06 17:21 UTC, after the operator confirmed Apple Business
  verification in Apple Business Manager, `npm run ios:external:gates` reports:
  - Apple Business organization verification: ready.
  - Populated App Store screenshots: ready, with 10 screenshots plus
    `tmp/ios-app-store-screenshots/manifest.md`.
- As of 2026-07-06 18:36 UTC, `npm run ios:beta:gate` passed after adding the
  iOS MVP endpoint matrix contract. The local beta gate now verifies that the
  Swift API client, native API docs, and PHP mobile v1 router agree on the
  first-release endpoint matrix.
- As of 2026-07-06 18:55 UTC, archive preflight is wired into the TestFlight
  preflight and MVP audit:
  - `npm run ios:archive:preflight` passed and wrote
    `tmp/ios-archive-preflight/latest.md`.
  - `npm run ios:testflight:preflight` passed and now runs archive preflight.
  - `npm run ios:mvp:audit` passed and wrote
    `tmp/ios-mvp-local-audit/latest.md`.
  - Archive preflight verifies the generated `FoxDesk` scheme, `Production`
    archive configuration, bundle id `net.foxdesk.ios`, AppIcon, PrivacyInfo,
    production API base, and production APNs entitlement before manual upload.
- As of 2026-07-06 19:05 UTC, `npm run ios:next` writes
  `tmp/ios-next-actions/latest.md` with the ordered operator checklist. Current
  status: Apple Business verification and populated screenshots are ready;
  App Store Connect app record, Apple Developer bundle/push capability, demo
  reviewer account, live API smoke, opt-in write smoke, physical iPhone APNs
  token, and App Store privacy review still require operator action.
  - Still missing: App Store Connect app record, Apple Developer bundle/push
    confirmation, demo reviewer account, live mobile API smoke credentials,
    opt-in write smoke, physical iPhone APNs token, and App Store privacy
    review.
- As of 2026-07-06 17:25 UTC, Xcode simulator tests passed again: 47/47.
  - Build log:
    `/Users/mac/Library/Developer/XcodeBuildMCP/workspaces/FoxDesk-a94a2094071c/logs/test_sim_2026-07-06T17-25-50-850Z_pid79619_b4a2c907.log`
  - Result bundle:
    `/Users/mac/Library/Developer/XcodeBuildMCP/workspaces/FoxDesk-a94a2094071c/result-bundles/test_sim_2026-07-06T17-25-50-850Z_pid79619_a886b059.xcresult`
- As of 2026-07-06 17:31 UTC, `npm run ios:beta:gate` passed end to
  end and wrote `tmp/ios-beta-readiness/latest.md`. The passed steps were:
  iOS MVP gate, Release build check, Staging build check, Simulator launch
  smoke, TestFlight preflight, Mobile API safe smoke, and APNs dry-run smoke.
  Live mobile API smoke, opt-in write smoke, and APNs live-send smoke remain
  intentionally skipped until operator-provided credentials and a physical
  iPhone APNs token are available.
- As of 2026-07-06 17:36 UTC, `npm run ios:submission:gate` was run without
  operator-provided external values. It correctly re-ran the local MVP audit
  and beta gate, then failed safely with exit code `2` because the App Store
  Connect record, Apple Developer bundle/push confirmation, privacy review
  flag, demo reviewer credentials, live smoke credentials, opt-in write smoke,
  physical-device APNs token, and real demo account credentials are still
  missing from the local release environment.
- As of 2026-07-06 17:48 UTC, the release gates explicitly track Apple
  Developer bundle/push readiness with `APPLE_DEVELOPER_BUNDLE_READY=1`.
  `npm run ios:beta:gate` passes locally and lists this as a missing human
  gate until bundle id `net.foxdesk.ios` is confirmed under `Aenze s.r.o.` with
  Push Notifications enabled. `npm run ios:submission:gate` fails safely with
  exit code `2` without that flag.
- As of 2026-07-06 17:56 UTC, the iOS privacy/account-deletion surface is
  covered by contract checks. Settings links to support, Privacy Policy, Terms,
  and a dedicated account deletion request mailto. The privacy manifest
  declares no tracking and discloses name, email, user ID, customer support
  data, and uploaded photos/videos. Verified commands:
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
  - `npm run ios:testflight:preflight`
  - `npm run ios:external:gates`
  - `npm run ios:beta:gate`
- As of 2026-07-06 18:00 UTC, the App Store metadata and submission packet use
  the live privacy URL `https://foxdesk.net/index.php?page=legal&type=privacy`.
  The shorter `https://foxdesk.net/privacy` currently returns 404 and is now
  blocked by `npm run ios:metadata:check`. Verified commands:
  - `npm run ios:metadata:check`
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
  - `npm run ios:testflight:preflight`
- As of 2026-07-06 18:05 UTC, the App Store support URL was moved from the old
  empty `https://foxdesk.net/#contact` anchor to
  `https://foxdesk.net/#support`. The public footer now exposes that anchor and
  a `support@foxdesk.net` support link. Verified commands:
  - `npm run ios:metadata:check`
  - `npm run ios:testflight:preflight`
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
- As of 2026-07-06 18:12 UTC, `docs/IOS_APPLE_DEVELOPER_STEPS.md` documents the
  Apple Developer-only release work: explicit App ID `net.foxdesk.ios`, Push
  Notifications capability, APNs `.p8` key handling, signing/provisioning, and
  physical-device APNs smoke. It is linked from the operator checklist,
  TestFlight runbook, App Store Connect steps, and this handoff.
- As of 2026-07-06 18:20 UTC, `docs/IOS_APP_PRIVACY_ANSWERS.md` and
  `docs/IOS_DEMO_REVIEWER_ACCOUNT.md` split App Store privacy and reviewer
  account setup into dedicated operator runbooks. They are linked from the
  submission packet, operator checklist, TestFlight runbook, and this handoff.
  Verified commands:
  - `npm run ios:testflight:preflight`
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
  - `npm run ios:metadata:check`
  - `npm run ios:external:gates`
- As of 2026-07-06 18:35 UTC, `tests/ios-mvp-endpoint-matrix-contract-test.php`
  locks the first-release mobile endpoint matrix across the PHP mobile v1
  router, `docs/NATIVE_APP_API.md`, and the Swift `FoxDeskAPIClient`. The MVP
  traceability row for APNs unregister now uses the real
  `POST /api/mobile/v1/device-token/unregister` endpoint. Verified commands:
  - `./bin/run-php.sh tests/ios-mvp-endpoint-matrix-contract-test.php`
  - `./bin/run-php.sh tests/ios-mvp-traceability-contract-test.php`
  - `npm run ios:gate`
- As of 2026-07-06 18:37 UTC, `npm run ios:beta:gate` passed after adding the
  endpoint matrix contract and wrote `tmp/ios-beta-readiness/latest.md`.
  Remaining gates are external/operator-only: App Store Connect app record,
  Apple Developer bundle/push confirmation, demo reviewer credentials, live
  mobile smoke credentials, opt-in write smoke, real-device APNs token, and
  App Store privacy review.
- As of 2026-07-06 18:42 UTC, the fast local MVP audit also runs
  `tests/ios-mvp-endpoint-matrix-contract-test.php`, and TestFlight preflight
  requires that contract to exist. Verified commands:
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
  - `npm run ios:testflight:preflight`
  - `npm run ios:mvp:audit`
- As of 2026-07-06 19:26 UTC, the iOS archive handoff uses an explicit
  `Production` configuration for App Store archives. `Release` remains as a
  compatibility build check, but archive preflight and the generated `FoxDesk`
  scheme now verify `Production` build settings before upload. Verified
  commands:
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
  - `npm run ios:production:check`
  - `npm run ios:archive:preflight`
  - `npm run ios:testflight:preflight`
  - `npm run ios:mvp:audit`
  - `npm run ios:next`
  - `npm run ios:beta:gate`
- As of 2026-07-06 20:11 UTC, Apple Business verification for `Aenze s.r.o.`
  is recorded as ready, the release packet was regenerated, and the native iOS
  tests passed through XcodeBuildMCP on the configured `foxdesk-ios` profile
  (`FoxDesk` scheme, `iPhone 17` simulator): 47 passed, 0 failed. Verified
  commands:
  - `npm run ios:external:gates`
  - `npm run ios:next`
  - `npm run ios:release:packet`
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
  - `npm run ios:demo:check -- --json`
  - XcodeBuildMCP `test_sim` on profile `foxdesk-ios`
- As of 2026-07-06 20:24 UTC, `npm run ios:beta:gate` now reports Apple
  Business verification as an informational ready gate from the operator
  checklist, matching `npm run ios:external:gates` and `npm run ios:next`.
  This keeps the beta readiness report from looking stale while still leaving
  App Store Connect and Apple Developer signing/push as the real release gates.
  Verified commands:
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
  - `npm run ios:beta:gate`
  - `npm run ios:release:packet`
- As of 2026-07-06 20:31 UTC, `.env.ios-release.example` is the committed
  template for local, ignored final-gate variables. Run
  `npm run ios:release:init`, edit `.env.ios-release`, and keep that file
  uncommitted. `bin/ios-release-env.sh` auto-loads it from the iOS gate scripts, so another Mac/agent can run
  `npm run ios:submission:gate` without hunting through several docs for env
  names or manually sourcing the file every time. `npm run ios:release:env`
  writes `tmp/ios-release-env/latest.md` with a safe redacted readiness report.
  Verified commands:
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
  - `npm run ios:release:env`
  - `npm run ios:mvp:audit`
  - `npm run ios:release:packet`

The native activity surface should now render time entries created together
with comments inline under that comment. Time entries without `comment_id`
remain separate rows so legacy/orphan work logs are still visible.

## Next Operator Handoff

Give the next agent or Mac this exact checklist:

1. Pull the repo and do not start by redesigning the app scope. The first iOS
   release is already defined as an agent/admin work companion.
2. Run `npm run ios:mvp:audit` and Xcode simulator tests before changing
   release gates.
3. Run `npm run ios:release:init`, edit `.env.ios-release`, and keep it
   uncommitted. The iOS gate scripts auto-load it for final-gate credentials
   and Apple readiness flags. Run `npm run ios:release:env` to check what is
   still missing without printing secrets.
4. Prepare a disposable live workspace account and run live smoke:
   `FOXDESK_IOS_SMOKE_EMAIL`, `FOXDESK_IOS_SMOKE_PASSWORD`,
   `FOXDESK_IOS_SMOKE_WRITE=1`.
5. Install the app on a physical iPhone, copy the APNs token from Push
   diagnostics, and run the APNs send smoke.
6. Regenerate screenshots, review them manually, then fill App Store Connect
   metadata from `docs/IOS_APP_STORE_CONNECT_METADATA.md`, following
   `docs/IOS_APP_STORE_CONNECT_STEPS.md`, and privacy answers.
7. Only after those items are ready, run `npm run ios:submission:gate`.
