# FoxDesk Agent Ticket Workflow

This is the canonical workflow for agents that create tickets, add comments,
and record tracked work without creating disconnected or duplicate records.

## Basic rules

- Use only the FoxDesk Agent API. Never use a web browser.
- At the start of every session, call `agent-docs`, then verify your identity
  with `agent-me`.
- Read the API key from `FOXDESK_API_TOKEN`. Never write it to a ticket,
  documentation, chat, screenshot, or output.
- Before changing an existing ticket, always call `agent-get-ticket`.
- Every POST request must contain a unique `Idempotency-Key`.

## Main ticket

The main ticket contains only:

- a concise work title;
- a short general description;
- the client and assignee;
- the status and priority.

Do not put minutes, total time, a day-by-day breakdown, a detailed agenda, a
timer, or a time entry in the main ticket body.

## Comments without tracked time

Use `agent-add-update` when the comment should not change worked or billable
time. Do not include any time fields in this request.

## Tracked work entries

Use one `agent-add-work-entry` request for each work record. Send the comment
and duration together so FoxDesk creates the comment and linked time entry in
one transaction:

```html
<p><strong>13 Jul 2026 - 27 min</strong></p>
<ul>
  <li>Adjusted campaign budgets based on performance.</li>
  <li>Reviewed the bidding strategy for the accessories campaign.</li>
</ul>
```

The request must include `content` and `duration_minutes`. Include `started_at`
and `ended_at` when the exact work interval is known. Set
`skip_notification:true` when the client should not receive an email. Never
create a separate comment and time entry for the same work.

## Verification

After finishing, call `agent-get-ticket` and verify:

- the client and assignee are correct;
- the main description is concise and contains no time data;
- the number and order of daily comments are correct;
- every tracked-work comment has one time entry with a non-null `comment_id`;
- `total_time_minutes` equals the sum of saved time entries;
- no duplicate active ticket was created.

Cancel an incorrect ticket only after the correct replacement has been created
and verified.

## Permanent deletion

Permanent deletion is an exceptional administrator action. First call
`agent-delete-ticket-preflight`, show its complete impact, and obtain explicit
confirmation of the exact ticket code. Then call
`agent-delete-ticket-permanently` with `tickets:read`, `delete:write`, and a
unique `Idempotency-Key`. Never use permanent deletion as a status change or
routine cleanup.

The live localized version is returned by:

```text
GET /index.php?page=api&action=agent-docs&instruction_language=en
```

Supported values are `en`, `cs`, `de`, `es`, and `it`.
