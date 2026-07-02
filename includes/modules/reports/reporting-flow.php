<?php
/**
 * Reporting flow helpers.
 *
 * Keep the billing/report publishing workflow small and reusable so the admin
 * reports page can stay focused on rendering data.
 */

function reporting_flow_steps(): array
{
    return [
        [
            'key' => 'client',
            'label' => t('Choose client'),
            'description' => t('Pick a client and period.'),
        ],
        [
            'key' => 'review',
            'label' => t('Review billable items'),
            'description' => t('Check billable rows.'),
        ],
        [
            'key' => 'adjust',
            'label' => t('Adjust rates or discounts'),
            'description' => t('Tune rates, discounts, or totals.'),
        ],
        [
            'key' => 'share',
            'label' => t('Share or export'),
            'description' => t('Send the final report.'),
        ],
    ];
}

function reporting_flow_time_presets(): array
{
    return [
        'this_month' => t('This month'),
        'last_month' => t('Last month'),
        'last_30_days' => t('Last 30 days'),
        'this_quarter' => t('This quarter'),
    ];
}

function reporting_flow_bounds(string $time_range): array
{
    $bounds = get_time_range_bounds($time_range);
    $from = $bounds['start'] ? substr((string) $bounds['start'], 0, 10) : date('Y-m-01');
    $to = $bounds['end'] ? substr((string) $bounds['end'], 0, 10) : date('Y-m-t');

    return [
        'range' => (string) ($bounds['range'] ?? $time_range),
        'from' => $from,
        'to' => $to,
    ];
}

function reporting_flow_review_url(?int $organization_id = null, string $time_range = 'this_month'): string
{
    $params = [
        'section' => 'reports',
        'tab' => 'detailed',
        'time_range' => $time_range,
        'show_money' => 1,
    ];

    if ($organization_id !== null && $organization_id > 0) {
        $params['organizations[]'] = $organization_id;
    }

    return url('admin', $params);
}

function reporting_flow_builder_url(?int $organization_id = null, string $time_range = 'this_month'): string
{
    $bounds = reporting_flow_bounds($time_range);
    $params = [
        'section' => 'report-builder',
        'date_from' => $bounds['from'],
        'date_to' => $bounds['to'],
    ];

    if ($organization_id !== null && $organization_id > 0) {
        $params['organization_id'] = $organization_id;
    }

    return url('admin', $params);
}

function reporting_flow_builder_url_from_filter_state(array $filter_state): string
{
    $selected_orgs = array_values(array_filter(
        array_map('intval', (array) ($filter_state['selected_orgs'] ?? [])),
        static fn (int $id): bool => $id > 0
    ));
    $organization_id = count($selected_orgs) === 1 ? (int) $selected_orgs[0] : null;

    $from = !empty($filter_state['range_start'])
        ? substr((string) $filter_state['range_start'], 0, 10)
        : '';
    $to = !empty($filter_state['range_end'])
        ? substr((string) $filter_state['range_end'], 0, 10)
        : '';

    if ($from === '' || $to === '') {
        $bounds = reporting_flow_bounds((string) ($filter_state['time_range'] ?? 'this_month'));
        $from = $from !== '' ? $from : $bounds['from'];
        $to = $to !== '' ? $to : $bounds['to'];
    }

    $params = [
        'section' => 'report-builder',
        'date_from' => $from,
        'date_to' => $to,
        'time_range' => (string) ($filter_state['time_range'] ?? 'this_month'),
    ];

    if ($organization_id !== null && $organization_id > 0) {
        $params['organization_id'] = $organization_id;
    }

    $query = url('admin', $params);

    $selected_agents = array_values(array_filter(
        array_map('intval', (array) ($filter_state['selected_agents'] ?? [])),
        static fn (int $id): bool => $id > 0
    ));
    if (!empty($selected_agents)) {
        $query .= '&' . http_build_query(['agents' => $selected_agents]);
    }

    $selected_tags = (array) ($filter_state['selected_tags'] ?? []);
    if (!empty($selected_tags)) {
        $query .= '&tags=' . rawurlencode(implode(', ', $selected_tags));
    }

    return $query;
}
