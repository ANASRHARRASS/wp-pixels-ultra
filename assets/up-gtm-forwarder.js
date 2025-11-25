(function (window) {
  'use strict';

  const cfg = window.UP_CONFIG || {};
  const ingestUrl = cfg.ingest_url || (window.location.origin + '/wp-json/up/v1/ingest');
  const wpNonce = cfg.wp_nonce || (window.wpApiSettings && window.wpApiSettings.nonce) || null;

  function sendToIngest(payload) {
    try {
      const body = JSON.stringify(payload);
      const headers = { 'Content-Type': 'application/json' };
      if (wpNonce) headers['X-WP-Nonce'] = wpNonce;

      // Always use fetch with keepalive for reliability and to ensure X-WP-Nonce is sent.
      // navigator.sendBeacon does not support custom headers, so cannot be used for authenticated requests.

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

        // Create a deep copy to avoid mutating caller's data
        const normalizedEvt = JSON.parse(JSON.stringify(evt));

        // Normalize GTM alias: support evt.user but prefer evt.user_data
        // Use the deep-copied object consistently (check normalizedEvt, not evt)
        if (normalizedEvt.user && !normalizedEvt.user_data) {
          normalizedEvt.user_data = normalizedEvt.user;
        }

        // Remove raw PII for privacy; these fields are not sent to the server
        if (normalizedEvt.user_data && typeof normalizedEvt.user_data === 'object') {
          delete normalizedEvt.user_data.email;
          delete normalizedEvt.user_data.phone;
          delete normalizedEvt.user_data.phone_number;
        }

        if (!normalizedEvt.event) normalizedEvt.event = normalizedEvt.event_name || 'custom_event';
        sendToIngest(normalizedEvt);
      } catch (e) {
        if (window.console) console.error('UP_GTM_FORWARD exception', e);
      }
    };
  }
})(window);
