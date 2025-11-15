(function () {
    'use strict';

    function sendToServer(event) {
        if (typeof UP_CONFIG === 'undefined' || !UP_CONFIG.ingest_url) return;
        try {
            var headers = { 'Content-Type': 'application/json' };
            // Use WP REST nonce for same-origin authorization instead of exposing server secret
            if (UP_CONFIG.nonce) headers['X-WP-Nonce'] = UP_CONFIG.nonce;
            fetch(UP_CONFIG.ingest_url, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(event),
                keepalive: true
            }).catch(function (e) { console.warn('UP server forward failed', e); });
        } catch (e) { console.warn('UP forward error', e); }
    }

    function generateEventId() {
        return 'ev_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
    }

    function injectGTM(id) {
        if (!id) return;
        // Standard GTM snippet (noscript omitted)
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
        var s = document.createElement('script'); s.async = true;
        s.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(id);
        var first = document.getElementsByTagName('script')[0];
        first.parentNode.insertBefore(s, first);
    }

    // Build a simple PageView event and fire
    var event = {
        event_name: 'PageView',
        event_id: generateEventId(),
        event_time: Math.floor(Date.now() / 1000),
        source_url: window.location.href,
        user_data: {},
        custom_data: {}
    };

    // Inject GTM if configured
    if (typeof UP_CONFIG !== 'undefined' && UP_CONFIG.gtm_id) {
        injectGTM(UP_CONFIG.gtm_id);
    }

    // Inject Meta Pixel (minimal) if configured
    if (typeof UP_CONFIG !== 'undefined' && UP_CONFIG.meta_pixel_id) {
        (function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments) }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s) })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
        try { window.fbq('init', UP_CONFIG.meta_pixel_id); window.fbq('track', 'PageView'); } catch (e) { }
    }

    // Inject TikTok Pixel (minimal) if configured
    if (typeof UP_CONFIG !== 'undefined' && UP_CONFIG.tiktok_pixel_id) {
        (function (w, d, t) { w.TiktokAnalyticsObject = t; var ttq = w[t] = w[t] || []; ttq.methods = ['page', 'track']; ttq.setAndDefer = function (n, e) { n[e] = function () { n.push([e].concat(Array.prototype.slice.call(arguments, 0))) } }; for (var i = 0; i < ttq.methods.length; i++)ttq.setAndDefer(ttq, ttq.methods[i]); ttq.load = function (e) { var s = document.createElement('script'); s.type = 'text/javascript'; s.async = true; s.src = 'https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=' + e; var a = document.getElementsByTagName('script')[0]; a.parentNode.insertBefore(s, a) }; ttq.load(UP_CONFIG.tiktok_pixel_id); ttq.page(); })(window, document, 'ttq');
    }

    // Fire server copy
    sendToServer(event);

    // Optionally fire vendor client snippets here (fbq, TikTok) if present

    // Capture clicks on WhatsApp links or elements with data-up-event for custom landing pages
    function findUpElement(el) {
        while (el && el !== document.body) {
            if (el.getAttribute && (el.getAttribute('data-up-event') || el.getAttribute('data-up-whatsapp') || el.classList.contains('up-whatsapp'))) return el;
            el = el.parentNode;
        }
        return null;
    }

    function parseWhatsappHref(href) {
        if (!href) return null;
        try {
            // Examples: https://wa.me/15551234567?text=hello  or https://api.whatsapp.com/send?phone=15551234567
            var u = new URL(href, window.location.href);
            if (u.hostname.indexOf('wa.me') !== -1 || u.hostname.indexOf('whatsapp') !== -1) {
                var phone = u.pathname.replace(/\//g, '').split('?')[0] || (u.searchParams.get('phone') || '');
                return { phone: phone, text: u.searchParams.get('text') || '' };
            }
        } catch (e) { }
        return null;
    }

    function handleUpClick(e) {
        try {
            var el = findUpElement(e.target);
            if (!el) return;
            var eventName = el.getAttribute('data-up-event') || (el.getAttribute('data-up-whatsapp') ? 'whatsapp_click' : (el.classList.contains('up-whatsapp') ? 'whatsapp_click' : null));
            var payload = {};
            if (el.getAttribute('data-up-payload')) {
                try { payload = JSON.parse(el.getAttribute('data-up-payload')); } catch (err) { payload = {}; }
            }

            // If it's a link, try parse whatsapp details
            if (el.tagName === 'A') {
                var href = el.getAttribute('href');
                var wa = parseWhatsappHref(href);
                if (wa) {
                    eventName = eventName || 'whatsapp_initiate';
                    payload.whatsapp_phone = wa.phone;
                    payload.whatsapp_text = wa.text;
                }
            }

            if (!eventName) return;

            var ev = {
                event_name: eventName,
                event_id: generateEventId(),
                event_time: Math.floor(Date.now() / 1000),
                source_url: window.location.href,
                user_data: {},
                custom_data: payload
            };

            // push to dataLayer for GTM if present
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(ev);

            // send to server ingest
            sendToServer(ev);
        } catch (err) { console.warn('UP click handler error', err); }
    }

    // listen capture to catch clicks early (works for dynamically added elements too)
    document.addEventListener('click', handleUpClick, true);

})();
