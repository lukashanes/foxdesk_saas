#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "$ROOT/bin/ios-release-env.sh"

log() {
  printf '[ios:companion:gate] %s\n' "$1"
}

fail() {
  printf '[ios:companion:gate] FAILED: %s\n' "$1" >&2
  exit 2
}

log "Checking the native free-companion business model"
(cd "$ROOT" && ./bin/run-php.sh tests/ios-companion-business-model-contract-test.php)

log "Checking generic workspace-access enforcement"
(cd "$ROOT" && ./bin/run-php.sh tests/api-workspace-access-contract-test.php)
(cd "$ROOT" && ./bin/run-php.sh tests/ios-workspace-access-gate-contract-test.php)

[[ "${IOS_COMPANION_MODEL_REVIEWED:-0}" == "1" ]] || fail "Set IOS_COMPANION_MODEL_REVIEWED=1 only after reviewing the final native UI, screenshots, and App Review notes for Apple guideline 3.1.3(f)."

log "OK: native free-companion gate passed"
