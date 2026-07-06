# FoxDesk iOS App Store Connect Metadata

Use this packet when creating or updating the App Store Connect record for the
first native FoxDesk iOS release. The iOS app is a work companion for existing
FoxDesk Cloud workspace users. It is not a public signup flow, billing surface,
platform admin, or self-hosted setup tool.

## App Record

- App name: `FoxDesk`
- Bundle ID: `net.foxdesk.ios`
- SKU: `foxdesk-ios`
- Primary language: English
- Category: Business
- Organization: `Aenze s.r.o.`
- Privacy Policy URL: `https://foxdesk.net/index.php?page=legal&type=privacy`
- Support URL: `https://foxdesk.net/#support`
- Marketing URL: `https://foxdesk.net`

## Subtitle

Support tickets and time

## Promotional Text

Manage support tickets, replies, time entries, attachments, and notifications
from your iPhone.

## Description

FoxDesk is a native companion app for FoxDesk Cloud agents and workspace admins.
It helps support teams keep customer work moving while they are away from the
desk.

With FoxDesk for iPhone, signed-in workspace users can:

- view assigned work and ticket queues,
- open ticket details and customer context,
- reply to customers or add internal notes,
- add worked time to a comment,
- create new tickets,
- upload camera photos and files,
- preview authorized attachments,
- search tickets and clients,
- receive ticket notifications and open the right ticket from a push.

FoxDesk for iOS is designed for existing FoxDesk Cloud workspaces. Subscription
management, billing, public pricing, platform administration, and self-hosted
setup remain on the web.

## Keywords

helpdesk,support,tickets,time tracking,customer service,agents,comments

## Review Notes

FoxDesk is for existing FoxDesk Cloud workspace users. Please sign in with the
demo workspace account provided in App Review Information.

The iOS app does not sell subscriptions, does not include in-app purchases, does
not show Stripe Checkout, and does not expose billing or platform
administration. Subscription and workspace billing management are handled on
the web.

The demo account should include:

- one open ticket,
- one waiting ticket,
- one done ticket,
- one ticket with public and internal comments,
- one ticket with an attachment,
- permission to create tickets, reply, add internal notes, log time, and upload
  attachments.

## App Review Demo Account

Fill these values in App Store Connect and in
`docs/IOS_APP_STORE_SUBMISSION.md` before submission:

- URL: `https://app.foxdesk.net`
- Email: `[fill before submission]`
- Password: `[fill before submission]`
- 2FA or backup code: `[fill if enabled]`

## Privacy Summary

FoxDesk for iOS is a signed-in business support tool. It does not track users
across apps or websites and does not use data for third-party advertising.

Data linked to the user and used for app functionality:

- name,
- email address,
- user ID,
- customer support content such as tickets, comments, work logs, and
  attachments,
- photos or files uploaded by the user as ticket attachments,
- device token for push notification delivery.

Data not collected for tracking:

- no advertising identifier,
- no third-party tracking domains,
- no sale of personal data.

## Screenshot Set

Use the generated screenshots in:

```bash
tmp/ios-app-store-screenshots
```

Required first-release screenshots:

- Sign in
- Dashboard/Work
- Tickets list
- Ticket detail with comments
- Ticket reply composer
- Attachment preview
- Search
- Client context
- Notifications
- Settings

Do not upload screenshots containing private customer data, API tokens,
provider internals, billing, platform admin, or self-hosted setup screens.

## Scope Guard

These surfaces are intentionally not part of the first iOS release:

- public signup,
- public pricing,
- Stripe Checkout,
- customer billing portal,
- subscription upgrade or cancellation,
- platform admin,
- tenant lifecycle tools,
- self-hosted install/update/migration setup,
- SMTP/IMAP/server configuration.

## Final Copy Check Before Upload

Before pasting into App Store Connect:

1. Verify current App Store Connect field limits in the Apple UI.
2. Paste the real demo account credentials into App Store Connect review notes.
3. Confirm screenshots are from the current native app, not the web app.
4. Confirm the app description still says this is for existing FoxDesk Cloud
   workspaces.
5. Confirm the metadata does not claim billing, platform admin, or self-hosted
   management in iOS.
