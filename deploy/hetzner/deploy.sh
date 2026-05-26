#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."

if [[ ! -f .env.production ]]; then
  echo "Missing .env.production. Copy .env.production.example and fill production values." >&2
  exit 1
fi

if [[ ! -f config.php ]]; then
  echo "Missing config.php. Copy config.production.example.php to config.php." >&2
  exit 1
fi

deploy/hetzner/preflight.sh

docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml up -d

echo "Waiting for app health..."
for i in {1..30}; do
  if docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app curl -fsS http://127.0.0.1/index.php?page=health >/dev/null; then
    echo "FoxDesk is healthy."
    exit 0
  fi
  sleep 2
done

echo "Health check failed. Recent logs:" >&2
docker compose --env-file .env.production -f docker-compose.prod.yml logs --tail=100 app >&2
exit 1
