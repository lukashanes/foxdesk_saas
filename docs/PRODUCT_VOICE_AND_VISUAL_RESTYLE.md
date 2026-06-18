# Product Voice And Visual Restyle

FoxDesk should feel calm, useful, and a little personal. We write like a capable support teammate: clear first, friendly second, never decorative.

## FoxDesk Voice

- Use plain English as the source copy. Czech and other languages translate from that source.
- Speak to the customer directly, but do not overdo warmth.
- Keep labels short. One clear label is better than a label plus a helper sentence.
- Give the next action when something needs attention.
- Do not expose internal implementation details on public or customer-facing pages.
- Avoid filler such as "This page explains", "Start with", "in one place", "currently", and repeated helper text that restates a heading.

Examples:

- Good: "All clear."
- Good: "We could not process payment. Update billing to keep using FoxDesk."
- Good: "Pick a client and period."
- Avoid: "Start with the queue that needs attention now."
- Avoid: billing-state jargon that tells the user what the database thinks instead of what to do next.

## Visual Direction

The app should be compact and operational. The public website can breathe more, but it must still feel premium and focused.

- Fixed typography scale. Do not scale font size with viewport width.
- Use `letter-spacing: 0` for normal UI text. Uppercase meta labels should be rare.
- Use fewer type sizes, radii, shadows, and local spacing values.
- Prefer dense, aligned tables and lists for operational pages.
- Keep cards for repeated items, modal panels, or framed tools. Do not stack cards inside cards.
- Public pages use more whitespace, stronger hierarchy, and product screenshots that are not cropped by decorative frames.

Reference principles:

- Apple Human Interface Guidelines, Typography: https://developer.apple.com/design/human-interface-guidelines/typography
- IBM Design Language, Type basics: https://www.ibm.com/design/language/typography/type-basics
- Material Design, Layout: https://m2.material.io/design/layout/understanding-layout.html

## Restyle Milestones

### Milestone 1: Voice Baseline

Done when:

- Work, Inbox, Billing, Client, Reports, and global search use short action-oriented copy.
- Empty states are brief and useful.
- Billing warnings explain what happened and what to do next.
- No customer-facing page uses internal setup language.

Verification:

- `npm run test:product-copy`
- `npm run test:edition-parity`

### Milestone 2: Design Tokens

Done when:

- Type, spacing, radius, border, and shadow tokens are centralized.
- Existing pages stop introducing new one-off font sizes and radii.
- Normal UI text has `letter-spacing: 0`.

### Milestone 3: App Shell

Done when:

- Navigation, top bar, search, collapsed sidebar, and content width behave consistently across workspace and platform admin.
- Workspace admin and platform admin are visually distinct.
- Desktop and mobile screenshots show no clipped text or broken spacing.

### Milestone 4: Core Workflows

Done when:

- Work, Inbox, Tickets, Ticket detail, Client, Reports, and Billing use the same density, hierarchy, and empty-state model.
- Primary actions are visually obvious.
- Secondary metadata moves out of the main reading path.

### Milestone 5: Public Web

Done when:

- Public pricing is simple and does not show internal economics.
- Product screenshots sit in their own section, not inside the pricing card.
- Marketing copy leads with helpdesk, time tracking, unlimited users, and simple billing.

### Milestone 6: Email Voice

Done when:

- One user action creates at most one meaningful notification.
- Email subjects are specific.
- HTML emails have readable spacing, line breaks, and a clear next action.

### Milestone 7: Visual QA

Done when:

- Browser screenshots pass desktop and mobile review for public web, login, Work, Inbox, Tickets, Billing, Reports, Client, and Platform.
- Automated smoke tests pass locally and in production.
- The CSS audit shows fewer unique font sizes, radii, and shadow styles than the baseline.
