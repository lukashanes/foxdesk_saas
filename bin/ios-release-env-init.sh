#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${FOXDESK_IOS_RELEASE_ENV_FILE:-$ROOT/.env.ios-release}"
TEMPLATE="$ROOT/.env.ios-release.example"
REPORT_PATH="tmp/ios-release-env/latest.md"

if [[ ! -f "$TEMPLATE" ]]; then
  printf '[ios:release:init] Missing template: %s\n' "$TEMPLATE" >&2
  exit 1
fi

if [[ -f "$ENV_FILE" ]]; then
  chmod 600 "$ENV_FILE"
  printf '[ios:release:init] Existing local env preserved: %s\n' "$ENV_FILE"
else
  cp "$TEMPLATE" "$ENV_FILE"
  chmod 600 "$ENV_FILE"
  printf '[ios:release:init] Created local env: %s\n' "$ENV_FILE"
fi

printf '[ios:release:init] Edit this file locally; never commit it.\n'
printf '[ios:release:init] Then run: npm run ios:release:env\n'

(cd "$ROOT" && npm run ios:release:env >/dev/null)
printf '[ios:release:init] Evidence report: %s\n' "$REPORT_PATH"
