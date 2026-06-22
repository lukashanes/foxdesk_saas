#!/usr/bin/env node
/**
 * Verify the operator evidence for a completed Stripe-hosted Checkout flow.
 *
 * This command intentionally validates an evidence file instead of attempting
 * to charge a live card. It is the machine-checkable final gate for BILLING-002
 * after an operator completes the hosted Checkout/Portal runbook.
 */

const fs = require('fs');
const path = require('path');

function usage() {
  console.error('Usage: node bin/verify-stripe-hosted-checkout-evidence.js <evidence.json> [--json]');
}

function readJson(filePath) {
  const resolved = path.resolve(filePath);
  const source = fs.readFileSync(resolved, 'utf8');
  return { resolved, source, data: JSON.parse(source) };
}

function has(value) {
  return typeof value === 'string' && value.trim() !== '';
}

function boolAt(data, pointer) {
  return pointer.split('.').reduce((value, key) => (value && value[key] !== undefined ? value[key] : undefined), data) === true;
}

function valueAt(data, pointer) {
  return pointer.split('.').reduce((value, key) => (value && value[key] !== undefined ? value[key] : undefined), data);
}

function addCheck(checks, name, ok, detail) {
  checks.push({ name, ok: Boolean(ok), detail });
}

function validateEvidence(source, data) {
  const checks = [];
  const failures = [];

  const mode = String(data.stripe_mode || '').trim();
  addCheck(checks, 'Stripe mode is explicit', ['test', 'live'].includes(mode), 'stripe_mode must be test or live.');
  addCheck(
    checks,
    'Live mode is explicitly approved',
    mode !== 'live' || data.approved_live_validation === true,
    'live evidence must set approved_live_validation=true.'
  );
  addCheck(
    checks,
    'Temporary workspace evidence',
    boolAt(data, 'workspace.temporary_workspace') && has(valueAt(data, 'workspace.tenant_reference')),
    'evidence must identify a temporary workspace with a redacted tenant reference.'
  );

  for (const [name, pointer] of [
    ['Checkout completed', 'checkout.completed'],
    ['Checkout redirected back to FoxDesk', 'checkout.redirected_back_to_foxdesk'],
    ['Billing address collected', 'checkout.billing_address_collected'],
    ['VAT ID collected', 'checkout.vat_id_collected'],
    ['Reverse-charge or zero-rate observed', 'checkout.reverse_charge_or_zero_rate_observed'],
    ['Checkout completion webhook observed', 'webhooks.checkout_session_completed_observed'],
    ['Paid or active subscription webhook observed', 'webhooks.invoice_paid_or_subscription_active_observed'],
    ['Failed payment behavior observed', 'webhooks.invoice_payment_failed_observed'],
    ['Subscription deleted or cancelled observed', 'webhooks.subscription_deleted_or_cancelled_observed'],
    ['Customer Portal opened', 'customer_portal.opened'],
    ['Portal payment method update observed', 'customer_portal.payment_method_update_observed'],
    ['Portal billing address update observed', 'customer_portal.billing_address_update_observed'],
    ['Portal invoice details observed', 'customer_portal.invoice_details_observed'],
    ['Portal VAT ID update observed', 'customer_portal.vat_id_update_observed'],
    ['Portal cancellation observed', 'customer_portal.cancellation_observed'],
    ['Temporary subscription cleaned', 'cleanup.subscription_cancelled'],
    ['Temporary Stripe customer cleaned', 'cleanup.stripe_customer_deleted_or_marked_test'],
    ['Temporary workspace cleaned', 'cleanup.temporary_workspace_cleaned_or_disabled'],
    ['Safe billing flow smoke passed', 'safe_smoke_commands.stripe_billing_flow_ok'],
    ['Safe webhook lifecycle smoke passed', 'safe_smoke_commands.stripe_webhook_lifecycle_ok'],
  ]) {
    addCheck(checks, name, boolAt(data, pointer), `${pointer} must be true.`);
  }

  addCheck(checks, 'Checkout host is Stripe', valueAt(data, 'checkout.host') === 'checkout.stripe.com', 'checkout.host must be checkout.stripe.com.');
  addCheck(checks, 'Portal host is Stripe', valueAt(data, 'customer_portal.host') === 'billing.stripe.com', 'customer_portal.host must be billing.stripe.com.');
  addCheck(checks, 'Checkout completed timestamp recorded', has(valueAt(data, 'checkout.completed_at')), 'checkout.completed_at must be set.');
  addCheck(checks, 'Tax result summary recorded', has(valueAt(data, 'checkout.tax_result_observed')), 'checkout.tax_result_observed must describe the observed tax result.');

  const eventIds = Array.isArray(valueAt(data, 'webhooks.event_ids_redacted')) ? valueAt(data, 'webhooks.event_ids_redacted') : [];
  addCheck(checks, 'Webhook event ids redacted', eventIds.length > 0, 'webhooks.event_ids_redacted must contain redacted event references.');

  const forbiddenPatterns = [
    ['Stripe secret key', /sk_(live|test)_[A-Za-z0-9]+/],
    ['full Checkout URL', /https:\/\/checkout\.stripe\.com\/[^\s"']+/],
    ['full Portal URL', /https:\/\/billing\.stripe\.com\/[^\s"']+/],
    ['test card number', /4242[\s-]?4242[\s-]?4242[\s-]?4242/],
    ['likely email address', /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i],
  ];
  for (const [label, pattern] of forbiddenPatterns) {
    addCheck(checks, `No ${label}`, !pattern.test(source), `evidence must not include ${label}.`);
  }

  for (const check of checks) {
    if (!check.ok) {
      failures.push(`${check.name}: ${check.detail}`);
    }
  }

  return {
    ok: failures.length === 0,
    checks,
    failures,
  };
}

function main() {
  const args = process.argv.slice(2);
  const json = args.includes('--json');
  const filePath = args.find((arg) => arg !== '--json') || process.env.STRIPE_HOSTED_CHECKOUT_EVIDENCE_PATH;

  if (!filePath) {
    usage();
    process.exit(2);
  }

  try {
    const { resolved, source, data } = readJson(filePath);
    const result = validateEvidence(source, data);
    result.file = resolved;

    if (json) {
      console.log(JSON.stringify(result, null, 2));
    } else if (result.ok) {
      console.log('Stripe hosted Checkout evidence OK');
    } else {
      console.error('Stripe hosted Checkout evidence FAILED');
      for (const failure of result.failures) {
        console.error(`- ${failure}`);
      }
    }

    process.exit(result.ok ? 0 : 1);
  } catch (error) {
    const result = {
      ok: false,
      failures: [error.message],
    };
    if (json) {
      console.log(JSON.stringify(result, null, 2));
    } else {
      console.error(`Stripe hosted Checkout evidence FAILED: ${error.message}`);
    }
    process.exit(1);
  }
}

if (require.main === module) {
  main();
}

module.exports = {
  validateEvidence,
};
