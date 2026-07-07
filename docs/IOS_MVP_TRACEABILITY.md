# FoxDesk iOS MVP Traceability

This file maps the first native iOS release scope to code and verification
evidence. The app is an agent/admin work companion for existing FoxDesk Cloud
workspaces. It is not a web wrapper, platform console, billing surface, public
customer portal, or full workspace settings app.

## Requirement Matrix

| MVP requirement | Mobile API contract | SwiftUI surface | Verification |
| --- | --- | --- | --- |
| Sign in to `app.foxdesk.net` | `POST /api/mobile/v1/login`, `POST /api/mobile/v1/verify-2fa`, `POST /api/mobile/v1/refresh`, `GET /api/mobile/v1/me` | `LoginView`, `RootView`, `AppSession`, `KeychainTokenStore` with device-only Keychain accessibility | `FoxDeskAPIClientTests`, `tests/mobile-api-contract-test.php`, `npm run ios:gate` |
| Dashboard / work overview | `GET /api/mobile/v1/work`, `GET /api/mobile/v1/tenant-state` | `DashboardView`, `DashboardIdentitySectionsView`, `DashboardWorkedTimeView`, `DashboardWorkQueuesView`, `RecentUpdatesSection` | `tests/app-home-contract-test.php`, `tests/mobile-api-contract-test.php`, `npm run ios:gate` |
| Agent ticket queues | `GET /api/mobile/v1/tickets?view=...` | `TicketsView`, `TicketRow`, `WorkQueueSections` | `FoxDeskAPIClientTests`, `tests/mobile-api-v1-routing-contract-test.php` |
| New ticket from iPhone | `GET /api/mobile/v1/tickets/create-options`, `POST /api/mobile/v1/tickets` | `NewTicketView` from Dashboard and Tickets | `FoxDeskAPIClientTests`, `tests/mobile-api-contract-test.php` |
| Ticket detail | `GET /api/mobile/v1/tickets/{id}` | `TicketDetailView`, `TicketActivityView`, `TicketAttachmentsView`, `TicketTimerView` | `FoxDeskAPIClientTests`, `tests/mobile-api-contract-test.php` |
| Admin ticket management | `GET /api/mobile/v1/tickets/{id}/actions`, `POST /api/mobile/v1/tickets/{id}` | `TicketManageSheet` from `TicketDetailView` | `FoxDeskAPIClientTests`, `tests/mobile-api-contract-test.php`, `tests/ios-mvp-scope-contract-test.php` |
| Public reply / internal note | `POST /api/mobile/v1/tickets/{id}/comments` | `CommentComposerSection` | `FoxDeskAPIClientTests`, `tests/mobile-api-contract-test.php` |
| Comment with time | `POST /api/mobile/v1/tickets/{id}/comment-with-time` | `CommentComposerSection` exact/manual time controls | `FoxDeskAPIClientTests`, `tests/mobile-api-contract-test.php` |
| Basic reply formatting | comment payload HTML accepted by mobile ticket endpoints | `MobileRichTextFormatter` used by `CommentComposerSection` | `MobileRichTextFormatterTests`, `tests/mobile-api-contract-test.php`, `npm run ios:gate` |
| Timer controls | `GET /api/mobile/v1/tickets/{id}/timer`, `POST /api/mobile/v1/tickets/{id}/timer` | `TimerControlSection`, `ActiveTimersSection` | `FoxDeskAPIClientTests`, `tests/mobile-api-contract-test.php` |
| Photos, files, and previews | `POST /api/mobile/v1/attachments`, `GET /api/mobile/v1/attachments/{id}` | `AttachmentUploadSection`, `CameraCaptureView`, `AttachmentPreviewView` | `FoxDeskAPIClientTests`, `tests/mobile-api-contract-test.php` |
| Push notifications | `POST /api/mobile/v1/device-token`, `POST /api/mobile/v1/device-token/unregister`, APNs payload | `PushRegistrationService`, `NotificationsView`, `PushNavigationRouter` | `PushRegistrationServiceTests`, `PushNavigationRouterTests`, `tests/ios-mvp-endpoint-matrix-contract-test.php`, `bin/test-apns-push.php` |
| Global search | `GET /api/mobile/v1/search` | `SearchView` | `FoxDeskAPIClientTests`, `tests/global-search-test.php`, `tests/mobile-api-contract-test.php` |
| Client context | `GET /api/mobile/v1/clients/{id}` | `ClientContextView`, search/client drill-ins, ticket header drill-in | `FoxDeskAPIClientTests`, `tests/client-overview-contract-test.php`, `tests/mobile-api-contract-test.php` |
| Offline and speed fallback | cached API payloads and local-only draft state | `DashboardView`, `TicketsView`, `TicketDetailView`, `TicketComposerView`, `NewTicketView`, `TicketAttachmentsView` using `HomeFeedCacheStore`, `TicketListCacheStore`, `TicketDetailCacheStore`, `TicketCommentDraftStore`, and `StagedAttachmentUploadState` | `FoxDeskAPIClientTests`, `tests/mobile-api-contract-test.php`, `npm run ios:mvp:audit` |
| Lightweight account/logout | `GET /api/mobile/v1/me`, `POST /api/mobile/v1/logout`, account links | `AccountView` | `tests/ios-mvp-scope-contract-test.php`, `npm run ios:gate` |

## Explicitly Out Of Scope

- Stripe Checkout, pricing, upgrades, Customer Portal, subscriptions, or in-app
  purchase.
- SaaS platform administration, tenant lifecycle, billing review, and public
  reports.
- Full workspace settings.
- Self-hosted server setup, SMTP, IMAP, Cloudflare, or R2 provider controls.

The iOS scope gate checks visible Swift strings so these concepts do not leak
into the first-release agent/admin work app.

## Release Evidence

Before TestFlight or App Store submission:

1. Run `npm run ios:mvp:audit`.
2. Confirm `tmp/ios-mvp-local-audit/latest.md` lists local MVP scope, API,
   preflight, and screenshot evidence.
3. Confirm `tests/ios-mvp-endpoint-matrix-contract-test.php` passes as part of
   `npm run ios:gate`; this locks router paths, native API docs, and the Swift
   client into the same first-release endpoint matrix.
4. Confirm `MobileRichTextFormatterTests` passes as part of `npm run ios:gate`;
   this protects mobile replies from collapsing paragraphs, lists, and basic
   inline formatting into plain text.
5. Run `npm run ios:beta:gate`.
6. Confirm `tmp/ios-beta-readiness/latest.md` lists local gates as passed.
7. Run `npm run ios:submission:gate` only after the human/live gates in
   `docs/IOS_HANDOFF.md` are ready.
