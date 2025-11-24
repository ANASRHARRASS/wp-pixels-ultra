/* Admin UI for Ultra Pixels queue/dead-letter management */
(function ($) {
    $(function () {
        const $form = $('form[action="options.php"]');
        const $txt = $('#event_mapping');

        if ($form.length && $txt.length) {
            $form.on('submit', function (e) {
                const val = $txt.val().trim();
                if (!val) return true;
                try {
                    JSON.parse(val);
                    return true;
                } catch (err) {
                    e.preventDefault();
                    alert('Event mapping JSON is invalid. Please correct the JSON before saving.\n\n' + err.message);
                    $txt.focus();
                    return false;
                }
            });
        }

        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function truncate(s, n) {
            if (!s) return '';
            if (s.length <= n) return s;
            return s.slice(0, n) + '…';
        }

        // Test event
        const $btn = $('#up-send-test');
        if ($btn.length && typeof UPAdmin !== 'undefined') {
            $btn.on('click', function (e) {
                e.preventDefault();
                const ev = $('#up-test-event').val() || 'purchase';
                $('#up-test-result').text('Sending test event...');
                fetch(UPAdmin.test_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': UPAdmin.nonce },
                    body: JSON.stringify({ event: ev })
                }).then(function (resp) { return resp.json(); }).then(function (data) {
                    $('#up-test-result').text(JSON.stringify(data, null, 2));
                }).catch(function (err) {
                    $('#up-test-result').text('Error: ' + err.message);
                });
            });
        }

        // Process queue button + status refresh
        const $proc = $('#up-process-queue');
        if ($proc.length && typeof UPAdmin !== 'undefined') {
            $proc.on('click', function (e) {
                e.preventDefault();
                $('#up-process-result').text('Processing...');
                fetch(UPAdmin.process_url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': UPAdmin.nonce } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.ok) {
                            $('#up-process-result').text('Processed: ' + (data.processed || 0));
                            return fetch(UPAdmin.status_url, { headers: { 'X-WP-Nonce': UPAdmin.nonce } })
                                .then(function (r) { return r.json(); })
                                .then(function (s) {
                                    if (s && s.ok) {
                                        $('#up-queue-length').text(s.length || 0);
                                        $('#up-last-processed').text(s.last_processed ? new Date(s.last_processed * 1000).toISOString() : 'never');
                                    }
                                });
                        } else {
                            $('#up-process-result').text('Error: ' + JSON.stringify(data));
                        }
                    })
                    .catch(function (err) { $('#up-process-result').text('Error: ' + err.message); });
            });

            // initial status fetch
            fetch(UPAdmin.status_url, { headers: { 'X-WP-Nonce': UPAdmin.nonce } }).then(function (r) { return r.json(); }).then(function (s) {
                if (s && s.ok) {
                    $('#up-queue-length').text(s.length || 0);
                    $('#up-last-processed').text(s.last_processed ? new Date(s.last_processed * 1000).toISOString() : 'never');
                }
            });
        }

        // Queue UI
        if (typeof UPAdmin !== 'undefined' && $('#up-queue-items').length) {
            const queueState = { limit: parseInt($('#up-queue-limit').val() || '20', 10), offset: 0 };

            const renderQueue = function (items) {
                const $c = $('#up-queue-items');
                if (!items || !items.length) { $c.html('<p>No items in queue.</p>'); return; }
                let html = '<table class="widefat"><thead><tr><th>ID</th><th>Platform</th><th>Event</th><th>Attempts</th><th>Next Attempt</th><th>Payload</th><th>Actions</th></tr></thead><tbody>';
                items.forEach(function (row) {
                    let payload = '';
                    try { payload = typeof row.payload === 'string' ? row.payload : JSON.stringify(row.payload); } catch (e) { payload = String(row.payload); }
                    html += '<tr data-id="' + row.id + '">';
                    html += '<td>' + row.id + '</td>';
                    html += '<td>' + escapeHtml(row.platform || '') + '</td>';
                    html += '<td>' + escapeHtml(row.event_name || '') + '</td>';
                    html += '<td>' + (row.attempts || 0) + '</td>';
                    html += '<td>' + (row.next_attempt ? new Date(row.next_attempt * 1000).toISOString() : '') + '</td>';
                    html += '<td><pre style="max-height:80px;overflow:auto;margin:0;padding:4px;background:#fff;border:1px solid #eee;">' + escapeHtml(truncate(payload, 1000)) + '</pre></td>';
                    html += '<td><button class="button up-queue-retry">Retry</button> <button class="button up-queue-delete">Delete</button></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                html += '<p style="margin-top:8px;"><button id="up-queue-prev" class="button">Prev</button> <button id="up-queue-next" class="button">Next</button></p>';
                $c.html(html);

                $c.find('.up-queue-retry').on('click', function () {
                    const id = $(this).closest('tr').data('id');
                    if (!confirm('Retry queue item #' + id + '?')) return;
                    fetch(UPAdmin.retry_url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': UPAdmin.nonce }, body: JSON.stringify({ id: id }) })
                        .then(function (r) { return r.json(); })
                        .then(function (d) { if (d && d.ok) { fetchQueue(); } else { alert('Error: ' + JSON.stringify(d)); } })
                        .catch(function (e) { alert('Error: ' + e.message); });
                });

                $c.find('.up-queue-delete').on('click', function () {
                    const id = $(this).closest('tr').data('id');
                    if (!confirm('Delete queue item #' + id + '?')) return;
                    fetch(UPAdmin.delete_url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': UPAdmin.nonce }, body: JSON.stringify({ id: id }) })
                        .then(function (r) { return r.json(); })
                        .then(function (d) { if (d && d.ok) { fetchQueue(); } else { alert('Error: ' + JSON.stringify(d)); } })
                        .catch(function (e) { alert('Error: ' + e.message); });
                });

                $('#up-queue-prev').on('click', function (e) { e.preventDefault(); queueState.offset = Math.max(0, queueState.offset - queueState.limit); fetchQueue(); });
                $('#up-queue-next').on('click', function (e) { e.preventDefault(); queueState.offset += queueState.limit; fetchQueue(); });
            };

            const fetchQueue = function () {
                const url = UPAdmin.items_url + '?limit=' + queueState.limit + '&offset=' + queueState.offset + '&_=' + Date.now();
                fetch(url, { headers: { 'X-WP-Nonce': UPAdmin.nonce } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.ok) { renderQueue(data.items || []); }
                        else { $('#up-queue-items').html('<p>Error loading queue: ' + JSON.stringify(data) + '</p>'); }
                        fetchDeadletter();
                        fetchLogs();
                    })
                    .catch(function (err) { $('#up-queue-items').html('<p>Error: ' + err.message + '</p>'); });
            };

            const renderDeadletter = function (items) {
                const $c = $('#up-deadletter-items');
                if (!items || !items.length) { $c.html('<p>No dead-letter items.</p>'); return; }
                let html = '<table class="widefat" id="up-deadletter-table"><thead><tr><th>ID</th><th>Platform</th><th>Event</th><th>Failed At</th><th>Failure</th><th>Payload</th><th>Actions</th></tr></thead><tbody>';
                items.forEach(function (row) {
                    let payload = '';
                    try { payload = typeof row.payload === 'string' ? row.payload : JSON.stringify(row.payload); } catch (e) { payload = String(row.payload); }
                    html += '<tr data-id="' + row.id + '">';
                    html += '<td>' + row.id + '</td>';
                    html += '<td>' + escapeHtml(row.platform || '') + '</td>';
                    html += '<td>' + escapeHtml(row.event_name || '') + '</td>';
                    html += '<td>' + (row.failed_at ? new Date(row.failed_at * 1000).toISOString() : '') + '</td>';
                    html += '<td class="error">' + escapeHtml(row.failure_message || '') + '</td>';
                    html += '<td><pre style="max-height:80px;overflow:auto;margin:0;padding:4px;background:#fff;border:1px solid #eee;">' + escapeHtml(truncate(payload, 1000)) + '</pre></td>';
                    html += '<td><button class="button up-dead-retry">Retry</button> <button class="button up-dead-delete">Delete</button></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                $('#up-deadletter-items').html(html);

                $('#up-deadletter-items .up-dead-retry').on('click', function () {
                    const id = $(this).closest('tr').data('id');
                    if (!confirm('Retry dead-letter #' + id + '?')) return;
                    fetch(UPAdmin.deadletter_retry_url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': UPAdmin.nonce }, body: JSON.stringify({ id: id }) })
                        .then(function (r) { return r.json(); })
                        .then(function (d) { if (d && d.ok) { alert('Retried to queue'); fetchQueue(); } else { alert('Error: ' + JSON.stringify(d)); } })
                        .catch(function (e) { alert('Error: ' + e.message); });
                });

                $('#up-deadletter-items .up-dead-delete').on('click', function () {
                    const id = $(this).closest('tr').data('id');
                    if (!confirm('Delete dead-letter #' + id + '?')) return;
                    fetch(UPAdmin.deadletter_delete_url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': UPAdmin.nonce }, body: JSON.stringify({ id: id }) })
                        .then(function (r) { return r.json(); })
                        .then(function (d) { if (d && d.ok) { alert('Deleted'); fetchQueue(); } else { alert('Error: ' + JSON.stringify(d)); } })
                        .catch(function (e) { alert('Error: ' + e.message); });
                });
            };

            const fetchDeadletter = function () {
                if (!UPAdmin.deadletter_url) return;
                const url = UPAdmin.deadletter_url + '?limit=20&offset=0&_=' + Date.now();
                fetch(url, { headers: { 'X-WP-Nonce': UPAdmin.nonce } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { if (d && d.ok) renderDeadletter(d.items || []); })
                    .catch(function (e) { console.error('deadletter fetch', e); }); // eslint-disable-line no-console
            };

            const renderLogs = function (logs) {
                const $c = $('#up-error-entries');
                if (!logs || !logs.length) { $c.html('<p>No logs.</p>'); return; }
                let html = '<ul style="list-style:none;padding:0;margin:0;">';
                logs.forEach(function (l) {
                    html += '<li style="padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.02);"><strong>' + escapeHtml(l.level || 'info') + '</strong> <span style="color:#9fb0c8">' + escapeHtml(l.time) + '</span><div style="font-family:monospace;font-size:12px;">' + escapeHtml(l.msg) + '</div></li>';
                });
                html += '</ul>';
                $c.html(html);
                $('#up-error-log').show();
            };

            const fetchLogs = function () {
                if (!UPAdmin.logs_url) return;
                fetch(UPAdmin.logs_url + '?_=' + Date.now(), { headers: { 'X-WP-Nonce': UPAdmin.nonce } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { if (d && d.ok) renderLogs(d.logs || []); })
                    .catch(function (e) { console.error('logs fetch', e); }); // eslint-disable-line no-console
            };

            $('#up-queue-refresh').on('click', function (e) {
                e.preventDefault();
                queueState.limit = parseInt($('#up-queue-limit').val() || '20', 10);
                queueState.offset = 0;
                fetchQueue();
            });

            // initial load
            fetchQueue();
            fetchDeadletter();
            fetchLogs();
        }
    });
})(jQuery);