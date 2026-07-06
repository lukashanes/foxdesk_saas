#!/usr/bin/env bash

# Source this file from iOS release/check scripts to load local operator-only
# gate variables. The committed template is .env.ios-release.example; the real
# .env.ios-release file is ignored because it can contain passwords and APNs
# device tokens.

ios_release_env_root="${ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
ios_release_env_file="${FOXDESK_IOS_RELEASE_ENV_FILE:-$ios_release_env_root/.env.ios-release}"

if [[ -f "$ios_release_env_file" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$ios_release_env_file"
  set +a
fi
