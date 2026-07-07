# iOS Demo Reviewer Account

Create one disposable FoxDesk Cloud workspace account for Apple App Review. This
account must demonstrate the app without exposing real customer data.

## Required Account

- Role: agent or workspace admin
- Workspace: disposable or curated demo workspace
- Email: fill in `docs/IOS_APP_STORE_SUBMISSION.md`
- Password: fill in `docs/IOS_APP_STORE_SUBMISSION.md`
- 2FA: disabled if possible, or provide `FOXDESK_IOS_DEMO_2FA_CODE`

## Required Demo Data

The account must have access to:

- at least one open ticket,
- at least one waiting ticket,
- at least one done ticket,
- at least one ticket with comments,
- at least one ticket with an attachment,
- at least one ticket linked to a client whose client context opens in the app,
- the linked client context should show at least one related ticket or contact,
- permission to create tickets,
- permission to add public replies,
- permission to add internal notes,
- permission to add comment-with-time records,
- permission to upload camera photos and files.

Use generic names and non-private content. Do not use real customer contracts,
tokens, invoices, or production secrets.

## Verification

Put reviewer credentials into the ignored local release file:

```bash
npm run ios:release:init
```

Then edit `.env.ios-release` and fill:

- `FOXDESK_IOS_DEMO_EMAIL`
- `FOXDESK_IOS_DEMO_PASSWORD`
- `FOXDESK_IOS_DEMO_2FA_CODE`, only if 2FA is enabled

Verify the redacted release state and the demo account:

```bash
npm run ios:release:env
npm run ios:demo:check -- --require-credentials --json
```

Before the final App Store submission gate, run one safe write proof against
the same demo workspace:

```bash
FOXDESK_IOS_DEMO_WRITE=1 npm run ios:demo:check -- --require-credentials --json
```

That opt-in check creates one internal demo ticket with `skip_notification:
true`, adds one linked internal timed comment, then reloads the created ticket
detail and verifies that the comment and `time_entry_id` are visible together.
It should not be left enabled for routine read-only checks.

The check must pass before App Store submission. The final strict gate loads
the same local `.env.ios-release` file without printing secret values.

The checker signs in through the mobile API and verifies:

- open, waiting, and done ticket queues are populated,
- ticket create options include clients, statuses, and priorities,
- at least one ticket has both comments and an attachment,
- at least one ticket opens a readable client context via
  `/api/mobile/v1/clients/{id}`.
- with `FOXDESK_IOS_DEMO_WRITE=1`, the account can create a demo ticket and add
  an internal comment-with-time record without notifying customers.

## App Review Notes

Paste this shape into App Review Information after replacing credentials:

```text
Use the demo account below to sign in to an existing FoxDesk Cloud workspace.
The app is a companion for agents and workspace admins. Billing, checkout, and
workspace subscription management are handled on the web and are not present in
the iOS app.

Demo account:
Email: <reviewer@example.com>
Password: <password>
2FA/backup code: <only if enabled>
```

## After Review

After Apple review is complete:

- rotate or remove the demo account password,
- remove any temporary 2FA backup code,
- keep the workspace data generic for future review cycles.
