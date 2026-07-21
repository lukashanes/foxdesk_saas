#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOS_DIR="$ROOT/ios/FoxDesk"
OUT_DIR="$ROOT/tmp/ios-upload-evidence"
JSON_OUT="$OUT_DIR/latest.json"
MD_OUT="$OUT_DIR/latest.md"
MODE="${1:---check}"

project_setting() {
  local name="$1"
  awk -F': ' -v name="$name" '$1 ~ "^[[:space:]]*" name "$" { print $2; exit }' "$IOS_DIR/project.yml"
}

fail() {
  printf '[ios:upload:evidence] FAILED: %s\n' "$1" >&2
  exit 2
}

project_version="$(project_setting MARKETING_VERSION)"
project_build="$(project_setting CURRENT_PROJECT_VERSION)"
default_archive="$ROOT/tmp/ios-archive/FoxDesk-${project_version}-${project_build}-final.xcarchive"
archive="${2:-$default_archive}"

archive_value() {
  local key="$1"
  plutil -extract "$key" raw "$archive/Info.plist" 2>/dev/null
}

upload_state() {
  plutil -extract Distributions json -o - "$archive/Info.plist" 2>/dev/null | node -e '
    const fs = require("node:fs");
    const rows = JSON.parse(fs.readFileSync(0, "utf8"));
    const upload = rows.find((row) => row && row.uploadDestination === "App Store" && row.uploadEvent);
    if (!upload) process.exit(2);
    process.stdout.write(String(upload.uploadEvent.state || ""));
  '
}

uploaded_build() {
  plutil -extract Distributions json -o - "$archive/Info.plist" 2>/dev/null | node -e '
    const fs = require("node:fs");
    const rows = JSON.parse(fs.readFileSync(0, "utf8"));
    const upload = rows.find((row) => row && row.uploadDestination === "App Store" && row.uploadEvent);
    if (!upload) process.exit(2);
    process.stdout.write(String(upload.uploadedBuildNumber || ""));
  '
}

verify_archive() {
  [[ -d "$archive" ]] || fail "Missing archive: $archive"
  [[ -f "$archive/Info.plist" ]] || fail "Archive Info.plist is missing."
  [[ -f "$archive/Products/Applications/FoxDesk.app/FoxDesk" ]] || fail "Archived FoxDesk binary is missing."

  archive_version="$(archive_value ApplicationProperties.CFBundleShortVersionString)"
  archive_build="$(archive_value ApplicationProperties.CFBundleVersion)"
  [[ "$archive_version" == "$project_version" ]] || fail "Archive version $archive_version does not match project version $project_version."
  [[ "$archive_build" == "$project_build" ]] || fail "Archive build $archive_build does not match project build $project_build."
  [[ "$(upload_state)" == "success" ]] || fail "Archive does not contain a successful App Store upload event."
  [[ "$(uploaded_build)" == "$project_build" ]] || fail "Uploaded archive build does not match project build $project_build."
}

record() {
  verify_archive

  newer_file="$(find "$IOS_DIR" -type f \
    ! -path '*/DerivedData/*' \
    ! -path '*/DeviceDerivedData/*' \
    ! -path '*/xcuserdata/*' \
    ! -name '.DS_Store' \
    -newer "$archive/Info.plist" \
    -print -quit)"
  [[ -z "$newer_file" ]] || fail "iOS source changed after the archive was created: ${newer_file#$ROOT/}. Create and upload a new build."

  mkdir -p "$OUT_DIR"
  source_fingerprint="$($ROOT/bin/ios-source-fingerprint.sh)"
  binary_hash="$(shasum -a 256 "$archive/Products/Applications/FoxDesk.app/FoxDesk" | awk '{print $1}')"
  archive_relative="${archive#$ROOT/}"
  generated_at="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"

  node -e '
    const fs = require("node:fs");
    const [out, generatedAt, archivePath, version, build, sourceFingerprint, binaryHash] = process.argv.slice(1);
    fs.writeFileSync(out, JSON.stringify({
      ok: true,
      generated_at: generatedAt,
      archive_path: archivePath,
      marketing_version: version,
      build_number: build,
      source_fingerprint: sourceFingerprint,
      archive_binary_sha256: binaryHash,
      upload_destination: "App Store",
      upload_state: "success"
    }, null, 2) + "\n");
  ' "$JSON_OUT" "$generated_at" "$archive_relative" "$project_version" "$project_build" "$source_fingerprint" "$binary_hash"

  cat > "$MD_OUT" <<MD
# FoxDesk iOS Upload Evidence

- Generated: $generated_at
- Archive: \`$archive_relative\`
- Version: \`$project_version\`
- Build: \`$project_build\`
- App Store upload event: **success**
- iOS source fingerprint: \`$source_fingerprint\`
- Archive binary SHA-256: \`$binary_hash\`

This proves Apple received the exact local archive recorded here. App Store
processing and build selection remain separate human/API gates.
MD

  printf '[ios:upload:evidence] Recorded %s\n' "${JSON_OUT#$ROOT/}"
}

check() {
  [[ -f "$JSON_OUT" ]] || fail "Missing upload evidence. Run: npm run ios:upload:record"

  evidence_archive="$(node -e 'const d=require(process.argv[1]); process.stdout.write(String(d.archive_path || ""));' "$JSON_OUT")"
  archive="$ROOT/$evidence_archive"
  verify_archive

  evidence_ok="$(node -e 'const d=require(process.argv[1]); process.stdout.write(d.ok === true ? "1" : "0");' "$JSON_OUT")"
  evidence_version="$(node -e 'const d=require(process.argv[1]); process.stdout.write(String(d.marketing_version || ""));' "$JSON_OUT")"
  evidence_build="$(node -e 'const d=require(process.argv[1]); process.stdout.write(String(d.build_number || ""));' "$JSON_OUT")"
  evidence_fingerprint="$(node -e 'const d=require(process.argv[1]); process.stdout.write(String(d.source_fingerprint || ""));' "$JSON_OUT")"
  evidence_binary_hash="$(node -e 'const d=require(process.argv[1]); process.stdout.write(String(d.archive_binary_sha256 || ""));' "$JSON_OUT")"
  current_fingerprint="$($ROOT/bin/ios-source-fingerprint.sh)"
  current_binary_hash="$(shasum -a 256 "$archive/Products/Applications/FoxDesk.app/FoxDesk" | awk '{print $1}')"

  [[ "$evidence_ok" == "1" ]] || fail "Upload evidence is not marked successful."
  [[ "$evidence_version" == "$project_version" ]] || fail "Recorded upload version is stale."
  [[ "$evidence_build" == "$project_build" ]] || fail "Recorded upload build is stale."
  [[ "$evidence_fingerprint" == "$current_fingerprint" ]] || fail "iOS source changed after upload. Create and upload a new build."
  [[ "$evidence_binary_hash" == "$current_binary_hash" ]] || fail "Archived binary changed after upload evidence was recorded."

  printf '[ios:upload:evidence] OK version=%s build=%s fingerprint=%s\n' "$project_version" "$project_build" "$current_fingerprint"
}

case "$MODE" in
  --record) record ;;
  --check) check ;;
  *) fail "Usage: $0 [--record|--check] [archive-path]" ;;
esac
