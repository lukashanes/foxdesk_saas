(function () {
    'use strict';

    function toast(message, type, options) {
        if (typeof window.showAppToast === 'function') {
            window.showAppToast(message, type, options || {});
            return;
        }
        window.alert(message);
    }

    function post(action, values) {
        var body = new FormData();
        Object.keys(values).forEach(function (key) { body.append(key, values[key]); });
        body.append('csrf_token', window.csrfToken || '');
        return fetch('index.php?page=api&action=' + encodeURIComponent(action), {
            method: 'POST',
            body: body
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || payload.message || 'Request failed.');
                }
                return payload;
            });
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-report-delete-time]');
        if (!button || button.disabled) return;

        var entryId = parseInt(button.getAttribute('data-entry-id') || '0', 10);
        if (!entryId) return;

        button.disabled = true;
        post('delete-time-entry', { entry_id: entryId }).then(function (payload) {
            document.querySelectorAll('[data-entry-id="' + entryId + '"]').forEach(function (row) {
                row.hidden = true;
                row.setAttribute('aria-hidden', 'true');
            });

            var reloadTimer = window.setTimeout(function () {
                window.location.reload();
            }, (parseInt(payload.undo_seconds || '10', 10) + 1) * 1000);

            toast(payload.message || 'Time entry deleted.', 'success', {
                duration: (parseInt(payload.undo_seconds || '10', 10) + 1) * 1000,
                actionLabel: payload.undo_label || 'Undo',
                onAction: function () {
                    window.clearTimeout(reloadTimer);
                    post(payload.undo_action || 'restore-time-entry', {
                        undo_token: payload.undo_token
                    }).then(function () {
                        window.location.reload();
                    }).catch(function (error) {
                        toast(error.message, 'error');
                    });
                }
            });
        }).catch(function (error) {
            button.disabled = false;
            toast(error.message, 'error');
        });
    });
})();
