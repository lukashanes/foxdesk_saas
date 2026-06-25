<?php
/**
 * Settings email action metadata.
 */

function settings_email_env_or_constant(string $name, string $default = ''): string
{
    if (defined($name)) {
        return (string) constant($name);
    }

    $value = getenv($name);
    return $value !== false ? (string) $value : $default;
}

function settings_email_app_edition(): string
{
    $edition = settings_email_env_or_constant('FOXDESK_EDITION', '');
    if ($edition === '') {
        $edition = settings_email_env_or_constant('FOXDESK_APP_EDITION', '');
    }

    return strtolower(trim($edition));
}

function settings_email_is_managed_surface(): bool
{
    $edition = settings_email_app_edition();
    if ($edition !== '') {
        return in_array($edition, ['saas', 'cloud', 'managed'], true);
    }

    return defined('APP_MARKETING_HOST') || defined('PLATFORM_HOST');
}

function settings_email_surface_type(): string
{
    return settings_email_is_managed_surface() ? 'managed' : 'self_hosted';
}

function settings_email_action_keys(?string $surface = null): array
{
    $surface = $surface !== null ? $surface : settings_email_surface_type();
    $keys = ['save_email', 'save_template'];

    if ($surface !== 'managed') {
        array_splice($keys, 1, 0, ['test_smtp', 'test_imap', 'run_imap_now']);
    }

    return $keys;
}

function settings_is_email_action(array $post, ?string $surface = null): bool
{
    foreach (settings_email_action_keys($surface) as $key) {
        if (isset($post[$key])) {
            return true;
        }
    }
    return false;
}

function settings_email_transport_actions(): array
{
    return ['test_smtp', 'test_imap', 'run_imap_now'];
}

function settings_email_has_transport_action(array $post): bool
{
    foreach (settings_email_transport_actions() as $key) {
        if (isset($post[$key])) {
            return true;
        }
    }

    return false;
}

function settings_email_support_surface(array $settings, string $support_address = ''): array
{
    $managed = settings_email_is_managed_surface();
    $enabled = $managed ? true : (($settings['email_notifications_enabled'] ?? '0') === '1');

    return [
        'type' => settings_email_surface_type(),
        'support_address' => $support_address,
        'delivery_label' => $enabled ? 'Active' : 'Off',
        'delivery_enabled' => $enabled,
        'show_transport_settings' => !settings_email_is_managed_surface(),
        'show_diagnostics' => false,
    ];
}
