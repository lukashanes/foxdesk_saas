# Stripe Public Beta Setup

FoxDesk public beta uses one Stripe product, one base recurring price, one metered storage price, Stripe Tax, Customer Portal, and signed webhooks.

The app implements:

- 14-day trial without payment during signup
- Checkout subscription activation after trial
- Stripe Tax with EU VAT ID collection
- Customer Portal for invoices, payment method, VAT ID, billing address, and cancellation
- Webhook-driven workspace access state
- Metered extra storage reporting

## Dashboard Setup

Use live mode only when you are ready to connect production. Use test mode first if you want to rehearse the flow with test cards.

### 1. Stripe Tax

1. Open Stripe Dashboard.
2. Go to `Settings`.
3. Open `Tax`.
4. Enable Stripe Tax.
5. Set business details for Aenze s.r.o.
6. Set default tax behavior to `exclusive`.
7. Keep prices exclusive so valid EU VAT payers see reverse-charge or zero-rate handling instead of paying VAT-inclusive totals.

### 2. Product And Base Price

1. Open `Product catalog`.
2. Create product `FoxDesk Cloud`.
3. Add a recurring monthly price:
   - amount: `EUR 9.90`
   - billing period: monthly
   - tax behavior: `exclusive`
   - product tax code: software/SaaS business use
4. Copy the live price id into `STRIPE_PRICE_CLOUD_BASE`.

Current live FoxDesk value:

```text
STRIPE_PRICE_CLOUD_BASE=price_1TduGWLE0xWWZe199qWeD07B
```

Do not encode the temporary launch discount in marketing text inside Stripe. The live beta price is simply the current price.

### 3. Metered Storage Price

1. Stay on product `FoxDesk Cloud`.
2. Add a recurring usage-based monthly price for extra storage:
   - amount: `EUR 1.90`
   - unit: one extra started GB
   - tax behavior: `exclusive`
3. Copy the live price id into `STRIPE_PRICE_STORAGE_OVERAGE`.

Current live FoxDesk value:

```text
STRIPE_PRICE_STORAGE_OVERAGE=price_1TduGXLE0xWWZe19fwYt9nIF
```
4. Create or confirm a Billing meter whose event name is exactly:

```text
foxdesk_storage_extra_gb
```

5. Keep `STRIPE_STORAGE_METER_EVENT_NAME=foxdesk_storage_extra_gb`.

### 4. Customer Portal

1. Open `Settings`.
2. Open `Billing`.
3. Open `Customer portal`.
4. Enable:
   - invoices
   - payment method update
   - billing address update
   - tax ID update
   - subscription cancellation
5. Save the portal configuration.
6. If Stripe shows a portal configuration id, put it into `STRIPE_PORTAL_CONFIGURATION_ID`. If not, FoxDesk uses the first active configuration.

### 5. Checkout Settings

FoxDesk creates Checkout Sessions through the API. In Stripe Dashboard only verify that payment methods you want are enabled for EUR subscriptions.

FoxDesk sends these important Checkout values:

- `mode=subscription`
- base recurring price
- optional metered storage price
- `automatic_tax[enabled]=true`
- `tax_id_collection[enabled]=true`
- tenant metadata
- existing FoxDesk trial end, if the workspace is still in trial

### 6. Webhook Endpoint

1. Open `Developers`.
2. Open `Webhooks`.
3. Add endpoint:

```text
https://app.foxdesk.net/index.php?page=stripe-webhook
```

4. Select these events:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.paid`
   - `invoice.payment_failed`
5. Copy the signing secret into `STRIPE_WEBHOOK_SECRET`.

### 7. Restricted API Key

Prefer a restricted live key for production. FoxDesk needs write/read access for:

- Customers
- Checkout Sessions
- Customer Portal
- Subscriptions
- Invoices
- Prices
- Products
- Billing Meters / Meter Events
- Webhook endpoint reads are not required by the app runtime

Copy the key into `STRIPE_SECRET_KEY`. Never put the key into git.

## Production Env Values

```env
BILLING_ENABLED=true
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_CLOUD_BASE=price_...
STRIPE_PRICE_STORAGE_OVERAGE=price_...
STRIPE_STORAGE_METER_EVENT_NAME=foxdesk_storage_extra_gb
STRIPE_TAX_ENABLED=true
STRIPE_TAX_ID_COLLECTION_ENABLED=true
STRIPE_TAX_ID_COLLECTION_REQUIRED=
BILLING_CURRENCY=EUR
BILLING_CLOUD_BASE_PRICE_CENTS=990
BILLING_STORAGE_OVERAGE_PRICE_CENTS=190
BILLING_INCLUDED_STORAGE_BYTES=1073741824
BILLING_TRIAL_DAYS=14
```

## Acceptance Checks

Stripe beta setup is done when:

- signup creates a trial workspace without card
- Billing page shows `Add billing` during trial
- Checkout collects billing address and VAT ID
- valid EU VAT ID changes tax treatment in Checkout
- Checkout completion returns to FoxDesk
- signed webhook marks workspace active
- Customer Portal lets the admin update card, invoice details, billing address, and VAT ID
- failed payment moves workspace to past due, then suspended after grace
- paid invoice restores active access
- storage usage dry-run works before live metering is enabled

Before marking `BILLING-002` as `retested_pass`, capture the hosted Checkout
completion evidence from [Stripe Hosted Checkout Test Runbook](STRIPE_HOSTED_CHECKOUT_TEST_RUNBOOK.md).
The production-safe smoke scripts are necessary, but they are not a substitute
for completing hosted Checkout with card entry, VAT ID entry, tax treatment,
return redirect, webhook receipt, and Customer Portal verification. Validate the
completed evidence with `npm run stripe:hosted-checkout:verify -- path/to/evidence.json`.

## API Fallback

If Stripe Dashboard settings pages fail in an embedded browser, configure the production portal and webhook through the API:

```bash
STRIPE_SECRET_KEY=sk_live_... npm run stripe:beta:configure -- --write-env=.stripe.generated.env
```

The command verifies both live price IDs, creates or updates the Customer Portal configuration, creates or updates the production webhook endpoint, and writes generated values such as `STRIPE_WEBHOOK_SECRET` to `.stripe.generated.env`. The generated env file is gitignored.
