#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
PHP_RUNNER="$ROOT_DIR/bin/run-php.sh"

cd "$ROOT_DIR"

find . -name '*.php' \
    -not -path './vendor/*' \
    -not -path './node_modules/*' \
    -print | sort | while IFS= read -r file; do
        "$PHP_RUNNER" -l "$file" >/dev/null
        echo "OK $file"
    done
