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
define('STRIPE_PRICE_STARTER', 'price_...');
define('STRIPE_PRICE_PRO', 'price_...');
```

When `BILLING_ENABLED` is `false`, the billing UI remains visible but Checkout and Portal actions return a clear configuration error.

## Stripe products

Create recurring Prices in Stripe for each public plan:

- `starter` -> `STRIPE_PRICE_STARTER`
- `pro` -> `STRIPE_PRICE_PRO`

FoxDesk stores the selected plan on the `tenants.plan` field. Checkout uses that plan to select the matching Stripe Price.

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
- Tenants with `status` set to `suspended` or `canceled` are redirected to Billing instead of normal app pages.

## Test coverage

The E2E suite verifies:

- public workspace signup still works
- platform admins can see created workspaces
- Stripe webhook rejects invalid signatures
- signed subscription webhooks update tenant billing state
- canceled tenant admins are redirected to the Billing page

Run locally:

```bash
npm run e2e
```
