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

set -a
source .env.production
set +a

deploy/hetzner/preflight.sh

docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml up -d

echo "Waiting for app health..."
healthy=0
for i in {1..30}; do
  if docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app curl -fsS http://127.0.0.1/index.php?page=health >/dev/null; then
    echo "FoxDesk is healthy."
    healthy=1
    break
  fi
  sleep 2
done

if [[ "$healthy" != "1" ]]; then
  echo "Health check failed. Recent logs:" >&2
  docker compose --env-file .env.production -f docker-compose.prod.yml logs --tail=100 app >&2
  exit 1
fi

if [[ "${FOXDESK_DEPLOY_SKIP_EVIDENCE:-}" == "1" ]]; then
  echo "Refusing to mark deploy complete because FOXDESK_DEPLOY_SKIP_EVIDENCE=1." >&2
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "npm is required to run the production deployment evidence gate." >&2
  exit 1
fi

npm run prod:deploy:evidence -- \
  --output-dir="${FOXDESK_DEPLOY_EVIDENCE_DIR}" \
  --restore-evidence="${FOXDESK_RESTORE_EVIDENCE_PATH}"

echo "Deployment evidence passed. Deploy can be marked complete."
