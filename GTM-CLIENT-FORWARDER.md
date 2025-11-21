# GTM Client-Side Forwarder Guide

This guide explains how to use Google Tag Manager's **client-side** (browser) tags to forward events to the WordPress Ultra Pixels plugin, which then handles server-side forwarding to platform APIs.

## Overview

Instead of requiring a GTM server-side container, you can use GTM's client-side tags to capture events and forward them to the WordPress plugin's REST endpoint. The plugin then handles server-side delivery to Meta, TikTok, Google Ads, and other platforms.

**Architecture:**
```
Browser/GTM Client → WordPress /wp-json/up/v1/ingest → Platform APIs
```

**Benefits:**
- ✅ No GTM server-side container required (saves cost)
- ✅ Easy to set up with standard GTM account
- ✅ Centralized event capture in GTM
- ✅ Server-side forwarding handled by WordPress plugin
- ✅ All benefits of server-side APIs (reliability, privacy, deduplication)

## Prerequisites

1. WordPress Ultra Pixels plugin installed and activated (v0.4.4+)
2. Google Tag Manager container on your website
3. Platform IDs configured in plugin settings (Meta Pixel ID, TikTok Pixel ID, etc.)

## Setup Instructions

### Step 1: Configure WordPress Plugin

1. Go to **WordPress Admin → Ultra Pixels → Settings**
2. Configure platform IDs:
   - Meta Pixel ID
   - TikTok Pixel ID
   - Google Ads Conversion ID
   - Snapchat Pixel ID
   - Pinterest Tag ID
3. Enable platforms you want to use (Enable Meta, Enable TikTok, etc.)
4. Configure API tokens if using direct platform forwarding:
   - CAPI Token (for Meta/TikTok)
   - Snapchat API Token
   - Pinterest Access Token
5. **Optional**: Enable "Use GTM Server for Event Forwarding" if you have a GTM server container (otherwise leave disabled)
6. Save changes

### Step 2: Verify Forwarder Script Loaded

The plugin automatically loads `gtm-forwarder.js` which provides the `window.UP_GTM_FORWARD()` function.

To verify it's loaded, open your website and check the browser console:
```javascript
console.log(typeof window.UP_GTM_FORWARD); // Should output: "function"
console.log(window.UP_CONFIG); // Should show plugin configuration
```

### Step 3: Create GTM Variables

Create these **Data Layer Variables** in GTM:

| Variable Name | Data Layer Variable Name | Type |
|--------------|-------------------------|------|
| DLV - Event Name | event_name | Data Layer Variable |
| DLV - Event ID | event_id | Data Layer Variable |
| DLV - Value | value | Data Layer Variable |
| DLV - Currency | currency | Data Layer Variable |
| DLV - Transaction ID | transaction_id | Data Layer Variable |
| DLV - Content IDs | content_ids | Data Layer Variable |
| DLV - User Email | user_data.email | Data Layer Variable |
| DLV - User Phone | user_data.phone | Data Layer Variable |

### Step 4: Create GTM Triggers

Create a **Custom Event** trigger:
- **Trigger Type**: Custom Event
- **Event name**: `up_event`
- **This trigger fires on**: All Custom Events

Optionally, create filtered triggers for specific events:
- Purchase trigger: `up_event` fires AND `event_name` equals `purchase`
- Add to Cart trigger: `up_event` fires AND `event_name` equals `add_to_cart`

### Step 5: Create Forwarder Tags

Create **Custom HTML** tags for each platform you want to forward to:

#### Meta (Facebook) Forwarder Tag

```html
<script>
(function() {
  if (typeof window.UP_GTM_FORWARD === 'function') {
    window.UP_GTM_FORWARD({
      platform: 'meta',
      event_name: '{{DLV - Event Name}}',
      event_id: '{{DLV - Event ID}}',
      event_time: Math.floor(Date.now() / 1000),
      user_data: {
        email: '{{DLV - User Email}}',
        phone: '{{DLV - User Phone}}'
      },
      custom_data: {
        value: parseFloat('{{DLV - Value}}') || 0,
        currency: '{{DLV - Currency}}' || 'USD',
        content_ids: '{{DLV - Content IDs}}'.split(',').filter(Boolean)
      },
      source_url: {{Page URL}}
    });
  }
})();
</script>
```

**Tag Settings:**
- **Type**: Custom HTML
- **Trigger**: up_event (or filtered trigger)
- **Tag firing priority**: 10 (fire after platform pixel tags for deduplication)

#### TikTok Forwarder Tag

```html
<script>
(function() {
  if (typeof window.UP_GTM_FORWARD === 'function') {
    window.UP_GTM_FORWARD({
      platform: 'tiktok',
      event_name: '{{DLV - Event Name}}',
      event_id: '{{DLV - Event ID}}',
      event_time: Math.floor(Date.now() / 1000),
      user_data: {
        email: '{{DLV - User Email}}',
        phone: '{{DLV - User Phone}}'
      },
      custom_data: {
        value: parseFloat('{{DLV - Value}}') || 0,
        currency: '{{DLV - Currency}}' || 'USD',
        content_ids: '{{DLV - Content IDs}}'.split(',').filter(Boolean)
      }
    });
  }
})();
</script>
```

#### Google Ads Forwarder Tag

```html
<script>
(function() {
  if (typeof window.UP_GTM_FORWARD === 'function') {
    window.UP_GTM_FORWARD({
      platform: 'google_ads',
      event_name: 'conversion',
      event_id: '{{DLV - Event ID}}',
      event_time: Math.floor(Date.now() / 1000),
      user_data: {
        email: '{{DLV - User Email}}',
        phone: '{{DLV - User Phone}}'
      },
      custom_data: {
        value: parseFloat('{{DLV - Value}}') || 0,
        currency: '{{DLV - Currency}}' || 'USD',
        transaction_id: '{{DLV - Transaction ID}}'
      }
    });
  }
})();
</script>
```

#### Snapchat Forwarder Tag

```html
<script>
(function() {
  if (typeof window.UP_GTM_FORWARD === 'function') {
    window.UP_GTM_FORWARD({
      platform: 'snapchat',
      event_name: '{{DLV - Event Name}}',
      event_id: '{{DLV - Event ID}}',
      event_time: Math.floor(Date.now() / 1000),
      user_data: {
        email: '{{DLV - User Email}}',
        phone: '{{DLV - User Phone}}'
      },
      custom_data: {
        value: parseFloat('{{DLV - Value}}') || 0,
        currency: '{{DLV - Currency}}' || 'USD'
      }
    });
  }
})();
</script>
```

#### Pinterest Forwarder Tag

```html
<script>
(function() {
  if (typeof window.UP_GTM_FORWARD === 'function') {
    window.UP_GTM_FORWARD({
      platform: 'pinterest',
      event_name: '{{DLV - Event Name}}',
      event_id: '{{DLV - Event ID}}',
      event_time: Math.floor(Date.now() / 1000),
      user_data: {
        email: '{{DLV - User Email}}'
      },
      custom_data: {
        value: parseFloat('{{DLV - Value}}') || 0,
        currency: '{{DLV - Currency}}' || 'USD'
      }
    });
  }
})();
</script>
```

### Step 6: Test Your Setup

1. **Enable GTM Preview Mode**
   - Go to GTM → Preview
   - Enter your website URL

2. **Trigger a Test Event**
   - Add a product to cart or complete a purchase
   - Check GTM Debug Console to see if `up_event` fires
   - Verify your forwarder tags fire

3. **Check Browser Console**
   ```javascript
   // Look for messages like:
   // "UP GTM forwarder: sending event to /wp-json/up/v1/ingest"
   ```

4. **Check WordPress Admin**
   - Go to **Ultra Pixels → Settings**
   - Check the **CAPI Queue** section
   - Click "Refresh" to see queued events
   - Click "Process now" to manually process queue

5. **Check Platform Event Managers**
   - Meta Events Manager: Look for test events
   - TikTok Events Manager: Check real-time events
   - Verify `event_id` matches between client and server events

## Event Structure

The `UP_GTM_FORWARD()` function accepts this payload structure:

```javascript
{
  platform: 'meta|tiktok|google_ads|snapchat|pinterest|generic',
  event_name: 'Purchase|AddToCart|ViewContent|etc',
  event_id: 'unique-event-id-for-deduplication',
  event_time: 1234567890, // Unix timestamp
  user_data: {
    email: 'user@example.com', // Will be hashed server-side
    phone: '+1234567890', // Will be hashed server-side
    // Do NOT include pre-hashed values
  },
  custom_data: {
    value: 99.99,
    currency: 'USD',
    content_ids: ['product-123', 'product-456'],
    transaction_id: 'order-12345',
    // Any custom fields specific to your use case
  },
  source_url: 'https://example.com/product-page' // Optional, auto-detected
}
```

**Important Notes:**
- **PII Handling**: Send raw email/phone in `user_data`. The forwarder script removes them before sending to WordPress, and the WordPress plugin handles proper hashing server-side.
- **Event IDs**: Use deterministic IDs for purchases (`order_{{Order ID}}`) to enable proper deduplication between client and server events.
- **Platform Names**: Use lowercase platform identifiers: `meta`, `tiktok`, `google_ads`, `snapchat`, `pinterest`.

## Event Mapping Examples

### WooCommerce Purchase Event

```javascript
window.dataLayer.push({
  event: 'up_event',
  event_name: 'purchase',
  event_id: 'order_12345',
  value: 99.99,
  currency: 'USD',
  transaction_id: '12345',
  content_ids: ['prod-1', 'prod-2'],
  user_data: {
    email: 'customer@example.com',
    phone: '+1234567890'
  }
});
```

### Add to Cart Event

```javascript
window.dataLayer.push({
  event: 'up_event',
  event_name: 'add_to_cart',
  event_id: 'atc_' + Date.now(),
  value: 29.99,
  currency: 'USD',
  content_ids: ['prod-123']
});
```

### Custom Lead Event (WhatsApp, Form)

```javascript
window.dataLayer.push({
  event: 'up_event',
  event_name: 'whatsapp_initiate',
  event_id: 'wa_' + Date.now(),
  custom_data: {
    whatsapp_phone: '+1234567890',
    campaign: 'summer-sale'
  }
});
```

## Advanced Configuration

### Conditional Forwarding

Only forward specific events to certain platforms:

```html
<script>
(function() {
  var eventName = '{{DLV - Event Name}}';
  var forwardToMeta = ['purchase', 'add_to_cart', 'begin_checkout'].indexOf(eventName) !== -1;
  
  if (forwardToMeta && typeof window.UP_GTM_FORWARD === 'function') {
    window.UP_GTM_FORWARD({
      platform: 'meta',
      event_name: eventName,
      // ... rest of payload
    });
  }
})();
</script>
```

### Event Name Mapping

Map internal event names to platform-specific names:

```html
<script>
(function() {
  var eventMap = {
    'purchase': 'Purchase',
    'add_to_cart': 'AddToCart',
    'view_item': 'ViewContent',
    'begin_checkout': 'InitiateCheckout'
  };
  
  var internalEvent = '{{DLV - Event Name}}';
  var metaEvent = eventMap[internalEvent] || internalEvent;
  
  if (typeof window.UP_GTM_FORWARD === 'function') {
    window.UP_GTM_FORWARD({
      platform: 'meta',
      event_name: metaEvent,
      // ... rest of payload
    });
  }
})();
</script>
```

### Consent Management

Respect user consent before forwarding:

```html
<script>
(function() {
  // Check if user has granted marketing consent
  var hasConsent = window.UP_CONSENT && window.UP_CONSENT.ads === true;
  
  if (hasConsent && typeof window.UP_GTM_FORWARD === 'function') {
    window.UP_GTM_FORWARD({
      platform: 'meta',
      // ... payload
    });
  }
})();
</script>
```

## Troubleshooting

### Events Not Appearing in Queue

1. **Check Browser Console**
   - Look for errors or warnings
   - Verify `UP_GTM_FORWARD` function exists
   - Check `UP_CONFIG` is populated

2. **Check Network Tab**
   - Filter for `/wp-json/up/v1/ingest`
   - Verify POST requests are being sent
   - Check response status (should be 202)

3. **Verify GTM Tags Fire**
   - Use GTM Preview mode
   - Check if custom HTML tags fire
   - Verify trigger conditions are met

### Events Stuck in Queue

1. **Check WordPress Admin**
   - Go to Ultra Pixels → Settings
   - Look at queue length
   - Check for errors in dead-letter table

2. **Manual Processing**
   - Click "Process now" button
   - Check logs for error messages

3. **Verify Platform Configuration**
   - Ensure platform is enabled
   - Check API tokens are valid
   - Verify pixel IDs are correct

### Duplicate Events in Platform Managers

1. **Check Event IDs**
   - Client and server events should use same `event_id`
   - Platforms automatically deduplicate by `event_id`

2. **Check Tag Sequencing**
   - Ensure forwarder tags fire AFTER platform pixel tags
   - Use tag firing priority if needed

## Best Practices

1. **Event Deduplication**
   - Use deterministic event IDs for purchases: `order_{{Order ID}}`
   - Use unique random IDs for other events: `ev_{{Timestamp}}_{{Random}}`

2. **PII Handling**
   - Always send raw PII (email, phone) from GTM
   - Never hash PII client-side
   - Let WordPress plugin handle server-side hashing

3. **Error Handling**
   - Wrap forwarding calls in try-catch
   - Check if `UP_GTM_FORWARD` function exists before calling
   - Log errors to console for debugging

4. **Performance**
   - Use `keepalive: true` for reliable delivery during navigation
   - Consider using `sendBeacon` for page unload events
   - Batch similar events when possible

5. **Testing**
   - Always test in GTM Preview mode first
   - Use platform test event tools
   - Monitor WordPress queue regularly

## Migration from Direct API Calls

If you're currently using direct platform API calls and want to migrate to this approach:

1. Keep existing platform pixel tags (for client-side tracking)
2. Add forwarder tags (for server-side tracking)
3. Ensure same `event_id` is used by both
4. Platforms will automatically deduplicate
5. Monitor for a few days to ensure everything works
6. Optionally remove direct API integrations from WordPress

## Support

For issues or questions:
- Check WordPress plugin logs: Ultra Pixels → Settings
- Enable WordPress debug logging: `define('WP_DEBUG_LOG', true);`
- Review `/wp-content/debug.log` for PHP errors
- Submit issues on GitHub repository

---

**Last Updated**: November 2025  
**Plugin Version**: 0.4.4+
