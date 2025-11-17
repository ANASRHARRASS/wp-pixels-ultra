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

    // Build a simple PageView event and fire (now GTM-friendly with event key)
    var event = {
        event: 'up_event',
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

    var GTM_MANAGES = (typeof UP_CONFIG !== 'undefined' && !!UP_CONFIG.gtm_manage_pixels);

    // Inject Meta Pixel (minimal) if configured and not managed by GTM
    if (!GTM_MANAGES && typeof UP_CONFIG !== 'undefined' && UP_CONFIG.meta_pixel_id) {
        (function (f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
            if (!f._fbq) f._fbq = n;
            n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
            t = b.createElement(e); t.async = true; t.src = v;
            s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
        })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
        try { window.fbq('init', UP_CONFIG.meta_pixel_id); window.fbq('track', 'PageView'); } catch (err) { /* ignore */ }
    }

    // Inject TikTok Pixel (minimal) if configured and not managed by GTM
    if (!GTM_MANAGES && typeof UP_CONFIG !== 'undefined' && UP_CONFIG.tiktok_pixel_id) {
        (function (w, d, t) {
            w.TiktokAnalyticsObject = t;
            var ttq = w[t] = w[t] || [];
            ttq.methods = ['page', 'track'];
            ttq.setAndDefer = function (n, e) { n[e] = function () { n.push([e].concat(Array.prototype.slice.call(arguments, 0))); }; };
            for (var i = 0; i < ttq.methods.length; i++) { ttq.setAndDefer(ttq, ttq.methods[i]); }
            ttq.load = function (e) {
                var s = document.createElement('script');
                s.type = 'text/javascript'; s.async = true;
                s.src = 'https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=' + e;
                var a = document.getElementsByTagName('script')[0];
                a.parentNode.insertBefore(s, a);
            };
            ttq.load(UP_CONFIG.tiktok_pixel_id);
            ttq.page();
        })(window, document, 'ttq');
    }

    // Inject Snapchat Pixel if configured and not managed by GTM
    if (!GTM_MANAGES && typeof UP_CONFIG !== 'undefined' && UP_CONFIG.snapchat_pixel_id) {
        (function (e, t, n) {
            if (e.snaptr) return;
            var a = e.snaptr = function () { a.handleRequest ? a.handleRequest.apply(a, arguments) : a.queue.push(arguments); };
            a.queue = [];
            var s = 'script';
            var r = t.createElement(s);
            r.async = true; r.src = n;
            var u = t.getElementsByTagName(s)[0];
            u.parentNode.insertBefore(r, u);
        })(window, document, 'https://sc-static.net/scevent.min.js');
        try { window.snaptr('init', UP_CONFIG.snapchat_pixel_id); window.snaptr('track', 'PAGE_VIEW'); } catch (err) { /* ignore */ }
    }

    // Inject Pinterest Tag if configured and not managed by GTM
    if (!GTM_MANAGES && typeof UP_CONFIG !== 'undefined' && UP_CONFIG.pinterest_tag_id) {
        (function (e) {
            if (!window.pintrk) {
                window.pintrk = function () { window.pintrk.queue.push(Array.prototype.slice.call(arguments)); };
                var n = window.pintrk;
                n.queue = []; n.version = '3.0';
                var t = document.createElement('script');
                t.async = true; t.src = e;
                var r = document.getElementsByTagName('script')[0];
                r.parentNode.insertBefore(t, r);
            }
        })('https://s.pinimg.com/ct/core.js');
        try { window.pintrk('load', UP_CONFIG.pinterest_tag_id); window.pintrk('page'); } catch (err) { /* ignore */ }
    }

    // Push to dataLayer for GTM first
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(event);

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

            // Normalize whatsapp fields into custom_data namespace
            if (payload.whatsapp_phone) {
                payload.channel = 'whatsapp';
            }
            var ev = {
                event: 'up_event',
                event_name: eventName,
                event_id: generateEventId(),
                event_time: Math.floor(Date.now() / 1000),
                source_url: window.location.href,
                user_data: {},
                custom_data: payload
            };

            // push to dataLayer for GTM
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(ev);

            // send to server ingest
            sendToServer(ev);
        } catch (err) { console.warn('UP click handler error', err); }
    }

    // listen capture to catch clicks early (works for dynamically added elements too)
    document.addEventListener('click', handleUpClick, true);

    // Automatic form submission tracking
    function handleFormSubmit(e) {
        try {
            var form = e.target;
            if (!form || form.tagName !== 'FORM') return;

            // Skip if form has data-up-no-track attribute
            if (form.getAttribute('data-up-no-track')) return;

            // Get form details
            var formName = form.getAttribute('name') || form.getAttribute('id') || 'unnamed_form';
            var formAction = form.getAttribute('action') || window.location.href;
            var formMethod = form.getAttribute('method') || 'get';

            // Check if it's a search form
            var isSearch = form.querySelector('input[type="search"], input[name="s"], input[name="search"]') !== null;

            var eventName = isSearch ? 'search' : 'form_submit';

            // Deterministic form event id based on name+action+method for dedup across client/server (hash-like simple approach)
            var hashSource = formName + '|' + formAction + '|' + formMethod;
            var hash = 0; for (var i=0;i<hashSource.length;i++){ hash = ((hash<<5)-hash) + hashSource.charCodeAt(i); hash |= 0; }
            var formEvent = {
                event: 'up_event',
                event_name: eventName,
                event_id: 'form_' + Math.abs(hash),
                event_time: Math.floor(Date.now() / 1000),
                source_url: window.location.href,
                custom_data: {
                    form_name: formName,
                    form_action: formAction,
                    form_method: formMethod,
                    form_field_count: form.querySelectorAll('input, textarea, select').length
                }
            };

            // Add search query if it's a search form
            if (isSearch) {
                var searchInput = form.querySelector('input[type="search"], input[name="s"], input[name="search"]');
                if (searchInput && searchInput.value) {
                    formEvent.custom_data.search_term = searchInput.value;
                }
            }

            // Push to dataLayer
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(formEvent);

            // Send to server
            sendToServer(formEvent);
        } catch (err) {
            console.warn('UP form handler error', err);
        }
    }

    // Listen for form submissions
    document.addEventListener('submit', handleFormSubmit, true);

    // Scroll depth tracking (25%, 50%, 75%, 90%)
    (function () {
        var depths = [25, 50, 75, 90];
        var tracked = {};

        function checkScrollDepth() {
            var scrolled = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;

            depths.forEach(function (depth) {
                if (scrolled >= depth && !tracked[depth]) {
                    tracked[depth] = true;

                    var scrollEvent = {
                        event: 'up_event',
                        event_name: 'scroll_depth',
                        event_id: 'scroll_' + depth, // deterministic per depth per page
                        event_time: Math.floor(Date.now() / 1000),
                        source_url: window.location.href,
                        custom_data: {
                            depth: depth + '%'
                        }
                    };

                    window.dataLayer = window.dataLayer || [];
                    window.dataLayer.push(scrollEvent);
                }
            });
        }

        // Throttle scroll events
        var scrollTimeout;
        window.addEventListener('scroll', function () {
            if (scrollTimeout) clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(checkScrollDepth, 200);
        });
    })();

})();
