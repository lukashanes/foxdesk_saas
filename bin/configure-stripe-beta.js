#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const API_VERSION = '2026-02-25.clover';
const BASE_URL = 'https://api.stripe.com/v1';
const WEBHOOK_EVENTS = [
  'checkout.session.completed',
  'customer.subscription.created',
  'customer.subscription.updated',
  'customer.subscription.deleted',
  'invoice.paid',
  'invoice.payment_failed',
];

const args = new Set(process.argv.slice(2));
const dryRun = args.has('--dry-run');
const json = args.has('--json');
const writeEnvArg = process.argv.find((arg) => arg.startsWith('--write-env='));
const writeEnvPath = writeEnvArg ? path.resolve(writeEnvArg.slice('--write-env='.length)) : '';

function env(name, fallback = '') {
  return process.env[name] && process.env[name].trim() !== '' ? process.env[name].trim() : fallback;
}

function maskSecret(value) {
  if (!value) return '';
  if (value.length <= 12) return '***';
  return `${value.slice(0, 8)}...${value.slice(-4)}`;
}

function formBody(params) {
  const body = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (Array.isArray(value)) {
      const arrayKey = key.endsWith('[]') ? key : `${key}[]`;
      value.forEach((item) => body.append(arrayKey, item));
    } else if (value !== undefined && value !== null) {
      body.append(key, String(value));
    }
  }
  return body;
}

async function stripeRequest(method, stripePath, params = {}) {
  const secret = env('STRIPE_SECRET_KEY');
  if (!secret) {
    throw new Error('STRIPE_SECRET_KEY is required.');
  }

  let url = `${BASE_URL}/${stripePath.replace(/^\/+/, '')}`;
  const init = {
    method,
    headers: {
      Authorization: `Bearer ${secret}`,
      'Stripe-Version': API_VERSION,
    },
  };

  if (method === 'GET') {
    const query = formBody(params).toString();
    if (query) url += `?${query}`;
  } else {
    init.headers['Content-Type'] = 'application/x-www-form-urlencoded';
    init.body = formBody(params);
  }

  const response = await fetch(url, init);
  const text = await response.text();
  const data = text ? JSON.parse(text) : {};
  if (!response.ok) {
    const message = data?.error?.message || `${method} ${stripePath} failed with HTTP ${response.status}`;
    throw new Error(message);
  }
  return data;
}

function portalParams() {
  return {
    active: 'true',
    default_return_url: env('STRIPE_PORTAL_RETURN_URL', 'https://app.foxdesk.net/index.php?page=billing'),
    'business_profile[headline]': 'FoxDesk Cloud billing',
    'business_profile[privacy_policy_url]': 'https://foxdesk.net/index.php?page=legal&type=privacy',
    'business_profile[terms_of_service_url]': 'https://foxdesk.net/index.php?page=legal&type=terms',
    'features[customer_update][enabled]': 'true',
    'features[customer_update][allowed_updates][]': ['address', 'email', 'name', 'tax_id'],
    'features[invoice_history][enabled]': 'true',
    'features[payment_method_update][enabled]': 'true',
    'features[subscription_cancel][enabled]': 'true',
    'features[subscription_cancel][mode]': 'at_period_end',
    'features[subscription_cancel][proration_behavior]': 'none',
    'features[subscription_cancel][cancellation_reason][enabled]': 'true',
    'features[subscription_cancel][cancellation_reason][options][]': [
      'too_expensive',
      'missing_features',
      'switched_service',
      'unused',
      'other',
    ],
    'features[subscription_update][enabled]': 'false',
    'metadata[app]': 'foxdesk',
    'metadata[purpose]': 'public_beta',
  };
}

async function configurePortal() {
  if (dryRun) {
    return { action: 'dry_run', id: null };
  }

  const list = await stripeRequest('GET', 'billing_portal/configurations', { limit: 100 });
  const existing = (list.data || []).find((item) => item.active && item.metadata?.app === 'foxdesk')
    || (list.data || []).find((item) => item.active);
  const params = portalParams();

  if (existing) {
    const updated = await stripeRequest('POST', `billing_portal/configurations/${existing.id}`, params);
    return { action: 'updated', id: updated.id, is_default: Boolean(updated.is_default) };
  }

  const created = await stripeRequest('POST', 'billing_portal/configurations', params);
  return { action: 'created', id: created.id, is_default: Boolean(created.is_default) };
}

async function configureWebhook() {
  const webhookUrl = env('STRIPE_WEBHOOK_URL', 'https://app.foxdesk.net/index.php?page=stripe-webhook');
  if (dryRun) {
    return { action: 'dry_run', id: null, url: webhookUrl, enabled_events: WEBHOOK_EVENTS };
  }

  const list = await stripeRequest('GET', 'webhook_endpoints', { limit: 100 });
  const existing = (list.data || []).find((item) => item.url === webhookUrl);
  const params = {
    url: webhookUrl,
    description: 'FoxDesk production billing webhooks',
    enabled_events: WEBHOOK_EVENTS,
    'metadata[app]': 'foxdesk',
    'metadata[purpose]': 'public_beta_billing',
  };

  if (existing) {
    const updated = await stripeRequest('POST', `webhook_endpoints/${existing.id}`, {
      ...params,
      disabled: 'false',
    });
    return { action: 'updated', id: updated.id, url: updated.url, enabled_events: updated.enabled_events };
  }

  const created = await stripeRequest('POST', 'webhook_endpoints', params);
  return {
    action: 'created',
    id: created.id,
    url: created.url,
    enabled_events: created.enabled_events,
    webhook_secret: created.secret || '',
  };
}

async function verifyPrice(id, expected) {
  if (!id) {
    return { id, ok: false, error: 'missing' };
  }
  if (dryRun) {
    return { id, ok: true, dry_run: true };
  }

  const price = await stripeRequest('GET', `prices/${id}`);
  const checks = {
    active: price.active === true,
    currency: String(price.currency || '').toLowerCase() === expected.currency,
    amount: Number(price.unit_amount || 0) === expected.amount,
    interval: price.recurring?.interval === expected.interval,
    tax_behavior: price.tax_behavior === 'exclusive',
  };
  if (expected.usage_type) {
    checks.usage_type = price.recurring?.usage_type === expected.usage_type;
  }

  return {
    id,
    ok: Object.values(checks).every(Boolean),
    checks,
    product: price.product,
  };
}

function writeGeneratedEnv(result) {
  if (!writeEnvPath) return null;

  const lines = [
    '# Generated by bin/configure-stripe-beta.js. Do not commit.',
    `STRIPE_PRICE_CLOUD_BASE=${env('STRIPE_PRICE_CLOUD_BASE', 'price_1TduGWLE0xWWZe199qWeD07B')}`,
    `STRIPE_PRICE_STORAGE_OVERAGE=${env('STRIPE_PRICE_STORAGE_OVERAGE', 'price_1TduGXLE0xWWZe19fwYt9nIF')}`,
    `STRIPE_PORTAL_CONFIGURATION_ID=${result.portal?.id || ''}`,
  ];

  if (result.webhook?.webhook_secret) {
    lines.push(`STRIPE_WEBHOOK_SECRET=${result.webhook.webhook_secret}`);
  }

  fs.writeFileSync(writeEnvPath, `${lines.join('\n')}\n`, { mode: 0o600 });
  return writeEnvPath;
}

async function main() {
  const result = {
    stripe_key: maskSecret(env('STRIPE_SECRET_KEY')),
    dry_run: dryRun,
    prices: {
      cloud_base: await verifyPrice(env('STRIPE_PRICE_CLOUD_BASE', 'price_1TduGWLE0xWWZe199qWeD07B'), {
        currency: 'eur',
        amount: 990,
        interval: 'month',
      }),
      storage_overage: await verifyPrice(env('STRIPE_PRICE_STORAGE_OVERAGE', 'price_1TduGXLE0xWWZe19fwYt9nIF'), {
        currency: 'eur',
        amount: 190,
        interval: 'month',
        usage_type: 'metered',
      }),
    },
    portal: await configurePortal(),
    webhook: await configureWebhook(),
  };

  const envFile = writeGeneratedEnv(result);
  if (envFile) {
    result.generated_env_file = envFile;
  }

  const safeResult = JSON.parse(JSON.stringify(result));
  if (safeResult.webhook?.webhook_secret) {
    safeResult.webhook.webhook_secret = maskSecret(safeResult.webhook.webhook_secret);
  }

  if (json) {
    console.log(JSON.stringify(safeResult, null, 2));
    return;
  }

  console.log(`Stripe key: ${safeResult.stripe_key || 'missing'}`);
  console.log(`Cloud price: ${safeResult.prices.cloud_base.ok ? 'OK' : 'CHECK'} ${safeResult.prices.cloud_base.id}`);
  console.log(`Storage price: ${safeResult.prices.storage_overage.ok ? 'OK' : 'CHECK'} ${safeResult.prices.storage_overage.id}`);
  console.log(`Portal: ${safeResult.portal.action}${safeResult.portal.id ? ` ${safeResult.portal.id}` : ''}`);
  console.log(`Webhook: ${safeResult.webhook.action}${safeResult.webhook.id ? ` ${safeResult.webhook.id}` : ''}`);
  if (safeResult.webhook.webhook_secret) {
    console.log(`Webhook secret: ${safeResult.webhook.webhook_secret}`);
    console.log('Full webhook secret was only written if --write-env was used.');
  }
  if (safeResult.generated_env_file) {
    console.log(`Generated env file: ${safeResult.generated_env_file}`);
  }
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
