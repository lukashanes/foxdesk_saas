#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOS_DIR="$ROOT/ios/FoxDesk"
PROJECT_FILE="$IOS_DIR/FoxDesk.xcodeproj/project.pbxproj"

log() {
  printf '[ios:production:check] %s\n' "$1"
}

fail() {
  printf '[ios:production:check] %s\n' "$1" >&2
  exit 1
}

select_destination() {
  if [[ -n "${IOS_DESTINATION:-}" ]]; then
    printf '%s' "$IOS_DESTINATION"
    return
  fi

  python3 <<'PY'
import json
import subprocess
import sys

preferred = ("iPhone 17", "iPhone 16", "iPhone 15", "iPhone 14", "iPad")
try:
    raw = subprocess.check_output(["xcrun", "simctl", "list", "devices", "available", "-j"], text=True)
    devices = []
    payload = json.loads(raw)
    for runtime, rows in payload.get("devices", {}).items():
        if "iOS" not in runtime:
            continue
        for row in rows:
            if row.get("isAvailable"):
                devices.append(row)
    for prefix in preferred:
        for row in devices:
            if row.get("name", "").startswith(prefix):
                print(f"id={row['udid']}")
                sys.exit(0)
    if devices:
        print(f"id={devices[0]['udid']}")
        sys.exit(0)
except Exception:
    pass

print("platform=iOS Simulator,name=iPhone 16 Pro")
PY
}

[[ -d "$IOS_DIR" ]] || fail "Missing ios/FoxDesk project directory."
[[ -f "$IOS_DIR/project.yml" ]] || fail "Missing XcodeGen manifest."

log "Generating Xcode project"
(cd "$IOS_DIR" && xcodegen generate >/dev/null)

[[ -f "$PROJECT_FILE" ]] || fail "Missing generated Xcode project."
grep -q 'Production' "$PROJECT_FILE" || fail "Generated project must contain a Production configuration."
grep -q 'APS_ENVIRONMENT = production;' "$PROJECT_FILE" || fail "Production config must use production APNs entitlement."
grep -Eq 'FOXDESK_API_BASE_URL = "?https://app\.foxdesk\.net/index\.php"?;' "$PROJECT_FILE" || fail "Production config must use production app.foxdesk.net API base."
grep -q 'CODE_SIGN_ENTITLEMENTS = FoxDesk/FoxDesk.entitlements;' "$PROJECT_FILE" || fail "App target must include push entitlements."

DESTINATION="$(select_destination)"
log "Building Production for ${DESTINATION}"
(cd "$IOS_DIR" && xcodebuild -project FoxDesk.xcodeproj -scheme FoxDesk -configuration Production -destination "$DESTINATION" CODE_SIGNING_ALLOWED=NO -quiet build)

log "OK"
