# Cloudflare Email Service

FoxDesk is prepared to send transactional email through Cloudflare Email Service REST API.

## Domain

Use the top-level domain for sending:

```text
foxdesk.net
```

Recommended app host:

```text
app.foxdesk.net
```

Recommended sender identities:

- `noreply@foxdesk.net`
- `support@foxdesk.net`
- `billing@foxdesk.net`
- `security@foxdesk.net`

## Required Cloudflare Setup

In Cloudflare Email Sending:

1. Select zone `foxdesk.net`.
2. Leave the subdomain field blank to use the top-level domain.
3. Let Cloudflare add DNS records for bounce handling, SPF, DKIM, and DMARC.
4. Create an API token with Email Sending permission.

Cloudflare docs:

- REST endpoint: `POST https://api.cloudflare.com/client/v4/accounts/{account_id}/email/sending/send`
- Authentication: `Authorization: Bearer <API_TOKEN>`

## FoxDesk Config

Set these in production config or environment:

```php
define('MAIL_PROVIDER', 'cloudflare');
define('CLOUDFLARE_ACCOUNT_ID', '...');
define('CLOUDFLARE_EMAIL_API_TOKEN', '...');
define('CLOUDFLARE_EMAIL_FROM', 'noreply@foxdesk.net');
define('CLOUDFLARE_EMAIL_FROM_NAME', 'FoxDesk');
define('CLOUDFLARE_EMAIL_REPLY_TO', 'support@foxdesk.net');
```

Optional fallback:

```php
define('MAIL_FALLBACK_ENABLED', '1');
```

Keep fallback disabled in production unless there is a deliberate SMTP/PHP mail fallback configured and tested.

## Test

Dry-run configuration check:

```bash
php bin/test-cloudflare-email.php --dry-run --json
```

Send a real email:

```bash
php bin/test-cloudflare-email.php --to=you@example.com --json
```

The command uses the same mailer path as password resets, ticket notifications, report emails, and system emails.
