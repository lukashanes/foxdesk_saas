<?php

$root = dirname(__DIR__);

$files = [
    'detail' => $root . '/ios/FoxDesk/FoxDesk/Sources/TicketDetailView.swift',
    'composer' => $root . '/ios/FoxDesk/FoxDesk/Sources/TicketComposerView.swift',
    'activity' => $root . '/ios/FoxDesk/FoxDesk/Sources/TicketActivityView.swift',
    'attachments' => $root . '/ios/FoxDesk/FoxDesk/Sources/TicketAttachmentsView.swift',
    'preview' => $root . '/ios/FoxDesk/FoxDesk/Sources/AttachmentPreviewView.swift',
    'manage' => $root . '/ios/FoxDesk/FoxDesk/Sources/TicketManageView.swift',
    'timer' => $root . '/ios/FoxDesk/FoxDesk/Sources/TicketTimerView.swift',
    'api' => $root . '/ios/FoxDesk/FoxDeskKit/Sources/API/FoxDeskAPIClient.swift',
    'models' => $root . '/ios/FoxDesk/FoxDeskKit/Sources/Models/TicketModels.swift',
];

$sources = [];
foreach ($files as $key => $path) {
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    $sources[$key] = $content;
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$detail = $sources['detail'];
$composer = $sources['composer'];
$activity = $sources['activity'];
$attachments = $sources['attachments'];
$preview = $sources['preview'];
$manage = $sources['manage'];
$timer = $sources['timer'];
$api = $sources['api'];
$models = $sources['models'];

$assert(str_contains($detail, 'TicketHeaderSection(ticket: detail.ticket)'), 'Ticket detail must render the ticket header.');
$assert(str_contains($detail, 'TimerControlSection(ticketID: detail.ticket.id)'), 'Ticket detail must render timer controls.');
$assert(str_contains($detail, 'CommentComposerSection(ticketID: detail.ticket.id)'), 'Ticket detail must render reply/internal-note composer.');
$assert(str_contains($detail, 'TicketActivitySections(comments: detail.comments, timeEntries: detail.timeEntries)'), 'Ticket detail must render comments and time activity.');
$assert(str_contains($detail, 'Section("Attachments")'), 'Ticket detail must render attachments section.');
$assert(str_contains($detail, 'AttachmentUploadSection(ticketID: detail.ticket.id)'), 'Ticket detail must support attachment uploads.');
$assert(str_contains($detail, 'AttachmentRow(attachment: attachment)'), 'Ticket detail must render attachment rows.');
$assert(str_contains($detail, 'TicketManageSheet(detail: detail)'), 'Ticket detail must expose the manage sheet when actions are available.');
$assert(str_contains($detail, 'ClientContextView('), 'Ticket detail must link to basic client context.');
$assert(str_contains($detail, 'TicketDetailCacheStore'), 'Ticket detail must keep cached fallback for offline/fast reopen.');
$assert(str_contains($detail, 'await detailCache.save'), 'Ticket detail must save refreshed detail to cache.');
$assert(str_contains($detail, 'await detailCache.load'), 'Ticket detail must load cached detail when offline.');

foreach (['Toggle("Internal note"', 'Toggle("Add time"', 'Toggle("Set date and time"', 'DatePicker("Date"', 'DatePicker("Start"', 'DatePicker("End"'] as $needle) {
    $assert(str_contains($composer, $needle), "Ticket composer missing expected control: {$needle}");
}
$assert(str_contains($composer, 'MobileRichTextFormatter.html(from: trimmed)'), 'Ticket composer must preserve basic rich text through formatter.');
$assert(str_contains($composer, 'durationMinutes: includeTime ? resolvedDurationMinutes : nil'), 'Ticket composer must send duration when logging time.');
$assert(str_contains($composer, 'manualDate: includeTime && useExactTime'), 'Ticket composer must support backdated/exact work date.');
$assert(str_contains($composer, 'manualStartTime: includeTime && useExactTime'), 'Ticket composer must support exact start time.');
$assert(str_contains($composer, 'manualEndTime: includeTime && useExactTime'), 'Ticket composer must support exact end time.');
$assert(str_contains($composer, 'createdAt: includeTime && useExactTime ? createdAtString() : nil'), 'Ticket composer must align comment creation timestamp with exact manual time.');
$assert(str_contains($composer, 'TicketCommentDraftStore'), 'Ticket composer must preserve drafts locally.');

$assert(str_contains($activity, 'Section("Comments")'), 'Ticket activity must render comment section.');
$assert(str_contains($activity, 'RichCommentText(html: comment.contentHtml'), 'Ticket activity must render rich comment text.');
$assert(str_contains($activity, 'LinkedCommentTimeRow(entry: entry)'), 'Ticket activity must render time linked to comments.');
$assert(str_contains($activity, 'Section("Time")'), 'Ticket activity must still render orphan time entries safely.');
foreach (['<li>', '<strong>', '<em>', '&amp;', '&lt;', '&gt;'] as $needle) {
    $assert(str_contains($activity, $needle), "Rich comment renderer must preserve/handle {$needle}.");
}

foreach (['Label("Take photo"', 'PhotosPicker(selection:', 'Label("Add photo"', 'Label("Add file"', '.fileImporter(', 'CameraCaptureView'] as $needle) {
    $assert(str_contains($attachments, $needle), "Attachment upload surface missing {$needle}.");
}
$assert(str_contains($attachments, 'failedUpload = PendingAttachmentUpload'), 'Attachment upload must keep failed upload data for retry.');
$assert(str_contains($attachments, 'Label("Retry upload"'), 'Attachment upload must expose retry action.');
$assert(str_contains($attachments, 'AttachmentThumbnailView(attachment: attachment)'), 'Attachment rows must render thumbnails.');
$assert(str_contains($attachments, 'AttachmentPreviewView(attachment: attachment)'), 'Attachment rows must open preview.');
$assert(str_contains($attachments, 'session.client.uploadAttachment'), 'Attachment upload must use the authenticated mobile API client.');
$assert(str_contains($attachments, 'session.client.attachmentMetadata'), 'Attachment thumbnails must resolve metadata when URLs are missing.');
$assert(str_contains($attachments, 'session.client.downloadResource'), 'Attachment thumbnails must download authorized preview resources.');

$assert(str_contains($preview, 'QuickLookPreview(url: url)'), 'Attachment preview must support non-image files through QuickLook.');
$assert(str_contains($preview, 'UIImage(data:'), 'Attachment preview must render image attachments inline.');
$assert(str_contains($preview, 'session.client.downloadResource'), 'Attachment preview must download authorized resources.');
$assert(str_contains($preview, 'session.client.attachmentMetadata'), 'Attachment preview must resolve metadata when URLs are missing.');
$assert(str_contains($preview, 'sanitizedFilename'), 'Attachment preview must sanitize temporary filenames.');

foreach (['Section("Status")', 'Section("Priority")', 'Section("Assignee")', 'session.client.updateTicket'] as $needle) {
    $assert(str_contains($manage, $needle), "Ticket manage sheet missing {$needle}.");
}
$assert(str_contains($manage, 'statusId:'), 'Ticket manage sheet must send status changes.');
$assert(str_contains($manage, 'priorityId:'), 'Ticket manage sheet must send priority changes.');
$assert(str_contains($manage, 'assigneeId:'), 'Ticket manage sheet must send assignee changes.');

foreach (['"start"', '"pause"', '"resume"', '"stop"', '"discard"', 'session.client.ticketTimer(', 'session.client.ticketTimerAction('] as $needle) {
    $assert(str_contains($timer, $needle), "Timer controls missing {$needle}.");
}

foreach (['public func ticketDetail(', 'public func addComment(', 'public func uploadAttachment(', 'public func attachmentMetadata(', 'public func updateTicket(', 'public func ticketTimer(', 'public func ticketTimerAction('] as $needle) {
    $assert(str_contains($api, $needle), "Swift API client missing ticket-detail workflow method: {$needle}");
}
$assert(str_contains($api, 'comment-with-time'), 'Swift API client must route timed comments to comment-with-time.');

foreach (['public struct TicketDetailPayload', 'public struct TicketComment', 'public struct TicketTimeEntry', 'public struct TicketAttachment', 'public struct AddCommentRequest', 'public struct UpdateTicketRequest'] as $needle) {
    $assert(str_contains($models, $needle), "Ticket models missing {$needle}.");
}

echo "iOS Ticket detail contract OK\n";
