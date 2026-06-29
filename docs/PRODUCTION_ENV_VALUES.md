# Production Environment Values

Production runs at:

```text
https://app.foxdesk.net
```

Hetzner IPv4:

```text
46.224.66.79
```

## Values Already Known

```env
APP_HOST=app.foxdesk.net
PLATFORM_HOST=platform.foxdesk.net
APP_MARKETING_HOST=foxdesk.net
APP_URL=https://app.foxdesk.net
PLATFORM_URL=https://platform.foxdesk.net
APP_MARKETING_URL=https://foxdesk.net
PROD_BASE_URL=https://app.foxdesk.net
PROD_PUBLIC_URL=https://foxdesk.net
APP_NAME=FoxDesk
TRUST_PROXY=true
MAIL_PROVIDER=cloudflare
CLOUDFLARE_EMAIL_FROM=notifications@foxdesk.net
CLOUDFLARE_EMAIL_FROM_NAME=FoxDesk
CLOUDFLARE_EMAIL_REPLY_TO=support@foxdesk.net
FOXDESK_TICKET_EMAIL_DOMAIN=foxdesk.net
FOXDESK_TICKET_EMAIL_LOCAL_PART=tickets
FOXDESK_EMAIL_ROUTE_SECRET=<openssl rand -hex 32>
FOXDESK_EMAIL_ALLOW_UNKNOWN_SENDERS=false
BILLING_ENABLED=true
BILLING_CURRENCY=EUR
BILLING_CLOUD_BASE_PRICE_CENTS=990
BILLING_STORAGE_OVERAGE_PRICE_CENTS=190
BILLING_INCLUDED_STORAGE_BYTES=1073741824
BILLING_TRIAL_DAYS=14
BILLING_TRIAL_GRACE_DAYS=3
BILLING_PAST_DUE_GRACE_DAYS=7
STRIPE_STORAGE_METER_EVENT_NAME=foxdesk_storage_extra_gb
STRIPE_PRICE_CLOUD_BASE=price_1TduGWLE0xWWZe199qWeD07B
STRIPE_PRICE_STORAGE_OVERAGE=price_1TduGXLE0xWWZe19fwYt9nIF
STRIPE_TAX_ENABLED=true
STRIPE_TAX_ID_COLLECTION_ENABLED=true
STRIPE_TAX_ID_COLLECTION_REQUIRED=
STORAGE_DRIVER=r2
R2_BUCKET=foxdesk-production
FOXDESK_BACKUP_DIR=/var/backups/foxdesk/db
FOXDESK_ATTACHMENT_BACKUP_PREFIX=backups/attachments
FOXDESK_RESTORE_EVIDENCE_PATH=/var/lib/foxdesk/evidence/restore-latest.json
FOXDESK_RESTORE_EVIDENCE_MAX_AGE_DAYS=30
FOXDESK_DEPLOY_EVIDENCE_DIR=/var/lib/foxdesk/evidence/deployments
FOXDESK_MONITORING_HEALTH_URL=https://app.foxdesk.net/index.php?page=health
FOXDESK_MONITORING_ALERT_EMAIL=ops@aenze.com
```

## Generate Locally

Generate `SECRET_KEY`:

```bash
openssl rand -hex 32
```

Generate database passwords:

```bash
openssl rand -base64 32
openssl rand -base64 32
```

Use one for `DB_PASS`, one for `DB_ROOT_PASS`.

## Cloudflare Values

You already configured Email Sending. Required values:

- `CLOUDFLARE_ACCOUNT_ID`
- `CLOUDFLARE_EMAIL_API_TOKEN`

Inbound ticket replies use Cloudflare Email Routing plus addressing:

- Enable Email Routing subaddressing for `foxdesk.net`.
- Route `tickets@foxdesk.net` to Worker `foxdesk-email-router`.
- Set Worker secret `FOXDESK_EMAIL_WEBHOOK_SECRET` to the same value as `FOXDESK_EMAIL_ROUTE_SECRET`.
- Keep `FOXDESK_EMAIL_ALLOW_UNKNOWN_SENDERS=false` unless public inbound ticket creation is intentionally enabled.

Required for R2:

- `R2_ENDPOINT`: `https://<account_id>.r2.cloudflarestorage.com`
- `R2_ACCESS_KEY_ID`: from R2 API token creation screen
- `R2_SECRET_ACCESS_KEY`: from R2 API token creation screen

Where:

1. Cloudflare Dashboard.
2. R2 Object Storage.
3. Create bucket `foxdesk-production`.
4. Manage R2 API Tokens.
5. Create a token scoped to the bucket.

Production smoke tests:

```bash
php bin/test-r2-storage.php --tenant-id=<test_tenant_id> --json
php bin/test-cloudflare-email.php --to=<your_email> --scenario=all --json
npm run prod:deploy:evidence
```

The R2 test must report `tenant_prefixed: true`. The email test sends signup, password reset, new-ticket, ticket-reply, and billing delivery probes through the configured provider.

## Stripe Values

Full setup checklist: [Stripe Public Beta Setup](STRIPE_PUBLIC_BETA_SETUP.md).

Required in Stripe live mode:

- `STRIPE_SECRET_KEY`
- `STRIPE_WEBHOOK_SECRET`
- `STRIPE_PRICE_CLOUD_BASE`
- `STRIPE_PRICE_STORAGE_OVERAGE`
- `STRIPE_TAX_ENABLED=true`
- `STRIPE_TAX_ID_COLLECTION_ENABLED=true`

Where:

1. Stripe Dashboard.
2. Developers -> API keys: copy live secret key.
3. Product catalog: create `FoxDesk Cloud`.
4. Add recurring base price: EUR 9.90/month introductory price with tax behavior `exclusive`. Public marketing should also show the regular price EUR 19.90/month.
5. Add recurring metered storage price: EUR 1.90 per extra GB/month with tax behavior `exclusive`.
6. Developers -> Webhooks: endpoint `https://app.foxdesk.net/index.php?page=stripe-webhook`.
7. Copy webhook signing secret.

For EU VAT payers, enable Stripe Tax, set the product tax code to SaaS business use, and allow Tax ID updates in the Customer Portal. With exclusive tax behavior, VAT is added only when due; valid EU VAT IDs are handled as reverse charge/zero-rate where applicable.

## Optional Or Environment-Specific

- IMAP inbound email values for self-hosted fallback.
- Turnstile keys.
- R2 backup credentials separate from attachment credentials.

For paid public beta, Turnstile/rate limiting and separate backup credentials
should be treated as launch-hardening items, not long-term optional work.

## Deployment And Recovery Evidence

Before a deploy can be marked complete, keep a dated restore evidence file at
`FOXDESK_RESTORE_EVIDENCE_PATH` and run:

```bash
npm run prod:deploy:evidence
```

The deployment evidence command verifies production env values, checks the
restore evidence, runs production smoke, writes JSON/Markdown evidence, and
creates a tar.gz archive with a SHA256 checksum.

Template:

```bash
docs/operations/backup-restore-evidence.template.json
```
