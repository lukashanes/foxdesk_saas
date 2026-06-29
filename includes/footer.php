</div>

<!-- Footer -->
<footer class="app-footer px-4 lg:px-8 py-3 text-xs">
    <div class="copyright">
        <a href="https://foxdesk.org" target="_blank" rel="noopener" class="text-theme-muted">FoxDesk</a>
    </div>
</footer>
</main>

<script>
    // App config for external JS (bridge PHP → JS)
    window.appConfig = {
        apiUrl: <?php echo json_encode(url('api')); ?>,
        page: <?php echo json_encode((string) ($page ?? '')); ?>,
        csrfToken: <?php echo json_encode(csrf_token()); ?>,
        deleteConfirmMsg: <?php echo json_encode(t('Are you sure you want to delete this item?')); ?>,
        invalidFileTypeMsg: <?php echo json_encode(t('Invalid file type.')); ?>,
        isStaff: <?php echo (is_agent() || is_admin()) ? 'true' : 'false'; ?>,
        isAdmin: <?php echo is_admin() ? 'true' : 'false'; ?>,
        pausedLabel: <?php echo json_encode(t('Paused')); ?>,
        activeTimersLabel: <?php echo json_encode(t('Active Timers')); ?>,
        cancelTicketConfirm: <?php echo json_encode(t('Cancel ticket? The ticket will be deleted.')); ?>,
        cancelTicketTooltip: <?php echo json_encode(t('Cancel ticket')); ?>,
        stopTimerLabel: <?php echo json_encode(t('Stop timer')); ?>,
        timerStoppedLabel: <?php echo json_encode(t('Timer stopped.')); ?>,
        errorLabel: <?php echo json_encode(t('Error')); ?>,
        savedLabel: <?php echo json_encode(t('Saved')); ?>,
        pauseLabel: <?php echo json_encode(t('Pause')); ?>,
        resumeLabel: <?php echo json_encode(t('Resume')); ?>
    };
</script>
<?php
$footer_asset_base_version = defined('APP_VERSION') ? (string) APP_VERSION : '1';
$footer_asset_version = static function (string $path) use ($footer_asset_base_version): string {
    $absolute_path = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/' . ltrim($path, '/');
    return $footer_asset_base_version . '-' . (string) (@filemtime($absolute_path) ?: '0');
};
?>
<script defer src="assets/js/app-api-client.js?v=<?php echo e($footer_asset_version('assets/js/app-api-client.js')); ?>"></script>
<script defer src="assets/js/app-contract-shell.js?v=<?php echo e($footer_asset_version('assets/js/app-contract-shell.js')); ?>"></script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').catch(function() {});
}
</script>
<script>
// ── Push Notification Subscription Management ────────────────────────────
(function() {
    var pushBtn, pushOn, pushOff;

    function initPushUI() {
        pushBtn = document.getElementById('push-toggle-btn');
        if (!pushBtn) return;
        pushOn = pushBtn.querySelector('.push-icon-on');
        pushOff = pushBtn.querySelector('.push-icon-off');

        // Only show if browser supports push
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

        pushBtn.classList.remove('is-hidden');

        // Check current state
        navigator.serviceWorker.ready.then(function(reg) {
            reg.pushManager.getSubscription().then(function(sub) {
                updatePushUI(!!sub);
            });
        });
    }

    function updatePushUI(subscribed) {
        if (!pushOn || !pushOff || !pushBtn) return;
        if (subscribed) {
            pushOn.classList.remove('is-hidden');
            pushOff.classList.add('is-hidden');
            pushBtn.classList.add('is-active');
            pushBtn.title = <?php echo json_encode(t('Push notifications enabled')); ?>;
        } else {
            pushOn.classList.add('is-hidden');
            pushOff.classList.remove('is-hidden');
            pushBtn.classList.remove('is-active');
            pushBtn.title = <?php echo json_encode(t('Enable push notifications')); ?>;
        }
    }

    window.togglePushNotifications = function() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

        navigator.serviceWorker.ready.then(function(reg) {
            reg.pushManager.getSubscription().then(function(sub) {
                if (sub) {
                    // Unsubscribe
                    sub.unsubscribe().then(function() {
                        // Tell server
                        fetch('index.php?page=api&action=push-unsubscribe', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken},
                            body: JSON.stringify({endpoint: sub.endpoint, csrf_token: window.csrfToken})
                        }).catch(function(){});
                        updatePushUI(false);
                    });
                } else {
                    // Subscribe
                    Notification.requestPermission().then(function(perm) {
                        if (perm !== 'granted') return;

                        // Get VAPID public key
                        fetch('index.php?page=api&action=push-vapid-key')
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (!data.publicKey) return;

                                // Convert base64url to Uint8Array
                                var raw = atob(data.publicKey.replace(/-/g, '+').replace(/_/g, '/'));
                                var arr = new Uint8Array(raw.length);
                                for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);

                                return reg.pushManager.subscribe({
                                    userVisibleOnly: true,
                                    applicationServerKey: arr
                                });
                            })
                            .then(function(newSub) {
                                if (!newSub) return;

                                var key = newSub.getKey('p256dh');
                                var auth = newSub.getKey('auth');

                                var body = {
                                    endpoint: newSub.endpoint,
                                    p256dh: key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : '',
                                    auth: auth ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth))) : '',
                                    csrf_token: window.csrfToken
                                };

                                fetch('index.php?page=api&action=push-subscribe', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken},
                                    body: JSON.stringify(body)
                                }).catch(function(){});

                                updatePushUI(true);
                            })
                            .catch(function(err) {
                                console.warn('Push subscription failed:', err);
                            });
                    });
                }
            });
        });
    };

    if (document.readyState !== 'loading') {
        initPushUI();
    } else {
        document.addEventListener('DOMContentLoaded', initPushUI);
    }
})();
</script>
<!-- Image Preview Lightbox -->
<div id="image-lightbox" class="image-lightbox" hidden aria-hidden="true">
    <div class="image-lightbox__dialog" role="dialog" aria-modal="true" aria-labelledby="lightbox-name">
        <img id="lightbox-img" class="image-lightbox__image" alt="">
        <div id="lightbox-name" class="image-lightbox__name"></div>
        <button type="button" class="image-lightbox__close" data-image-preview-close aria-label="<?php echo e(t('Close')); ?>">&times;</button>
    </div>
</div>
<script defer src="assets/js/image-preview.js?v=<?php echo e($footer_asset_version('assets/js/image-preview.js')); ?>"></script>
<script defer src="assets/js/app-footer.js?v=<?php echo e($footer_asset_version('assets/js/app-footer.js')); ?>"></script>
</body>

</html>
