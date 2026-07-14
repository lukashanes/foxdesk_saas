#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOS_DIR="$ROOT/ios/FoxDesk"
BUNDLE_ID="net.foxdesk.ios"
OUT_DIR="${IOS_APP_STORE_SCREENSHOT_DIR:-$ROOT/tmp/ios-app-store-screenshots}"
DERIVED_DATA="$OUT_DIR/DerivedData"
MANIFEST="$OUT_DIR/manifest.md"

ios_source_fingerprint() {
  find "$IOS_DIR" -type f \
    ! -path '*/DerivedData/*' \
    ! -path '*/xcuserdata/*' \
    ! -name '.DS_Store' \
    -print0 \
    | sort -z \
    | xargs -0 shasum -a 256 \
    | shasum -a 256 \
    | awk '{print $1}'
}

project_setting() {
  local name="$1"
  awk -F': ' -v name="$name" '$1 ~ "^[[:space:]]*" name "$" { print $2; exit }' "$IOS_DIR/project.yml"
}

SCREENS=(
  signin
  dashboard
  tickets
  ticket-detail
  reply
  attachment
  search
  client
  notifications
  account
)

FORBIDDEN_SCREEN_LABELS=(
  "billing"
  "platform admin"
  "settings"
  "self-hosted setup"
  "API tokens"
  "Push diagnostics"
  "APNs token"
)

log() {
  printf '[ios:screenshots] %s\n' "$1"
}

fail() {
  printf '[ios:screenshots] %s\n' "$1" >&2
  exit 1
}

select_simulator_udid() {
  if [[ -n "${IOS_SIMULATOR_UDID:-}" ]]; then
    printf '%s' "$IOS_SIMULATOR_UDID"
    return
  fi

  python3 <<'PY'
import json
import subprocess
import sys

preferred = ("iPhone 17 Pro Max", "iPhone 17", "iPhone 16 Pro Max", "iPhone 16", "iPhone 15 Pro Max", "iPhone 15")
types = json.loads(subprocess.check_output(["xcrun", "simctl", "list", "devicetypes", "-j"], text=True)).get("devicetypes", [])
runtimes = json.loads(subprocess.check_output(["xcrun", "simctl", "list", "runtimes", "-j"], text=True)).get("runtimes", [])
ios_runtimes = [row for row in runtimes if row.get("isAvailable") and row.get("platform") == "iOS"]
if not ios_runtimes:
    sys.exit("No available iOS simulator runtime found.")

def version_tuple(value):
    return tuple(int(part) for part in str(value or "0").split(".") if part.isdigit())

runtime = sorted(ios_runtimes, key=lambda row: version_tuple(row.get("version")), reverse=True)[0]
device_type = None
for name in preferred:
    device_type = next((row for row in types if row.get("name") == name), None)
    if device_type:
        break
if device_type is None:
    device_type = next((row for row in types if row.get("productFamily") == "iPhone"), None)
if device_type is None:
    sys.exit("No available iPhone simulator device type found.")

udid = subprocess.check_output([
    "xcrun", "simctl", "create", "FoxDesk App Store Screenshots",
    device_type["identifier"], runtime["identifier"],
], text=True).strip()
print(udid)
PY
}

[[ -d "$IOS_DIR" ]] || fail "Missing ios/FoxDesk project directory."
[[ -f "$IOS_DIR/project.yml" ]] || fail "Missing XcodeGen manifest."

mkdir -p "$OUT_DIR"
rm -rf "$DERIVED_DATA"
find "$OUT_DIR" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpg' -o -name '*.jpeg' -o -name '*.launch.log' \) -delete

SIMULATOR_UDID="$(select_simulator_udid)"
DESTINATION="id=$SIMULATOR_UDID"
if [[ -z "${IOS_SIMULATOR_UDID:-}" ]]; then
  cleanup_simulator() {
    xcrun simctl shutdown "$SIMULATOR_UDID" >/dev/null 2>&1 || true
    xcrun simctl delete "$SIMULATOR_UDID" >/dev/null 2>&1 || true
  }
  trap cleanup_simulator EXIT
fi

log "Generating Xcode project"
(cd "$IOS_DIR" && xcodegen generate >/dev/null)

IOS_SOURCE_FINGERPRINT="$(ios_source_fingerprint)"
MARKETING_VERSION="$(project_setting MARKETING_VERSION)"
BUILD_NUMBER="$(project_setting CURRENT_PROJECT_VERSION)"

log "Building Debug screenshot fixture for $DESTINATION"
(cd "$IOS_DIR" && xcodebuild -project FoxDesk.xcodeproj -scheme FoxDesk -configuration Debug -destination "$DESTINATION" -derivedDataPath "$DERIVED_DATA" CODE_SIGNING_ALLOWED=NO -quiet build)

APP_PATH="$(find "$DERIVED_DATA/Build/Products/Debug-iphonesimulator" -name 'FoxDesk.app' -type d | head -n 1)"
[[ -n "$APP_PATH" && -d "$APP_PATH" ]] || fail "Unable to locate built FoxDesk.app."

log "Booting simulator $SIMULATOR_UDID"
xcrun simctl boot "$SIMULATOR_UDID" >/dev/null 2>&1 || true
xcrun simctl bootstatus "$SIMULATOR_UDID" -b >/dev/null

log "Installing app"
xcrun simctl install "$SIMULATOR_UDID" "$APP_PATH"

{
  printf '# FoxDesk iOS App Store Screenshot Evidence\n\n'
  printf -- '- Generated: %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
  printf -- '- Simulator: %s\n' "$SIMULATOR_UDID"
  printf -- '- Mode: debug-only populated fixture\n\n'
  printf -- '- Marketing version: %s\n' "$MARKETING_VERSION"
  printf -- '- Build number: %s\n' "$BUILD_NUMBER"
  printf -- '- iOS source fingerprint: `%s`\n\n' "$IOS_SOURCE_FINGERPRINT"
  printf '## Scope Guard\n\n'
  printf 'These screenshots are for the first native iOS work-companion release. They must show only agent/admin work surfaces: sign in, dashboard, tickets, ticket detail, reply, attachments, search, client context, notifications, and account.\n\n'
  printf 'Before upload, manually confirm the images do not show internal or out-of-scope surfaces:\n\n'
  for label in "${FORBIDDEN_SCREEN_LABELS[@]}"; do
    printf -- '- [ ] No %s screen or secret value is visible.\n' "$label"
  done
  printf '\n'
  printf '## Screenshots\n\n'
} > "$MANIFEST"

for screen in "${SCREENS[@]}"; do
  file="$OUT_DIR/${screen}.png"
  log "Capturing $screen"
  xcrun simctl terminate "$SIMULATOR_UDID" "$BUNDLE_ID" >/dev/null 2>&1 || true
  LAUNCH_OUTPUT="$(xcrun simctl launch "$SIMULATOR_UDID" "$BUNDLE_ID" --foxdesk-screenshot-mode --foxdesk-screenshot-screen "$screen")"
  printf '%s\n' "$LAUNCH_OUTPUT" > "$OUT_DIR/${screen}.launch.log"
  grep -Eq "${BUNDLE_ID}: [0-9]+" "$OUT_DIR/${screen}.launch.log" || fail "Launch failed for screenshot screen: $screen"
  sleep "${IOS_SCREENSHOT_CAPTURE_DELAY:-2}"
  xcrun simctl io "$SIMULATOR_UDID" screenshot "$file" >/dev/null
  [[ -s "$file" ]] || fail "Screenshot was not created: $file"
  dimensions="$(sips -g pixelWidth -g pixelHeight "$file" 2>/dev/null | awk '/pixel/{print $2}' | paste -sdx -)"
  [[ "$dimensions" =~ ^[0-9]+x[0-9]+$ ]] || fail "Unable to read screenshot dimensions for $file."
  printf -- '- `%s.png` — %s\n' "$screen" "$dimensions" >> "$MANIFEST"
done

log "OK screenshots=$OUT_DIR manifest=$MANIFEST"
