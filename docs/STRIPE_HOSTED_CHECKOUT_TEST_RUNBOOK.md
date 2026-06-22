# Stripe Hosted Checkout Test Runbook

This runbook is the final evidence path for `BILLING-002`.

Use it when proving that a real Stripe-hosted Checkout and Customer Portal flow
works end to end. The production-safe smoke scripts prove that FoxDesk creates
valid Checkout/Portal sessions and handles lifecycle webhooks. This runbook is
for the part that cannot be honestly proven without completing hosted Checkout:
card entry, VAT ID entry, Stripe Tax treatment, redirect back to FoxDesk, and
the follow-up Portal lifecycle.

## Safety Rules

- Prefer Stripe test mode for the first full hosted Checkout completion.
- Do not use live payment details unless this is an intentional production
  validation by the operator.
- Use a dedicated temporary test workspace/customer, never an active paid
  customer workspace.
- Redact Checkout Session URLs, Customer Portal URLs, customer IDs,
  subscription IDs, payment method details, VAT IDs, and email addresses in any
  shared evidence.
- Keep screenshots only when they do not expose full hosted session URLs or
  sensitive customer details.
- If using live mode, cancel and clean the temporary subscription/customer after
  evidence is captured.

## Preflight

Run the existing safe checks first:

```bash
npm run stripe:hosted-checkout:preflight -- --run-safe-smoke
php bin/test-stripe-billing-flow.php --json
php bin/test-stripe-webhook-lifecycle.php --json
```

Required result:

- `ok=true` for both commands
- Checkout host is `checkout.stripe.com`
- Portal host is `billing.stripe.com`
- automatic tax check passes
- tax ID collection check passes
- webhook lifecycle covers checkout completion, failed payment, paid recovery,
  duplicate guard, and subscription deletion

To prepare the redacted evidence file and merge those safe smoke results in one
step, run:

```bash
npm run stripe:hosted-checkout:prepare -- --run-smoke --mode test --out tmp/stripe-hosted-checkout-evidence.json
```

Run this in the environment that has the intended Stripe configuration loaded
(`BILLING_ENABLED`, `STRIPE_SECRET_KEY`, base price, storage price, tax and VAT
settings). On a local machine without PHP or Stripe env, the helper uses
`./bin/run-php.sh` but will stop with explicit missing-config errors instead of
creating misleading evidence.

Use `--mode live --approved-live` only for an intentional live validation by the
operator. The prepare command does not complete Checkout and does not make the
evidence pass. It only fills the safe API smoke fields and leaves hosted
Checkout, VAT, Portal, and cleanup observations for the operator to complete.

To see the remaining manual evidence as a grouped checklist, run:

```bash
npm run stripe:hosted-checkout:checklist -- tmp/stripe-hosted-checkout-evidence.json
```

## Hosted Checkout Completion

1. Create a temporary workspace through signup or platform admin.
2. Open Billing inside that workspace.
3. Start Checkout from the workspace Billing page.
4. Confirm the browser is on Stripe-hosted Checkout.
5. Enter a valid card for the selected Stripe mode.
6. Enter a billing address.
7. Enter a VAT ID when testing EU business treatment.
8. Verify Checkout shows the expected tax treatment:
   - VAT is collected when due.
   - Valid EU VAT ID business treatment changes the tax result through Stripe
     Tax, including reverse-charge or zero-rate where applicable.
9. Complete Checkout.
10. Confirm Stripe redirects back to FoxDesk.
11. Confirm the workspace is active or trialing with a saved Stripe customer and
    subscription.
12. Confirm `checkout.session.completed` and the follow-up invoice/subscription
    events were accepted by the signed webhook endpoint.

## Customer Portal Lifecycle

1. Open Customer Portal from the workspace Billing page.
2. Confirm the browser is on `billing.stripe.com`.
3. Verify the workspace admin can update:
   - payment method
   - billing address
   - invoice details
   - VAT ID
   - subscription cancellation
4. Cancel the temporary subscription if this is a cleanup validation.
5. Confirm FoxDesk receives the cancellation/deletion event and updates tenant
   lifecycle state.

## Failed Payment And Recovery

Use Stripe test mode or controlled webhook simulation for this part.

Required proof:

- `invoice.payment_failed` moves the workspace to past due or grace state.
- repeated payment failure does not shorten an existing grace window
  incorrectly.
- `invoice.paid` restores active access.
- subscription deletion or cancellation moves the temporary workspace out of
  paid active access.
- after grace expires, maintenance can suspend access.

The synthetic lifecycle smoke proves the webhook handler transitions. A full
hosted Stripe evidence package should still mention whether failed-payment
behavior was tested through Stripe test mode, webhook replay, or the local
lifecycle smoke.

## Evidence Checklist

Record the evidence in an operator note or JSON file. Use
`docs/stripe-hosted-checkout-evidence.template.json` as the shape.

Minimum accepted evidence:

- Stripe mode: `test` or explicitly approved `live`
- temporary workspace id or redacted tenant reference
- redacted Checkout Session id
- redacted Stripe customer id
- redacted subscription id
- Checkout completion timestamp
- VAT ID collection observed
- reverse charge / zero-rate / tax-due behavior observed where applicable
- redirect back to FoxDesk observed
- webhook accepted event ids observed or redacted
- Customer Portal opened on `billing.stripe.com`
- Portal card/billing address/invoice/VAT ID/cancel controls observed
- cleanup result for the temporary workspace/customer/subscription
- commands and outputs for:
  - `php bin/test-stripe-billing-flow.php --json`
  - `php bin/test-stripe-webhook-lifecycle.php --json`

After filling the JSON evidence, run:

```bash
npm run stripe:hosted-checkout:verify -- path/to/stripe-hosted-checkout-evidence.json
```

The verifier must pass before `BILLING-002` is moved to `retested_pass`.

For the paid public launch gate, set both values after verification:

```bash
FOXDESK_ACK_STRIPE_LIVE_TESTED=true
STRIPE_HOSTED_CHECKOUT_EVIDENCE_PATH=path/to/stripe-hosted-checkout-evidence.json
npm run launch:go-no-go -- --strict-paid
```

`BILLING-002` can move from `needs_external_smoke` to `retested_pass` only after
this evidence exists and matches the current production or approved test-mode
Stripe configuration.

## References

- Stripe test card values for test mode: https://docs.stripe.com/testing
- Stripe Tax zero-tax and reverse-charge behavior: https://docs.stripe.com/tax/zero-tax
- Stripe Tax EU reverse-charge behavior: https://docs.stripe.com/tax/supported-countries/european-union
