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
APP_URL=https://app.foxdesk.net
APP_NAME=FoxDesk
TRUST_PROXY=true
MAIL_PROVIDER=cloudflare
CLOUDFLARE_EMAIL_FROM=noreply@foxdesk.net
CLOUDFLARE_EMAIL_FROM_NAME=FoxDesk
CLOUDFLARE_EMAIL_REPLY_TO=support@foxdesk.net
BILLING_CURRENCY=EUR
BILLING_CLOUD_BASE_PRICE_CENTS=990
BILLING_STORAGE_OVERAGE_PRICE_CENTS=190
BILLING_INCLUDED_STORAGE_BYTES=1073741824
BILLING_TRIAL_DAYS=14
BILLING_TRIAL_GRACE_DAYS=3
BILLING_PAST_DUE_GRACE_DAYS=7
STRIPE_STORAGE_METER_EVENT_NAME=foxdesk_storage_extra_gb
STRIPE_TAX_ENABLED=true
STRIPE_TAX_ID_COLLECTION_ENABLED=true
STRIPE_TAX_ID_COLLECTION_REQUIRED=
STORAGE_DRIVER=r2
R2_BUCKET=foxdesk-production
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

## Stripe Values

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
4. Add recurring base price: EUR 9.90/month launch price with tax behavior `exclusive`.
5. Add recurring metered storage price: EUR 1.90 per extra GB/month with tax behavior `exclusive`.
6. Developers -> Webhooks: endpoint `https://app.foxdesk.net/index.php?page=stripe-webhook`.
7. Copy webhook signing secret.

For EU VAT payers, enable Stripe Tax, set the product tax code to SaaS business use, and allow Tax ID updates in the Customer Portal. With exclusive tax behavior, VAT is added only when due; valid EU VAT IDs are handled as reverse charge/zero-rate where applicable.

## Still Optional For First Private Beta

- IMAP inbound email values.
- Turnstile keys.
- R2 backup credentials separate from attachment credentials.
