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

FoxDesk for iOS is designed for existing FoxDesk Cloud workspace users. It
focuses on ticket work, time tracking, attachments, search, and notifications.
Workspace setup and administration are not part of the native app.

## Keywords

helpdesk,support,tickets,time tracking,customer service,agents,comments

## Review Notes

FoxDesk is for existing FoxDesk Cloud workspace users. Please sign in with the
demo workspace account provided in App Review Information.

FoxDesk for iOS is a free stand-alone companion to the paid FoxDesk Cloud web
tool under Apple App Review Guideline 3.1.3(f). It has no purchasing inside the
app and no calls to action to purchase outside the app. It does not create
accounts, show pricing, sell subscriptions, include in-app purchases, open
Checkout, or link to subscription management. Users sign in with an existing
organization-provided workspace account.

The iOS app does not include in-app purchases.

If signup, pricing, purchasing, upgrades, or external purchase calls to action
are added later, this review note is no longer sufficient and the release must
be reassessed for StoreKit and in-app purchase requirements.

The demo account should include:

- one open ticket,
- one waiting ticket,
- one done ticket,
- one ticket with public and internal comments,
- one ticket with an attachment,
- permission to create tickets, reply, add internal notes, log time, and upload
  attachments.

## App Review Demo Account

Fill these values in App Store Connect review notes before submission. For
local verification, put the same credentials in the ignored `.env.ios-release`
file. Do not commit real demo credentials to any Markdown file:

- URL: `https://app.foxdesk.net`
- Email: `[fill before submission]`
- Password: `[fill before submission]`
- 2FA or backup code: `[fill if enabled]`

App Review contact:

- First name: `Lukas`
- Last name: `Hanes`
- Email: `lh@aenze.com`
- Phone: `[enter a monitored phone number with country code]`

Set `Sign-in required` to `Yes` and save the demo email and password in the
dedicated App Review fields, not only in Review Notes. The account must remain
active throughout review and must not require an email magic link or a one-time
code unless the code is included in the review information.

## Content Rights

Answer `Yes` when App Store Connect asks whether the app contains, shows, or
accesses third-party content. FoxDesk displays ticket text, files, names, logos,
and other content supplied by customer workspaces. Confirm that the app has the
necessary rights to display that content.

Use this explanation if Apple asks for detail:

```text
FoxDesk displays private business support content uploaded by authorised users
of each customer workspace. The content is not a public catalogue. FoxDesk's
Terms require each business customer to hold all necessary rights, permissions,
notices, and legal bases for customer and third-party content and grant Aenze
s.r.o. the limited rights required to host, process, and display it to authorised
workspace users.
```

## Agreements

This build is a free app with no in-app purchases. The Account Holder must open
`Business > Agreements` and confirm that the Apple Developer Program License
Agreement for free app distribution is active and that App Store Connect shows
no blocking agreement action. Do not sign the Paid Apps Agreement, add banking,
or add tax forms solely for this free build unless App Store Connect presents a
specific blocking requirement or a later release sells an app or in-app
purchase.

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
- Account

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
