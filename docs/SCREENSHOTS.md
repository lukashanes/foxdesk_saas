# FoxDesk Cloud Screenshots

FoxDesk Cloud public website screenshots are generated from the populated local
SaaS app and stored as optimized public assets.

Regenerate them from the repository root:

```bash
npm run local:seed
npm run local:screenshots
```

Generated public assets:

- `assets/public/dashboard-light.webp`
- `assets/public/dashboard-dark.webp`
- `assets/public/ticket-detail-light.webp`
- `assets/public/ticket-detail-dark.webp`
- `assets/public/time-report-light.webp`
- `assets/public/time-report-dark.webp`
- `assets/public/FoxDesk_preview.jpg`

The open-source self-hosted repository stores its screenshot evidence separately
under `docs/screenshots`. Do not copy SaaS platform screenshots into the
self-hosted release channel.
