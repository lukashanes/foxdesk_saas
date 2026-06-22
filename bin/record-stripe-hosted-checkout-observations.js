#!/usr/bin/env node
/**
 * Safely record operator observations for BILLING-002 hosted Checkout evidence.
 *
 * This helper patches the redacted evidence JSON after an operator completes
 * Stripe-hosted Checkout and Customer Portal checks. It refuses common secrets
 * and full hosted URLs so the evidence stays shareable.
 */

const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);

function usage() {
  console.error(`Usage: node bin/record-stripe-hosted-checkout-observations.js <evidence.json> [options]

Options:
  --checkout-completed
  --redirected-back
  --billing-address
  --vat-id
  --reverse-charge
  --completed-at <iso8601>
  --tax-result <summary>
  --portal-opened
  --portal-payment-method
  --portal-billing-address
  --portal-invoice-details
  --portal-vat-id
  --portal-cancellation
  --subscription-cleaned
  --notes <safe-note>
  --json

The helper only records observations. It does not complete Checkout, cancel
subscriptions, or change Stripe/FoxDesk state.
`);
}

function argValue(name, fallback = '') {
  const prefix = `${name}=`;
  const inline = args.find((arg) => arg.startsWith(prefix));
  if (inline) return inline.slice(prefix.length);
  const index = args.indexOf(name);
  if (index >= 0 && args[index + 1] && !args[index + 1].startsWith('--')) return args[index + 1];
  return fallback;
}

function setTrue(data, pointer) {
  const parts = pointer.split('.');
  let target = data;
  while (parts.length > 1) {
    const part = parts.shift();
    if (!target[part] || typeof target[part] !== 'object') target[part] = {};
    target = target[part];
  }
  target[parts[0]] = true;
}

function setValue(data, pointer, value) {
  const parts = pointer.split('.');
  let target = data;
  while (parts.length > 1) {
    const part = parts.shift();
    if (!target[part] || typeof target[part] !== 'object') target[part] = {};
    target = target[part];
  }
  target[parts[0]] = value;
}

function assertSafe(value, label) {
  const source = String(value || '');
  const forbidden = [
    ['Stripe secret key', /sk_(live|test)_[A-Za-z0-9]+/],
    ['full Checkout URL', /https:\/\/checkout\.stripe\.com\/[^\s"']+/],
    ['full Portal URL', /https:\/\/billing\.stripe\.com\/[^\s"']+/],
    ['test card number', /4242[\s-]?4242[\s-]?4242[\s-]?4242/],
    ['likely email address', /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i],
    ['likely VAT ID', /\b(CZ|SK|DE|AT|PL|FR|IT|ES|NL|BE|IE|DK|SE|FI|PT|HU|RO|BG|HR|SI|LT|LV|EE|LU|MT|CY|EL)[A-Z0-9]{8,12}\b/i],
  ];
  const hit = forbidden.find(([, pattern]) => pattern.test(source));
  if (hit) {
    throw new Error(`${label} contains sensitive data: ${hit[0]}. Record a redacted observation instead.`);
  }
}

function assertSafeSerializedEvidence(source) {
  assertSafe(source, 'Evidence file');
}

function main() {
  if (args.includes('--help') || args.includes('-h')) {
    usage();
    process.exit(0);
  }

  const json = args.includes('--json');
  const filePath = args.find((arg) => !arg.startsWith('--'));
  if (!filePath) {
    usage();
    process.exit(2);
  }

  const resolved = path.resolve(filePath);
  const data = JSON.parse(fs.readFileSync(resolved, 'utf8'));
  const changes = [];

  const flagMap = [
    ['--checkout-completed', 'checkout.completed', 'checkout completed'],
    ['--redirected-back', 'checkout.redirected_back_to_foxdesk', 'redirected back'],
    ['--billing-address', 'checkout.billing_address_collected', 'billing address collected'],
    ['--vat-id', 'checkout.vat_id_collected', 'VAT ID collected'],
    ['--reverse-charge', 'checkout.reverse_charge_or_zero_rate_observed', 'reverse-charge or zero-rate observed'],
    ['--portal-opened', 'customer_portal.opened', 'Customer Portal opened'],
    ['--portal-payment-method', 'customer_portal.payment_method_update_observed', 'Portal payment method controls observed'],
    ['--portal-billing-address', 'customer_portal.billing_address_update_observed', 'Portal billing address controls observed'],
    ['--portal-invoice-details', 'customer_portal.invoice_details_observed', 'Portal invoice details observed'],
    ['--portal-vat-id', 'customer_portal.vat_id_update_observed', 'Portal VAT ID controls observed'],
    ['--portal-cancellation', 'customer_portal.cancellation_observed', 'Portal cancellation controls observed'],
    ['--subscription-cleaned', 'cleanup.subscription_cancelled', 'temporary subscription cleaned'],
  ];

  for (const [flag, pointer, label] of flagMap) {
    if (args.includes(flag)) {
      setTrue(data, pointer);
      changes.push(label);
    }
  }

  const completedAt = argValue('--completed-at');
  if (completedAt) {
    if (Number.isNaN(Date.parse(completedAt))) {
      throw new Error('--completed-at must be a valid ISO-8601 timestamp.');
    }
    setValue(data, 'checkout.completed_at', new Date(completedAt).toISOString());
    changes.push('checkout completion timestamp recorded');
  }

  const taxResult = argValue('--tax-result');
  if (taxResult) {
    assertSafe(taxResult, '--tax-result');
    setValue(data, 'checkout.tax_result_observed', taxResult.trim());
    changes.push('tax result summary recorded');
  }

  const notes = argValue('--notes');
  if (notes) {
    assertSafe(notes, '--notes');
    const existing = typeof data.notes === 'string' && data.notes.trim() !== '' ? data.notes.trim() : '';
    setValue(data, 'notes', existing ? `${existing}\n${notes.trim()}` : notes.trim());
    changes.push('notes appended');
  }

  if (changes.length === 0) {
    throw new Error('No observations were provided. Pass at least one recording flag.');
  }

  const serialized = JSON.stringify(data, null, 2);
  assertSafeSerializedEvidence(serialized);
  fs.writeFileSync(resolved, `${serialized}\n`);

  const summary = {
    ok: true,
    file: resolved,
    changes,
    next_step: 'Run npm run stripe:hosted-checkout:checklist -- <file>, then verify when no checklist items remain.',
  };
  if (json) {
    console.log(JSON.stringify(summary, null, 2));
  } else {
    console.log(`Updated Stripe hosted Checkout evidence: ${resolved}`);
    for (const change of changes) console.log(`- ${change}`);
    console.log(summary.next_step);
  }
}

try {
  main();
} catch (error) {
  const message = `Stripe hosted Checkout observation recording FAILED: ${error.message}`;
  if (args.includes('--json')) {
    console.log(JSON.stringify({ ok: false, errors: [error.message] }, null, 2));
  } else {
    console.error(message);
  }
  process.exit(1);
}
