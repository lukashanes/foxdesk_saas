#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOS_DIR="$ROOT_DIR/ios/FoxDesk"
PROJECT="$IOS_DIR/FoxDesk.xcodeproj"
PROJECT_FILE="$PROJECT/project.pbxproj"
MANIFEST="$IOS_DIR/project.yml"
ENTITLEMENTS="$IOS_DIR/FoxDesk/FoxDesk.entitlements"
PRIVACY="$IOS_DIR/FoxDesk/PrivacyInfo.xcprivacy"
APP_ICON="$IOS_DIR/FoxDesk/Assets.xcassets/AppIcon.appiconset/Contents.json"
EVIDENCE_DIR="$ROOT_DIR/tmp/ios-archive-preflight"
EVIDENCE_REPORT="$EVIDENCE_DIR/latest.md"
LIST_JSON="$EVIDENCE_DIR/xcodebuild-list.json"
BUILD_SETTINGS="$EVIDENCE_DIR/production-build-settings.txt"

mkdir -p "$EVIDENCE_DIR"

cat > "$EVIDENCE_REPORT" <<REPORT
# FoxDesk iOS Archive Preflight

- Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
- Git revision: $(git -C "$ROOT_DIR" rev-parse --short HEAD 2>/dev/null || printf 'unknown')
- Project: ios/FoxDesk/FoxDesk.xcodeproj
- Scheme: FoxDesk
- Configuration: Production
- Bundle identifier: net.foxdesk.ios

## Checks

REPORT

fail() {
  printf '[ios:archive:preflight] %s\n' "$1" >&2
  printf -- '- Failed: %s\n' "$1" >> "$EVIDENCE_REPORT"
  exit 1
}

log() {
  printf '[ios:archive:preflight] %s\n' "$1"
  printf -- '- %s\n' "$1" >> "$EVIDENCE_REPORT"
}

contains() {
  local file="$1"
  local needle="$2"
  local message="$3"
  grep -Fq -- "$needle" "$file" || fail "$message"
}

contains_regex() {
  local file="$1"
  local pattern="$2"
  local message="$3"
  grep -Eq -- "$pattern" "$file" || fail "$message"
}

[[ -d "$IOS_DIR" ]] || fail "Missing ios/FoxDesk project directory."
[[ -f "$MANIFEST" ]] || fail "Missing XcodeGen manifest."
[[ -f "$ENTITLEMENTS" ]] || fail "Missing app entitlements."
[[ -f "$PRIVACY" ]] || fail "Missing PrivacyInfo.xcprivacy."
[[ -f "$APP_ICON" ]] || fail "Missing AppIcon asset catalog."

contains "$MANIFEST" "PRODUCT_BUNDLE_IDENTIFIER: net.foxdesk.ios" "Production app bundle id must be net.foxdesk.ios."
contains "$MANIFEST" "CODE_SIGN_STYLE: Automatic" "Project must use automatic signing for operator archive handoff."
contains "$MANIFEST" "CODE_SIGN_ENTITLEMENTS: FoxDesk/FoxDesk.entitlements" "App target must include entitlements."
marketing_version="$(awk '/MARKETING_VERSION:/ { print $2; exit }' "$MANIFEST")"
[[ "$marketing_version" =~ ^[0-9]+([.][0-9]+){1,2}$ ]] || fail "Marketing version must be declared before archive."
build_number="$(awk '/CURRENT_PROJECT_VERSION:/ { print $2; exit }' "$MANIFEST")"
[[ "$build_number" =~ ^[1-9][0-9]*$ ]] || fail "Build number must be a positive integer before archive."
framework_marketing_count="$(grep -Ec "MARKETING_VERSION:[[:space:]]+${marketing_version}$" "$MANIFEST" || true)"
framework_build_count="$(grep -Ec "CURRENT_PROJECT_VERSION:[[:space:]]+${build_number}$" "$MANIFEST" || true)"
[[ "$framework_marketing_count" -ge 2 ]] || fail "App and embedded FoxDeskKit framework must both declare CFBundleShortVersionString."
[[ "$framework_build_count" -ge 2 ]] || fail "App and embedded FoxDeskKit framework must use the same CFBundleVersion."
contains "$MANIFEST" "ASSETCATALOG_COMPILER_APPICON_NAME: AppIcon" "Archive must use the AppIcon asset catalog."
contains "$MANIFEST" 'TARGETED_DEVICE_FAMILY: "1"' "First release must target iPhone only."
contains "$MANIFEST" "APS_ENVIRONMENT: production" "Production config must use production APNs."
contains "$MANIFEST" "https://app.foxdesk.net/index.php" "Production config must use production app.foxdesk.net API."
contains "$MANIFEST" "INFOPLIST_KEY_ITSAppUsesNonExemptEncryption: NO" "App must declare that it does not use non-exempt encryption."
contains "$ENTITLEMENTS" '$(APS_ENVIRONMENT)' "APNs entitlement must be driven by build configuration."
contains "$PRIVACY" "NSPrivacyTracking" "Privacy manifest must declare tracking state."
contains "$PRIVACY" "NSPrivacyCollectedDataTypes" "Privacy manifest must declare collected data types."
contains "$APP_ICON" "ios-marketing" "AppIcon set must include the App Store marketing icon slot."

log "Generating Xcode project"
(cd "$IOS_DIR" && xcodegen generate >/dev/null)

[[ -f "$PROJECT_FILE" ]] || fail "Missing generated Xcode project."
contains "$PROJECT_FILE" "FoxDesk" "Generated project must contain the FoxDesk target/scheme."
contains "$PROJECT_FILE" "Production" "Generated project must contain Production configuration."
contains "$PROJECT_FILE" "APS_ENVIRONMENT = production;" "Generated Production project must contain production APNs config."
contains_regex "$PROJECT_FILE" 'FOXDESK_API_BASE_URL = "?https://app\.foxdesk\.net/index\.php"?;' "Generated Production project must use the production API base."

log "Inspecting Xcode schemes"
(cd "$IOS_DIR" && xcodebuild -list -json -project FoxDesk.xcodeproj > "$LIST_JSON")
python3 - "$LIST_JSON" <<'PY'
import json
import sys

path = sys.argv[1]
with open(path, "r", encoding="utf-8") as handle:
    payload = json.load(handle)

schemes = payload.get("project", {}).get("schemes", [])
if "FoxDesk" not in schemes:
    raise SystemExit("FoxDesk scheme is missing from the generated project.")
PY

log "Inspecting Production build settings for generic iOS archive"
(cd "$IOS_DIR" && xcodebuild -showBuildSettings -project FoxDesk.xcodeproj -scheme FoxDesk -configuration Production -destination 'generic/platform=iOS' > "$BUILD_SETTINGS")

contains "$BUILD_SETTINGS" "PRODUCT_BUNDLE_IDENTIFIER = net.foxdesk.ios" "Production build settings must use bundle id net.foxdesk.ios."
contains "$BUILD_SETTINGS" "CONFIGURATION = Production" "Archive preflight must inspect the Production configuration."
contains "$BUILD_SETTINGS" "CODE_SIGN_STYLE = Automatic" "Production archive should use automatic signing."
contains "$BUILD_SETTINGS" "CODE_SIGN_ENTITLEMENTS = FoxDesk/FoxDesk.entitlements" "Production build settings must include entitlements."
contains "$BUILD_SETTINGS" "ASSETCATALOG_COMPILER_APPICON_NAME = AppIcon" "Production build settings must use AppIcon."
contains "$BUILD_SETTINGS" "APS_ENVIRONMENT = production" "Production build settings must use production APNs."
contains "$BUILD_SETTINGS" "INFOPLIST_KEY_FOXDESK_API_BASE_URL = https://app.foxdesk.net/index.php" "Production archive must point to production app.foxdesk.net."
contains "$BUILD_SETTINGS" "INFOPLIST_KEY_ITSAppUsesNonExemptEncryption = NO" "Production archive must declare no non-exempt encryption."
contains "$BUILD_SETTINGS" "IPHONEOS_DEPLOYMENT_TARGET = 17.0" "Production archive must target iOS 17."
contains "$BUILD_SETTINGS" "TARGETED_DEVICE_FAMILY = 1" "Production archive must target iPhone only."

cat >> "$EVIDENCE_REPORT" <<'REPORT'

## Archive Command

Run this after Apple Developer signing and App Store Connect setup are ready:

```bash
cd ios/FoxDesk
xcodebuild \
  -project FoxDesk.xcodeproj \
  -scheme FoxDesk \
  -configuration Production \
  -destination 'generic/platform=iOS' \
  -archivePath ../../tmp/ios-archive/FoxDesk.xcarchive \
  archive
```

Then validate and upload the archive in Xcode Organizer or the approved App
Store Connect upload flow. This preflight intentionally does not upload or
sign on its own.

REPORT

log "Evidence report: tmp/ios-archive-preflight/latest.md"
log "OK"
