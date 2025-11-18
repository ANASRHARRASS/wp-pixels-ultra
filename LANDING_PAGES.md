# Landing Page Integration Guide

This guide explains how to track WhatsApp interactions and custom events on your landing pages using the Ultra Pixels plugin.

## Quick Start

The Ultra Pixels plugin automatically injects a tracking pixel loader (`assets/pixel-loader.js`) on every page. This script:
- Sends a `PageView` event to your ingest endpoint
- Tracks clicks on WhatsApp links, forms, scroll depth, and custom elements
- Pushes unified `up_event` entries into the dataLayer (GTM-friendly)
- Forwards events to Meta, TikTok (and optionally Google Ads, Snapchat, Pinterest) via server-side queue/CAPI (if enabled)
- Can let GTM manage all client pixels (set **Let GTM manage all client pixels = Yes**)

## Tracking WhatsApp Interactions

### Method 1: Simple WhatsApp Link (No Extra Code Needed)

Just add a normal WhatsApp link. The plugin auto-detects and sends a `whatsapp_initiate` event:

```html
<a href="https://wa.me/15551234567?text=Hello%20I%20need%20help" class="button">
  Contact us on WhatsApp
</a>
```

The plugin extracts:
- **phone**: the phone number from the URL
- **text**: the pre-filled message (if present)

And fires an event to your ingest endpoint as a `whatsapp_initiate` event (mapped in settings to Meta `Contact` / TikTok `Contact`).

### Method 2: Custom Data Attributes

Add `data-up-event` and `data-up-payload` to track additional context:

```html
<a href="https://wa.me/15551234567" 
   data-up-event="whatsapp_initiate" 
   data-up-payload='{"button_location":"hero", "button_label":"Get Help"}'>
  WhatsApp Support
</a>
```

The `data-up-payload` value should be a JSON string containing custom fields. These are sent as `custom_data` in the event.

### Method 3: WhatsApp Button with Class

Mark any element with the `up-whatsapp` class or `data-up-whatsapp` attribute:

```html
<button class="up-whatsapp" data-up-payload='{"campaign":"summer_sale"}'>
  Ask us on WhatsApp
</button>
```

This sends a `whatsapp_click` event (mapped in settings to Meta `Lead` / TikTok `Lead`).

## Tracking Custom Events

Use `data-up-event` on any clickable element to send custom events:

```html
<!-- Video play button -->
<button data-up-event="video_play" data-up-payload='{"video_id":"demo_1", "video_title":"Product Demo"}'>
  ▶ Play Demo
</button>

<!-- Download button -->
<a href="/ebook.pdf" data-up-event="download_start" data-up-payload='{"file_name":"ebook.pdf", "file_type":"pdf"}'>
  Download Free eBook
</a>

<!-- Signup form submit trigger -->
<form data-up-event="form_submit_click">
  <input type="email" placeholder="Your email" />
  <button>Subscribe</button>
</form>

<!-- Social share -->
<button data-up-event="share_click" data-up-payload='{"platform":"facebook", "page":"product"}'>
  Share on Facebook
</button>
```

## Event Data Structure

Each event sent to the ingest endpoint (and pushed to `dataLayer`) uses the unified schema:

```json
{
  "event": "up_event",
  "event_name": "whatsapp_initiate",
  "event_id": "ev_1699900000000_abc123xyz",
  "event_time": 1699900000,
  "user_data": {},
  "custom_data": {
    "button_location": "hero",
    "whatsapp_phone": "+15551234567",
    "whatsapp_text": "Hello I need help"
  }
}
```

- **event**: Always `up_event` for GTM Custom Event Trigger
- **event_name**: The business event type (from `data-up-event` or auto-detected)
- **event_id**: Unique identifier; deterministic for purchases (`order_<id>`) & forms (`form_<hash>`)
- **event_time**: Unix timestamp (seconds)
- **user_data**: Hashed user info (empty client-side; enriched server-side)
- **custom_data**: Extra fields from `data-up-payload` and auto-detected data (WhatsApp phone/text, form metadata, scroll depth)

## Real-World Examples

### E-commerce Product Page

```html
<div class="product">
  <h1>Wireless Headphones</h1>
  <price>$99</price>
  
  <!-- Add to cart with tracking -->
  <button class="add-to-cart" 
          data-up-event="custom_add_to_cart" 
          data-up-payload='{"product_id":"wp-123", "product_name":"Wireless Headphones", "price":99}'>
    Add to Cart
  </button>

  <!-- WhatsApp support -->
  <p>Need help?</p>
  <a href="https://wa.me/15551234567?text=I%20have%20a%20question%20about%20Wireless%20Headphones" 
     class="button secondary">
    Ask on WhatsApp
  </a>
</div>
```

### Landing Page with Multiple CTAs

```html
<section class="hero">
  <h1>Free Trial Available</h1>
  
  <!-- CTA buttons with tracking -->
  <a href="/signup" 
     data-up-event="cta_click" 
     data-up-payload='{"button_type":"primary", "cta":"start_trial"}' 
     class="button primary">
    Start Free Trial
  </a>
  
  <a href="https://wa.me/15551234567?text=I%20want%20to%20know%20more%20about%20your%20service" 
     class="button secondary">
    Ask on WhatsApp
  </a>
</section>

<section class="features">
  <!-- Feature cards -->
  <div class="feature" data-up-event="feature_view">
    <h3>Feature One</h3>
    <p>Description...</p>
  </div>
</section>

<section class="contact">
  <h2>Questions?</h2>
  
  <!-- Multiple contact methods -->
  <a href="mailto:hello@example.com" 
     data-up-event="contact_email_click" 
     data-up-payload='{"contact_method":"email"}'>
    Email us
  </a>
  
  <a href="https://wa.me/15551234567" 
     data-up-event="whatsapp_initiate" 
     data-up-payload='{"button_location":"footer"}'>
    WhatsApp
  </a>
  
  <button data-up-event="chat_open" 
          data-up-payload='{"chat_type":"livechat"}'>
    Live Chat
  </button>
</section>
```

### Lead Magnet with Opt-in

```html
<div class="opt-in-form">
  <h2>Get Our Free Guide</h2>
  
  <form id="lead-form">
    <input type="email" name="email" placeholder="your@email.com" required />
    <button type="submit" 
            data-up-event="lead_form_submit" 
            data-up-payload='{"offer":"free_guide", "guide_name":"Getting Started"}'>
      Send Me the Guide
    </button>
  </form>
  
  <p>Or reach out directly:</p>
  <a href="https://wa.me/15551234567?text=I%20want%20your%20free%20guide" 
     class="button secondary">
    WhatsApp the Guide
  </a>
</div>

<script>
  document.getElementById('lead-form').addEventListener('submit', function(e) {
    // Form submission is tracked by data-up-event on the button
    // Additional tracking can be added here if needed
  });
</script>
```

## Event Mapping in Admin Settings

All custom events are mapped to Meta and TikTok event names in the admin settings under **Ultra Pixels → Event Mapping**.

Default mappings include:
- `whatsapp_initiate` → Meta `Contact` / TikTok `Contact`
- `whatsapp_click` → Meta `Lead` / TikTok `Lead`
- `purchase` → Meta `Purchase` / TikTok `PlaceAnOrder`
- `add_to_cart` → Meta `AddToCart` / TikTok `AddToCart`
- `view_item` → Meta `ViewContent` / TikTok `ViewContent`
- `view_item_list` → Meta `ViewCategory` / TikTok `BrowseCategory`
- `begin_checkout` → Meta `InitiateCheckout` / TikTok `InitiateCheckout`

You can customize these mappings by editing the JSON in the admin panel.

## Advanced: Custom Event Mapping

Edit the **Event Mapping (JSON)** textarea in `Ultra Pixels → Settings` to customize platform-specific event names:

```json
{
  "whatsapp_initiate": {
    "meta": {
      "event_name": "Contact",
      "include_user_data": true
    },
    "tiktok": {
      "event_name": "Contact",
      "include_user_data": true
    }
  },
  "video_play": {
    "meta": {
      "event_name": "ViewContent",
      "include_user_data": false
    },
    "tiktok": {
      "event_name": "ViewContent",
      "include_user_data": false
    }
  }
}
```

## Debugging

Open your browser's **DevTools** (F12) and check:

1. **Network tab**: Look for POST requests to `/wp-json/up/v1/ingest` — these are events being sent to your plugin.
2. **Console**: Watch for `UP` events logged or any errors from the pixel loader.
3. **Admin Queue**: Go to `Ultra Pixels → Settings` and check the **Queue Items** section to see if events were enqueued.

Example network request:
```
POST /wp-json/up/v1/ingest
Content-Type: application/json
X-WP-Nonce: [nonce-value]

{
  "event_name": "whatsapp_initiate",
  "event_id": "ev_1699900000000_abc123xyz",
  "event_time": 1699900000,
  "user_data": {},
  "custom_data": {
    "whatsapp_phone": "+15551234567",
    "whatsapp_text": "Hello I need help"
  }
}
```

## Privacy & Consent

The plugin does **not** automatically respect privacy/consent preferences. You **must** implement a CMP (Consent Management Platform) or cookie consent tool that:

1. Waits for user consent before allowing pixels to fire.
2. Does not allow the plugin's pixel loader to run until consent is granted.

Example with a simple consent check:

```html
<script>
  // Check if user has given consent before loading tracking
  if (window.userConsent && window.userConsent.analytics) {
    // Load the plugin's pixel loader (already loaded automatically by WordPress)
  } else {
    // Block or delay event sending
  }
</script>
```

## FAQ

**Q: Will WhatsApp links work on mobile?**
Yes, the WhatsApp links automatically open the WhatsApp app on mobile and the web version on desktop.

**Q: Can I track form submissions?**
Yes, add `data-up-event="form_submit"` to your form or submit button. Note: standard form submissions redirect away from the page, so tracking happens asynchronously via `keepalive: true` in the fetch call.

**Q: How do I see what's in the queue?**
Go to `Ultra Pixels → Settings` and scroll to **Queue Items** — the plugin shows all pending events with retry/delete options.

**Q: My WhatsApp events aren't appearing in Meta/TikTok?**
1. Confirm the CAPI endpoint and token are configured.
2. Check the **Queue Items** section to see if events are enqueued.
3. Use `wp up-capi process` (WP-CLI) or click "Process now" to manually trigger queue processing.
4. Verify the event mappings in **Event Mapping** are correct.

**Q: Can I send user data (email, phone)?**
Yes, the plugin supports `user_data` with hashed values (SHA256 for email/phone). This is set in the event mapping (`include_user_data: true`) and populated server-side if available (e.g., from WooCommerce orders).

## Support

For issues or questions:
- Check the admin **Ultra Pixels → Settings** page for queue/status information.
- Review browser DevTools (Network tab) for event payloads.
- Check WordPress error logs (`wp-content/debug.log` if debugging is enabled).

---

**Version**: 0.4.2  
**Last Updated**: November 2025
