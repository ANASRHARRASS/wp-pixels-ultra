(function (window) {
  'use strict';

  var cfg = window.UP_CONFIG || {};
  var ingestUrl = cfg.ingest_url || (window.location.origin + '/wp-json/up/v1/ingest');
  var wpNonce = cfg.wp_nonce || (window.wpApiSettings && window.wpApiSettings.nonce) || null;

  function sendToIngest(payload) {
    try {
      var body = JSON.stringify(payload);
      var headers = { 'Content-Type': 'application/json' };
      if (wpNonce) headers['X-WP-Nonce'] = wpNonce;

      // Prefer sendBeacon for navigation reliability when nonce not required
      if (navigator && navigator.sendBeacon && !wpNonce) {
        try {
          var blob = new Blob([body], { type: 'application/json' });
          navigator.sendBeacon(ingestUrl, blob);
          return;
        } catch (e) { /* fallback to fetch */ }
      }

      fetch(ingestUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers,
        body: body,
        keepalive: true
      }).then(function (res) {
        if (!res.ok && window.console) console.warn('UP forwarder: server returned', res.status);
      }).catch(function (err) {
        if (window.console) console.error('UP forwarder fetch failed', err);
      });
    } catch (err) {
      if (window.console) console.error('UP forwarder exception', err);
    }
  }

  if (!window.UP_GTM_FORWARD) {
    window.UP_GTM_FORWARD = function (evt) {
      try {
        if (!evt || typeof evt !== 'object') return;
        // Remove raw PII coming from GTM; server will handle hashing/advanced matching
        if (evt.user && (evt.user.email || evt.user.phone || evt.user.phone_number)) {
          delete evt.user.email;
          delete evt.user.phone;
          delete evt.user.phone_number;
        }
        if (!evt.event) evt.event = evt.event_name || 'custom_event';
        sendToIngest(evt);
      } catch (e) {
        if (window.console) console.error('UP_GTM_FORWARD exception', e);
      }
    };
  }
})(window);
