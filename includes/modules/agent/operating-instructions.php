<?php

/**
 * Canonical operating instructions for external FoxDesk agents.
 *
 * English strings are the source contract. Localized values are loaded directly
 * from the language catalogs so API responses do not mutate the user's UI locale.
 */

function foxdesk_agent_instruction_languages(): array
{
    return ['en', 'cs', 'de', 'es', 'it'];
}

function foxdesk_agent_instruction_language(?string $requested, ?array $user = null): string
{
    $requested = strtolower(trim((string) $requested));
    if (in_array($requested, foxdesk_agent_instruction_languages(), true)) {
        return $requested;
    }

    $user_language = strtolower(trim((string) ($user['language'] ?? '')));
    return in_array($user_language, foxdesk_agent_instruction_languages(), true)
        ? $user_language
        : 'en';
}

function foxdesk_agent_instruction_text(string $key, string $language): string
{
    static $catalogs = [];

    $language = foxdesk_agent_instruction_language($language);
    if (!isset($catalogs[$language])) {
        $path = BASE_PATH . '/includes/lang/' . $language . '.php';
        $catalogs[$language] = is_file($path) ? require $path : [];
    }
    if (!isset($catalogs['en'])) {
        $catalogs['en'] = require BASE_PATH . '/includes/lang/en.php';
    }

    return (string) ($catalogs[$language][$key] ?? $catalogs['en'][$key] ?? $key);
}

function foxdesk_agent_operating_instructions(?string $language = null, ?array $user = null): array
{
    $language = foxdesk_agent_instruction_language($language, $user);
    $tr = static fn(string $key): string => foxdesk_agent_instruction_text($key, $language);

    $daily_example = '<p><strong>' . $tr('13 Jul 2026 - 27 min') . '</strong></p>'
        . '<ul><li>' . $tr('Adjusted campaign budgets based on performance.') . '</li>'
        . '<li>' . $tr('Reviewed the bidding strategy for the accessories campaign.') . '</li></ul>';

    return [
        'schema_version' => 2,
        'language' => $language,
        'available_languages' => foxdesk_agent_instruction_languages(),
        'language_parameter' => 'instruction_language',
        'title' => $tr('Agent instructions: FoxDesk tickets'),
        'basic_rules' => [
            $tr('Use only the FoxDesk Agent API. Never use a web browser.'),
            $tr('At the start of every session, call agent-docs and then verify your identity with agent-me.'),
            $tr('Read the API key from FOXDESK_API_TOKEN. Never write it to a ticket, documentation, chat, screenshot, or output.'),
            $tr('Before changing an existing ticket, always read its current state with agent-get-ticket.'),
            $tr('Every POST request must include a unique Idempotency-Key.'),
        ],
        'ticket_creation' => [
            'title' => $tr('Main ticket'),
            'include' => [
                $tr('A concise work title.'),
                $tr('A short general description.'),
                $tr('The client and assignee.'),
                $tr('The status and priority.'),
            ],
            'exclude' => [
                $tr('Minutes or total time.'),
                $tr('A day-by-day work breakdown.'),
                $tr('A detailed work agenda.'),
                $tr('A timer or time entry.'),
            ],
        ],
        'daily_entries' => [
            'title' => $tr('Tracked work entries'),
            'action' => 'agent-add-work-entry',
            'example_html' => $daily_example,
            'rules' => [
                $tr('Add one separate comment for each workday.'),
                $tr('Keep daily comments in chronological order.'),
                $tr('Include the date, approved minutes, and the specific work completed.'),
                $tr('Use agent-add-update for a comment without tracked time.'),
                $tr('Use agent-add-work-entry when the work must count toward tracked or billable time.'),
                $tr('Send the comment and duration together so FoxDesk links them atomically.'),
                $tr('Include started_at and ended_at when exact work times are known.'),
                $tr('Do not create a separate comment and time entry for the same work.'),
                $tr('Set skip_notification to true when the client should not receive an email.'),
            ],
        ],
        'verification' => [
            'title' => $tr('Verification'),
            'action' => 'agent-get-ticket',
            'checks' => [
                $tr('The client and assignee are correct.'),
                $tr('The main description is concise and contains no time data.'),
                $tr('The number and order of daily comments are correct.'),
                $tr('Each tracked work comment has a matching time entry with a non-null comment_id.'),
                $tr('total_time_minutes matches the sum of saved time entries.'),
                $tr('No duplicate active ticket was created.'),
            ],
            'replacement_rule' => $tr('Cancel an incorrect ticket only after the correct replacement has been created and verified.'),
        ],
        'machine_rules' => [
            'api_only' => true,
            'required_first_actions' => ['agent-docs', 'agent-me'],
            'read_before_write_action' => 'agent-get-ticket',
            'post_requires_unique_idempotency_key' => true,
            'comment_only_action' => 'agent-add-update',
            'tracked_work_action' => 'agent-add-work-entry',
            'tracked_work_required_fields' => ['content', 'duration_minutes'],
            'tracked_work_requires_linked_comment_id' => true,
            'expected_total_time_rule' => 'sum(time_entries.duration_minutes)',
        ],
    ];
}

function foxdesk_agent_operating_instructions_markdown(?string $language = null, ?array $user = null): string
{
    $instructions = foxdesk_agent_operating_instructions($language, $user);
    $lines = ['# ' . $instructions['title'], ''];

    $lines[] = '## ' . foxdesk_agent_instruction_text('Basic rules', $instructions['language']);
    foreach ($instructions['basic_rules'] as $rule) {
        $lines[] = '- ' . $rule;
    }

    $lines[] = '';
    $lines[] = '## ' . $instructions['ticket_creation']['title'];
    $lines[] = foxdesk_agent_instruction_text('The main ticket contains only:', $instructions['language']);
    foreach ($instructions['ticket_creation']['include'] as $rule) {
        $lines[] = '- ' . $rule;
    }
    $lines[] = '';
    $lines[] = foxdesk_agent_instruction_text('Do not put the following in the main ticket body:', $instructions['language']);
    foreach ($instructions['ticket_creation']['exclude'] as $rule) {
        $lines[] = '- ' . $rule;
    }

    $lines[] = '';
    $lines[] = '## ' . $instructions['daily_entries']['title'];
    foreach ($instructions['daily_entries']['rules'] as $rule) {
        $lines[] = '- ' . $rule;
    }
    $lines[] = '';
    $lines[] = '```html';
    $lines[] = $instructions['daily_entries']['example_html'];
    $lines[] = '```';

    $lines[] = '';
    $lines[] = '## ' . $instructions['verification']['title'];
    $lines[] = foxdesk_agent_instruction_text('After finishing, call agent-get-ticket and verify:', $instructions['language']);
    foreach ($instructions['verification']['checks'] as $rule) {
        $lines[] = '- ' . $rule;
    }
    $lines[] = '- ' . $instructions['verification']['replacement_rule'];

    return implode("\n", $lines) . "\n";
}
