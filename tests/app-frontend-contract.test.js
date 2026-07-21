const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(message);
    process.exit(1);
  }
}

const footer = read('includes/footer.php');
const header = read('includes/header.php');
const workspaceSurface = read('includes/components/workspace-surface.php');
const ticketRegistrySurface = read('includes/components/ticket-registry-surface.php');
const ticketsPage = [
  read('pages/tickets.php'),
  read('includes/modules/tickets/ticket-list-page-controller.php'),
  read('includes/components/ticket-list-page.php'),
  read('includes/components/ticket-list-board.php'),
  read('includes/components/ticket-list-table.php'),
].join('\n');
const inboxPage = read('pages/inbox.php');
const clientPage = read('pages/client.php');
const reportsPage = [
  read('pages/admin/reports.php'),
  read('includes/modules/reports/report-page-controller.php'),
  read('includes/modules/reports/report-page-view-model.php'),
  read('includes/modules/reports/report-page-render.php'),
  read('includes/modules/reports/views/page.php'),
  read('includes/modules/reports/views/billing.php'),
].join('\n');
const apiClient = read('assets/js/app-api-client.js');
const shellBridge = read('assets/js/app-contract-shell.js');
const docs = read('docs/product-architecture-refactor.md');

assert(header.includes('data-app-page="<?php echo e((string) ($page ?? \'\')); ?>"'), 'Body must expose current app page.');
assert(header.includes('data-app-shell="php"'), 'Body must declare the current PHP shell boundary.');

assert(footer.includes('window.appConfig = {'), 'Footer must define appConfig before app frontend assets.');
assert(footer.includes('csrfToken:'), 'appConfig must expose CSRF token to app API client.');
assert(footer.indexOf('window.appConfig = {') < footer.indexOf('assets/js/app-api-client.js'), 'API client must load after appConfig.');
assert(footer.indexOf('assets/js/app-api-client.js') < footer.indexOf('assets/js/app-contract-shell.js'), 'Contract shell must load after API client.');
assert(footer.includes('$footer_asset_version = static function'), 'Footer must use file-aware cache busting for JS assets.');
assert(footer.includes("filemtime($absolute_path)"), 'Footer JS asset version must change when a file changes.');
for (const asset of ['assets/js/app-api-client.js', 'assets/js/app-contract-shell.js', 'assets/js/image-preview.js', 'assets/js/app-footer.js']) {
  assert(footer.includes(`$footer_asset_version('${asset}')`), `${asset} must use the shared JS cache-busting helper.`);
}
assert(footer.includes("assets/js/page-transitions.js"), 'Footer must load smooth page transitions.');
assert(footer.includes("$footer_asset_version('assets/js/page-transitions.js')"), 'Page transition JS must use file-aware cache busting.');
assert(footer.indexOf('assets/js/app-contract-shell.js') < footer.indexOf('assets/js/page-transitions.js'), 'Page transition behavior must load after the contract shell bridge.');
assert(footer.includes('id="image-lightbox"'), 'Footer must render the shared image preview lightbox.');
assert(footer.includes('data-image-preview-close'), 'Footer lightbox must expose a close control for image preview JS.');
assert(footer.indexOf('assets/js/image-preview.js') < footer.indexOf('assets/js/app-footer.js'), 'Image preview behavior must load before footer enhancements.');

for (const endpoint of [
  'app-shell',
  'app-home',
  'app-ticket-list',
  'app-ticket-actions',
  'app-client-overview',
  'app-reporting-review',
  'app-notifications-summary',
  'app-tenant-state',
]) {
  assert(apiClient.includes(endpoint), `API client missing endpoint wrapper for ${endpoint}.`);
}

for (const needle of [
  'window.FoxDeskApi',
  'credentials: \'same-origin\'',
  'X-CSRF-Token',
  'currentTicketListParams',
  "params.status_id = source.get('status')",
  "params.organization_id = source.get('organization')",
  "params.view = 'archived'",
  'normalizePayload',
  'FoxDeskApiError',
]) {
  assert(apiClient.includes(needle), `API client missing behavior: ${needle}`);
}

for (const needle of [
  'data-app-contract-surface="<?php echo e($contract_surface); ?>"',
  'data-app-contract-collection',
  'data-app-contract-action="app-home"',
  'data-work-queue-key',
  'data-work-queue-count',
  'data-work-ticket-list',
  'data-work-empty-label',
]) {
  assert(workspaceSurface.includes(needle), `Work surface missing contract mount: ${needle}`);
}
assert(inboxPage.includes("redirect('work'"), 'Legacy inbox page must redirect into Work.');
assert(!inboxPage.includes("'contract_surface' => 'inbox'"), 'Inbox page must not mount a separate inbox contract surface.');
assert(!inboxPage.includes("'contract_collection' => 'inbox'"), 'Inbox page must not hydrate a separate inbox collection.');

assert(ticketsPage.includes('data-app-contract-surface="tickets"'), 'Tickets page must expose app-ticket-list contract surface.');
assert(ticketsPage.includes('data-app-contract-action="app-ticket-list"'), 'Tickets page must declare app-ticket-list contract action.');
assert(ticketsPage.includes('data-ticket-contract-mode="refresh"'), 'Tickets page must declare the ticket contract refresh mode.');
assert(ticketsPage.includes('data-ticket-contract-row'), 'Tickets page must expose stable ticket row contract mounts.');
for (const field of ['title', 'status', 'priority', 'client', 'assignee', 'code']) {
  assert(ticketsPage.includes(`data-ticket-field="${field}"`), `Tickets page missing contract field mount: ${field}.`);
}
assert(ticketRegistrySurface.includes('data-ticket-view-key'), 'Ticket view tabs must expose stable view keys.');
assert(ticketRegistrySurface.includes('data-ticket-view-count'), 'Ticket view tabs must expose count mount points.');

assert(clientPage.includes('data-app-contract-surface="client"'), 'Client page must expose app-client-overview contract surface.');
assert(clientPage.includes('data-app-contract-action="app-client-overview"'), 'Client page must declare app-client-overview contract action.');
assert(clientPage.includes('data-client-id="<?php echo (int) $org[\'id\']; ?>"'), 'Client page must expose the client id for contract hydration.');
assert(clientPage.includes('data-client-ticket-list'), 'Client page must expose the ticket list mount.');
assert(clientPage.includes('data-client-contact-list'), 'Client page must expose the contact list mount.');
for (const stat of ['open', 'waiting', 'done', 'time', 'billable']) {
  assert(clientPage.includes(`data-client-stat="${stat}"`), `Client page missing stat mount: ${stat}.`);
}
for (const field of ['title', 'code', 'assignee', 'updated', 'status']) {
  assert(clientPage.includes(`data-client-ticket-field="${field}"`), `Client page missing ticket field mount: ${field}.`);
}

assert(reportsPage.includes('data-app-contract-surface="reporting-review"'), 'Detailed reports must expose app-reporting-review contract surface.');
assert(reportsPage.includes('data-app-contract-action="app-reporting-review"'), 'Detailed reports must declare app-reporting-review contract action.');
assert(reportsPage.includes('data-report-total="billable_amount"'), 'Detailed reports must expose billable amount total mount.');
assert(reportsPage.includes('data-report-entry-row'), 'Detailed reports must expose stable entry row mounts.');
assert(reportsPage.includes('data-report-entry-field="amount"'), 'Detailed reports must expose amount field mounts.');
assert(reportsPage.includes('data-report-entry-field="rate"'), 'Detailed reports must expose rate field mounts.');

for (const needle of [
  'hydrateWorkSurface',
  'renderWorkspaceQueue',
  'createWorkspaceTicketRow',
  'document.createElement',
  'replaceChildren',
	  'hydrateTicketRegistry',
	  'syncTicketRegistryRows',
	  'updateTicketRegistryRow',
	  'data-contract-row-count',
	  'fieldNode.closest(\'.tl-dropdown\')',
		  'fieldNode.hasAttribute(\'data-value\')',
		  'getAppHome({ limit: limit })',
		  'getTicketList(params)',
		  'hydrateClientSurface',
		  'syncClientStats',
			  'syncClientTickets',
			  'syncClientContacts',
			  'getClientOverview({',
			  'hydrateReportingReviewSurface',
			  'syncReportingTotals',
			  'syncReportingRows',
			  'getReportingReview(reportingReviewParams(root))',
  'foxdesk:app-shell-ready',
  'foxdesk:work-contract-ready',
  'foxdesk:tickets-contract-ready',
  'foxdesk:client-contract-ready',
  'foxdesk:reporting-review-contract-ready',
  'data-contract-hydrated',
]) {
  assert(shellBridge.includes(needle), `Contract shell missing behavior: ${needle}`);
}

assert(!shellBridge.includes('.innerHTML'), 'Contract shell bridge must not rerender PHP surfaces through innerHTML.');
assert(docs.includes('The tenth behavior change adds a frontend contract bridge'), 'Architecture docs must describe the frontend contract bridge.');
assert(docs.includes('The eleventh behavior change makes Work the contract-first workspace surface'), 'Architecture docs must describe the contract-first Work surface.');
assert(docs.includes('The thirteenth behavior change makes the Client center consume the'), 'Architecture docs must describe the client center contract refresh.');
assert(docs.includes('The fourteenth behavior change makes the detailed Reports billing review'), 'Architecture docs must describe the reporting review contract refresh.');
assert(docs.includes('The fifteenth behavior change reduces duplicate notification noise'), 'Architecture docs must describe notification dedupe.');
assert(docs.includes('The sixteenth behavior change tightens SaaS host separation'), 'Architecture docs must describe SaaS host separation.');

console.log('App frontend contract bridge OK');
