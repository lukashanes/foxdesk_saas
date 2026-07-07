# iOS Apple Developer Steps

This runbook covers the Apple Developer work that cannot be completed from the
local repository. Apple Business verification for `Aenze s.r.o.` is already
recorded, but it does not create the App ID, signing profile, or push capability.

Use an Apple Developer account with Account Holder or Admin access.

## 1. Confirm Or Create The App ID

Open:

```text
https://developer.apple.com/account/resources/identifiers/list
```

Search for:

```text
net.foxdesk.ios
```

If it does not exist, create a new identifier:

- Identifier type: App IDs
- Type: App
- Platform: iOS
- Description: FoxDesk iOS
- Bundle ID type: Explicit
- Bundle ID: `net.foxdesk.ios`

The Xcode project already expects this exact bundle id.

## 2. Enable Push Notifications

Open the `net.foxdesk.ios` identifier and enable:

```text
Push Notifications
```

Expected build behavior:

- Debug APNs environment: development
- Staging APNs environment: development
- Production archive APNs environment: production
- Release compatibility APNs environment: production
- Entitlement: `aps-environment`

Do not mark this gate ready until Push Notifications are enabled on the explicit
App ID.

## 3. Create Or Reuse An APNs Auth Key

Open:

```text
https://developer.apple.com/account/resources/authkeys/list
```

Create or reuse a key with:

- Name: FoxDesk APNs
- Capability: Apple Push Notifications service (APNs)

Download the `.p8` file immediately. Apple only allows downloading it once.
Never commit the `.p8` file to Git.

Production backend values:

```bash
APNS_TEAM_ID=<Apple team id>
APNS_KEY_ID=<key id from Apple Developer>
APNS_AUTH_KEY_PATH=<server path to the .p8 file>
APNS_BUNDLE_ID=net.foxdesk.ios
```

`APNS_AUTH_KEY` can be used instead of `APNS_AUTH_KEY_PATH` only when the secret
storage system supports multiline secrets safely.

## 4. Confirm Signing And Provisioning

In Xcode or Apple Developer:

- Team: `Aenze s.r.o.`
- Bundle ID: `net.foxdesk.ios`
- Production archive signing can use the App Store distribution identity/profile
- Production archive entitlement resolves to production APNs

If automatic signing is not available, create provisioning profiles for:

- iOS App Development, bundle `net.foxdesk.ios`
- App Store, bundle `net.foxdesk.ios`

## 5. Physical iPhone APNs Smoke

Use a real iPhone. The simulator cannot validate production APNs delivery.

1. Install a Debug or Staging build.
2. Allow notifications.
3. Open Account -> Push diagnostics.
4. Copy the APNs device token into `APNS_TEST_DEVICE_TOKEN` in the ignored
   `.env.ios-release` file.
5. Run:

```bash
npm run ios:release:env
npm run ios:apns:smoke -- --json
npm run ios:apns:smoke -- --send --environment=production
```

Pass criteria:

- the dry-run validates every first-release ticket push payload type without
  sending a notification,
- the iPhone receives the push notification,
- tapping the notification opens the matching ticket,
- the payload contains `ticket_id`,
- Production/App Store UI does not show Push diagnostics.

## 6. Mark The External Gate

Only after the App ID exists and Push Notifications are enabled, run release
gates with:

```bash
APPLE_DEVELOPER_BUNDLE_READY=1
```

This does not replace the App Store Connect app record, demo reviewer account,
live smoke credentials, APNs physical-device smoke, or privacy review gates.

## Official References

- Register an App ID: https://developer.apple.com/help/account/identifiers/register-an-app-id/
- Enable capabilities: https://developer.apple.com/help/account/identifiers/enable-app-capabilities/
- Create a private key: https://developer.apple.com/help/account/keys/create-a-private-key/
- Create a provisioning profile: https://developer.apple.com/help/account/provisioning-profiles/create-an-app-store-provisioning-profile/
