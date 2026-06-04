<?php
/**
 * Public report theme stylesheet.
 *
 * Report links can have a customer color, but the public report itself should
 * stay CSP-friendly. This endpoint exposes the color as CSS custom properties
 * without requiring inline styles in report HTML.
 */

$token = trim((string) ($_GET['token'] ?? ''));

header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: private, max-age=300');

function public_report_normalize_hex_color(string $hex, string $fallback = '#3B82F6'): string
{
    $hex = trim($hex);
    if ($hex === '') {
        return $fallback;
    }

    if ($hex[0] !== '#') {
        $hex = '#' . $hex;
    }

    if (preg_match('/^#[0-9a-fA-F]{3}$/', $hex)) {
        $hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
    }

    return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtoupper($hex) : $fallback;
}

function public_report_darken_hex_color(string $hex, int $percent = 25): string
{
    $hex = ltrim(public_report_normalize_hex_color($hex), '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, (int) round($r - ($r * $percent / 100)));
    $g = max(0, (int) round($g - ($g * $percent / 100)));
    $b = max(0, (int) round($b - ($b * $percent / 100)));

    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

$color = '#3B82F6';

if ($token !== '' && function_exists('get_report_template_share_by_token')) {
    $share = get_report_template_share_by_token($token);
    if ($share && function_exists('is_report_share_active') && is_report_share_active($share)) {
        $template = function_exists('get_report_template') ? get_report_template((int) ($share['report_template_id'] ?? 0)) : null;
        if ($template && empty($template['is_draft']) && empty($template['is_archived'])) {
            $color = public_report_normalize_hex_color((string) (($template['theme_color'] ?? '') ?: ($template['organization_theme_color'] ?? '') ?: $color));
        }
    }
}

$dark = public_report_darken_hex_color($color, 25);

echo "body.report-public-page{--report-theme-color:{$color};--report-theme-color-dark:{$dark};}\n";
