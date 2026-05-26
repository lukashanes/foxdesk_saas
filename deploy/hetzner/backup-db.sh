#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."

if [[ ! -f .env.production ]]; then
  echo "Missing .env.production." >&2
  exit 1
fi

set -a
source .env.production
set +a

backup_dir="${FOXDESK_BACKUP_DIR:-./backups/db}"
mkdir -p "${backup_dir}"

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
out="${backup_dir}/foxdesk-db-${stamp}.sql.gz"

docker compose --env-file .env.production -f docker-compose.prod.yml exec -T db \
  mariadb-dump -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --routines --triggers "${DB_NAME}" \
  | gzip -9 > "${out}"

find "${backup_dir}" -type f -name 'foxdesk-db-*.sql.gz' -mtime +14 -delete

echo "Created ${out}"
