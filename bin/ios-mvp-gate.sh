#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOS_DIR="$ROOT/ios/FoxDesk"
PROJECT_FILE="$IOS_DIR/FoxDesk.xcodeproj/project.pbxproj"

log() {
  printf '[ios:gate] %s\n' "$1"
}

require_file() {
  if [[ ! -f "$1" ]]; then
    printf '[ios:gate] Missing required file: %s\n' "$1" >&2
    exit 1
  fi
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
            if not row.get("isAvailable"):
                continue
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

log "Checking iOS project files"
require_file "$IOS_DIR/project.yml"
require_file "$IOS_DIR/FoxDesk/FoxDesk.entitlements"
require_file "$IOS_DIR/FoxDesk/PrivacyInfo.xcprivacy"
require_file "$IOS_DIR/FoxDesk/Assets.xcassets/Contents.json"
require_file "$IOS_DIR/FoxDesk/Assets.xcassets/AppIcon.appiconset/Contents.json"
require_file "$IOS_DIR/FoxDesk/Assets.xcassets/AppIcon.appiconset/AppIcon-1024.png"

log "Validating manifest and asset metadata"
plutil -lint "$IOS_DIR/FoxDesk/PrivacyInfo.xcprivacy" >/dev/null
python3 -m json.tool "$IOS_DIR/FoxDesk/Assets.xcassets/Contents.json" >/dev/null
python3 -m json.tool "$IOS_DIR/FoxDesk/Assets.xcassets/AppIcon.appiconset/Contents.json" >/dev/null

ICON_SIZE="$(sips -g pixelWidth -g pixelHeight "$IOS_DIR/FoxDesk/Assets.xcassets/AppIcon.appiconset/AppIcon-1024.png" 2>/dev/null | awk '/pixel/{print $2}' | paste -sdx -)"
if [[ "$ICON_SIZE" != "1024x1024" ]]; then
  printf '[ios:gate] AppIcon-1024.png must be 1024x1024, got %s\n' "$ICON_SIZE" >&2
  exit 1
fi

log "Generating Xcode project"
(cd "$IOS_DIR" && xcodegen generate >/dev/null)

log "Checking generated Xcode resources"
require_file "$PROJECT_FILE"
grep -q 'PrivacyInfo.xcprivacy in Resources' "$PROJECT_FILE"
grep -q 'Assets.xcassets in Resources' "$PROJECT_FILE"
grep -q 'ASSETCATALOG_COMPILER_APPICON_NAME = AppIcon;' "$PROJECT_FILE"
grep -q 'CODE_SIGN_ENTITLEMENTS = FoxDesk/FoxDesk.entitlements;' "$PROJECT_FILE"

DESTINATION="$(select_destination)"
log "Running Xcode tests on ${DESTINATION}"
(cd "$IOS_DIR" && xcodebuild -project FoxDesk.xcodeproj -scheme FoxDesk -destination "$DESTINATION" CODE_SIGNING_ALLOWED=NO -quiet test)

log "Running mobile API contracts"
(cd "$ROOT" && ./bin/run-php.sh tests/mobile-api-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/mobile-api-v1-routing-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/mobile-session-bootstrap-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/mobile-idempotency-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/background-job-tenant-isolation-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-mvp-endpoint-matrix-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/native-app-api-freeze-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/app-home-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-mvp-scope-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-companion-business-model-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/api-workspace-access-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-workspace-access-gate-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-companion-readiness-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-dashboard-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-ticket-detail-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-mvp-traceability-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-external-gates-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-submission-gate-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-upload-evidence-contract-test.php)

log "iOS MVP gate OK"
