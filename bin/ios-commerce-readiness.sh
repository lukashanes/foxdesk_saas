#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
printf '[ios:commerce:gate] Deprecated alias; running ios:companion:gate.\n'
exec "$ROOT/bin/ios-companion-readiness.sh" "$@"
