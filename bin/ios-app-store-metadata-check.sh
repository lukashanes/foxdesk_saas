#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
METADATA="$ROOT_DIR/docs/IOS_APP_STORE_CONNECT_METADATA.md"
SUBMISSION_PACKET="$ROOT_DIR/docs/IOS_APP_STORE_SUBMISSION.md"

fail() {
  printf '[ios:metadata:check] %s\n' "$1" >&2
  exit 1
}

contains() {
  local file="$1"
  local needle="$2"
  local message="$3"
  grep -Fq -- "$needle" "$file" || fail "$message"
}

not_contains() {
  local file="$1"
  local needle="$2"
  local message="$3"
  if grep -Fq -- "$needle" "$file"; then
    fail "$message"
  fi
}

printf '[ios:metadata:check] Checking App Store metadata packet\n'

[[ -f "$METADATA" ]] || fail "Missing docs/IOS_APP_STORE_CONNECT_METADATA.md."
[[ -f "$SUBMISSION_PACKET" ]] || fail "Missing docs/IOS_APP_STORE_SUBMISSION.md."

contains "$METADATA" 'Bundle ID: `net.foxdesk.ios`' "Metadata must document the production bundle id."
contains "$METADATA" 'Aenze s.r.o.' "Metadata must document the verified organization."
contains "$METADATA" 'Privacy Policy URL: `https://foxdesk.net/index.php?page=legal&type=privacy`' "Metadata must use the live privacy policy URL."
contains "$METADATA" 'Support URL: `https://foxdesk.net/#support`' "Metadata must use the public support anchor URL."
contains "$METADATA" 'FoxDesk Cloud agents and workspace admins' "Metadata must keep the app scoped to existing Cloud agents/admins."
contains "$METADATA" 'Support tickets and time' "Metadata must include the App Store subtitle."
contains "$METADATA" 'does not include in-app purchases' "Review notes must say subscriptions are not sold in iOS."
contains "$METADATA" 'demo workspace account' "Review notes must describe the demo workspace account."
contains "$METADATA" 'Data linked to the user' "Metadata must include privacy summary."
contains "$METADATA" 'device token for push notification delivery' "Privacy summary must mention APNs device token."
contains "$METADATA" 'tmp/ios-app-store-screenshots' "Metadata must point to generated screenshot evidence."
contains "$METADATA" 'not part of the first iOS release' "Metadata must include the scope guard."
contains "$METADATA" 'Confirm screenshots are from the current native app' "Metadata must include a final screenshot copy check."

contains "$SUBMISSION_PACKET" 'FoxDesk is for existing FoxDesk Cloud workspace users' "Submission packet must match metadata scope."
contains "$SUBMISSION_PACKET" 'does not include in-app purchases' "Submission packet must match review notes."
contains "$SUBMISSION_PACKET" 'Privacy Policy URL: `https://foxdesk.net/index.php?page=legal&type=privacy`' "Submission packet must use the live privacy policy URL."
contains "$SUBMISSION_PACKET" 'Support URL: `https://foxdesk.net/#support`' "Submission packet must use the public support anchor URL."
contains "$SUBMISSION_PACKET" 'npm run ios:production:check' "Submission packet must require the Production build check before upload."
contains "$SUBMISSION_PACKET" 'npm run ios:archive:preflight' "Submission packet must require archive preflight before upload."
contains "$SUBMISSION_PACKET" 'Production/App Store build does not show Push diagnostics.' "Submission packet must describe the Production/App Store diagnostics boundary."

not_contains "$METADATA" 'https://foxdesk.net/privacy' "Metadata must not use the currently non-routed /privacy URL."
not_contains "$SUBMISSION_PACKET" 'https://foxdesk.net/privacy' "Submission packet must not use the currently non-routed /privacy URL."
not_contains "$METADATA" 'https://foxdesk.net/#contact' "Metadata must not use the old empty contact anchor."
not_contains "$SUBMISSION_PACKET" 'https://foxdesk.net/#contact' "Submission packet must not use the old empty contact anchor."
not_contains "$METADATA" 'Stripe Checkout is available in iOS' "Metadata must not claim Stripe Checkout exists in iOS."
not_contains "$METADATA" 'Subscription management, billing, public pricing' "Public App Store description must not advertise web billing surfaces."
not_contains "$METADATA" 'upgrade subscriptions in the iOS app' "Metadata must not claim subscription upgrade exists in iOS."
not_contains "$METADATA" 'platform admin from iPhone' "Metadata must not claim platform admin exists in iOS."
not_contains "$METADATA" 'self-hosted setup from iPhone' "Metadata must not claim self-hosted setup exists in iOS."
not_contains "$SUBMISSION_PACKET" 'Release build does not show Push diagnostics.' "Submission packet must not use old Release-build wording for App Store diagnostics."

printf '[ios:metadata:check] OK\n'
