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

      // Prefer sendBeacon for navigation reliability; server accepts same-origin without nonce
      if (navigator && navigator.sendBeacon) {
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
        // Normalize GTM alias: support evt.user but prefer evt.user_data
        if (evt.user && !evt.user_data) {
          evt.user_data = evt.user;
        }
        // Remove raw PII coming from GTM; server will handle hashing
        if (evt.user_data) {
          if (evt.user_data.email) delete evt.user_data.email;
          if (evt.user_data.phone) delete evt.user_data.phone;
          if (evt.user_data.phone_number) delete evt.user_data.phone_number;
        }
        if (!evt.event) evt.event = evt.event_name || 'custom_event';
        sendToIngest(evt);
      } catch (e) {
        if (window.console) console.error('UP_GTM_FORWARD exception', e);
      }
    };
  }
})(window);
