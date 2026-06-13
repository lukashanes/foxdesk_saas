# Deployment And Recovery Evidence

FoxDesk production deploys are complete only after health, smoke, backup
restore evidence, and deployment evidence all pass.

## Required Production Values

`.env.production` must include:

```env
PROD_BASE_URL=https://app.foxdesk.net
PROD_PUBLIC_URL=https://foxdesk.net
FOXDESK_BACKUP_DIR=/var/backups/foxdesk/db
FOXDESK_RESTORE_EVIDENCE_PATH=/var/lib/foxdesk/evidence/restore-latest.json
FOXDESK_RESTORE_EVIDENCE_MAX_AGE_DAYS=30
FOXDESK_DEPLOY_EVIDENCE_DIR=/var/lib/foxdesk/evidence/deployments
FOXDESK_MONITORING_HEALTH_URL=https://app.foxdesk.net/index.php?page=health
FOXDESK_MONITORING_ALERT_EMAIL=ops@aenze.com
```

`deploy/hetzner/preflight.sh` fails when any of those values are missing,
placeholder, local-only, or not production-safe.

## Restore Evidence

Before a paid production deploy, run a real restore test into an isolated target.
Store the result at `FOXDESK_RESTORE_EVIDENCE_PATH`.

Use this template:

```bash
cp docs/operations/backup-restore-evidence.template.json /var/lib/foxdesk/evidence/restore-latest.json
```

Then replace all template values with the actual backup path, restore target,
operator, timestamp, and check results. The deployment evidence gate requires:

- `status` is `passed`
- `testedAt` is a valid ISO timestamp and not older than the configured max age
- `sourceBackup` identifies the exact backup file
- `restoreTarget` identifies the isolated restore target
- every item in `checks` has `status` set to `passed` or `pass`

## Deployment Evidence Gate

Run after the production stack is healthy:

```bash
npm run prod:deploy:evidence
```

The command:

- validates production env values without printing secrets
- verifies restore evidence
- runs `npm run prod:smoke`
- writes `deployment-evidence.json`
- writes `deployment-evidence.md`
- copies the restore evidence into the evidence directory
- creates `foxdesk-deploy-evidence-*.tar.gz`
- creates a matching `.sha256` checksum

The deploy is complete only when the command returns `deploy_complete_allowed`.

## Hetzner Deploy Flow

`deploy/hetzner/deploy.sh` runs:

1. `deploy/hetzner/preflight.sh`
2. `docker compose build`
3. `docker compose up -d`
4. container-local health check
5. `npm run prod:deploy:evidence`

If evidence fails, keep the deploy marked incomplete even if the containers are
running.

## Cutover Evidence

Self-hosted to SaaS cutover still uses the separate cutover chain:

```bash
npm run prod:cutover:gate
npm run cutover:preflight -- /path/to/result.json
npm run cutover:postcheck -- /path/to/cutover-preflight.json
npm run cutover:archive -- --dir=/path/to/cutover-evidence
```

Cutover preflight can also require deployment and restore evidence:

```bash
npm run cutover:preflight -- /path/to/result.json \
  --deploy-evidence=/var/lib/foxdesk/evidence/deployments/deployment-evidence.json \
  --restore-evidence=/var/lib/foxdesk/evidence/restore-latest.json
```
