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

sync_missing_template_keys() {
  local key
  local line
  local -a missing_keys=()

  while IFS='=' read -r key _; do
    [[ "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || continue
    if ! grep -Eq "^${key}=" "$ENV_FILE"; then
      missing_keys+=("$key")
    fi
  done < <(grep -E '^[A-Za-z_][A-Za-z0-9_]*=' "$TEMPLATE")

  if [[ "${#missing_keys[@]}" -eq 0 ]]; then
    printf '[ios:release:init] Existing local env already has current template keys.\n'
    return
  fi

  {
    printf '\n# Added by ios:release:init on %s to keep this local file in sync with .env.ios-release.example.\n' "$(date -u '+%Y-%m-%d %H:%M:%S UTC')"
    for key in "${missing_keys[@]}"; do
      line="$(grep -E "^${key}=" "$TEMPLATE" | head -n 1)"
      if [[ -n "$line" ]]; then
        printf '%s\n' "$line"
      fi
    done
  } >> "$ENV_FILE"

  printf '[ios:release:init] Added missing template keys: %s\n' "${missing_keys[*]}"
}

if [[ -f "$ENV_FILE" ]]; then
  chmod 600 "$ENV_FILE"
  printf '[ios:release:init] Existing local env preserved: %s\n' "$ENV_FILE"
  sync_missing_template_keys
else
  cp "$TEMPLATE" "$ENV_FILE"
  chmod 600 "$ENV_FILE"
  printf '[ios:release:init] Created local env: %s\n' "$ENV_FILE"
fi

printf '[ios:release:init] Edit this file locally; never commit it.\n'
printf '[ios:release:init] Then run: npm run ios:release:env\n'

(cd "$ROOT" && npm run ios:release:env >/dev/null)
printf '[ios:release:init] Evidence report: %s\n' "$REPORT_PATH"
