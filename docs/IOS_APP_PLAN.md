# FoxDesk iOS App Plan

## Product Goal

Build a native SwiftUI iPhone app for FoxDesk Cloud agents and workspace admins.
The app is a fast agent/admin work companion for existing SaaS workspaces.
It is not a copy of the full PHP web admin.

The first release must help agents work on tickets from a phone:

1. Sign in to `app.foxdesk.net`.
2. See the work dashboard with active timers, queues, worked time, and recent
   ticket updates.
3. Open personal and team ticket queues.
4. Open ticket detail.
5. Manage ticket status, priority, and assignee when the signed-in user has
   permission.
6. Add public replies and internal notes.
7. Add time directly with a comment.
8. Upload photos and files.
9. Receive push notifications.
10. Search globally.
11. Open basic client context.

Billing, reporting, platform administration, self-hosted setup, and full
workspace settings stay on the web for the first release.

Explicitly out of scope:

- Stripe Checkout, pricing, upgrades, Customer Portal, subscriptions, and
  in-app purchase.
- SaaS platform administration.
- Full workspace settings.
- Self-hosted server setup.

## Architecture Contract

- UI: SwiftUI, native navigation, no web wrapper.
- Networking: `URLSession` with async/await.
- API: versioned `/api/mobile/v1/...` JSON endpoints.
- Auth: mobile access token plus refresh token stored in iOS Keychain with a
  FoxDesk service/account namespace and device-only accessibility.
- State: small SwiftUI views with explicit session/API dependencies.
- Uploads: mobile API attachment upload, no direct R2 key exposure.
- Push: APNs registered through the FoxDesk mobile API.
- Cache: lightweight local caches and reply drafts for fast/offline fallback.

The app must not use browser session cookies, scrape web pages, expose Stripe
Checkout, or surface provider internals such as Cloudflare, R2, SMTP, or IMAP.

## Milestones

### 1. Mobile API Contract

Done when these routes exist through `/api/mobile/v1/...` and are covered by
contracts:

- `POST /login`
- `GET /me`
- `GET /work` including compact recent notification items for Dashboard updates
- `GET /tickets`
- `GET /tickets/{id}`
- `POST /tickets/{id}/comments`
- `POST /tickets/{id}/comment-with-time`
- `POST /attachments`
- `GET /search`
- `POST /device-token`

Evidence:

- `docs/NATIVE_APP_API.md`
- `tests/mobile-api-contract-test.php`
- `tests/mobile-api-v1-routing-contract-test.php`
- `npm run ios:gate`

### 2. Native iOS Project

Done when the repo contains:

- `ios/FoxDesk`
- app target `FoxDesk`
- framework `FoxDeskKit`
- app tests `FoxDeskTests`
- kit tests `FoxDeskKitTests`
- Debug, Staging, and Release configurations
- bundle id `net.foxdesk.ios`
- Keychain token storage with `kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly`
- login screen connected to the SaaS mobile API

Evidence:

- `ios/FoxDesk/project.yml`
- `ios/FoxDesk/FoxDesk/Sources/LoginView.swift`
- `ios/FoxDesk/FoxDeskKit/Sources/Security/KeychainTokenStore.swift`
- `npm run ios:release:check`
- `npm run ios:staging:check`

### 3. Agent Work Flow

Done when an agent can complete this path from iPhone:

1. Sign in.
2. Open Dashboard and review recent ticket updates.
3. Open or create a ticket.
4. Update status, priority, or assignee if the account has permission.
5. Reply or add an internal note.
6. Add a time entry tied to the comment.
7. Reopen the ticket in the web app and see the same ticket fields,
   comment, and time record.

Evidence:

- `DashboardView`
- `TicketsView`
- `TicketDetailView`
- `TicketComposerView`
- `NewTicketView`
- `FoxDeskAPIClientTests`
- `FOXDESK_IOS_SMOKE_WRITE=1 npm run ios:api:smoke -- --require-credentials --json`

### 4. Photos, Attachments, And Rich Text

Done when the app can:

- take a photo,
- pick a photo,
- pick a file,
- upload attachments to a ticket,
- show image thumbnails,
- open authorized previews/downloads,
- preserve basic paragraph/list/bold/italic formatting.

Evidence:

- `CameraCaptureView`
- `TicketAttachmentsView`
- `AttachmentPreviewView`
- `MobileRichTextFormatter`
- `FoxDeskAPIClientTests`
- real-device smoke from the TestFlight runbook

### 5. Push Notifications

Done when a physical iPhone can:

- register its APNs token,
- receive a new-ticket or ticket-update push,
- open the matching ticket after tapping the notification,
- unregister the device on logout.

Evidence:

- `PushRegistrationService`
- `PushNavigationRouter`
- `PushRegistrationServiceTests`
- `PushNavigationRouterTests`
- `npm run ios:apns:smoke -- --json`
- `APNS_TEST_DEVICE_TOKEN=<token> npm run ios:apns:smoke -- --send --environment=production`

The dry-run must pass first. It validates every first-release ticket push
payload type without sending a notification; the physical iPhone smoke proves
Apple delivery and tap-to-ticket routing.

### 6. Offline And Speed

Done when:

- Dashboard can show a labeled cached copy.
- Ticket lists can show labeled cached results.
- Ticket detail can show a labeled cached detail.
- Reply drafts survive leaving a ticket.
- Failed attachment uploads can be retried without creating duplicates.

Evidence:

- `HomeFeedCacheStore`
- `TicketListCacheStore`
- `TicketDetailCacheStore`
- `TicketCommentDraftStore`
- `StagedAttachmentUploadState`
- `FoxDeskAPIClientTests`

## Current Release Gate

## Current Status On 2026-07-06

The native iOS MVP is no longer only a plan. The repository currently contains
a working SwiftUI app and the matching mobile API contract for the agent/admin
work-companion scope:

- Sign-in, token refresh, Keychain storage, and logout are implemented.
- Dashboard/Work, ticket queues, ticket detail, search, notifications, client
  context, and lightweight settings exist as native SwiftUI screens.
- New-ticket creation, public replies, internal notes, comment-with-time, timer
  controls, attachment upload, image previews, and cached fallbacks are
  implemented.
- Comment-linked time entries now round-trip through the mobile API as
  `comment_id` and render inline under their related comment in the native
  ticket activity, avoiding duplicated standalone time rows.
- APNs registration and deep-link routing are implemented locally; production
  APNs still needs a physical-device smoke test with Apple credentials.
- Local verification on 2026-07-06:
  - `./bin/run-php.sh tests/mobile-api-contract-test.php`
  - `./bin/run-php.sh tests/native-app-api-freeze-contract-test.php`
  - Xcode simulator test: 47/47 passed
  - `npm run ios:mvp:audit`

The remaining work is launch validation and Apple-side execution, not building
the first MVP from scratch.

## Next Execution Order

1. **Live workspace smoke**
   - Use a staging or disposable production agent/admin account.
   - Run read-only smoke first.
   - Run write smoke only with `FOXDESK_IOS_SMOKE_WRITE=1`; it must create one
     internal smoke ticket, add a timed internal comment, upload one attachment,
     download the uploaded attachment through the authorized URL, reload detail,
     and suppress notifications.

2. **Physical iPhone APNs smoke**
   - Install a debug/staging build on a real iPhone.
   - Copy the APNs token from Account -> Push diagnostics.
   - Send one test push and verify tapping it opens the correct ticket.

3. **Screenshot and App Store packet**
   - Regenerate populated screenshots.
   - Review them manually before upload.
   - Fill demo reviewer account details.
   - Review App Store privacy answers.

4. **TestFlight build**
   - Run the strict submission gate with all human/live environment variables.
   - Archive and upload only after the strict gate passes.

Local readiness:

```bash
npm run ios:mvp:audit
npm run ios:beta:gate
```

`ios:mvp:audit` is the fast local scope/API/preflight audit. It writes
`tmp/ios-mvp-local-audit/latest.md` and is useful before continuing iOS work on
another machine. `ios:beta:gate` remains the fuller build/simulator evidence
gate.

Strict submission readiness:

```bash
npm run ios:release:init
npm run ios:release:env
npm run ios:submission:gate
```

The strict gate is expected to fail until App Store Connect, demo account,
live mobile smoke, APNs hardware smoke, populated screenshot review, and
privacy review are complete.

## Source Documents

- `docs/IOS_APP_LAUNCH_PLAN.md`: detailed implementation and release plan.
- `docs/IOS_MVP_TRACEABILITY.md`: requirement-to-code evidence.
- `docs/IOS_TESTFLIGHT_RUNBOOK.md`: TestFlight execution checklist.
- `docs/IOS_HANDOFF.md`: current state and handoff instructions.
- `docs/IOS_OPERATOR_CHECKLIST.md`: external operator steps before TestFlight.
- `docs/IOS_APP_STORE_SUBMISSION.md`: App Store submission packet.
- `docs/NATIVE_APP_API.md`: mobile API reference.
