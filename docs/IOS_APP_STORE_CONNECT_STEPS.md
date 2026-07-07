# FoxDesk iOS App Store Connect Steps

This runbook turns the remaining App Store Connect setup into a short operator
checklist. It assumes the local iOS MVP gate is green and that the first native
release stays scoped to the FoxDesk Cloud agent/admin work companion.

Apple Business verification is already recorded for `Aenze s.r.o.`. That helps
with organization identity, but App Store Connect still needs its own app
record and Apple Developer signing/capability setup.

## 1. Create The App Record

Open App Store Connect:

```text
https://appstoreconnect.apple.com/apps
```

Create a new app:

1. Open `Apps`.
2. Click the add button.
3. Choose `New App`.
4. Fill:
   - Platform: `iOS`
   - Name: `FoxDesk`
   - Primary language: `English`
   - Bundle ID: `net.foxdesk.ios`
   - SKU: `foxdesk-ios`
   - User access: `Full Access`, unless you intentionally restrict app access.
5. Click `Create`.

Operator role needed: Account Holder, Admin, or App Manager.

After this is done, the external gate can be marked with:

```bash
APP_STORE_CONNECT_APP_RECORD_READY=1
npm run ios:release:env
```

Prefer setting the flag in the ignored local `.env.ios-release` file instead
of pasting release values into the terminal.

## 2. Confirm Bundle ID And Capabilities

Use the detailed Apple Developer runbook:

```text
docs/IOS_APPLE_DEVELOPER_STEPS.md
```

In Apple Developer / Certificates, Identifiers & Profiles, confirm:

- Bundle ID exists: `net.foxdesk.ios`
- Team/organization: `Aenze s.r.o.`
- Push Notifications capability is enabled.

The Xcode project already expects:

- bundle id: `net.foxdesk.ios`
- Debug/Staging APNs: development
- Production archive APNs: production
- Release compatibility APNs: production

After this is done, the external gate can be marked with:

```bash
APPLE_DEVELOPER_BUNDLE_READY=1
npm run ios:release:env
```

Prefer setting the flag in the ignored local `.env.ios-release` file.

## 3. Paste Metadata

Use:

```text
docs/IOS_APP_STORE_CONNECT_METADATA.md
```

Paste the prepared fields into App Store Connect:

- Subtitle
- Promotional Text
- Description
- Keywords
- Review Notes
- Privacy summary answers
- Support / privacy / marketing URLs

Keep the first iOS release wording strict:

- existing FoxDesk Cloud workspace users,
- agent/admin work companion,
- no in-app purchases,
- no billing or subscription management in iOS,
- no platform admin,
- no self-hosted setup.

Run before upload:

```bash
npm run ios:release:env
npm run ios:metadata:check
npm run ios:production:check
npm run ios:archive:preflight
```

The archive preflight writes `tmp/ios-archive-preflight/latest.md` and checks
that the Production archive will use bundle id `net.foxdesk.ios`, production
APNs, the production `app.foxdesk.net` API base, AppIcon, and PrivacyInfo.

## 4. Add Demo Reviewer Account

Create or choose a real demo workspace user on `app.foxdesk.net`.

Requirements:

- role: agent or workspace admin
- can sign in from iOS
- has at least one open ticket
- has at least one waiting ticket
- has at least one done ticket
- has at least one ticket with comments and attachment
- can add replies, internal notes, time entries, and attachments

Keep committed files generic. Use this packet only for non-secret review
wording and demo-account requirements:

```text
docs/IOS_APP_STORE_SUBMISSION.md
```

Paste the real demo email/password only into App Store Connect review fields
and the ignored local `.env.ios-release` file for verification. Do not commit real demo credentials to any Markdown file.

Verify the account before submitting:

```bash
npm run ios:demo:check -- --require-credentials --json
```

Put `FOXDESK_IOS_DEMO_EMAIL` and `FOXDESK_IOS_DEMO_PASSWORD` in the ignored
local `.env.ios-release` file. If the account uses 2FA, also set
`FOXDESK_IOS_DEMO_2FA_CODE`. The command checks sign-in, workspace shell, Work
data, create-ticket options, open/waiting/done tickets, and one ticket with
comments plus an attachment.

## 5. Upload Screenshots

Generate screenshots:

```bash
npm run ios:screenshots
```

Review:

```text
tmp/ios-app-store-screenshots/manifest.md
tmp/ios-app-store-screenshots/*.png
```

Only upload screenshots that:

- show the native iOS app,
- use populated but non-private demo data,
- do not show billing/platform/admin/self-hosted screens,
- do not show API tokens or provider internals.

## 6. Run Live Smoke

Use a staging workspace when possible. If this must run on production, use only
a disposable production workspace and set
`FOXDESK_IOS_ALLOW_PRODUCTION_WRITE_SMOKE=1` for that explicit run.

Read-only smoke:

```bash
npm run ios:api:smoke -- --require-credentials --json
```

One write smoke:

```bash
FOXDESK_IOS_SMOKE_WRITE=1 npm run ios:api:smoke -- --require-credentials --json
```

Put `FOXDESK_IOS_SMOKE_EMAIL` and `FOXDESK_IOS_SMOKE_PASSWORD` in
`.env.ios-release` for this step. Keep `FOXDESK_IOS_SMOKE_WRITE=0` by default
and enable write mode only for this explicit smoke run.
The write smoke creates one internal smoke ticket, adds one timed internal
comment, uploads one small attachment, downloads that attachment through the
authorized URL, reloads ticket detail, verifies the linked time entry, and
suppresses notifications.

## 7. Run Physical iPhone APNs Smoke

Install a debug or staging build on a physical iPhone.

In the app:

1. Sign in.
2. Open Account.
3. Enable notifications.
4. Open Push diagnostics.
5. Copy APNs token.

Then run:

```bash
npm run ios:apns:smoke -- --json
npm run ios:apns:smoke -- --send --environment=production
```

Put `APNS_TEST_DEVICE_TOKEN` in `.env.ios-release` for this step.
Pass criteria:

- the dry-run validates every first-release ticket push payload type without
  sending a notification,
- iPhone receives the push,
- tapping the push opens the correct ticket,
- notification payload contains a valid `ticket_id`,
- Production/App Store UI does not show Push diagnostics.

## 8. Final Local Gate

After all human/operator steps are done:

```bash
npm run ios:release:env
npm run ios:submission:gate
```

Only call the iOS release ready for TestFlight after this command passes.

## Sources

- Apple App Store Connect Help: Add a new app
  `https://developer.apple.com/help/app-store-connect/create-an-app-record/add-a-new-app/`
