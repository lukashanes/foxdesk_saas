# iOS Apple Developer Steps

This runbook covers the Apple Developer work that cannot be completed from the
local repository. Apple Business verification for `Aenze s.r.o.` is already
recorded, and the FoxDesk App ID / APNs key setup below has been completed.

Use an Apple Developer account with Account Holder or Admin access.

## 1. Confirm Or Create The App ID

Open:

```text
https://developer.apple.com/account/resources/identifiers/list
```

Current production identifier:

```text
net.foxdesk.ios
```

Verified Apple team:

```text
XS4ZQYPKLB
```

If the identifier ever needs to be recreated, use:

- Identifier type: App IDs
- Type: App
- Platform: iOS
- Description: FoxDesk iOS
- Bundle ID type: Explicit
- Bundle ID: `net.foxdesk.ios`

The Xcode project already expects this exact bundle id.

## 2. Enable Push Notifications

Open the `net.foxdesk.ios` identifier and confirm:

```text
Push Notifications
Associated Domains
```

Expected build behavior:

- Debug APNs environment: development
- Staging APNs environment: development
- Production archive APNs environment: production
- Release compatibility APNs environment: production
- Entitlement: `aps-environment`

Do not mark this gate ready unless Push Notifications and Associated Domains are
enabled on the explicit App ID.

## 3. Create Or Reuse An APNs Auth Key

Open:

```text
https://developer.apple.com/account/resources/authkeys/list
```

Current APNs key:

- Name: FoxDesk APNs Production
- Key ID: `UQX5NGK25C`
- Team ID: `XS4ZQYPKLB`
- Capability: Apple Push Notifications service (APNs)

The `.p8` file was downloaded once and stored locally at:

```text
/Users/mac/.foxdesk/secrets/AuthKey_UQX5NGK25C.p8
```

Never commit the `.p8` file to Git. Copy it to the production secret store or
server with mode `600`.

Production backend values:

```bash
APNS_TEAM_ID=XS4ZQYPKLB
APNS_KEY_ID=UQX5NGK25C
APNS_AUTH_KEY_PATH=<server path to the .p8 file>
APNS_BUNDLE_ID=net.foxdesk.ios
```

`APNS_AUTH_KEY` can be used instead of `APNS_AUTH_KEY_PATH` only when the secret
storage system supports multiline secrets safely.

For local Docker-backed smoke tests, a host path such as
`/Users/mac/.foxdesk/secrets/AuthKey_UQX5NGK25C.p8` is not visible inside the
PHP container unless it is mounted. Use the multiline `APNS_AUTH_KEY` value for
local dry-runs, or mount the secret and point `APNS_AUTH_KEY_PATH` at the
container-visible path.

## 4. Confirm Signing And Provisioning

In Xcode or Apple Developer:

- Team: `Aenze s.r.o.`
- Team ID: `XS4ZQYPKLB`
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
