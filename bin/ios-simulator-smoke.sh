#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOS_DIR="$ROOT/ios/FoxDesk"
BUNDLE_ID="net.foxdesk.ios"
OUT_DIR="${IOS_SMOKE_OUTPUT_DIR:-$ROOT/tmp/ios-smoke}"
DERIVED_DATA="$OUT_DIR/DerivedData"
SCREENSHOT="$OUT_DIR/foxdesk-login.png"
REPORT="$OUT_DIR/latest.md"

log() {
  printf '[ios:sim:smoke] %s\n' "$1"
}

fail() {
  printf '[ios:sim:smoke] %s\n' "$1" >&2
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

preferred = ("iPhone 17", "iPhone 16", "iPhone 15", "iPhone 14")
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

SIMULATOR_UDID="$(select_simulator_udid)"
DESTINATION="id=$SIMULATOR_UDID"

log "Generating Xcode project"
(cd "$IOS_DIR" && xcodegen generate >/dev/null)

log "Building Debug for $DESTINATION"
(cd "$IOS_DIR" && xcodebuild -project FoxDesk.xcodeproj -scheme FoxDesk -configuration Debug -destination "$DESTINATION" -derivedDataPath "$DERIVED_DATA" CODE_SIGNING_ALLOWED=NO -quiet build)

APP_PATH="$(find "$DERIVED_DATA/Build/Products/Debug-iphonesimulator" -name 'FoxDesk.app' -type d | head -n 1)"
[[ -n "$APP_PATH" && -d "$APP_PATH" ]] || fail "Unable to locate built FoxDesk.app."

log "Booting simulator $SIMULATOR_UDID"
xcrun simctl boot "$SIMULATOR_UDID" >/dev/null 2>&1 || true
xcrun simctl bootstatus "$SIMULATOR_UDID" -b >/dev/null

log "Installing app"
xcrun simctl install "$SIMULATOR_UDID" "$APP_PATH"

log "Launching app"
xcrun simctl terminate "$SIMULATOR_UDID" "$BUNDLE_ID" >/dev/null 2>&1 || true
LAUNCH_OUTPUT="$(xcrun simctl launch "$SIMULATOR_UDID" "$BUNDLE_ID")"
printf '%s\n' "$LAUNCH_OUTPUT" > "$OUT_DIR/launch.log"
grep -Eq "${BUNDLE_ID}: [0-9]+" "$OUT_DIR/launch.log" || fail "Launch did not return a simulator process id."

sleep "${IOS_SMOKE_SCREENSHOT_DELAY:-4}"

log "Capturing screenshot"
xcrun simctl io "$SIMULATOR_UDID" screenshot "$SCREENSHOT" >/dev/null
[[ -s "$SCREENSHOT" ]] || fail "Screenshot was not created."

DIMENSIONS="$(sips -g pixelWidth -g pixelHeight "$SCREENSHOT" 2>/dev/null | awk '/pixel/{print $2}' | paste -sdx -)"
[[ "$DIMENSIONS" =~ ^[0-9]+x[0-9]+$ ]] || fail "Unable to read screenshot dimensions."

cat > "$REPORT" <<MD
# FoxDesk iOS Simulator Smoke

- Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
- Simulator UDID: \`$SIMULATOR_UDID\`
- Bundle ID: \`$BUNDLE_ID\`
- Configuration: \`Debug\`
- Screenshot: \`tmp/ios-smoke/foxdesk-login.png\`
- Screenshot dimensions: \`$DIMENSIONS\`
- Launch log: \`tmp/ios-smoke/launch.log\`
- Status: ready

MD

log "OK screenshot=$SCREENSHOT dimensions=$DIMENSIONS"
