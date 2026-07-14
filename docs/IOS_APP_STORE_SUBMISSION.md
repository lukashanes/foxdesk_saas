# FoxDesk iOS App Store Submission Packet

## Purpose

This packet is the human handoff for App Store Connect and Apple App Review.
It keeps the first native iOS release scoped to the FoxDesk Cloud agent/admin
work companion and prevents billing, platform, or self-hosted setup surfaces
from leaking into the app.

## App Identity

- App name: `FoxDesk`
- Bundle identifier: `net.foxdesk.ios`
- SKU: `foxdesk-ios`
- Primary language: English
- Category: Business
- Minimum iOS version: iOS 17
- Backend: `https://app.foxdesk.net/api/mobile/v1`
- Privacy Policy URL: `https://foxdesk.net/index.php?page=legal&type=privacy`
- Support URL: `https://foxdesk.net/#support`

## Apple Account Status

- Organization: `Aenze s.r.o.`
- Apple Business verification: done, reported by the operator and re-confirmed
  from Apple Business email on 2026-07-07.

This confirms the organization for Apple Business features. It does not by
itself create the App Store Connect app record or enable Developer Program
signing capabilities. Before TestFlight upload, confirm that the Apple Developer
team owns bundle ID `net.foxdesk.ios`, Push Notifications are enabled for it,
the App Store Connect record exists, and the final gate has
`APPLE_DEVELOPER_BUNDLE_READY=1`.

## App Store Short Description

FoxDesk is a native companion app for FoxDesk Cloud agents and workspace admins.
Use it to view assigned support work, open tickets, reply to customers, add
internal notes, log time, upload attachments, search, and receive push
notifications.

Full App Store Connect copy is maintained in
`docs/IOS_APP_STORE_CONNECT_METADATA.md`. Use that file for subtitle,
promotional text, description, keywords, review notes, privacy summary, and the
final scope guard before pasting into App Store Connect.

## Review Notes

FoxDesk is for existing FoxDesk Cloud workspace users. Reviewers should sign in
with the demo workspace account below. The app does not sell subscriptions,
does not include in-app purchases, does not show pricing, and does not open
Stripe Checkout. Billing and workspace subscription management are handled on
the web outside the iOS app.

Demo reviewer account:

- URL: `https://app.foxdesk.net`
- Email: `[fill before submission]`
- Password: `[fill before submission]`
- 2FA or backup code: `[fill if enabled]`

The demo account must have:

- At least one open ticket.
- At least one waiting ticket.
- At least one done ticket.
- A ticket with comments and an attachment.
- A ticket linked to a client whose context opens with related tickets or
  contacts.
- Permission to add public replies, internal notes, time entries, and
  attachments.

Before submission, verify the demo account with:

```bash
npm run ios:release:env
npm run ios:demo:check -- --require-credentials --json
FOXDESK_IOS_DEMO_WRITE=1 npm run ios:demo:check -- --require-credentials --json
```

Put `FOXDESK_IOS_DEMO_EMAIL` and `FOXDESK_IOS_DEMO_PASSWORD` in the ignored
local `.env.ios-release` file. If 2FA is enabled, also set
`FOXDESK_IOS_DEMO_2FA_CODE`. Set `FOXDESK_IOS_DEMO_WRITE=1` only for the final
safe write proof; it creates one internal demo ticket, adds a linked internal
timed comment, reloads the ticket detail, and keeps notifications suppressed.

Detailed setup is in `docs/IOS_DEMO_REVIEWER_ACCOUNT.md`.

## Privacy Answers

The iOS app is a signed-in business support tool. It does not track users across
apps or websites and does not use data for third-party advertising.

Data linked to the user and used for app functionality:

- Name
- Email address
- User ID
- Customer support content such as tickets, comments, and work logs
- Photos or files uploaded by the user as ticket attachments
- Device token for push notification delivery

Data not collected for tracking:

- No advertising identifier
- No third-party tracking domains
- No sale of personal data

The detailed App Store privacy answer sheet is in
`docs/IOS_APP_PRIVACY_ANSWERS.md`. Review that document against the current App
Store Connect form before setting `APP_STORE_PRIVACY_REVIEWED=1`.

The first iOS release must also be configured as a free download, have explicit
country or region availability, have Content Rights answered for
customer-supplied ticket content, and have no blocking agreement, tax, or
banking action. Disable Apple Silicon Mac and Apple Vision Pro availability
until those platforms are intentionally tested and supported.

## Capabilities

- Sign in to an existing FoxDesk Cloud workspace.
- View Dashboard/Work overview.
- View ticket queues.
- Open ticket detail.
- Create tickets.
- Add public replies.
- Add internal notes.
- Add time to a comment.
- Start/pause/resume/stop ticket timers.
- Upload camera photos and files.
- Preview/download authorized attachments.
- Search tickets and clients.
- View client context.
- Receive APNs ticket notifications.
- Request account deletion through the support/legal link.

## Explicitly Out Of Scope

These must not be visible in the first iOS release:

- Stripe Checkout
- Customer Portal
- public pricing
- subscription upgrade or cancellation UI
- platform admin
- tenant lifecycle tools
- self-hosted install/update/migration setup
- SMTP/IMAP or server configuration

## Screenshot Checklist

Capture screenshots from a populated workspace:

- Sign in
- Dashboard/Work
- Tickets list
- Ticket detail with comments
- Ticket reply composer
- Attachment preview
- Search
- Client context
- Notifications
- Account

Avoid screenshots with internal provider details, tokens, private customer
data, or billing/admin-only surfaces.

Generate local screenshot evidence with:

```bash
npm run ios:screenshots
```

The command builds a Debug-only populated screenshot fixture, launches each
required screen in the iOS simulator, and writes screenshots plus
`manifest.md` to `tmp/ios-app-store-screenshots`. Review the images before
uploading them to App Store Connect; the fixture is intentionally excluded from
Production/App Store builds.

The manifest records the marketing version, build number, and a fingerprint of
the iOS source tree. `npm run ios:assets:check` fails if screenshots no longer
match the current source or privacy manifest.

## Pre-Submission Gates

Run these before archive upload in this order:

Prepare local gate variables from the committed template:

```bash
npm run ios:release:init
# fill real App Review, smoke, and APNs values locally
```

The initializer uses `.env.ios-release.example` as the committed template and
preserves an existing local `.env.ios-release`.

Never commit `.env.ios-release`. The iOS gate scripts auto-load it when it
exists. To use a different local path, set
`FOXDESK_IOS_RELEASE_ENV_FILE=/path/to/file`.

Check the local env status without printing secrets:

```bash
npm run ios:release:env
```

```bash
npm run ios:mvp:audit
npm run ios:gate
npm run ios:production:check
npm run ios:release:check
npm run ios:beta:gate
npm run ios:screenshots
npm run ios:external:gates
npm run ios:sim:smoke
npm run ios:archive:preflight
npm run ios:testflight:preflight
npm run ios:demo:check -- --require-credentials --json
npm run ios:api:smoke -- --require-credentials --json
FOXDESK_IOS_SMOKE_WRITE=1 npm run ios:api:smoke -- --require-credentials --json
npm run ios:apns:smoke -- --json
npm run ios:apns:smoke -- --send --environment=production
```

Set `FOXDESK_IOS_SMOKE_WRITE=1` and `APNS_TEST_DEVICE_TOKEN` in the ignored
local `.env.ios-release` file for the write and physical-device APNs smokes.

After the App Store Connect record, demo account, live smoke credentials, write
smoke, physical-device APNs token, screenshots, and privacy review are all
ready, run the strict final gate:

```bash
npm run ios:release:env
npm run ios:submission:gate
```

`npm run ios:mvp:audit` is the fast local scope/API/preflight audit and writes
`tmp/ios-mvp-local-audit/latest.md`. `npm run ios:external:gates` writes
`tmp/ios-external-gates/latest.md` with the human/operator and live-service gate
status. `npm run ios:submission:gate` is the strict final gate. It fails until
the App Store Connect record, demo account, live mobile API credentials, write
smoke, Apple Developer bundle/push confirmation, physical-device APNs token,
populated screenshots, and human privacy review are all present.

Human evidence required:

- Demo reviewer account tested.
- Physical iPhone APNs smoke captured.
- Photo upload reaches the web app.
- File upload reaches the web app.
- App Store privacy answers reviewed by a human.
- Production/App Store build does not show Push diagnostics.
- No billing or upgrade flow is visible inside iOS.
