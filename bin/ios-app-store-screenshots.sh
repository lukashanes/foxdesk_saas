#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOS_DIR="$ROOT/ios/FoxDesk"
BUNDLE_ID="net.foxdesk.ios"
OUT_DIR="${IOS_APP_STORE_SCREENSHOT_DIR:-$ROOT/tmp/ios-app-store-screenshots}"
DERIVED_DATA="$OUT_DIR/DerivedData"
MANIFEST="$OUT_DIR/manifest.md"

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
raw = subprocess.check_output(["xcrun", "simctl", "list", "devices", "available", "-j"], text=True)
payload = json.loads(raw)
devices = []
for runtime, rows in payload.get("devices", {}).items():
    if "iOS" not in runtime:
        continue
    for row in rows:
        if row.get("isAvailable"):
            devices.append(row)
for prefix in preferred:
    for row in devices:
        if row.get("name", "").startswith(prefix):
            print(row["udid"])
            sys.exit(0)
if devices:
    print(devices[0]["udid"])
    sys.exit(0)
sys.exit("No available iOS simulator found.")
PY
}

[[ -d "$IOS_DIR" ]] || fail "Missing ios/FoxDesk project directory."
[[ -f "$IOS_DIR/project.yml" ]] || fail "Missing XcodeGen manifest."

mkdir -p "$OUT_DIR"
rm -rf "$DERIVED_DATA"
find "$OUT_DIR" -maxdepth 1 -type f \( -name '*.png' -o -name '*.jpg' -o -name '*.jpeg' \) -delete

SIMULATOR_UDID="$(select_simulator_udid)"
DESTINATION="id=$SIMULATOR_UDID"

log "Generating Xcode project"
(cd "$IOS_DIR" && xcodegen generate >/dev/null)

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
