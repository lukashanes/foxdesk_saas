# FoxDesk iOS TestFlight Runbook

## Purpose

This runbook prepares the native FoxDesk iOS app for an internal TestFlight
build. The first iOS release is a companion app for existing FoxDesk Cloud
agents and workspace admins. It is not a billing, platform admin, customer
portal, or self-hosted setup app.

For continuing the work on another Mac or with another agent, start with
`docs/IOS_APP_PLAN.md` for the MVP boundary and then `docs/IOS_HANDOFF.md` for
the current implemented state, gate commands, human blockers, smoke environment
variables, and the exact condition for calling the iOS release ready.

## Product Boundary

- App name: `FoxDesk`
- Bundle identifier: `net.foxdesk.ios`
- Backend: `https://app.foxdesk.net/api/mobile/v1`
- Staging backend: `https://staging.app.foxdesk.net/api/mobile/v1`
- Minimum iOS target: iOS 17
- No billing or upgrade flow inside iOS.
- No Stripe Checkout, pricing page, Customer Portal, or in-app purchase CTA in
  the MVP.
- Users sign in with their existing FoxDesk Cloud workspace account.

## Internal TestFlight checklist

1. Run local gate:

   ```bash
   npm run ios:mvp:audit
   npm run ios:beta:gate
   ```

   The MVP audit is the fast local scope/API/preflight check and writes
   `tmp/ios-mvp-local-audit/latest.md`. The beta gate runs the local MVP gate,
   TestFlight preflight, safe mobile API smoke, and APNs dry-run smoke. It also
   prints the human gates that still need App Store Connect or a physical
   iPhone. Each beta run writes a handoff-friendly evidence report to
   `tmp/ios-beta-readiness/latest.md`. The report includes an automatic human-gate status.
   It covers the App Store Connect record, App Review notes template, live
   smoke credentials, opt-in write smoke, physical-device APNs token, populated
   screenshots, and privacy review.

   If you need to run the pieces individually:

   ```bash
   npm run ios:mvp:audit
   npm run ios:gate
   npm run ios:production:check
   npm run ios:release:check
   npm run ios:staging:check
   npm run ios:sim:smoke
   npm run ios:screenshots
   npm run ios:archive:preflight
   npm run ios:external:gates
   npm run ios:testflight:preflight
   npm run ios:api:smoke -- --json
   npm run ios:apns:smoke -- --json
   ```

   Before uploading a build or handing it to App Review, run the strict
   submission gate. Unlike `ios:beta:gate`, this fails when human gates are
   still missing:

   ```bash
   npm run ios:release:init
   npm run ios:release:env
   npm run ios:submission:gate
   ```

   The strict gate also requires demo reviewer credentials in the ignored local
   `.env.ios-release` file and at least 8 populated screenshots plus
   `manifest.md` in `tmp/ios-app-store-screenshots`. Paste real demo
   credentials into App Store Connect review notes, not into committed docs.
   Generate the local screenshot set with `npm run ios:screenshots`, then
   review the images before uploading them. To inspect only the external gate
   state without rerunning builds, run `npm run ios:external:gates`; it writes
   `tmp/ios-external-gates/latest.md`.

   Live-service gates are evidence-based, not env-presence based. The external
   gate report marks demo/API/APNs work as ready only after these redacted
   evidence files exist and pass:

   - `tmp/ios-demo-account-check/latest-live-demo-account.json`
   - `tmp/ios-api-smoke/latest-live-read-only.json`
   - `tmp/ios-api-smoke/latest-live-write.json`
   - `tmp/ios-apns-smoke/latest-send.json`

   For a short operator-facing checklist of the remaining actions, run:

   ```bash
   npm run ios:next
   ```

   It writes `tmp/ios-next-actions/latest.md` and links each missing gate to the
   exact runbook, command, or environment variable needed next.

   For a complete handoff packet before switching Macs, handing work to another
   agent, or starting upload preparation, run:

   ```bash
   npm run ios:release:packet
   ```

   It writes `tmp/ios-release-packet/latest.md` with evidence paths, the strict
   submission gate, the Production archive command, and the human gates still
   outside automation.

   `ios:api:smoke` is read-only by default. Without credentials it performs a
   safe preflight and prints the required environment variables. To run the
   live mobile API smoke:

   ```bash
   npm run ios:release:env
   npm run ios:api:smoke -- --require-credentials --json
   ```

   Put `FOXDESK_IOS_SMOKE_EMAIL`, `FOXDESK_IOS_SMOKE_PASSWORD`, and optional
   `FOXDESK_IOS_SMOKE_2FA_CODE` in the ignored `.env.ios-release` file.

   Before TestFlight handoff, run the opt-in write smoke once against a staging
   or disposable production workspace. It creates one internal smoke ticket,
   adds a timed internal comment, uploads one small attachment, verifies the
   created ticket detail, and uses `skip_notification: true`:
   set `FOXDESK_IOS_SMOKE_WRITE=1` in `.env.ios-release` before this run.

   ```bash
   npm run ios:release:env
   npm run ios:api:smoke -- --require-credentials --json
   ```

   If the smoke account needs a specific client/status/priority/assignee, set
   `FOXDESK_IOS_SMOKE_CLIENT_ID`, `FOXDESK_IOS_SMOKE_STATUS_ID`,
   `FOXDESK_IOS_SMOKE_PRIORITY_ID`, or `FOXDESK_IOS_SMOKE_ASSIGNEE_ID`.

   Verify the App Review demo account separately. This check signs in through
   the same mobile API and confirms the workspace is not empty:

   ```bash
   npm run ios:release:env
   npm run ios:demo:check -- --require-credentials --json
   ```

   Put `FOXDESK_IOS_DEMO_EMAIL`, `FOXDESK_IOS_DEMO_PASSWORD`, and optional
   `FOXDESK_IOS_DEMO_2FA_CODE` in the ignored `.env.ios-release` file. The
   check requires at least one open ticket, one waiting ticket, one done ticket,
   and one ticket with comments and an attachment.

2. Confirm App Store Connect:
   - App record exists for `net.foxdesk.ios`.
   - SKU and app name are assigned.
   - Primary language is selected.
   - Privacy Policy URL points to FoxDesk Cloud legal page.
   - Support URL points to FoxDesk support/contact page.

3. Confirm Apple Developer account:
   - Follow `docs/IOS_APPLE_DEVELOPER_STEPS.md`.
   - Bundle ID `net.foxdesk.ios` exists.
   - Push Notifications capability is enabled.
   - Automatic signing is available for the selected team.
   - Production archive configuration can use production APNs entitlement.
   - Release compatibility build can still use production APNs entitlement.

4. Confirm backend production config:
   - `APNS_TEAM_ID`
   - `APNS_KEY_ID`
   - `APNS_AUTH_KEY` or `APNS_AUTH_KEY_PATH`
   - `APNS_BUNDLE_ID=net.foxdesk.ios`
   - `app.foxdesk.net/api/mobile/v1/me` works with mobile Bearer auth.
   - `app.foxdesk.net/api/mobile/v1/device-token` accepts device registration.

5. Confirm Demo reviewer account:
   - Follow `docs/IOS_DEMO_REVIEWER_ACCOUNT.md`.
   - Existing FoxDesk Cloud workspace user.
   - Role: agent or admin.
   - Has at least one open ticket, one waiting ticket, one done ticket.
   - Has a ticket with comments and attachments.
   - Has permission to add public replies, internal notes, time entries, and
     attachments.
   - If 2FA is enabled, provide a stable backup code in App Review notes.
   - `npm run ios:demo:check -- --require-credentials --json` passes with the
     demo account credentials.
   - Do not treat the account as ready until the command above passes against
     the same credentials that will be pasted into App Store Connect review
     notes.

6. Confirm internal smoke on simulator:
   - App launches to sign-in screen.
   - Sign-in works with demo account.
   - `ios:api:smoke` passes against the same backend and demo account.
   - Dashboard loads active timers, work queues, worked time, and recent work.
   - Dashboard can create a ticket and route into the newly-created ticket detail.
   - Dashboard can show a labeled saved copy if the network is unavailable,
     then refresh when connectivity returns.
   - Tickets tab loads Mine/New/Waiting/Done/All.
   - Ticket detail loads comments, attachments, actions, and timer state.
   - A previously opened ticket can show a labeled saved copy if the network is
     unavailable, then refresh when connectivity returns.
   - Public reply works.
   - Internal note works.
   - Comment with time works.
   - Image attachments show thumbnails in ticket detail.
   - Attachment preview/download works.
   - Search finds tickets and clients.
   - Account shows workspace state, support, legal, deletion request, logout.

7. Confirm real-device smoke:
   - Production/TestFlight build installs on a physical iPhone.
   - Camera permission prompt appears when taking a ticket photo.
   - Photo upload reaches the web app.
   - File attachment from Files reaches the web app.
   - If a photo/file upload fails, the ticket detail keeps the failed item and
     shows `Retry upload` without requiring a new picker selection.
   - In an internal debug/staging build, open Account → Push diagnostics,
     confirm the API row points to the backend being tested, and tap `Copy APNs token`
     after enabling notifications. Use that token for the live APNs smoke
     command below. Production/App Store builds must not show the diagnostics
     section.
   - APNs dry-run passes:

     ```bash
     npm run ios:apns:smoke -- --json
     ```

   - APNs live send reaches the physical iPhone:

     ```bash
     npm run ios:release:env
     npm run ios:apns:smoke -- --send --environment=production
     ```

   - APNs dry-run validates every first-release ticket push payload type:
     `new_ticket`, `new_comment`, `assigned_to_you`, `mentioned`,
     `ticket_updated`, `status_changed`, `priority_changed`, and
     `due_date_reminder`.
   - APNs live send sends one selected notification type to the physical iPhone.
     Do not send every type during the live smoke; the dry-run proves payload
     shape for the event set, while the physical iPhone proves Apple delivery.
   - Tapping the push notification opens the matching ticket.

8. Archive and upload:
   - Run the archive preflight:

     ```bash
     npm run ios:archive:preflight
     ```

     If another person will perform the upload, also run:

     ```bash
     npm run ios:release:packet
     ```

     It writes `tmp/ios-archive-preflight/latest.md` and verifies the generated
     project, `FoxDesk` scheme, `Production` archive configuration, bundle id
     `net.foxdesk.ios`, AppIcon, PrivacyInfo, production API base, and
     production APNs entitlement before anyone uploads a build.

   - After Apple Developer signing and App Store Connect setup are ready,
     archive the Production build:

     ```bash
     cd ios/FoxDesk
     xcodebuild \
       -project FoxDesk.xcodeproj \
       -scheme FoxDesk \
      -configuration Production \
       -destination 'generic/platform=iOS' \
       -archivePath ../../tmp/ios-archive/FoxDesk.xcarchive \
       archive
     ```

   - Validate the archive in Xcode Organizer.
   - Distribute the archive to App Store Connect.
   - Add the uploaded build to the Internal Testing group.

## App Review Notes Draft

FoxDesk is a support-ticket companion app for existing FoxDesk Cloud workspaces.
The iOS app lets signed-in agents and workspace admins view their work, open
ticket queues, reply to tickets, add internal notes, log time, upload
attachments, preview files, search, and receive push notifications.

Billing, workspace subscription management, platform administration, public
pricing, and setup flows are handled on the web and are intentionally not
included in the first iOS release.

Demo account:

- URL: `https://app.foxdesk.net`
- Email: `[fill before submission]`
- Password: `[fill before submission]`
- 2FA/backup code: `[fill if enabled]`

## Release Gates

A build is ready for internal TestFlight only when:

- `npm run ios:gate` passes.
- `npm run ios:production:check` passes.
- `npm run ios:release:check` passes.
- `npm run ios:archive:preflight` passes and writes
  `tmp/ios-archive-preflight/latest.md`.
- `npm run ios:sim:smoke` passes and writes
  `tmp/ios-smoke/foxdesk-login.png`.
- `npm run ios:testflight:preflight` passes.
- `npm run ios:apns:smoke -- --json` passes.
- Simulator smoke is captured.
- Real-device APNs smoke is captured.
- Demo reviewer account works.
- App Store Connect privacy answers are reviewed by a human using
  `docs/IOS_APP_PRIVACY_ANSWERS.md`.
- No billing or upgrade flow is visible inside iOS.
