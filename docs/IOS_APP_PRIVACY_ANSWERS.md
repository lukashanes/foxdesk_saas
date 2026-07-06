# iOS App Privacy Answers

Use this sheet when filling App Store Connect App Privacy. It is based on the
first FoxDesk iOS release scope: a signed-in FoxDesk Cloud companion for agents
and workspace admins.

Do not mark `APP_STORE_PRIVACY_REVIEWED=1` until an operator has compared this
sheet against the current App Store Connect form and the production build.

## App-Level Answers

- Does the app collect data from this app? Yes.
- Does the app track users across apps or websites owned by other companies? No.
- Does the app use data for third-party advertising? No.
- Does the app use the advertising identifier? No.
- Does the app include third-party tracking domains? No.

## Data Linked To The User

Select these data types as linked to the user and used for app functionality.

| Data type | Use | Reason |
| --- | --- | --- |
| Name | App functionality | Shows the signed-in agent/admin identity and ticket activity authors. |
| Email Address | App functionality | Login identity, support contact identity, and workspace membership. |
| User ID | App functionality | FoxDesk user id, session identity, and permission checks. |
| Customer Support | App functionality | Tickets, comments, internal notes, work logs, and customer support context shown in the app. |
| Photos or Videos | App functionality | Optional camera/photo attachments uploaded by the user to tickets. |

## Data Not Used For Tracking

For every selected data type, answer that the data is not used for tracking.

The app does not:

- sell personal data,
- use third-party advertising,
- share data with data brokers,
- combine app data with third-party data for tracking,
- use IDFA or another advertising identifier.

## Data Not Collected In The iOS App

Do not select these unless the production build changes:

- Location
- Contacts
- Browsing History
- Search History outside FoxDesk ticket search
- Purchases
- Financial Info
- Health and Fitness
- Sensitive Info
- Diagnostics for third-party analytics
- Advertising Data

## Device Token

APNs device tokens are used only for push notification delivery. If App Store
Connect requires a category, include it with app functionality / notifications,
not tracking.

The app should disclose push notification delivery in review notes, but the
token is not a customer-facing identifier and must not be exposed in Release UI.

## Account Deletion

The app links to a support request for account deletion from Settings:

```text
mailto:support@foxdesk.net?subject=FoxDesk%20account%20deletion%20request
```

This is acceptable only if the operator process handles deletion requests
reliably. If Apple Review requests in-app deletion automation later, add a
dedicated deletion request endpoint before resubmission.

## Production Build Checks

Before setting `APP_STORE_PRIVACY_REVIEWED=1`, verify:

- Release UI does not show Push diagnostics.
- Release UI links to Privacy Policy and Terms.
- Release UI links to support/account deletion request.
- The app has no billing, checkout, or subscription purchase UI.
- The app has no analytics/tracking SDK.
- The privacy manifest still declares no tracking.

## Related Files

- `ios/FoxDesk/FoxDesk/PrivacyInfo.xcprivacy`
- `ios/FoxDesk/FoxDesk/Sources/SettingsView.swift`
- `docs/IOS_APP_STORE_SUBMISSION.md`
- `docs/IOS_APP_STORE_CONNECT_METADATA.md`
