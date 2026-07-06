# FoxDesk iOS App Launch Plan

## Goal

Ship a native SwiftUI iPhone app for FoxDesk Cloud first. The app should feel
like a fast companion for daily support work, not a wrapper around the PHP web
interface.

The current SaaS source of truth is the hosted FoxDesk Cloud stack:
`app.foxdesk.net` for workspaces, Cloudflare Email/R2 for inbound replies and
attachments, and web-only Stripe billing. The iOS app must reflect that state
without becoming a purchase surface.

## MVP Scope

First release is an agent/admin work companion for existing FoxDesk Cloud
workspaces. It is not a platform admin, billing, or public customer portal app.
The requirement-to-code evidence is maintained in `docs/IOS_MVP_TRACEABILITY.md`.

1. Sign in to a FoxDesk Cloud workspace.
2. Load `app-shell`, `app-home`, and `app-tenant-state`.
3. Show the Dashboard/Work overview, active timers, and actionable ticket
   queues.
4. Search and open tickets.
5. Create a ticket from Dashboard or the Tickets tab, then open the new ticket.
6. Reply publicly, add internal notes, and add comment-with-time records.
7. Start, pause, resume, stop, and discard ticket timers.
8. Upload photos/files, show image thumbnails, and open authorized attachment
   previews/downloads; never expose R2 keys.
9. Show client context for a ticket or selected client.
10. Push notifications for actionable ticket events.
11. Account/settings screen with workspace status, logout, privacy, support, and
    data/deletion request links.

Out of scope for first release:

- SaaS platform admin and tenant operations.
- Stripe Checkout, pricing, upgrades, Customer Portal, and in-app purchases.
- Full workspace settings.
- Report editing or billing review.
- Self-hosted server selection.

## Backend Milestones

1. `app-shell`: stable navigation, capabilities, queues, search sections, and
   reporting entrypoints.
2. `app-home`: compact first-screen data for native clients.
3. Mobile auth endpoint with rate limiting and optional two-factor support.
4. Ticket detail API shaped for native UI.
5. Comment/reply API with attachment and comment-with-time support.
6. Device token registration for push notifications.
7. App privacy and account deletion flows.
8. Tenant state endpoint for SaaS lifecycle, usage, and access messages.
9. Global search endpoint.
10. Mobile-safe upload endpoint using Bearer auth without browser CSRF.

## Current Implementation

- Mobile auth endpoints are available through `/api/mobile/v1/...`, with
  `index.php?page=api&action=...` kept as a compatibility alias:
  - `mobile-login`
  - `mobile-verify-2fa`
  - `mobile-refresh`
  - `mobile-me`
  - `mobile-logout`
  - `mobile-register-device`
  - `mobile-unregister-device`
- Native ticket endpoints are exposed through `/api/mobile/v1/...`; the legacy
  `index.php?page=api&action=...` names remain as compatibility aliases:
  - `app-ticket-list`
  - `app-ticket-detail`
  - `app-ticket-actions`
  - `app-ticket-create-options`
  - `app-create-ticket`
  - `app-add-comment`
  - `app-add-comment-with-time`
  - `app-ticket-timer`
  - `app-ticket-timer-action`
  - `app-attachment-metadata`
  - `app-client-overview`
  - `global-search`
  - `upload`
  - `app-tenant-state`
- Mobile sessions are stored separately from automation API tokens.
- iOS access tokens are short lived. Refresh tokens are rotated on refresh.
- The SwiftUI session restores stored tokens by retrying `/me` through
  `mobile-refresh` after a 401, stores rotated tokens, and all authenticated
  app API calls go through the same refresh-aware session gate.
- TOTP and backup codes are supported for mobile sign-in.
- APNs device tokens can be registered. Backend APNs dispatch is implemented
  and stays disabled until `APNS_TEAM_ID`, `APNS_KEY_ID`, `APNS_AUTH_KEY` or
  `APNS_AUTH_KEY_PATH`, and `APNS_BUNDLE_ID` are configured.
- Native SwiftUI project scaffold now lives in `ios/FoxDesk`:
  - app target: `FoxDesk`
  - shared framework: `FoxDeskKit`
  - tests: `FoxDeskKitTests`
  - bundle identifier: `net.foxdesk.ios`
  - APNs entitlement configured through `FoxDesk/FoxDesk.entitlements`
  - app icon asset catalog generated from the FoxDesk logo
  - current screens: login, 2FA, dashboard, tickets, ticket detail, search,
    notifications, and lightweight account/logout settings.
  - build configurations: Debug and Staging use development APNs; Release uses
    production APNs. `npm run ios:staging:check` builds the Staging
    configuration against `https://staging.app.foxdesk.net/index.php`.
- The current iOS client calls the versioned `/api/mobile/v1/...` routes for
  mobile auth, ticket list, ticket detail,
  ticket create options, ticket creation, global search, comment/comment-with-time, timer actions,
  attachment metadata, client overview, and multipart attachment upload
  endpoints. Ticket detail now has native timer controls, exact manual
  date/start/end time logging for comment-with-time records, camera/photo/file
  upload entry points, and the new-ticket form can stage camera/photo/file
  attachments before the ticket exists and upload them immediately after
  creation. Ticket detail also has image thumbnails in the attachment list, an in-memory retry action for the last failed attachment
  upload, per-ticket/per-user local reply drafts, cached home feeds, cached ticket
  lists, and per-ticket detail caches for a fast/offline fallback, mobile rich-text formatting for
  paragraphs, lists, bold/italic emphasis, and a client-context drill-in. The
  dashboard account/timer sections, worked-time chart, work queues, quick
  actions, ticket list, ticket detail, ticket management, timer controls,
  ticket activity, comment/time composer, and attachment/upload UI are split
  into separate SwiftUI files so dashboard and detail work can evolve
  without growing the list/detail screens back into monoliths. The
  worked-time chart renders the per-day agent breakdown from `app-home` as a
  stacked native chart with an agent legend, so admins can see who contributed
  hours in the selected period. Admin dashboards also render a compact team
  activity list from the same feed with running state, selected-period totals,
  latest work, and direct ticket drill-ins. The
  reusable new-ticket form is available from both Dashboard and Tickets; after
  creation the app opens the new ticket detail. Tickets can load additional
  pages from the native paginated list endpoint instead of trapping agents on
  the first page of results. Dashboard uses the native
  `app-home` feed for active timers, worked-time totals, a last-30-days time
  chart, recent work entries, unread notifications, and work queues. The shell
  reads `app-tenant-state`, shows workspace status in Settings, and blocks work
  tabs when server access is not allowed. The Notifications tab uses
  `app-notifications` and `app-notification-read-state` for the native inbox,
  ticket drill-ins, pull-to-refresh, and mark-read actions. Settings can request
  iOS notification permission and register the APNs token through
  `mobile-register-device`; sign-out unregisters the current device through
  `mobile-unregister-device` before clearing local session tokens. Notification
  taps now route into the Tickets tab and open the matching ticket when the APNs
  payload includes `ticket_id`, including cold-start taps that arrive before the
  SwiftUI router exists. Ticket detail opens authorized attachment
  previews/downloads in-app without exposing R2 keys. Backend APNs delivery is
  prepared; the remaining release gate is
  production APNs credentials plus real-device smoke testing. Internal
  debug/staging builds expose Settings → Push diagnostics so testers can verify
  the active API base URL and copy the physical-device APNs token for the smoke
  command; this diagnostic surface is excluded from Release UI.
- Production SaaS evidence currently verifies deployment evidence, R2 storage,
  Cloudflare outbound email, inbound email archive, and Stripe live meter
  configuration. Legal review and full Stripe live-flow acknowledgement remain
  human/operator gates before paid public launch.

## iOS Milestones

1. Mobile API readiness gate.
2. Create Xcode SwiftUI project: `ios/FoxDesk`. **Done.**
3. Bundle identifier: `net.foxdesk.ios`. **Done.**
4. Minimum target: iOS 17 unless customer devices require iOS 16. **Done.**
5. Build app architecture:
   - `AppSession`
   - `APIClient`
   - `SecureTokenStore` using Keychain
   - per-tab `NavigationStack` routing
   - `AppShellStore`
   - `HomeFeedStore`
   - `TicketStore`
   - `AttachmentUploadService`
   - `PushRegistrationService`
6. Build read-only screens:
   - Login
   - Dashboard/Work
   - Tickets
   - Ticket detail
   - Search
   - Client context
   - Workspace access state
   - Settings
7. Build write flows:
   - New ticket
   - Public reply
   - Internal note
   - Comment with time, including optional exact date/start/end work logging
   - Mobile rich text for paragraphs, lists, bold, and italic
   - Timer sheet
   - Camera capture
   - Attachment/photo upload
8. Push notification registration, backend dispatch, and routing. **Registration,
   local tap routing, and backend dispatch are implemented; real-device APNs
   smoke remains.**
9. TestFlight build.
10. External beta.
11. App Store review.

## Execution Plan From Current State

The iOS app already has the first-release technical skeleton, native API
surface, SwiftUI screens, screenshot fixture, and local gates. The remaining
work is therefore not "build the app from scratch"; it is to prove the
agent/admin workflow on real accounts, finish Apple-side setup, and keep the
scope tight.

### Milestone A — Local Agent/Admin MVP Evidence

Goal: prove the app can perform the daily agent workflow without web fallback.

Done when:

- `npm run ios:beta:gate` passes locally.
- `tmp/ios-beta-readiness/latest.md` shows all local gates passed.
- `npm run ios:screenshots` has produced a populated screenshot set and
  `tmp/ios-app-store-screenshots/manifest.md`.
- The generated screenshots show login, dashboard/work, tickets, ticket detail,
  reply/time entry, attachment, search, client, notifications, and settings.

Current state: implemented locally. Screenshots still need human review before
they are uploaded to App Store Connect.

### Milestone B — Live Workspace Smoke

Goal: prove the native app works against a real FoxDesk Cloud workspace.

Done when:

- `FOXDESK_IOS_SMOKE_EMAIL` and `FOXDESK_IOS_SMOKE_PASSWORD` are set for a
  staging or disposable production agent/admin user.
- Read-only smoke passes against `https://app.foxdesk.net/api/mobile/v1`.
- `FOXDESK_IOS_SMOKE_WRITE=1` has been run once on staging or a disposable
  workspace.
- The write smoke creates exactly one internal smoke ticket, adds a timed
  internal comment, uploads one attachment, reloads the ticket detail, and uses
  `skip_notification: true`.

This is the most important remaining non-Apple validation because it proves
that the API, permissions, tenant isolation, comments with time, attachments,
and native refresh/auth path all work together.

### Milestone C — Real iPhone APNs Smoke

Goal: prove notifications work on hardware, not only as a dry-run payload.

Done when:

- A debug/staging build is installed on a physical iPhone.
- Settings -> Push diagnostics shows the intended backend.
- The device token is copied from the app and supplied as
  `APNS_TEST_DEVICE_TOKEN`.
- `npm run ios:apns:smoke -- --send --environment=production` reaches the
  device.
- A tapped push notification opens the matching ticket in the native app.

### Milestone D — App Store Connect And Submission Packet

Goal: make the app uploadable and reviewable by Apple.

Done when:

- App Store Connect app record exists for `net.foxdesk.ios`.
- Demo reviewer account fields in `docs/IOS_APP_STORE_SUBMISSION.md` are filled.
- Demo reviewer account passes `npm run ios:demo:check -- --require-credentials --json`.
- App Store privacy answers are reviewed by a human/operator.
- Generated populated screenshots are reviewed and uploaded.
- `APP_STORE_CONNECT_APP_RECORD_READY=1`,
  `APPLE_DEVELOPER_BUNDLE_READY=1`, and `APP_STORE_PRIVACY_REVIEWED=1` are
  valid operator claims, not placeholders.

### Milestone E — Strict Submission Gate

Goal: prevent a false "ready" claim.

Done when:

```bash
npm run ios:release:init
npm run ios:release:env
npm run ios:submission:gate
```

passes without skipped live checks or missing human gates.

Only after this milestone should the project be considered ready for an
internal TestFlight handoff.

## Local Gate

Run the local iOS gate before TestFlight work or before handing the project to
another machine:

```bash
npm run ios:gate
npm run ios:release:check
npm run ios:sim:smoke
npm run ios:testflight:preflight
npm run ios:api:smoke -- --json
npm run ios:apns:smoke -- --json
```

When another agent or Mac continues the iOS work, use `docs/IOS_HANDOFF.md` as
the first file. It keeps the active product scope, gate commands, smoke
environment variables, and human blockers in one place so the work does not
drift into a web wrapper, billing app, or platform-admin app.

`npm run ios:beta:gate` also writes a handoff-friendly evidence report to
`tmp/ios-beta-readiness/latest.md` with the passed local gates, skipped live
checks, and remaining human gates. The report marks human gates as ready or
missing from local evidence: App Store Connect record flag, demo reviewer
account placeholders, live smoke credentials, write-smoke flag, APNs device
token, populated screenshot folder, and privacy-review flag.

Use `npm run ios:screenshots` to create the populated App Store screenshot set
in `tmp/ios-app-store-screenshots`. The command launches a Debug-only
`--foxdesk-screenshot-mode` fixture, captures the required agent/admin screens,
and writes a manifest for review. Release builds must not expose that fixture.

The gate validates the iOS privacy manifest, app icon asset catalog, generated
Xcode resources, Swift tests, and the frozen mobile API contracts. The Swift
tests include a mocked agent workflow smoke for create-ticket options, ticket
creation, paginated ticket list requests, ticket detail, comment-with-time,
attachment upload, and attachment metadata, plus local reply draft, cached
home-feed persistence, and cached ticket-list persistence, so the
native client cannot silently fall back to legacy web API routes or lose the
basic offline continuity expected from the MVP. Dashboard is cached per user so
the first screen can show a labeled saved copy while `app-home` refreshes or
when the device is offline. Ticket detail is also cached per user and ticket so
an opened ticket can show a labeled saved copy while the app refreshes or when
the device is offline. App-level `FoxDeskTests` cover
push notification ticket routing, including cold-start notification taps that
arrive before `PushNavigationRouter` is initialized, so a tapped notification
can be carried through launch/sign-in and open the matching ticket detail.
`ios:api:smoke` is read-only by default; without credentials it prints the
required environment variables, and with `FOXDESK_IOS_SMOKE_EMAIL` plus
`FOXDESK_IOS_SMOKE_PASSWORD` it verifies mobile login, `me`, `work`, tickets,
first ticket detail, search, and logout against the configured SaaS backend.
Before TestFlight handoff, set `FOXDESK_IOS_SMOKE_WRITE=1` once against a
staging or disposable production workspace to create a smoke ticket, add an
internal comment-with-time record, upload a small attachment, and reload the
ticket detail through `/api/mobile/v1`. Set `IOS_DESTINATION` when you want a
specific simulator, for example:

```bash
IOS_DESTINATION='platform=iOS Simulator,name=iPhone 16 Pro' npm run ios:gate
```

For physical-device APNs verification, first run the dry-run payload/config
check. In an internal debug/staging build, open Settings → Push diagnostics,
confirm that the API row points to the backend you intend to test, enable
notifications, and copy the device token. Then pass it to the live-send command:

```bash
npm run ios:apns:smoke -- --json
npm run ios:release:env
npm run ios:apns:smoke -- --send --environment=production
```

## App Store Preparation

- App name: `FoxDesk`.
- Bundle identifier: `net.foxdesk.ios`.
- Subtitle should be short and benefit-led.
- App icon asset catalog is included in the Xcode project.
- Privacy Policy URL required.
- Support URL required.
- Demo account required for App Review if login is required.
- App privacy answers must include account, support-ticket, contact, attachment,
  diagnostics, and payment/billing data that the app or server collects.
- The iOS app target includes `FoxDesk/PrivacyInfo.xcprivacy` with no tracking
  domains and app-functionality declarations for name, email address, user id,
  customer-support ticket content, and photos/videos used as attachments.
  App Store Connect privacy answers still need a human/operator review before
  TestFlight or App Review submission.
- If third-party/social login is added later, evaluate Sign in with Apple.
- If the app lets users create accounts, account deletion must be available from
  the app. For the first release, prefer login to existing FoxDesk Cloud
  workspaces and account/deletion request links in Settings.

## Payment And Compliance Strategy

Preferred first release: publish the iOS app as a free companion app for
existing FoxDesk Cloud workspaces. Do not sell, upgrade, show pricing, or link to
Stripe Checkout from inside the iOS app. Billing, plan changes, invoices, and
Stripe Customer Portal stay web-only.

The app may display read-only workspace status such as `Active`,
`Trial active`, `Included access`, `Past due grace`, or `Workspace suspended`.
For blocked access, show the server-provided access message and ask the user to
contact their workspace admin or FoxDesk support. Do not include a "Manage
billing", "Upgrade", "Start plan", "Restart plan", or external checkout link in
the iOS UI for the first release.

This keeps the iOS app focused on support work and avoids App Review risk around
external purchase calls to action. Existing customers can sign in and use the
service they already purchased on the web.

If FoxDesk later sells subscriptions inside the iOS app, use Apple's In-App
Purchase system with StoreKit 2 and auto-renewable subscriptions. In that mode,
the app must not use Stripe for in-app digital subscription purchases.

Apple billing setup:

1. Sign in to App Store Connect as the Account Holder.
2. Open Business.
3. On the Agreements tab, sign the Paid Apps Agreement.
4. Add required tax forms.
5. Add banking information.
6. Create the app record for `net.foxdesk.ios`.
7. Under Monetization, create a Subscription Group and subscription products.
8. Implement StoreKit 2 purchase, restore purchases, receipt/transaction
   validation, and App Store Server Notifications on the FoxDesk backend.
9. Map Apple subscription entitlement state to the tenant billing state.
10. Submit the subscription products with the app build for review.

## Decisions Needed

- Apple Team ID.
- Whether first beta supports only `app.foxdesk.net` or also self-hosted
  instances. Recommended: `app.foxdesk.net` only for App Store release.
- Whether first login uses email/password, magic link, or admin-created mobile
  token. Recommended first release: email/password with TOTP support and
  server-issued mobile tokens. Magic link can be added later.
- Whether Stripe subscription management opens the web billing portal from the
  app or remains web-only for the first release. Recommended: web-only and no
  in-app purchase calls to action.

## Updated Native Screen Plan

### Login

- Target `https://app.foxdesk.net/index.php` for App Store builds.
- Support email/password, TOTP, backup codes, token refresh, and logout.
- Do not support self-hosted server selection in the first App Store build.

### Dashboard / Work

- Show worked-time summary, last-30-days chart, recent work, team activity for
  admins, active timers, and the most useful queues in one first screen.
- Team activity should use the same avatar identity model as account surfaces,
  with an initials fallback when no photo is available.
- Show compact ticket cards with code, status, client, requester, tags, and an
  Email pill when `source=email`.
- Refresh `app-home` and `app-tenant-state` together.
- Current scaffold renders exact worked-time totals, a compact time chart,
  recent work entries, active timers, work queues, unread notification count,
  and ticket drill-ins from `app-home`. Dashboard quick actions open the native
  Notifications inbox and show the unread count as a badge.

### Tickets And Search

- Use `app-ticket-list` for saved views and pagination.
- Keep the first iOS ticket tabs focused on agent work: Mine, New, Waiting, Done, and All. Mine uses `view=open&assigned_to=me`; New uses `view=new`.
- Use `global-search` for Spotlight-style search across open tickets, done
  tickets, clients, and contacts. Ticket results open `TicketDetailView`;
  client results and contact results with `organization_id` open
  `ClientContextView`.
- Keep per-tab navigation history: Dashboard, Tickets, Notifications, Settings.

### Notifications

- Use `app-notifications` for the native notification inbox and pagination
  when an agent needs older updates.
- Use `app-notification-read-state` for mark-read, mark-unread, ticket-level
  read state, and mark-all-read.
- Tapping a ticket notification opens native `TicketDetailView` without sending
  the user to the web app.

### Ticket Detail

- Keep public reply as the first write action.
- Include internal note, timer actions, assignment/status actions, comment with
  time, and attachment preview/download through the frozen `app-*` endpoints.
- Use `MobileRichTextFormatter` so iOS-created replies preserve paragraphs,
  bullet/numbered lists, and basic emphasis instead of flattening all text.
- Treat attachment URLs as opaque authorized backend URLs.
- Current scaffold links from ticket detail to `app-client-overview` for client
  stats, contacts, monthly time, and recent tickets.
- Current scaffold can capture a new photo from the iPhone camera, pick an
  existing image, or attach a file from Files.
- The new-ticket form can stage photos/files before save; after the ticket is
  created, the app uploads the staged attachments to the new ticket and retries
  failed uploads without creating a duplicate ticket.
- Current scaffold opens ticket attachments in-app: images render directly and
  other downloaded files use the native Quick Look preview.
- Current scaffold keeps local reply drafts per ticket and user, including
  internal-note and time-entry choices, and clears the draft after a successful
  send.

### Settings

- Show signed-in user, workspace name, access state, subscription state, and
  server notice copy.
- Keep legal/support/deletion links.
- No Stripe, pricing, checkout, portal, or upgrade links.
- Current scaffold keeps Settings available even when workspace work screens
  are blocked by `app-tenant-state`.
- Current scaffold also exposes a simple push-notification registration action
  backed by `mobile-register-device`.
- Current scaffold links to support, Privacy Policy, Terms, and account deletion
  request from Settings for App Store review readiness.

### Access Lock

- If `app-tenant-state.data.access.allowed` is false, replace Work/Inbox with a
  locked state.
- Offer only Refresh and Sign Out.
- Message copy should come from the SaaS backend and remain neutral for App
  Review.
