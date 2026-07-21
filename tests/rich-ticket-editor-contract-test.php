<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/ticket-detail-source.php';
$ticket_detail_page = file_get_contents($root . '/pages/ticket-detail.php');
$new_ticket_page = new_ticket_composed_source($root);
$ticket_detail_js = ticket_detail_browser_source($root);
$autosave_js = file_get_contents($root . '/assets/js/autosave.js');
$rich_text_js = file_get_contents($root . '/assets/js/rich-text-editor.js');
$functions = file_get_contents($root . '/includes/functions.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

foreach ([
    'ticket detail page' => $ticket_detail_page,
    'new ticket page' => $new_ticket_page,
    'ticket detail JS' => $ticket_detail_js,
    'autosave JS' => $autosave_js,
    'rich text JS' => $rich_text_js,
    'functions' => $functions,
] as $label => $content) {
    $assert($content !== false, $label . ' must be readable.');
}

$assert(str_contains($ticket_detail_page, 'assets/js/rich-text-editor.js'), 'Ticket detail must load the shared rich text helper.');
$assert(str_contains($new_ticket_page, 'assets/js/rich-text-editor.js'), 'New ticket must load the shared rich text helper.');
$assert(str_contains($new_ticket_page, 'FoxDeskRichText.fieldValue(window.descriptionEditor)'), 'New ticket must save semantic rich text from Quill.');
$assert(str_contains($ticket_detail_js, 'window.FoxDeskRichText.fieldValue(editor)'), 'Ticket detail must save semantic rich text from Quill.');
$assert(str_contains($ticket_detail_js, 'window.FoxDeskRichText.loadHtml(editor, content)'), 'Ticket detail must load edited comments through Quill clipboard.');
$assert(str_contains($autosave_js, 'window.FoxDeskRichText.fieldValue'), 'Autosave must store semantic rich text.');
$assert(str_contains($autosave_js, 'window.FoxDeskRichText.loadHtml'), 'Autosave restore must use the shared rich text loader.');
$assert(str_contains($rich_text_js, 'getSemanticHTML(0, length)'), 'Rich text helper must prefer Quill semantic HTML.');
$assert(str_contains($rich_text_js, '/<img\\b/i.test(html)'), 'Rich text helper must treat image-only comments as non-empty.');
$assert(str_contains($functions, '/^(https:|\\/?image\\.php|\\/?attachment\\.php)/i'), 'HTML sanitizer fallback must allow local proxied image URLs.');

echo "Rich ticket editor contract OK\n";
