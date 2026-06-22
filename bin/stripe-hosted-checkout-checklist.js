#!/usr/bin/env node
/**
 * Turn hosted Checkout evidence verifier failures into an operator checklist.
 *
 * This is intentionally a thin wrapper around the verifier. It does not lower
 * the BILLING-002 bar; it makes the remaining manual hosted Stripe observations
 * easier to complete without missing a field.
 */

const fs = require('fs');
const path = require('path');
const { validateEvidence } = require('./verify-stripe-hosted-checkout-evidence');

function usage() {
  console.error('Usage: node bin/stripe-hosted-checkout-checklist.js <evidence.json> [--json]');
}

function readEvidence(filePath) {
  const resolved = path.resolve(filePath);
  const source = fs.readFileSync(resolved, 'utf8');
  return { resolved, source, data: JSON.parse(source) };
}

function groupForCheck(name) {
  if (name.startsWith('Checkout') || ['Billing address collected', 'VAT ID collected', 'Reverse-charge or zero-rate observed', 'Tax result summary recorded'].includes(name)) {
    return 'Hosted Checkout';
  }
  if (name.startsWith('Portal') || name.startsWith('Customer Portal')) {
    return 'Customer Portal';
  }
  if (name.startsWith('Temporary')) {
    return 'Cleanup';
  }
  if (name.startsWith('No ')) {
    return 'Sensitive Data';
  }
  if (name.includes('Webhook') || name.includes('payment') || name.includes('subscription')) {
    return 'Webhook Evidence';
  }
  return 'Evidence Metadata';
}

function actionForCheck(name) {
  const actions = {
    'Checkout completed': 'Complete the Stripe-hosted Checkout session for the temporary workspace.',
    'Checkout redirected back to FoxDesk': 'After payment, confirm Stripe redirects back to the FoxDesk Billing page.',
    'Billing address collected': 'Enter and confirm the billing address in Stripe Checkout.',
    'VAT ID collected': 'Enter a VAT ID or record why the selected test scenario cannot use one.',
    'Reverse-charge or zero-rate observed': 'Record the observed VAT/tax result shown by Stripe Tax.',
    'Checkout completed timestamp recorded': 'Set checkout.completed_at to the UTC time when Checkout completed.',
    'Tax result summary recorded': 'Fill checkout.tax_result_observed with a short human-readable tax result.',
    'Customer Portal opened': 'Open Customer Portal from FoxDesk Billing and confirm billing.stripe.com.',
    'Portal payment method update observed': 'Confirm the Portal exposes payment method update controls.',
    'Portal billing address update observed': 'Confirm the Portal exposes billing address controls.',
    'Portal invoice details observed': 'Confirm the Portal exposes invoice details.',
    'Portal VAT ID update observed': 'Confirm the Portal exposes VAT/tax ID controls.',
    'Portal cancellation observed': 'Confirm the Portal exposes cancellation controls or complete cancellation for cleanup.',
    'Temporary subscription cleaned': 'Cancel or delete the temporary subscription and set cleanup.subscription_cancelled=true.',
  };

  return actions[name] || 'Update the evidence JSON so this verifier check passes.';
}

function buildChecklist(filePath) {
  const { resolved, source, data } = readEvidence(filePath);
  const result = validateEvidence(source, data);
  const missing = result.checks
    .filter((check) => !check.ok)
    .map((check) => ({
      group: groupForCheck(check.name),
      check: check.name,
      action: actionForCheck(check.name),
      detail: check.detail,
    }));

  const groups = {};
  for (const item of missing) {
    if (!groups[item.group]) groups[item.group] = [];
    groups[item.group].push(item);
  }

  return {
    ok: result.ok,
    file: resolved,
    missing_count: missing.length,
    groups,
  };
}

function printText(checklist) {
  if (checklist.ok) {
    console.log('Stripe hosted Checkout evidence is complete.');
    console.log(`Evidence: ${checklist.file}`);
    return;
  }

  console.log(`Stripe hosted Checkout evidence needs ${checklist.missing_count} item${checklist.missing_count === 1 ? '' : 's'}.`);
  console.log(`Evidence: ${checklist.file}`);
  for (const [group, items] of Object.entries(checklist.groups)) {
    console.log('');
    console.log(`${group}:`);
    for (const item of items) {
      console.log(`- [ ] ${item.check}: ${item.action}`);
    }
  }
}

function main() {
  const args = process.argv.slice(2);
  const json = args.includes('--json');
  const filePath = args.find((arg) => arg !== '--json');

  if (!filePath) {
    usage();
    process.exit(2);
  }

  const checklist = buildChecklist(filePath);
  if (json) {
    console.log(JSON.stringify(checklist, null, 2));
  } else {
    printText(checklist);
  }
  process.exit(checklist.ok ? 0 : 1);
}

if (require.main === module) {
  try {
    main();
  } catch (error) {
    console.error(`Stripe hosted Checkout checklist FAILED: ${error.message}`);
    process.exit(1);
  }
}

module.exports = {
  buildChecklist,
};
