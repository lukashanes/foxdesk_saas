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

1. Sign in to a FoxDesk Cloud workspace.
2. Load `app-shell` and `app-home`.
3. Load `app-tenant-state` and respect workspace access.
4. Show Work queues and Inbox queues.
5. Open ticket detail.
6. Reply and view email-originated ticket context.
7. Show attachments through authorized backend URLs; never expose R2 keys.
8. Push notifications for actionable ticket events.
9. Account/settings screen with workspace status, logout, privacy, support, and
   data/deletion request links.

## Backend Milestones

1. `app-shell`: stable navigation, capabilities, queues, search sections, and
   reporting entrypoints.
2. `app-home`: compact first-screen data for native clients.
3. Mobile auth endpoint with rate limiting and optional two-factor support.
4. Ticket detail API shaped for native UI.
5. Comment/reply API with attachment support.
6. Device token registration for push notifications.
7. App privacy and account deletion flows.
8. Tenant state endpoint for SaaS lifecycle, usage, and access messages.

## Current Implementation

- Mobile auth endpoints are available through `index.php?page=api&action=...`:
  - `mobile-login`
  - `mobile-verify-2fa`
  - `mobile-refresh`
  - `mobile-me`
  - `mobile-logout`
  - `mobile-register-device`
  - `mobile-unregister-device`
- Native ticket endpoints are available through:
  - `app-ticket-detail`
  - `app-add-comment`
  - `app-tenant-state`
- Mobile sessions are stored separately from automation API tokens.
- iOS access tokens are short lived. Refresh tokens are rotated on refresh.
- TOTP and backup codes are supported for mobile sign-in.
- APNs device tokens can be registered, but APNs delivery is still a later
  milestone.
- Initial SwiftUI scaffold lives in `ios/FoxDesk` with login, Work, Inbox,
  Settings, ticket detail, and public reply flow.
- The iOS scaffold now reads `app-tenant-state`, shows workspace status in
  Settings, and blocks work screens when SaaS access is suspended, expired,
  canceled, or blocked.
- Production SaaS evidence currently verifies deployment evidence, R2 storage,
  Cloudflare outbound email, inbound email archive, and Stripe live meter
  configuration. Legal review and full Stripe live-flow acknowledgement remain
  human/operator gates before paid public launch.

## iOS Milestones

1. Create Xcode SwiftUI project: `FoxDesk`.
2. Bundle identifier: `net.foxdesk.ios`.
3. Minimum target: iOS 17 unless customer devices require iOS 16.
4. Build app architecture:
   - `AppSession`
   - `APIClient`
   - `SecureTokenStore` using Keychain
   - `AppShellStore`
   - `HomeFeedStore`
5. Screens:
   - Login
   - Work
   - Inbox
   - Ticket detail
   - Workspace access state
   - Timer sheet
   - Settings
6. TestFlight build.
7. External beta.
8. App Store review.

## App Store Preparation

- App name: `FoxDesk`.
- Bundle identifier: `net.foxdesk.ios`.
- Subtitle should be short and benefit-led.
- Privacy Policy URL required.
- Support URL required.
- Demo account required for App Review if login is required.
- App privacy answers must include account, support-ticket, contact, attachment,
  diagnostics, and payment/billing data that the app or server collects.
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
  token. Recommended: email/password with TOTP support and server-issued mobile
  tokens.
- Whether Stripe subscription management opens the web billing portal from the
  app or remains web-only for the first release. Recommended: web-only and no
  in-app purchase calls to action.

## Updated Native Screen Plan

### Login

- Target `https://app.foxdesk.net/index.php` for App Store builds.
- Support email/password, TOTP, backup codes, token refresh, and logout.
- Do not support self-hosted server selection in the first App Store build.

### Work And Inbox

- Preserve separate navigation stacks for Work and Inbox.
- Show compact ticket cards with code, status, client, requester, tags, and an
  Email pill when `source=email`.
- Refresh `app-home` and `app-tenant-state` together.

### Ticket Detail

- Keep public reply as the first write action.
- Add internal note, timer actions, assignment/status actions, and attachment
  preview/download in later beta slices using the frozen `app-*` endpoints.
- Treat attachment URLs as opaque authorized backend URLs.

### Settings

- Show signed-in user, workspace name, access state, subscription state, and
  server notice copy.
- Keep legal/support/deletion links.
- No Stripe, pricing, checkout, portal, or upgrade links.

### Access Lock

- If `app-tenant-state.data.access.allowed` is false, replace Work/Inbox with a
  locked state.
- Offer only Refresh and Sign Out.
- Message copy should come from the SaaS backend and remain neutral for App
  Review.
