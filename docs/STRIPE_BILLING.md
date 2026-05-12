# Stripe Billing Setup

FoxDesk SaaS has a prepared billing layer for Stripe Checkout, Stripe Customer Portal, and signed Stripe webhooks.

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
define('BILLING_CURRENCY', 'EUR');
define('BILLING_CLOUD_BASE_PRICE_CENTS', 1900);
define('BILLING_STORAGE_OVERAGE_PRICE_CENTS', 79);
define('BILLING_INCLUDED_STORAGE_BYTES', 1073741824);
```

When `BILLING_ENABLED` is `false`, the billing UI remains visible but Checkout and Portal actions return a clear configuration error.

## Stripe products

Create two recurring Prices in Stripe for the single public plan:

- FoxDesk Cloud base subscription -> `STRIPE_PRICE_CLOUD_BASE`
- Metered extra storage per GB -> `STRIPE_PRICE_STORAGE_OVERAGE`

FoxDesk stores `cloud` on the `tenants.plan` field. Checkout adds the base subscription and, when configured, the metered storage price.

Default commercial model:

- unlimited users
- unlimited clients
- unlimited agents
- unlimited tickets
- 1 GB storage included
- EUR 19.00/month base price
- EUR 0.79/extra GB/month storage overage

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

The endpoint verifies the `Stripe-Signature` header with `STRIPE_WEBHOOK_SECRET`. Invalid or stale signatures are rejected before any database update.

## Runtime behavior

- Platform admins can open Checkout or Customer Portal for any workspace from `Platform`.
- Workspace admins can open their own billing page from the user menu.
- Stripe subscription changes update `tenants.stripe_customer_id`, `tenants.stripe_subscription_id`, `tenants.subscription_status`, and `tenants.status`.
- Billing and Platform show storage usage, included storage, billable extra GB, and estimated monthly overage.
- Tenants with `status` set to `suspended` or `canceled` are redirected to Billing instead of normal app pages.

## Test coverage

The E2E suite verifies:

- public workspace signup still works
- platform admins can see created workspaces
- Stripe webhook rejects invalid signatures
- signed subscription webhooks update tenant billing state
- storage usage and the single-plan billing UI render on Platform and Billing
- canceled tenant admins are redirected to the Billing page

Run locally:

```bash
npm run e2e
```
