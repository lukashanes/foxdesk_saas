<?php

$root = dirname(__DIR__);
$page = file_get_contents($root . '/pages/ticket-detail.php');
$asset = file_get_contents($root . '/assets/js/ticket-detail.js');
$rich_text_asset = file_get_contents($root . '/assets/js/rich-text-editor.js');
$paste_drop_asset = file_get_contents($root . '/assets/js/attachment-paste-drop.js');
$quill_upload_asset = file_get_contents($root . '/assets/js/quill-image-upload.js');
$upload_handler = file_get_contents($root . '/includes/api/upload-handler.php');
$theme = file_get_contents($root . '/theme.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($page !== false, 'Ticket detail page must be readable.');
$assert($asset !== false, 'Ticket detail JS asset must be readable.');
$assert($rich_text_asset !== false, 'Rich text editor JS asset must be readable.');
$assert($paste_drop_asset !== false, 'Attachment paste/drop JS asset must be readable.');
$assert($quill_upload_asset !== false, 'Quill image upload JS asset must be readable.');
$assert($upload_handler !== false, 'Upload handler must be readable.');
$assert($theme !== false, 'Theme CSS must be readable.');

foreach ([
    'window.FoxDeskTicketDetailConfig',
    'assets/js/ticket-detail.js',
    'assets/js/rich-text-editor.js',
    'assets/js/quill-image-upload.js',
    'assets/js/attachment-paste-drop.js',
    'assets/js/autosave.js',
] as $needle) {
    $assert(str_contains($page, $needle), 'Ticket detail page missing JS contract: ' . $needle);
}

foreach ([
    'function quickEditField',
    'function openEditCommentModal',
    'function openEditTimeEntry',
    'function openEditTicketModal',
    'function openTicketTimeline',
    'const ICONS',
    'let commentEditor',
    'let editDescriptionEditor',
] as $inlineNeedle) {
    $assert(!str_contains($page, $inlineNeedle), 'Ticket detail page must not own inline JS behavior: ' . $inlineNeedle);
}

foreach ([
    'window.quickEditField',
    'window.openEditCommentModal',
    'window.openEditTimeEntry',
    'window.openEditTicketModal',
    'window.openTicketTimeline',
    'window.deleteAttachment',
    'index.php?page=api&action=delete-attachment',
    'initUploadPreview',
    'FoxDeskAttachmentPasteDrop.bind',
    "targetSelectors: ['#comment-form', '#comment-upload-zone']",
    'initQuillEditors',
    'initTags',
    'initTimer',
    'updateCompleteActionTitle',
    'completeTimerHelp',
    'completeHelp',
    'initAutosave',
    "classList.add('is-open')",
    "classList.remove('is-open')",
    "classList.add('ticket-timeline-open')",
    "classList.remove('ticket-timeline-open')",
    'ticket-timeline-empty',
    'quillFieldValue(window.commentEditor)',
    'quillFieldValue(window.internalEditor)',
    'quillFieldValue(editDescriptionEditor)',
    'quillFieldValue(editCommentEditor)',
    'loadQuillContent(editCommentEditor, content)',
] as $assetNeedle) {
    $assert(str_contains($asset, $assetNeedle), 'Ticket detail JS asset missing behavior: ' . $assetNeedle);
}

foreach ([
    'window.FoxDeskRichText',
    'getSemanticHTML',
    'fieldValue',
    'loadHtml',
    'isBlankHtml',
] as $richTextNeedle) {
    $assert(str_contains($rich_text_asset, $richTextNeedle), 'Rich text editor asset missing behavior: ' . $richTextNeedle);
}

foreach ([
    'data.file.url',
    "image.php?f=' + encodeURIComponent(data.file.filename)",
] as $quillUploadNeedle) {
    $assert(str_contains($quill_upload_asset, $quillUploadNeedle), 'Quill image upload asset missing behavior: ' . $quillUploadNeedle);
}

foreach ([
    "\$result['url'] = 'image.php?f='",
    "db_insert('attachments'",
    'attachment_storage_fields($result)',
    "\$result['attachment_id']",
] as $uploadNeedle) {
    $assert(str_contains($upload_handler, $uploadNeedle), 'Upload handler missing inline image attachment persistence: ' . $uploadNeedle);
}

foreach ([
    'window.FoxDeskAttachmentPasteDrop',
    'function autoBindKnownSurfaces',
    "inputId: 'comment-file-input'",
    "inputId: 'file-input'",
    "targetSelectors: ['#comment-form', '#comment-upload-zone']",
    "targetSelectors: ['#new-ticket-form', '#upload-zone']",
] as $pasteDropNeedle) {
    $assert(str_contains($paste_drop_asset, $pasteDropNeedle), 'Attachment paste/drop asset missing behavior: ' . $pasteDropNeedle);
}

foreach ([
    'body.ticket-timeline-open',
    '.ticket-timeline-overlay.is-open',
    '.ticket-timeline-empty',
] as $themeNeedle) {
    $assert(str_contains($theme, $themeNeedle), 'Theme CSS missing ticket timeline state: ' . $themeNeedle);
}

echo "Ticket detail JS contract OK\n";
