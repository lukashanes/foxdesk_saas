#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IOS_DIR="$ROOT/ios/FoxDesk"

[[ -d "$IOS_DIR" ]] || {
  printf '[ios:source:fingerprint] Missing iOS project: %s\n' "$IOS_DIR" >&2
  exit 2
}

find "$IOS_DIR" -type f \
  ! -path '*/DerivedData/*' \
  ! -path '*/DeviceDerivedData/*' \
  ! -path '*/xcuserdata/*' \
  ! -name '.DS_Store' \
  -print0 \
  | sort -z \
  | xargs -0 shasum -a 256 \
  | shasum -a 256 \
  | awk '{print $1}'
