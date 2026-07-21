#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOS_DIR="$ROOT/ios/FoxDesk"
PRIVACY_MANIFEST="$IOS_DIR/FoxDesk/PrivacyInfo.xcprivacy"
SCREENSHOT_DIR="$ROOT/tmp/ios-app-store-screenshots"
SCREENSHOT_MANIFEST="$SCREENSHOT_DIR/manifest.md"
OUT_DIR="$ROOT/tmp/ios-app-store-assets"
OUT="$OUT_DIR/latest.md"
STRICT=0

if [[ "${1:-}" == "--strict" ]]; then
  STRICT=1
fi

mkdir -p "$OUT_DIR"
failures=()

project_setting() {
  local name="$1"
  awk -F': ' -v name="$name" '$1 ~ "^[[:space:]]*" name "$" { print $2; exit }' "$IOS_DIR/project.yml"
}

privacy_has() {
  local value="$1"
  plutil -convert xml1 -o - "$PRIVACY_MANIFEST" | grep -Fq "<string>${value}</string>"
}

for value in \
  NSPrivacyCollectedDataTypeName \
  NSPrivacyCollectedDataTypeEmailAddress \
  NSPrivacyCollectedDataTypeUserID \
  NSPrivacyCollectedDataTypeCustomerSupport \
  NSPrivacyCollectedDataTypePhotosorVideos \
  NSPrivacyCollectedDataTypeOtherUserContent \
  NSPrivacyCollectedDataTypeDeviceID \
  NSPrivacyAccessedAPICategoryUserDefaults \
  CA92.1; do
  privacy_has "$value" || failures+=("Privacy manifest is missing $value.")
done

if [[ "$(plutil -extract NSPrivacyTracking raw "$PRIVACY_MANIFEST" 2>/dev/null || true)" != "false" ]]; then
  failures+=("Privacy manifest must declare NSPrivacyTracking=false.")
fi

screenshot_count=0
if [[ -d "$SCREENSHOT_DIR" ]]; then
  screenshot_count="$(find "$SCREENSHOT_DIR" -maxdepth 1 -type f -name '*.png' | wc -l | tr -d ' ')"
fi
[[ "$screenshot_count" -ge 8 ]] || failures+=("Expected at least 8 App Store screenshots; found $screenshot_count.")
[[ -f "$SCREENSHOT_MANIFEST" ]] || failures+=("Screenshot manifest is missing. Run npm run ios:screenshots.")
[[ -f "$SCREENSHOT_DIR/account.png" ]] || failures+=("Screenshot packet is missing account.png.")

current_fingerprint="$($ROOT/bin/ios-source-fingerprint.sh)"
marketing_version="$(project_setting MARKETING_VERSION)"
build_number="$(project_setting CURRENT_PROJECT_VERSION)"
manifest_fingerprint=""
manifest_version=""
manifest_build=""

if [[ -f "$SCREENSHOT_MANIFEST" ]]; then
  manifest_fingerprint="$(sed -n 's/^- iOS source fingerprint: `\([^`]*\)`.*/\1/p' "$SCREENSHOT_MANIFEST" | head -n 1)"
  manifest_version="$(sed -n 's/^- Marketing version: //p' "$SCREENSHOT_MANIFEST" | head -n 1)"
  manifest_build="$(sed -n 's/^- Build number: //p' "$SCREENSHOT_MANIFEST" | head -n 1)"
  [[ "$manifest_fingerprint" == "$current_fingerprint" ]] || failures+=("Screenshots are stale for the current iOS sources. Regenerate them.")
  [[ "$manifest_version" == "$marketing_version" ]] || failures+=("Screenshot marketing version does not match project.yml.")
  [[ "$manifest_build" == "$build_number" ]] || failures+=("Screenshot build number does not match project.yml.")
fi

dimension_set=""
if [[ "$screenshot_count" -gt 0 ]]; then
  dimension_set="$(find "$SCREENSHOT_DIR" -maxdepth 1 -type f -name '*.png' -print0 \
    | xargs -0 -n 1 sh -c 'sips -g pixelWidth -g pixelHeight "$0" 2>/dev/null | awk '\''/pixel/{print $2}'\'' | paste -sdx -' \
    | sort -u \
    | paste -sd, -)"
  if [[ "$dimension_set" == *","* ]]; then
    failures+=("Screenshot packet contains inconsistent dimensions: $dimension_set.")
  fi
fi

status="ready"
[[ "${#failures[@]}" -eq 0 ]] || status="blocked"

{
  printf '# FoxDesk iOS App Store Assets Check\n\n'
  printf -- '- Status: **%s**\n' "$status"
  printf -- '- Marketing version: `%s`\n' "$marketing_version"
  printf -- '- Build number: `%s`\n' "$build_number"
  printf -- '- Screenshots: `%s`\n' "$screenshot_count"
  printf -- '- Screenshot dimensions: `%s`\n' "${dimension_set:-missing}"
  printf -- '- iOS source fingerprint: `%s`\n\n' "$current_fingerprint"
  printf '## Findings\n\n'
  if [[ "${#failures[@]}" -eq 0 ]]; then
    printf -- '- None. Privacy and screenshot assets match the current iOS source.\n'
  else
    for failure in "${failures[@]}"; do
      printf -- '- %s\n' "$failure"
    done
  fi
} > "$OUT"

printf '[ios:assets:check] status=%s report=tmp/ios-app-store-assets/latest.md\n' "$status"
if [[ "$STRICT" == "1" && "$status" != "ready" ]]; then
  exit 2
fi
