# FoxDesk iOS App Store Legal Checklist

This checklist is the exact operator handoff for the first FoxDesk iOS release.
The build is a free companion for existing FoxDesk Cloud business workspaces.
It has no account creation, pricing, purchase, billing, or external purchase
link in the app.

## 1. App Privacy

Open `App Store Connect > FoxDesk > App Privacy`.

- Privacy Policy URL:
  `https://foxdesk.net/index.php?page=legal&type=privacy`
- Data collected: `Yes`
- Tracking: `No`
- Third-party advertising: `No`

Enter the data types and purposes from `docs/IOS_APP_PRIVACY_ANSWERS.md`:

- Name
- Email Address
- User ID
- Customer Support
- Photos or Videos
- Other User Content
- Device ID
- Search History

For every selected type use `App Functionality`, `Linked to the user: Yes`, and
`Used for tracking: No`. Compare the answers with the production build, publish
the privacy answers, then set `APP_STORE_PRIVACY_REVIEWED=1` locally.

## 2. App Review Information

Open the selected iOS version and complete `App Review Information`:

- Contact first name: `Lukas`
- Contact last name: `Hanes`
- Contact email: `lh@aenze.com`
- Contact phone: a monitored number including country code
- Sign-in required: `Yes`
- Demo email and password: persistent credentials from `.env.ios-release`
- Notes: paste the Review Notes from
  `docs/IOS_APP_STORE_CONNECT_METADATA.md`

The demo account must not expire during review and must contain populated open,
waiting, and done tickets, comments, an attachment, and the permissions listed
in the metadata packet. Save the fields, verify the same credentials with
`npm run ios:demo:check -- --require-credentials --json`, then set
`APP_STORE_REVIEW_INFO_READY=1` locally.

## 3. Content Rights

Open `App Information > Content Rights`.

- Does the app contain, show, or access third-party content? `Yes`
- Does the app have the necessary rights? `Yes`

FoxDesk displays private content supplied by authorised users of customer
workspaces. The Terms require each business customer to hold the necessary
rights and legal bases and grant Aenze s.r.o. the limited right to host,
process, and display that content to authorised workspace users. Save the
answer, then set `APP_STORE_CONTENT_RIGHTS_READY=1` locally.

## 4. Agreements

The Account Holder opens `Business > Agreements`.

- Confirm the Apple Developer Program License Agreement for free app
  distribution is active.
- Resolve any agreement marked `Action Needed` or another blocking status.
- This build is free and has no in-app purchases. Do not add the Paid Apps
  Agreement, tax forms, or banking solely for this build unless App Store
  Connect explicitly requires them or FoxDesk later sells through Apple.

When no blocking action remains, set `APP_STORE_AGREEMENTS_READY=1` locally.

## 5. Separate Web SaaS Contract

The FoxDesk Terms now state that the service is offered only for business or
professional use. Signup and Stripe Checkout both require the customer to
confirm business purpose and authority. Customer workspace content remains the
customer's responsibility, subject to Aenze's mandatory processor and security
obligations.

The commercial rule is:

- the trial is a free evaluation,
- a paid subscription starts only after an explicit Checkout,
- subscriptions renew monthly,
- cancellation stops renewal and takes effect at the end of the current paid
  period,
- paid periods are non-refundable except where mandatory law requires
  otherwise or Aenze approves a discretionary remedy.

Stripe Dashboard must contain valid Terms and Privacy URLs so Checkout can
render the required Terms acceptance checkbox.

This web contract and billing flow is separate from the iOS submission. The
native app does not show pricing, trial state, billing state, Checkout, Portal,
or an external purchase call to action.

## Final Gate

Do not add the version for review until all four flags above are `1`, the
selected build is the intended release build, and this command passes:

```bash
npm run ios:submission:gate
```
