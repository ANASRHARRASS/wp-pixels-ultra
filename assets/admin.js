/* filepath: c:\Users\tchiboka\Desktop\anas\ultra\assets\admin.js */
(function($){
    $(function(){
        var $form = $('form[action="options.php"]');
        var $txt = $('#event_mapping');

        if ($form.length && $txt.length) {
            $form.on('submit', function(e){
                var val = $txt.val().trim();
                if (!val) return true;
                try {
                    JSON.parse(val);
                    return true;
                } catch(err) {
                    e.preventDefault();
                    alert('Event mapping JSON is invalid. Please correct the JSON before saving.\n\n' + err.message);
                    $txt.focus();
                    return false;
                }
            });
        }

        // Test event handler
        var $btn = $('#up-send-test');
        if ($btn.length && typeof UPAdmin !== 'undefined') {
            $btn.on('click', function(e){
                e.preventDefault();
                var ev = $('#up-test-event').val() || 'purchase';
                $('#up-test-result').text('Sending test event...');
                fetch(UPAdmin.test_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': UPAdmin.nonce
                    },
                    body: JSON.stringify({ event: ev })
                }).then(function(resp){
                    return resp.json();
                }).then(function(data){
                    $('#up-test-result').text(JSON.stringify(data, null, 2));
                }).catch(function(err){
                    $('#up-test-result').text('Error: ' + err.message);
                });
            });
        }

        // Process queue handler
        var $proc = $('#up-process-queue');
        if ($proc.length && typeof UPAdmin !== 'undefined') {
            $proc.on('click', function(e){
                e.preventDefault();
                $('#up-process-result').text('Processing...');
                fetch(UPAdmin.process_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': UPAdmin.nonce
                    }
                }).then(function(r){ return r.json(); }).then(function(data){
                    if (data && data.ok) {
                        $('#up-process-result').text('Processed: ' + (data.processed||0));
                        // refresh status
                        fetch(UPAdmin.status_url, { headers: { 'X-WP-Nonce': UPAdmin.nonce } }).then(function(r){ return r.json(); }).then(function(s){
                            if (s && s.ok) {
                                $('#up-queue-length').text(s.length||0);
                                $('#up-last-processed').text(s.last_processed ? new Date(s.last_processed * 1000).toISOString() : 'never');
                            }
                        });
                    } else {
                        $('#up-process-result').text('Error: ' + JSON.stringify(data));
                    }
                }).catch(function(err){ $('#up-process-result').text('Error: ' + err.message); });
            });

            // initial status fetch
            fetch(UPAdmin.status_url, { headers: { 'X-WP-Nonce': UPAdmin.nonce } }).then(function(r){ return r.json(); }).then(function(s){
                if (s && s.ok) {
                    $('#up-queue-length').text(s.length||0);
                    $('#up-last-processed').text(s.last_processed ? new Date(s.last_processed * 1000).toISOString() : 'never');
                }
            });
        }

        // Queue items listing
        if (typeof UPAdmin !== 'undefined' && $('#up-queue-items').length) {
            var queueState = { limit: parseInt($('#up-queue-limit').val() || '20', 10), offset: 0 };

            function truncate(s, n) {
                if (!s) return '';
                if (s.length <= n) return s;
                return s.slice(0, n) + '…';
            }

            function renderQueue(items) {
                var $c = $('#up-queue-items');
                if (!items || !items.length) {
                    $c.html('<p>No items in queue.</p>');
                    return;
                }
                var html = '<table class="widefat"><thead><tr><th>ID</th><th>Attempts</th><th>Available At</th><th>Payload</th><th>Actions</th></tr></thead><tbody>';
                items.forEach(function(row){
                    var payload = '';
                    try { payload = typeof row.payload === 'string' ? row.payload : JSON.stringify(row.payload); } catch(e){ payload = String(row.payload); }
                    html += '<tr data-id="'+row.id+'">';
                    html += '<td>'+row.id+'</td>';
                    html += '<td>'+ (row.attempts || 0) +'</td>';
                    html += '<td>' + (row.available_at ? new Date(row.available_at * 1000).toISOString() : '') + '</td>';
                    html += '<td><pre style="max-height:80px;overflow:auto;margin:0;padding:4px;background:#fff;border:1px solid #eee;">'+escapeHtml(truncate(payload, 1000))+'</pre></td>';
                    html += '<td><button class="button up-queue-retry">Retry</button> <button class="button up-queue-delete">Delete</button></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                // pagination controls
                html += '<p style="margin-top:8px;"><button id="up-queue-prev" class="button">Prev</button> <button id="up-queue-next" class="button">Next</button></p>';
                $c.html(html);

                $c.find('.up-queue-retry').on('click', function(){
                    var id = $(this).closest('tr').data('id');
                    if (!confirm('Retry queue item #' + id + '?')) return;
                    fetch(UPAdmin.retry_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': UPAdmin.nonce },
                        body: JSON.stringify({ id: id })
                    }).then(function(r){ return r.json(); }).then(function(d){
                        if (d && d.ok) { alert('Retry scheduled'); fetchQueue(); } else { alert('Error: ' + JSON.stringify(d)); }
                    }).catch(function(err){ alert('Error: ' + err.message); });
                });

                $c.find('.up-queue-delete').on('click', function(){
                    var id = $(this).closest('tr').data('id');
                    if (!confirm('Delete queue item #' + id + '? This cannot be undone.')) return;
                    fetch(UPAdmin.delete_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': UPAdmin.nonce },
                        body: JSON.stringify({ id: id })
                    }).then(function(r){ return r.json(); }).then(function(d){
                        if (d && d.ok) { alert('Deleted'); fetchQueue(); } else { alert('Error: ' + JSON.stringify(d)); }
                    }).catch(function(err){ alert('Error: ' + err.message); });
                });

                $('#up-queue-prev').on('click', function(){ if (queueState.offset <= 0) return; queueState.offset = Math.max(0, queueState.offset - queueState.limit); fetchQueue(); });
                $('#up-queue-next').on('click', function(){ queueState.offset = queueState.offset + queueState.limit; fetchQueue(); });
            }

            function escapeHtml(str) {
                return String(str).replace(/[&<>\"]/g, function(s){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[s]; });
            }

            function fetchQueue() {
                var url = UPAdmin.items_url + '?limit=' + queueState.limit + '&offset=' + queueState.offset;
                fetch(url, { headers: { 'X-WP-Nonce': UPAdmin.nonce } }).then(function(r){ return r.json(); }).then(function(data){
                    if (data && data.ok) {
                        renderQueue(data.items || []);
                    } else {
                        $('#up-queue-items').html('<p>Error loading queue: ' + JSON.stringify(data) + '</p>');
                    }
                }).catch(function(err){ $('#up-queue-items').html('<p>Error: ' + err.message + '</p>'); });
            }

            $('#up-queue-refresh').on('click', function(e){ e.preventDefault(); queueState.limit = parseInt($('#up-queue-limit').val()||'20',10); queueState.offset = 0; fetchQueue(); });

            // initial load
            fetchQueue();
        }
    });
})(jQuery);