/**
 * FoxDesk contract shell bridge.
 *
 * Progressive enhancement over existing PHP-rendered screens. The server still
 * renders the page, while this bridge verifies and refreshes lightweight state
 * from app-* API contracts that future web/native clients will share.
 */
(function(window, document) {
    'use strict';

    var CACHE_KEY = 'foxdesk.appShell.v1';
    var CACHE_TTL_MS = 45000;

    function api() {
        return window.FoxDeskApi || null;
    }

    function now() {
        return Date.now ? Date.now() : new Date().getTime();
    }

    function readShellCache() {
        try {
            var raw = window.sessionStorage.getItem(CACHE_KEY);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (!parsed || !parsed.timestamp || (now() - parsed.timestamp) > CACHE_TTL_MS) {
                return null;
            }
            return parsed.payload || null;
        } catch (error) {
            return null;
        }
    }

    function writeShellCache(payload) {
        try {
            window.sessionStorage.setItem(CACHE_KEY, JSON.stringify({
                timestamp: now(),
                payload: payload,
            }));
        } catch (error) {}
    }

    function dispatch(name, detail) {
        document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    }

    function setCountText(element, value) {
        if (!element) return;
        var count = Number(value || 0);
        element.textContent = String(count);
        element.setAttribute('data-contract-count', String(count));
    }

    function clearAndAppend(parent, children) {
        if (!parent) return;
        if (parent.replaceChildren) {
            parent.replaceChildren.apply(parent, children);
            return;
        }
        while (parent.firstChild) {
            parent.removeChild(parent.firstChild);
        }
        children.forEach(function(child) {
            parent.appendChild(child);
        });
    }

    function appendText(parent, text, className) {
        if (text === undefined || text === null || text === '') return null;
        var span = document.createElement('span');
        if (className) span.className = className;
        span.textContent = String(text);
        parent.appendChild(span);
        return span;
    }

    function valueName(value) {
        if (!value) return '';
        if (typeof value === 'string') return value;
        if (typeof value === 'object') return value.name || '';
        return String(value);
    }

    function formatDateLabel(value) {
        if (!value) return '';
        var match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) return String(value);
        return match[3] + '.' + match[2] + '.' + match[1];
    }

    function formatDurationLabel(minutes) {
        minutes = Math.max(0, Math.round(Number(minutes) || 0));
        var hours = Math.floor(minutes / 60);
        var mins = minutes % 60;
        return hours > 0 ? hours + 'h ' + mins + 'min' : mins + ' min';
    }

    function formatMoneyLabel(amount, currency) {
        return Number(amount || 0).toLocaleString('cs-CZ', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).replace(/\u00a0/g, ' ') + ' ' + (currency || 'CZK');
    }

    var TICKET_STATUS_ACCENT_CLASSES = [
        'ticket-status-accent--new',
        'ticket-status-accent--active',
        'ticket-status-accent--waiting',
        'ticket-status-accent--done',
        'ticket-status-accent--archived',
    ];

    function ticketStatusGroup(ticket) {
        if (!ticket || !ticket.status) return 'active';
        return ticket.status.group || (ticket.status.is_closed ? 'done' : 'active');
    }

    function ticketById(data) {
        var map = {};
        var tickets = data && Array.isArray(data.tickets) ? data.tickets : [];
        tickets.forEach(function(ticket) {
            if (!ticket || typeof ticket.id === 'undefined') return;
            map[String(ticket.id)] = ticket;
        });
        return map;
    }

    function fieldValue(ticket, field) {
        if (!ticket) return '';
        switch (field) {
            case 'title':
                return ticket.title || '';
            case 'status':
                return valueName(ticket.status);
            case 'priority':
                return valueName(ticket.priority);
            case 'client':
                return valueName(ticket.client) || '—';
            case 'assignee':
                return valueName(ticket.assignee) || 'Unassigned';
            case 'code':
                return ticket.code || (ticket.id ? '#' + ticket.id : '');
            case 'updated':
                return formatDateLabel(ticket.updated_at || ticket.created_at);
            default:
                return '';
        }
    }

    function setTicketFieldText(node, value) {
        if (!node || node.contains(document.activeElement)) return;
        var next = value === undefined || value === null || value === '' ? '—' : String(value);
        if (node.textContent !== next) {
            node.textContent = next;
        }
    }

    function updateTicketRegistryAccent(row, ticket) {
        if (!row || !ticket) return;
        TICKET_STATUS_ACCENT_CLASSES.forEach(function(className) {
            row.classList.remove(className);
        });
        row.classList.add('ticket-status-accent--' + ticketStatusGroup(ticket));
    }

    function updateTicketRegistryRow(row, ticket) {
        if (!row || !ticket) return;
        row.setAttribute('data-contract-seen', '1');
        row.setAttribute('data-ticket-url', ticket.url || row.getAttribute('data-ticket-url') || '');
        updateTicketRegistryAccent(row, ticket);

        row.querySelectorAll('[data-ticket-field]').forEach(function(fieldNode) {
            if (fieldNode.closest('.tl-dropdown')) return;
            var field = fieldNode.getAttribute('data-ticket-field');
            setTicketFieldText(fieldNode, fieldValue(ticket, field));
            if (field === 'title' && fieldNode.hasAttribute('data-value')) {
                fieldNode.setAttribute('data-value', ticket.title || '');
            }
        });
    }

    function syncTicketRegistryRows(root, data) {
        if (!root || !data) return;
        var tickets = ticketById(data);
        var seen = 0;
        root.querySelectorAll('[data-ticket-contract-row][data-ticket-id]').forEach(function(row) {
            var ticket = tickets[row.getAttribute('data-ticket-id')];
            if (!ticket) return;
            updateTicketRegistryRow(row, ticket);
            seen += 1;
        });
        root.setAttribute('data-contract-row-count', String(seen));
    }

    function createWorkspaceEmpty(root) {
        var empty = document.createElement('div');
        empty.className = 'workspace-empty';
        empty.setAttribute('data-work-empty', '');
        var title = document.createElement('div');
        title.className = 'workspace-empty__title';
        title.textContent = root.getAttribute('data-work-empty-label') || 'All clear';
        empty.appendChild(title);
        return empty;
    }

    function createWorkspaceTicketRow(ticket, options) {
        var row = document.createElement('a');
        row.className = 'workspace-ticket-row';
        row.href = ticket.url || '#';
        row.setAttribute('data-ticket-id', String(ticket.id || ''));

        var main = document.createElement('div');
        main.className = 'workspace-ticket-row__main';

        var meta = document.createElement('div');
        meta.className = 'workspace-ticket-row__meta';

        var dot = document.createElement('span');
        dot.className = 'workspace-ticket-row__dot';
        dot.setAttribute('aria-hidden', 'true');
        if (ticket.status && ticket.status.group) {
            dot.classList.add('workspace-ticket-row__dot--' + String(ticket.status.group));
        }
        meta.appendChild(dot);

        appendText(meta, ticket.code || (ticket.id ? '#' + ticket.id : ''));
        appendText(meta, valueName(ticket.status));
        appendText(meta, valueName(ticket.client));
        if (options.showSource) appendText(meta, ticket.source);

        var title = document.createElement('div');
        title.className = 'workspace-ticket-row__title';
        title.textContent = ticket.title || '';

        main.appendChild(meta);
        main.appendChild(title);

        var side = document.createElement('div');
        side.className = 'workspace-ticket-row__side';
        if (options.showAssignee) {
            appendText(side, valueName(ticket.assignee), null);
        }
        appendText(side, formatDateLabel(ticket.updated_at || ticket.created_at), null);
        var workedMinutes = Number(ticket.worked_minutes || ticket.workedMinutes || 0);
        if (workedMinutes > 0) {
            var worked = document.createElement('span');
            worked.className = 'workspace-ticket-row__time';
            worked.textContent = formatDurationLabel(workedMinutes);
            side.appendChild(worked);
        }

        row.appendChild(main);
        row.appendChild(side);
        return row;
    }

    function renderWorkspaceQueue(root, queue) {
        var list = root.querySelector('[data-work-ticket-list]');
        if (!list || !queue) return;
        var items = Array.isArray(queue.items) ? queue.items : [];
        var options = {
            showAssignee: root.getAttribute('data-work-show-assignee') === '1',
            showSource: root.getAttribute('data-work-show-source') === '1',
        };

        if (items.length === 0) {
            clearAndAppend(list, [createWorkspaceEmpty(root)]);
            return;
        }

        clearAndAppend(list, items.map(function(item) {
            return createWorkspaceTicketRow(item, options);
        }));
    }

    function applyShell(payload) {
        var shell = payload && payload.data ? payload.data.app_shell : null;
        if (!shell) return;
        window.FoxDeskShell = shell;
        document.body.setAttribute('data-app-contract-ready', '1');

        var role = shell.user && shell.user.role ? shell.user.role : '';
        document.querySelectorAll('.app-shell-context').forEach(function(el) {
            el.setAttribute('data-app-role', role);
        });

        dispatch('foxdesk:app-shell-ready', { shell: shell });
    }

    function loadShell() {
        var cached = readShellCache();
        if (cached) {
            applyShell(cached);
            return Promise.resolve(cached);
        }
        if (!api()) return Promise.resolve(null);
        return api().getAppShell()
            .then(function(payload) {
                writeShellCache(payload);
                applyShell(payload);
                return payload;
            })
            .catch(function(error) {
                dispatch('foxdesk:app-shell-error', { error: error });
                return null;
            });
    }

    function hydrateWorkSurface(root) {
        if (!api() || !root) return;
        var queueLinks = root.querySelectorAll('[data-work-queue-key]');
        var limit = Number(root.getAttribute('data-app-contract-limit') || 6);
        var collection = root.getAttribute('data-app-contract-collection') || root.getAttribute('data-app-contract-surface') || 'work';
        var activeKey = root.getAttribute('data-work-active-key') || 'mine';
        api().getAppHome({ limit: limit })
            .then(function(payload) {
                var home = payload && payload.data ? payload.data.home : null;
                var queues = home && home[collection] ? home[collection] : {};
                queueLinks.forEach(function(link) {
                    var key = link.getAttribute('data-work-queue-key');
                    var queue = queues[key] || null;
                    if (!queue) return;
                    setCountText(link.querySelector('[data-work-queue-count]'), queue.count);
                });
                renderWorkspaceQueue(root, queues[activeKey] || null);
                root.setAttribute('data-contract-hydrated', '1');
                dispatch('foxdesk:work-contract-ready', { home: home, collection: collection, activeKey: activeKey });
            })
            .catch(function(error) {
                root.setAttribute('data-contract-error', '1');
                dispatch('foxdesk:work-contract-error', { error: error });
            });
    }

    function hydrateTicketRegistry(root) {
        if (!api() || !root) return;
        var params = api().currentTicketListParams();
        params.limit = root.getAttribute('data-app-contract-limit') || params.limit || 25;
        api().getTicketList(params)
            .then(function(payload) {
                var data = payload && payload.data ? payload.data : {};
                var counts = data.counts || {};
                root.querySelectorAll('[data-ticket-view-key]').forEach(function(tab) {
                    var key = tab.getAttribute('data-ticket-view-key');
                    setCountText(tab.querySelector('[data-ticket-view-count]'), counts[key]);
                });
                if (data.pagination && typeof data.pagination.total !== 'undefined') {
                    root.setAttribute('data-contract-total', String(data.pagination.total));
                }
                syncTicketRegistryRows(root, data);
                root.setAttribute('data-contract-hydrated', '1');
                dispatch('foxdesk:tickets-contract-ready', { ticketList: data });
            })
            .catch(function(error) {
                root.setAttribute('data-contract-error', '1');
                dispatch('foxdesk:tickets-contract-error', { error: error });
            });
    }

    function clientStatValue(data, stat) {
        var counts = data && data.counts ? data.counts : {};
        var time = data && data.time ? data.time : {};
        switch (stat) {
            case 'open':
            case 'waiting':
            case 'done':
            case 'all':
            case 'archived':
                return counts[stat] || 0;
            case 'time':
                return time.minutes_label || String(time.minutes || 0) + ' min';
            case 'billable':
                return time.billable_amount_label || String(time.billable_amount || 0);
            default:
                return '';
        }
    }

    function syncClientStats(root, data) {
        root.querySelectorAll('[data-client-stat]').forEach(function(node) {
            setTicketFieldText(node, clientStatValue(data, node.getAttribute('data-client-stat')));
        });
    }

    function syncClientTabs(root, data) {
        var counts = data && data.counts ? data.counts : {};
        var activeView = data && data.view ? data.view : root.getAttribute('data-client-view');
        root.querySelectorAll('[data-client-view-key]').forEach(function(tab) {
            var key = tab.getAttribute('data-client-view-key');
            tab.classList.toggle('is-active', key === activeView);
            setCountText(tab.querySelector('[data-client-view-count]'), counts[key]);
        });
        if (activeView) root.setAttribute('data-client-view', activeView);
    }

    function updateClientTicketRow(row, ticket) {
        if (!row || !ticket) return;
        row.setAttribute('data-contract-seen', '1');
        row.href = ticket.url || row.href;
        row.querySelectorAll('[data-client-ticket-field]').forEach(function(fieldNode) {
            var field = fieldNode.getAttribute('data-client-ticket-field');
            setTicketFieldText(fieldNode, fieldValue(ticket, field));
            if (field === 'status' && ticket.status && ticket.status.color) {
                fieldNode.style.setProperty('--client-status-color', ticket.status.color);
            }
        });
    }

    function syncClientTickets(root, data) {
        var tickets = ticketById(data);
        var seen = 0;
        root.querySelectorAll('[data-client-ticket-row][data-ticket-id]').forEach(function(row) {
            var ticket = tickets[row.getAttribute('data-ticket-id')];
            if (!ticket) return;
            updateClientTicketRow(row, ticket);
            seen += 1;
        });
        root.setAttribute('data-client-ticket-row-count', String(seen));
    }

    function contactById(data) {
        var map = {};
        var contacts = data && Array.isArray(data.contacts) ? data.contacts : [];
        contacts.forEach(function(contact) {
            if (!contact || typeof contact.id === 'undefined') return;
            map[String(contact.id)] = contact;
        });
        return map;
    }

    function clientContactValue(contact, field) {
        if (!contact) return '';
        switch (field) {
            case 'name':
                return contact.name || '';
            case 'email':
                return contact.email || '';
            case 'role':
                return contact.role || '';
            default:
                return '';
        }
    }

    function syncClientContacts(root, data) {
        var contacts = contactById(data);
        var seen = 0;
        root.querySelectorAll('[data-client-contact-row][data-contact-id]').forEach(function(row) {
            var contact = contacts[row.getAttribute('data-contact-id')];
            if (!contact) return;
            row.setAttribute('data-contract-seen', '1');
            row.querySelectorAll('[data-client-contact-field]').forEach(function(fieldNode) {
                setTicketFieldText(fieldNode, clientContactValue(contact, fieldNode.getAttribute('data-client-contact-field')));
            });
            seen += 1;
        });
        root.setAttribute('data-client-contact-row-count', String(seen));
    }

    function hydrateClientSurface(root) {
        if (!api() || !root) return;
        var clientId = root.getAttribute('data-client-id') || '';
        if (!clientId) return;
        api().getClientOverview({
            organization_id: clientId,
            view: root.getAttribute('data-client-view') || 'open',
        })
            .then(function(payload) {
                var data = payload && payload.data ? payload.data : {};
                syncClientStats(root, data);
                syncClientTabs(root, data);
                syncClientTickets(root, data);
                syncClientContacts(root, data);
                root.setAttribute('data-contract-hydrated', '1');
                dispatch('foxdesk:client-contract-ready', { clientOverview: data });
            })
            .catch(function(error) {
                root.setAttribute('data-contract-error', '1');
                dispatch('foxdesk:client-contract-error', { error: error });
            });
    }

    function reportingReviewParams(root) {
        return {
            time_range: root.getAttribute('data-report-time-range') || 'this_month',
            from_date: root.getAttribute('data-report-from-date') || '',
            to_date: root.getAttribute('data-report-to-date') || '',
            organization_ids: root.getAttribute('data-report-organization-ids') || '',
            agent_ids: root.getAttribute('data-report-agent-ids') || '',
            tags: root.getAttribute('data-report-tags') || '',
            limit: root.getAttribute('data-report-limit') || 250,
        };
    }

    function reportingEntryById(data) {
        var map = {};
        var entries = data && Array.isArray(data.entries) ? data.entries : [];
        entries.forEach(function(entry) {
            if (!entry || typeof entry.id === 'undefined') return;
            map[String(entry.id)] = entry;
        });
        return map;
    }

    function reportingTotalLabel(data, key, currency) {
        var labels = data && data.total_labels ? data.total_labels : {};
        var totals = data && data.totals ? data.totals : {};
        if (labels[key]) return labels[key];
        if (key === 'minutes' || key === 'billable_minutes') {
            return formatDurationLabel(totals[key]);
        }
        return formatMoneyLabel(totals[key], currency);
    }

    function syncReportingTotals(root, data) {
        var currency = root.getAttribute('data-report-currency') || 'CZK';
        root.querySelectorAll('[data-report-total]').forEach(function(node) {
            setTicketFieldText(node, reportingTotalLabel(data, node.getAttribute('data-report-total'), currency));
        });
    }

    function reportingEntryFieldValue(entry, field, currency) {
        if (!entry) return '';
        switch (field) {
            case 'ticket':
                return entry.ticket && entry.ticket.title ? entry.ticket.title : '';
            case 'client':
                return valueName(entry.client) || '—';
            case 'agent':
                return valueName(entry.agent) || '—';
            case 'minutes':
                return formatDurationLabel(entry.actual_minutes);
            case 'amount':
                return formatMoneyLabel(entry.billable_amount, currency);
            case 'rate':
                return formatMoneyLabel(entry.billable_rate, currency) + '/h';
            default:
                return '';
        }
    }

    function syncReportingRows(root, data) {
        var entries = reportingEntryById(data);
        var currency = root.getAttribute('data-report-currency') || 'CZK';
        var seen = 0;
        root.querySelectorAll('[data-report-entry-row][data-entry-id]').forEach(function(row) {
            var entry = entries[row.getAttribute('data-entry-id')];
            if (!entry) return;
            row.setAttribute('data-contract-seen', '1');
            row.querySelectorAll('[data-report-entry-field]').forEach(function(fieldNode) {
                if (fieldNode.closest('form')) return;
                setTicketFieldText(fieldNode, reportingEntryFieldValue(entry, fieldNode.getAttribute('data-report-entry-field'), currency));
            });
            seen += 1;
        });
        root.setAttribute('data-report-entry-row-count', String(seen));
    }

    function hydrateReportingReviewSurface(root) {
        if (!api() || !root) return;
        api().getReportingReview(reportingReviewParams(root))
            .then(function(payload) {
                var data = payload && payload.data ? payload.data : {};
                syncReportingTotals(root, data);
                syncReportingRows(root, data);
                if (data.pagination && typeof data.pagination.has_more !== 'undefined') {
                    root.setAttribute('data-report-has-more', data.pagination.has_more ? '1' : '0');
                }
                root.setAttribute('data-contract-hydrated', '1');
                dispatch('foxdesk:reporting-review-contract-ready', { reportingReview: data });
            })
            .catch(function(error) {
                root.setAttribute('data-contract-error', '1');
                dispatch('foxdesk:reporting-review-contract-error', { error: error });
            });
    }

    function hydrateSurfaces() {
        document.querySelectorAll('[data-app-contract-surface="work"]').forEach(hydrateWorkSurface);
        document.querySelectorAll('[data-app-contract-surface="inbox"]').forEach(hydrateWorkSurface);
        document.querySelectorAll('[data-app-contract-surface="tickets"]').forEach(hydrateTicketRegistry);
        document.querySelectorAll('[data-app-contract-surface="client"]').forEach(hydrateClientSurface);
        document.querySelectorAll('[data-app-contract-surface="reporting-review"]').forEach(hydrateReportingReviewSurface);
    }

    function boot() {
        if (!api()) return;
        loadShell().then(hydrateSurfaces);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
