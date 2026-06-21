# Stripe Billing Setup

FoxDesk SaaS implements Stripe Checkout, Stripe Customer Portal, signed Stripe
webhooks, Stripe Tax/VAT ID collection, trial access, billing lifecycle
enforcement, and metered storage usage reporting.

Use Stripe Billing with recurring Prices and Checkout Sessions. Do not build a custom renewal loop with one-off PaymentIntents.

For the dashboard setup checklist used before public beta, see [Stripe Public Beta Setup](STRIPE_PUBLIC_BETA_SETUP.md).

## Local configuration

Copy the local template and keep real secrets out of git:

```bash
cp config.local.example.php config.local.php
```

Set these values in `config.local.php` or in the container environment:

```php
define('BILLING_ENABLED', true);
define('STRIPE_SECRET_KEY', 'sk_test_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
define('STRIPE_PRICE_CLOUD_BASE', 'price_...');
define('STRIPE_PRICE_STORAGE_OVERAGE', 'price_...');
define('STRIPE_STORAGE_METER_EVENT_NAME', 'foxdesk_storage_extra_gb');
define('STRIPE_TAX_ENABLED', true);
define('STRIPE_TAX_ID_COLLECTION_ENABLED', true);
define('STRIPE_TAX_ID_COLLECTION_REQUIRED', '');
define('BILLING_CURRENCY', 'EUR');
define('BILLING_CLOUD_BASE_PRICE_CENTS', 990);
define('BILLING_STORAGE_OVERAGE_PRICE_CENTS', 190);
define('BILLING_INCLUDED_STORAGE_BYTES', 1073741824);
define('BILLING_TRIAL_DAYS', 14);
define('BILLING_TRIAL_GRACE_DAYS', 3);
define('BILLING_PAST_DUE_GRACE_DAYS', 7);
```

When `BILLING_ENABLED` is `false`, the billing UI remains visible but Checkout and Portal actions return a clear configuration error.

## Stripe products

Create two recurring Prices in Stripe for the single public plan:

- FoxDesk Cloud base subscription -> `STRIPE_PRICE_CLOUD_BASE`
- Metered extra storage per GB -> `STRIPE_PRICE_STORAGE_OVERAGE`

Set both Prices to `tax_behavior=exclusive`. FoxDesk public prices are treated as prices before tax. Stripe Tax then adds VAT/sales tax only when due; EU VAT-registered business customers can enter a VAT ID in Checkout and Stripe applies reverse charge or zero-rate where applicable. If a Price is `inclusive`, reverse charge does not reduce the total amount paid, so EU VAT payers would not see a lower total.

Enable Stripe Tax in live mode before setting `STRIPE_TAX_ENABLED=true`:

- head office: Aenze s.r.o., Moskevska 1842, 272 04 Kladno, Czech Republic
- default tax behavior: `exclusive`
- product tax code: `txcd_10103001` (`Software as a service (SaaS) - business use`)
- customer portal: enable customer updates for billing address and Tax ID

Create a Stripe meter with event name matching `STRIPE_STORAGE_METER_EVENT_NAME`. Configure it to aggregate the latest reported value for the billing period. FoxDesk reports the current extra started GB as a whole-number meter event.

FoxDesk stores `cloud` on the `tenants.plan` field. Checkout adds the base subscription and, when configured, the metered storage price.

Default commercial model:

- unlimited users
- unlimited clients
- unlimited agents
- unlimited tickets
- 1 GB storage included
- EUR 9.90/month launch base price through May 31, 2026
- EUR 1.90/extra GB/month storage overage

Launch offer:

- Keep the live recurring Price at EUR 9.90/month for launch.
- Create a new recurring Price and switch `STRIPE_PRICE_CLOUD_BASE` after the launch window if the public price changes.
- Do not create permanent product copy that exposes internal margin or infrastructure cost.

## Webhook

Configure Stripe to send webhooks to:

```text
https://your-domain.example/index.php?page=stripe-webhook
```

Required events:

- `checkout.session.completed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.paid`
- `invoice.payment_failed`

The endpoint verifies the `Stripe-Signature` header with `STRIPE_WEBHOOK_SECRET`. Invalid or stale signatures are rejected before any database update.

## Runtime behavior

- Signup creates a 14-day trial workspace with no card required.
- If a trialing workspace opens Checkout before the trial ends, FoxDesk sends the existing trial end to Stripe so the first paid billing period starts after the trial instead of immediately.
- Trial workspaces use `tenants.status = trialing`, `tenants.subscription_status = trialing`, and `tenants.trial_ends_at`.
- Trial lifecycle emails are recorded in `billing_trial_email_events` so signup, 3-day, 1-day, and expired notices are sent at most once per workspace.
- When the trial end passes, the workspace stays accessible through `BILLING_TRIAL_GRACE_DAYS`. After that grace period, FoxDesk marks it `trial_expired` and locks normal app access. Workspace admins can still open Billing and activate through Stripe Checkout.
- Maintenance (`php bin/run-maintenance.php`) and pseudo-cron both expire trials, suspend overdue failed-payment tenants, and send pending trial reminders.
- Platform admins can open Checkout or Customer Portal for any workspace from `Platform`.
- Customer Portal uses `STRIPE_PORTAL_CONFIGURATION_ID` when present; otherwise it falls back to the first active Stripe Portal configuration.
- Checkout collects billing address and business tax ID when `STRIPE_TAX_ID_COLLECTION_ENABLED=true`.
- Checkout enables Stripe automatic tax when `STRIPE_TAX_ENABLED=true`.
- Platform admins can extend a trial, block a tenant, grant free access, or manually reactivate a workspace from the workspace catalog.
- Free/manual access is a platform override. FoxDesk stores `billing_override_reason`, `billing_override_at`, and `billing_override_by`, and writes the operator action to the security log.
- Workspace admins can open their own billing page from the user menu.
- Stripe subscription changes update `tenants.stripe_customer_id`, `tenants.stripe_subscription_id`, `tenants.subscription_status`, and `tenants.status`.
- Paid invoices reactivate the workspace and clear suspension timestamps. Failed invoices mark the workspace `past_due`, preserve the first failed-payment timestamp in `suspended_at`, and keep app access open through `BILLING_PAST_DUE_GRACE_DAYS`.
- After the past-due grace period, maintenance changes `tenants.status` to `suspended` and normal app pages redirect to Billing until Stripe reports a paid invoice.
- Billing and Platform show storage usage, included storage, billable extra GB, and estimated monthly overage.
- `bin/report-billing-usage.php` reports daily storage meter events to Stripe for tenants with a Stripe customer id.
- Tenants with `status` set to `trial_expired`, `suspended`, `blocked`, or `canceled` are redirected to Billing instead of normal app pages. `past_due` tenants stay usable until the configured grace period ends.

## Billing state matrix

The app uses `billing_lifecycle_state_matrix()` as the single source for access,
workspace billing buttons, platform actions, and banner copy.

| State | App access | Workspace action | Platform action |
| --- | --- | --- | --- |
| `trialing` | allowed | Add billing | extend trial, grant free, block |
| `active` | allowed | Manage billing | grant free, block |
| `manual_free` | allowed | Manage billing details when Stripe billing is enabled | reactivate, block |
| `past_due_grace` | allowed until grace ends | Update payment | grant free, block |
| `suspended` | blocked from normal app pages | Update payment or start plan | reactivate or grant free |
| `cancelled` | blocked from normal app pages | Restart plan | reactivate or grant free |
| `migrated_pending_cutover` | allowed for review | no checkout action | grant free or block |

## Usage reporting

Run manually:

```bash
php bin/report-billing-usage.php --json
```

Run without calling Stripe:

```bash
php bin/report-billing-usage.php --dry-run --json
```

Validate one tenant end to end against Stripe test mode:

```bash
php bin/validate-stripe-usage.php --tenant-id=1 --live --json
```

The validation command:

- checks the configured Stripe key mode without printing the secret
- refuses live Stripe keys unless `--allow-live-key` is explicitly passed
- checks that an active Billing Meter exists for `STRIPE_STORAGE_METER_EVENT_NAME`
- runs a local dry-run first
- sends one tenant-scoped meter event when `--live` is used
- checks meter event summaries and invoice preview when Stripe has enough data

Stripe meter events are processed asynchronously, so a fresh event can be
accepted before it appears in summaries or invoice previews. Re-run the command
after a short delay if the meter event was reported but the summary has not
caught up yet.

For targeted local checks:

```bash
php bin/report-billing-usage.php --tenant-id=1 --period=2026-06-01 --dry-run --json
```

The maintenance runner also calls usage reporting:

```bash
php bin/run-maintenance.php
```

Reporting is idempotent per tenant and day. Each row is stored in
`billing_usage_reports` with a deterministic idempotency key. A dry-run row does
not block a later live report for the same tenant and day; only a successfully
reported Stripe event blocks a duplicate live report.

`billing_tenant_usage()` now separates storage by backend:

- `storage_local_bytes` counts local attachment files
- `storage_r2_bytes` counts attachments already moved to Cloudflare R2
- `storage_unknown_bytes` keeps future/unknown drivers visible
- `storage_bytes` remains the billing total used for included storage and Stripe
  overage reporting

Monthly abuse-monitoring counters are stored in `billing_usage_events` for
outbound email and API volume. Inbound email volume is read from
`email_ingest_logs` and attributed by `tenant_id` or by the linked ticket tenant.
These counters are visible in Billing and Platform, but they are not billed in
Stripe yet.

## Test coverage

The E2E suite verifies:

- public workspace signup still works
- platform admins can see created workspaces
- Stripe webhook rejects invalid signatures
- signed subscription webhooks update tenant billing state
- failed invoice grace, suspension, and paid-invoice reactivation are enforced
- storage usage and the single-plan billing UI render on Platform and Billing
- local/R2 storage breakdown and email/API usage counters are surfaced in UI
- dry-run metered usage reporting creates an idempotent local usage report
- canceled tenant admins are redirected to the Billing page

Run locally:

```bash
npm run e2e
```
