<?php
/**
 * Notifications Page — Full-page notification list
 * Shows all notifications with filters, ticket grouping, deep links, and mark-as-read.
 */

$page_title = t('Notifications');
$page = 'notifications';
$user = current_user();

// Load notification functions
if (file_exists(BASE_PATH . '/includes/notification-functions.php')) {
    require_once BASE_PATH . '/includes/notification-functions.php';
}

// Filter: all | action | info | resolved
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'action', 'info', 'resolved'])) {
    $filter = 'all';
}

$unread_count = 0;
if (function_exists('get_unread_notification_count') && function_exists('notifications_table_exists') && notifications_table_exists()) {
    $unread_count = get_unread_notification_count((int) $user['id']);
}

require_once BASE_PATH . '/includes/header.php';

/**
 * Helper: render a single notification card (primary or standalone).
 */
function render_notif_card(array $notif, int $group_count = 1): void
{
    $n_is_read = (bool) $notif['is_read'];
    $n_time = notification_time_ago($notif['created_at']);
    $n_ticket_id = $notif['ticket_id'] ? (int) $notif['ticket_id'] : null;
    $n_data = $notif['data'] ?? [];
    $n_comment_id = $n_data['comment_id'] ?? null;
    $n_snippet = get_notification_snippet($notif);
    $n_is_action = is_action_required_notification($notif['type'], $n_data);

    // Ticket subject (primary line)
    $n_subject = $n_data['ticket_subject'] ?? '';
    if (mb_strlen($n_subject) > 60) {
        $n_subject = mb_substr($n_subject, 0, 57) . '...';
    }
    // Action text (secondary line — no ticket subject)
    $n_action = format_notification_action($notif);

    // Deep link URL
    $n_href = '#';
    if ($n_ticket_id) {
        $n_href = 'index.php?page=ticket&id=' . $n_ticket_id . '&ref=notifications&nid=' . (int)$notif['id'];
        if ($n_comment_id) {
            $n_href .= '#comment-' . $n_comment_id;
        }
    }

    $n_actor_name = trim(($notif['actor_first_name'] ?? '') . ' ' . ($notif['actor_last_name'] ?? ''));
    $n_actor_avatar = $notif['actor_avatar'] ?? null;
    $n_initials = mb_strtoupper(mb_substr($notif['actor_first_name'] ?? '?', 0, 1));
    $avatar_bg = 'hsl(' . abs(crc32($n_actor_name)) % 360 . ', 55%, 60%)';

    $type_meta = notification_type_meta((string) $notif['type']);
    $type_icon = $type_meta['icon'];
    $type_color = $type_meta['color'];
    ?>
    <div class="notif-card <?php echo $n_is_read ? '' : 'unread'; ?>"
         id="notif-item-<?php echo (int)$notif['id']; ?>"
         data-id="<?php echo (int)$notif['id']; ?>">
        <!-- Avatar -->
        <a href="<?php echo $n_href; ?>" class="notif-avatar" style="background: <?php echo $avatar_bg; ?>;">
            <?php if ($n_actor_avatar && !str_starts_with($n_actor_avatar, 'data:')): ?>
                <img src="<?php echo e(upload_url($n_actor_avatar)); ?>" alt=""
                     onerror="this.style.display='none';this.parentElement.textContent='<?php echo e($n_initials); ?>'">
            <?php elseif ($n_actor_avatar && str_starts_with($n_actor_avatar, 'data:')): ?>
                <img src="<?php echo e($n_actor_avatar); ?>" alt="">
            <?php else: ?>
                <?php echo e($n_initials); ?>
            <?php endif; ?>
        </a>
        <!-- Content — ticket subject first, action second -->
        <a href="<?php echo $n_href; ?>" class="notif-card-content" style="text-decoration: none;">
            <?php if ($n_subject): ?>
                <div class="notif-card-subject"><?php echo e($n_subject); ?></div>
            <?php endif; ?>
            <div class="notif-card-action"><?php echo e($n_action); ?></div>
            <?php if ($n_snippet): ?>
                <div class="notif-card-snippet"><?php echo e($n_snippet); ?></div>
            <?php endif; ?>
            <div class="notif-card-meta">
                <span class="notif-type-icon" style="color: <?php echo e($type_color); ?>;">
                    <?php echo get_icon($type_icon, 'w-3 h-3'); ?>
                </span>
                <span class="notif-card-time"><?php echo e($n_time); ?></span>
                <?php if ($n_is_action): ?>
                    <span class="notif-action-badge"><?php echo e(t('Action required')); ?></span>
                <?php endif; ?>
            </div>
        </a>
        <!-- Actions -->
        <div class="notif-card-actions">
            <?php if ($group_count > 1): ?>
                <button type="button" class="notif-group-toggle" title="<?php echo e(t('Show all')); ?>">
                    <span class="notif-group-count">+<?php echo $group_count - 1; ?></span>
                </button>
            <?php endif; ?>
            <?php if ($n_ticket_id && $group_count > 1 && !$n_is_read): ?>
                <button type="button" class="notif-mark-read-btn"
                        onclick="event.stopPropagation(); markTicketNotifsRead(<?php echo $n_ticket_id; ?>)"
                        title="<?php echo e(t('Mark all for this ticket as read')); ?>">
                    <?php echo get_icon('check-circle', 'w-4 h-4'); ?>
                </button>
            <?php endif; ?>
            <?php if (!$n_is_read): ?>
                <button type="button" class="notif-mark-read-btn"
                        onclick="event.stopPropagation(); markNotifRead(<?php echo (int)$notif['id']; ?>)"
                        title="<?php echo e(t('Mark as read')); ?>">
                    <?php echo get_icon('check', 'w-4 h-4'); ?>
                </button>
            <?php endif; ?>
            <a href="<?php echo $n_href; ?>" class="notif-mark-read-btn"
               title="<?php echo e(t('Open')); ?>">
                <?php echo get_icon('chevron-right', 'w-4 h-4'); ?>
            </a>
        </div>
    </div>
    <?php
}

/**
 * Helper: render a compact child notification card.
 * Shows action text only (ticket subject is in the parent card).
 */
function render_child_card(array $notif): void
{
    $n_is_read = (bool) $notif['is_read'];
    $n_action = format_notification_action($notif);
    $n_time = notification_time_ago($notif['created_at']);
    $n_ticket_id = $notif['ticket_id'] ? (int) $notif['ticket_id'] : null;
    $n_data = $notif['data'] ?? [];
    $n_comment_id = $n_data['comment_id'] ?? null;
    $n_is_action = is_action_required_notification($notif['type'], $n_data);

    $n_href = '#';
    if ($n_ticket_id) {
        $n_href = 'index.php?page=ticket&id=' . $n_ticket_id . '&ref=notifications&nid=' . (int)$notif['id'];
        if ($n_comment_id) $n_href .= '#comment-' . $n_comment_id;
    }

    $type_meta = notification_type_meta((string) $notif['type']);
    $type_icon = $type_meta['icon'];
    $type_color = $type_meta['color'];
    ?>
    <a href="<?php echo $n_href; ?>" class="notif-child-card <?php echo $n_is_read ? '' : 'unread'; ?>"
       id="notif-item-<?php echo (int)$notif['id']; ?>" data-id="<?php echo (int)$notif['id']; ?>">
        <span class="notif-type-icon" style="color: <?php echo e($type_color); ?>;">
            <?php echo get_icon($type_icon, 'w-3.5 h-3.5'); ?>
        </span>
        <span class="notif-child-text"><?php echo e($n_action); ?></span>
        <span class="notif-child-time"><?php echo e($n_time); ?></span>
        <?php if ($n_is_action): ?>
            <span class="notif-action-badge"><?php echo e(t('Action required')); ?></span>
        <?php endif; ?>
    </a>
    <?php
}
?>

<div class="notif-page-wrap" id="notifPageWrap">
    <!-- Header -->
    <div class="notif-page-header">
        <div class="notif-page-title">
            <?php echo get_icon('bell', 'w-6 h-6'); ?>
            <?php echo e(t('Notifications')); ?>
            <?php if ($unread_count > 0): ?>
                <span class="notif-page-badge"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" class="notif-mark-all-btn" onclick="markAllNotifRead()"
                    <?php echo $unread_count <= 0 ? 'disabled' : ''; ?>>
                <?php echo get_icon('check', 'w-4 h-4 inline-block'); ?>
                <?php echo e(t('Mark all as read')); ?>
            </button>
            <div class="notif-view-toggle">
                <button type="button" class="notif-view-btn active" data-view="normal"
                        onclick="setNotifView('normal')" title="<?php echo e(t('Normal')); ?>">
                    <?php echo get_icon('grid', 'w-4 h-4'); ?>
                </button>
                <button type="button" class="notif-view-btn" data-view="compact"
                        onclick="setNotifView('compact')" title="<?php echo e(t('Compact')); ?>">
                    <?php echo get_icon('list', 'w-4 h-4'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="notif-filter-tabs" style="margin-bottom: 16px;">
        <a href="<?php echo url('notifications'); ?>"
           class="notif-filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
            <?php echo e(t('All')); ?>
        </a>
        <a href="<?php echo url('notifications', ['filter' => 'action']); ?>"
           class="notif-filter-tab <?php echo $filter === 'action' ? 'active' : ''; ?>">
            <?php echo e(t('Action required')); ?>
        </a>
        <a href="<?php echo url('notifications', ['filter' => 'info']); ?>"
           class="notif-filter-tab <?php echo $filter === 'info' ? 'active' : ''; ?>">
            <?php echo e(t('Informational')); ?>
        </a>
        <a href="<?php echo url('notifications', ['filter' => 'resolved']); ?>"
           class="notif-filter-tab <?php echo $filter === 'resolved' ? 'active' : ''; ?>">
            <?php echo e(t('Resolved')); ?>
        </a>
    </div>

    <!-- Notification list -->
    <div id="notif-list">
        <div id="notif-page-loading" class="text-center py-8 text-sm" style="color: var(--text-muted);">
            <?php echo e(t('Loading...')); ?>
        </div>
        <noscript>
            <div class="notif-empty">
                <?php echo get_icon('bell', 'w-12 h-12 notif-empty-icon'); ?>
                <p class="text-base font-medium" style="color: var(--text-secondary);"><?php echo e(t('Notifications require JavaScript to load on this page.')); ?></p>
            </div>
        </noscript>
    </div>
</div>

<script>
    const NOTIFICATION_TYPE_META = <?php echo json_encode(notification_type_meta_map(), JSON_UNESCAPED_SLASHES); ?>;

(function() {
    var _offset = 0;
    var _filter = <?php echo json_encode($filter); ?>;
    var _includeResolved = _filter === 'resolved';
    var _loading = false;

    // ── Toggle group expand on click (mobile + desktop fallback) ─────────
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.notif-group-toggle');
        if (!toggle) return;
        e.preventDefault();
        e.stopPropagation();
        var group = toggle.closest('.notif-ticket-group');
        if (group) group.classList.toggle('expanded');
    });

    // ── Mark single notification as read ─────────────────────────────────
    window.markNotifRead = function(id) {
        fetch('index.php?page=api&action=mark-notification-read', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'notification_id=' + id + '&csrf_token=' + encodeURIComponent(window.csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                var el = document.getElementById('notif-item-' + id);
                if (el) {
                    el.classList.remove('unread');
                    el.querySelectorAll('button.notif-mark-read-btn').forEach(function(btn) { btn.remove(); });
                }
                var count = res.unread_count ?? 0;
                refreshPageBadge(count);
                syncMarkAllButton(count);
                if (typeof updateBadge === 'function') updateBadge(count);
            }
        });
    };

    // ── Mark all for a ticket as read (group dismiss) ──────────────────
    window.markTicketNotifsRead = function(ticketId) {
        fetch('index.php?page=api&action=mark-ticket-notifications-read', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ticket_id=' + ticketId + '&csrf_token=' + encodeURIComponent(window.csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                // Find the ticket group and mark all items read
                document.querySelectorAll('.notif-card[data-id], .notif-child-card[data-id]').forEach(function(el) {
                    // Check if this notification's link contains the ticket ID
                    var link = el.querySelector('a[href*="id=' + ticketId + '"]') || el;
                    var href = link.getAttribute('href') || '';
                    if (href.indexOf('id=' + ticketId + '&') !== -1 || href.indexOf('id=' + ticketId + '#') !== -1) {
                        el.classList.remove('unread');
                        var btn = el.querySelector('button.notif-mark-read-btn');
                        if (btn) btn.remove();
                    }
                });
                // Update badge with server count
                var count = res.unread_count ?? 0;
                refreshPageBadge(count);
                syncMarkAllButton(count);
                if (typeof updateBadge === 'function') updateBadge(count);
            }
        });
    };

    // ── Mark all as read ─────────────────────────────────────────────────
    window.markAllNotifRead = function() {
        fetch('index.php?page=api&action=mark-all-notifications-read', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=' + encodeURIComponent(window.csrfToken)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                document.querySelectorAll('.notif-card.unread').forEach(function(el) {
                    el.classList.remove('unread');
                });
                document.querySelectorAll('.notif-child-card.unread').forEach(function(el) {
                    el.classList.remove('unread');
                });
                document.querySelectorAll('.notif-card-actions .notif-mark-read-btn').forEach(function(btn) {
                    if (btn.tagName === 'BUTTON') btn.remove();
                });
                refreshPageBadge(res.unread_count ?? 0);
                syncMarkAllButton(res.unread_count ?? 0);
                if (typeof updateBadge === 'function') updateBadge(res.unread_count ?? 0);
            }
        });
    };

    // ── Load more ────────────────────────────────────────────────────────
    window.loadMoreNotifs = function() {
        if (_loading) return;
        _loading = true;
        var btn = document.getElementById('notif-load-more-btn');
        if (btn) btn.textContent = '...';

        fetch('index.php?page=api&action=get-notifications&limit=50&offset=' + _offset + '&include_resolved=' + (_includeResolved ? '1' : '0'))
        .then(function(r) { return r.json(); })
        .then(function(res) {
            _loading = false;
            var groups = res.groups || {};
            var fetchedCount = countFetchedNotifications(groups);
            _offset += fetchedCount;

            var hasItems = renderPageGroups(groups, false);
            if (!hasItems || fetchedCount < 50 || _offset >= 200) {
                if (btn) btn.remove();
            } else {
                if (btn) btn.textContent = <?php echo json_encode(t('Load more')); ?>;
            }
        })
        .catch(function() {
            _loading = false;
            if (btn) btn.textContent = <?php echo json_encode(t('Load more')); ?>;
        });
    };

    function refreshPageBadge(count) {
        var badge = document.querySelector('.notif-page-badge');
        if (badge) {
            if (count <= 0) {
                badge.remove();
            } else {
                badge.textContent = count > 99 ? '99+' : count;
            }
        }
    }

    function syncMarkAllButton(count) {
        var markAllBtn = document.querySelector('.notif-mark-all-btn');
        if (markAllBtn) {
            markAllBtn.disabled = count <= 0;
        }
    }

    function loadInitialNotifs() {
        var loading = document.getElementById('notif-page-loading');
        if (loading) loading.classList.remove('hidden');

        fetch('index.php?page=api&action=get-notifications&limit=50&offset=0&include_resolved=' + (_includeResolved ? '1' : '0'))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (loading) loading.remove();

                if (!res.success) {
                    document.getElementById('notif-list').innerHTML = renderEmptyState();
                    return;
                }

                var groups = res.groups || {};
                _offset = countFetchedNotifications(groups);
                renderPageGroups(groups, true);
                refreshPageBadge(res.unread_count || 0);
                syncMarkAllButton(res.unread_count || 0);
                if (typeof updateBadge === 'function') updateBadge(res.unread_count || 0);

                var existingLoadMore = document.getElementById('notif-load-more-btn');
                if (existingLoadMore) existingLoadMore.remove();
                if (_offset >= 50) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'notif-load-more';
                    btn.id = 'notif-load-more-btn';
                    btn.textContent = <?php echo json_encode(t('Load more')); ?>;
                    btn.onclick = function() { loadMoreNotifs(); };
                    document.getElementById('notif-list').appendChild(btn);
                }
            })
            .catch(function() {
                if (loading) loading.remove();
                document.getElementById('notif-list').innerHTML = renderEmptyState();
            });
    }

    // ── Helper: check if action required ─────────────────────────────────
    function isActionRequired(n) {
        var data = n.data || {};
        return n.type === 'assigned_to_you' || n.type === 'due_date_reminder' ||
               (n.type === 'new_comment' && data.action_required);
    }

    // ── Helper: escape HTML ──────────────────────────────────────────────
    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    function avatarColor(name) {
        var h = 0;
        for (var i = 0; i < name.length; i++) h = (name.charCodeAt(i) + ((h << 5) - h));
        return 'hsl(' + (Math.abs(h) % 360) + ', 55%, 60%)';
    }

    function typeInfo(type) {
        return NOTIFICATION_TYPE_META[type] || { icon: 'bell', color: '#6b7280' };
    }

    function getGroupLabel(groupKey) {
        if (groupKey === 'today') return <?php echo json_encode(t('Today')); ?>;
        if (groupKey === 'yesterday') return <?php echo json_encode(t('Yesterday')); ?>;
        return <?php echo json_encode(t('Earlier')); ?>;
    }

    function notificationMatchesFilter(n) {
        var isAction = !!(n && n.is_action);
        var isResolved = !!(n && n.is_resolved);

        if (_filter === 'action') return isAction;
        if (_filter === 'info') return !isAction && !isResolved;
        if (_filter === 'resolved') return isResolved;
        return !isResolved;
    }

    function filterTicketGroupForPage(tg) {
        if (!tg || !tg.primary) return null;

        var items = [tg.primary].concat(tg.others || []).filter(notificationMatchesFilter);
        if (!items.length) return null;

        return {
            ticket_id: tg.ticket_id,
            primary: items[0],
            others: items.slice(1),
            count: items.length,
            has_unread: items.some(function(item) { return !item.is_read; })
        };
    }

    function countFetchedNotifications(groups) {
        var total = 0;
        ['today', 'yesterday', 'earlier'].forEach(function(grp) {
            (groups[grp] || []).forEach(function(tg) {
                total += Number(tg.count || 0);
            });
        });
        return total;
    }

    function renderEmptyState() {
        return '<div class="notif-empty">'
            + <?php echo json_encode(get_icon('bell', 'w-12 h-12 notif-empty-icon')); ?>
            + '<p class="text-base font-medium" style="color: var(--text-secondary);">' + esc(<?php echo json_encode(t('No notifications')); ?>) + '</p>'
            + '<p class="text-sm mt-1">' + esc(<?php echo json_encode(t('Activity on your tickets will appear here')); ?>) + '</p>'
            + '</div>';
    }

    function renderPageGroups(groups, replace) {
        var container = document.getElementById('notif-list');
        if (!container) return false;

        var html = '';
        var renderedAny = false;

        ['today', 'yesterday', 'earlier'].forEach(function(grp) {
            var filteredGroups = (groups[grp] || [])
                .map(filterTicketGroupForPage)
                .filter(Boolean);

            if (!filteredGroups.length) return;

            renderedAny = true;
            html += '<div class="notif-date-label" data-notif-group-label="' + grp + '">' + esc(getGroupLabel(grp)) + '</div>';
            html += '<div class="space-y-1" data-notif-group="' + grp + '">';
            filteredGroups.forEach(function(tg) {
                html += buildNotifGroup(tg);
            });
            html += '</div>';
        });

        if (!renderedAny && replace) {
            container.innerHTML = renderEmptyState();
            return false;
        }

        if (replace) {
            container.innerHTML = html;
        } else if (html) {
            container.insertAdjacentHTML('beforeend', html);
        }

        return renderedAny;
    }

    // ── Build a full ticket group (primary + children) ───────────────────
    function buildNotifGroup(tg) {
        var primary = tg.primary;
        var others = tg.others || [];
        var count = tg.count || 1;

        if (count <= 1) {
            return buildNotifCard(primary, 1);
        }

        var html = '<div class="notif-ticket-group">';
        html += buildNotifCard(primary, count);
        html += '<div class="notif-group-children">';
        others.forEach(function(child) {
            html += buildChildCard(child);
        });
        html += '</div></div>';
        return html;
    }

    // ── Build primary notification card ──────────────────────────────────
    function buildNotifCard(n, groupCount) {
        groupCount = groupCount || 1;
        var data = n.data || {};
        var isRead = !!n.is_read;
        var isAction = isActionRequired(n);
        var actorName = ((n.actor_first_name || '') + ' ' + (n.actor_last_name || '')).trim();
        var initials = (n.actor_first_name || '?').charAt(0).toUpperCase();
        var snippet = n.snippet || data.comment_preview || '';
        var commentId = data.comment_id || null;
        var ticketId = n.ticket_id || null;
        var subject = data.ticket_subject || '';
        if (subject.length > 60) {
            subject = subject.slice(0, 57) + '...';
        }
        var actionText = n.action_text || n.formatted_text || n.text || n.type;
        var href = '#';
        if (ticketId) {
            href = 'index.php?page=ticket&id=' + ticketId + '&ref=notifications&nid=' + n.id;
            if (commentId) href += '#comment-' + commentId;
        }

        var html = '<div class="notif-card ' + (isRead ? '' : 'unread') + '" id="notif-item-' + n.id + '" data-id="' + n.id + '">';
        html += '<a href="' + esc(href) + '" class="notif-avatar" style="background:' + avatarColor(actorName) + '">';
        if (n.actor_avatar) {
            html += '<img src="' + esc(n.actor_avatar) + '" alt="" onerror="this.style.display=\'none\';this.parentElement.textContent=\'' + esc(initials) + '\'">';
        } else {
            html += esc(initials);
        }
        html += '</a>';
        html += '<a href="' + esc(href) + '" class="notif-card-content" style="text-decoration:none">';
        if (subject) {
            html += '<div class="notif-card-subject">' + esc(subject) + '</div>';
        }
        html += '<div class="notif-card-action">' + esc(actionText) + '</div>';
        if (snippet) html += '<div class="notif-card-snippet">' + esc(snippet) + '</div>';
        html += '<div class="notif-card-meta">';
        html += '<span class="notif-card-time">' + esc(n.time_ago || '') + '</span>';
        if (isAction) html += '<span class="notif-action-badge">' + esc(<?php echo json_encode(t('Action required')); ?>) + '</span>';
        html += '</div></a>';
        html += '<div class="notif-card-actions">';
        if (groupCount > 1) {
            html += '<button type="button" class="notif-group-toggle"><span class="notif-group-count">+' + (groupCount - 1) + '</span></button>';
        }
        if (!isRead) {
            html += '<button type="button" class="notif-mark-read-btn" onclick="event.stopPropagation();markNotifRead(' + n.id + ')" title="' + esc(<?php echo json_encode(t('Mark as read')); ?>) + '">';
            html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
            html += '</button>';
        }
        html += '<a href="' + esc(href) + '" class="notif-mark-read-btn">';
        html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
        html += '</a></div></div>';
        return html;
    }

    // ── Build compact child card ─────────────────────────────────────────
    function buildChildCard(n) {
        var data = n.data || {};
        var isRead = !!n.is_read;
        var isAction = isActionRequired(n);
        var ticketId = n.ticket_id || null;
        var commentId = data.comment_id || null;
        var href = '#';
        if (ticketId) {
            href = 'index.php?page=ticket&id=' + ticketId + '&ref=notifications&nid=' + n.id;
            if (commentId) href += '#comment-' + commentId;
        }
        var text = n.action_text || n.formatted_text || n.text || n.type;

        var html = '<a href="' + esc(href) + '" class="notif-child-card ' + (isRead ? '' : 'unread') + '" id="notif-item-' + n.id + '" data-id="' + n.id + '">';
        html += '<span class="notif-child-text">' + esc(text) + '</span>';
        html += '<span class="notif-child-time">' + esc(n.time_ago || '') + '</span>';
        if (isAction) html += '<span class="notif-action-badge">' + esc(<?php echo json_encode(t('Action required')); ?>) + '</span>';
        html += '</a>';
        return html;
    }

    loadInitialNotifs();
})();

    // ── Compact / Normal view toggle ─────────────────────────────────────
    var savedView = localStorage.getItem('notif_view') || 'normal';
    var wrap = document.getElementById('notifPageWrap');
    if (savedView === 'compact' && wrap) {
        wrap.classList.add('compact');
        var btns = document.querySelectorAll('.notif-view-btn');
        btns.forEach(function(b) {
            b.classList.toggle('active', b.getAttribute('data-view') === 'compact');
        });
    }

    window.setNotifView = function(view) {
        var w = document.getElementById('notifPageWrap');
        if (!w) return;
        if (view === 'compact') {
            w.classList.add('compact');
        } else {
            w.classList.remove('compact');
        }
        localStorage.setItem('notif_view', view);
        document.querySelectorAll('.notif-view-btn').forEach(function(b) {
            b.classList.toggle('active', b.getAttribute('data-view') === view);
        });
    };
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
