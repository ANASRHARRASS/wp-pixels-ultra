# Complete GTM Setup Guide for Ultra Pixels Plugin

This guide provides step-by-step instructions for setting up Google Tag Manager with the Ultra Pixels plugin for best-practice tracking across Meta, TikTok, Google Ads, and other platforms.

## Table of Contents
1. [Quick Start](#quick-start)
2. [GTM Container Setup](#gtm-container-setup)
3. [Platform Configuration](#platform-configuration)
4. [Server-Side GTM (Optional)](#server-side-gtm-optional)
5. [Testing & Validation](#testing--validation)
6. [Elementor Integration](#elementor-integration)
7. [Advanced Features](#advanced-features)

---

## Quick Start

### Prerequisites
- WordPress website with WooCommerce (optional but recommended)
- Ultra Pixels plugin installed and activated
- Google Tag Manager account

### 5-Minute Setup
1. **Install GTM Container**
   ```
   - Go to GTM Admin → Import Container
   - Upload: gtm-templates/ultra-pixels-gtm-container.json
   - Choose "Merge" with existing or "New" workspace
   - Preview and Publish
   ```

2. **Configure Plugin**
   ```
   WordPress Admin → Ultra Pixels → Settings
   - GTM Container ID: GTM-XXXXXX
   - Enable GTM: Yes
   - Meta Pixel ID: (your pixel ID)
   - TikTok Pixel ID: (your pixel ID)
   - Save Changes
   ```

3. **Update GTM Variables**
   ```
   In GTM:
   - Variables → Meta Pixel ID → YOUR_META_PIXEL_ID
   - Variables → TikTok Pixel ID → YOUR_TIKTOK_PIXEL_ID
   - Publish changes
   ```

4. **Test**
   - Use GTM Preview mode
   - Load your website
   - Verify events fire in GTM Debug Console

---

## GTM Container Setup

### Option A: Import Pre-configured Container (Recommended)

The plugin includes a complete GTM container template with pre-configured tags, triggers, and variables.

**Steps:**
1. Download `gtm-templates/ultra-pixels-gtm-container.json` from plugin directory
2. Go to GTM Admin → Import Container
3. Choose the downloaded JSON file
4. Select import option:
   - **New workspace**: If starting fresh
   - **Merge**: To add to existing container (recommended)
5. Review changes in preview mode
6. Publish when ready

**What's Included:**
- ✅ Meta Pixel tags (PageView + Dynamic Events)
- ✅ TikTok Pixel tags (PageView + Dynamic Events)
- ✅ GA4 Enhanced Ecommerce tag
- ✅ Server-side event forwarding tag
- ✅ All necessary triggers (PageView, Custom Events, Forms)
- ✅ DataLayer variables for event data
- ✅ Event name mapping functions

### Option B: Manual Setup

If you prefer to set up manually or need to customize:

#### Step 1: Create Variables

Create these DataLayer Variables (Variables → New → Data Layer Variable):

| Variable Name | Data Layer Variable Name | Default Value |
|--------------|-------------------------|---------------|
| DLV - Event Name | event_name | - |
| DLV - Event ID | event_id | - |
| DLV - Fallback Event ID | dlv_event_id | - |
| DLV - Event Time | event_time | - |
| DLV - Value | custom_data.value | 0 |
| DLV - Currency | custom_data.currency | USD |
| DLV - Content IDs | custom_data.content_ids | [] |
| DLV - Custom Data | custom_data | - |
| DLV - User Data | user_data | - |
| DLV - WhatsApp Href | custom_data.href | - |
| DLV - WhatsApp Label | custom_data.content_name | - |

Create these Constant Variables:

| Variable Name | Value |
|--------------|-------|
| Meta Pixel ID | YOUR_META_PIXEL_ID |
| TikTok Pixel ID | YOUR_TIKTOK_PIXEL_ID |
| GA4 Measurement ID | G-XXXXXXXXXX |

Create Custom JavaScript Variables for platform-specific event mapping:

**DLV - TikTok Event Name:**
```javascript
function() {
  var eventMap = {
    'purchase': 'PlaceAnOrder',
    'add_to_cart': 'AddToCart',
    'view_item': 'ViewContent',
    'add_payment_info': 'AddPaymentInfo',
    'view_item_list': 'BrowseCategory',
    'begin_checkout': 'InitiateCheckout',
    'whatsapp_initiate': 'Contact',
    'whatsapp_click': 'Contact',
    'whatsapp_lead_click': 'Contact',
    'form_submit': 'SubmitForm',
    'PageView': 'PageView'
  };
  var eventName = {{DLV - Event Name}};
  return eventMap[eventName] || eventName;
}
```

**DLV - GA4 Event Name:**
```javascript
function() {
  var eventMap = {
    'purchase': 'purchase',
    'add_to_cart': 'add_to_cart',
    'view_item': 'view_item',
    'begin_checkout': 'begin_checkout',
    'view_item_list': 'view_item_list',
    'PageView': 'page_view'
  };
  var eventName = {{DLV - Event Name}};
  return eventMap[eventName] || eventName;
}
```

#### Step 2: Create Triggers

**Trigger 1: All Pages - PageView**
- Type: Page View
- Fires on: All Pages

**Trigger 2: Ultra Pixels Custom Event**
- Type: Custom Event
- Event name: up_event
- Fires on: All Custom Events

**Trigger 3: Purchase Event**
- Type: Custom Event
- Condition: {{DLV - Event Name}} equals purchase
- Fires on: Some Custom Events

**Trigger 4: Form Submission**
- Type: Form Submission
- Fires on: All Forms

#### Step 3: Create Tags

**Tag 1: Meta Pixel - PageView**
- Type: Custom HTML
- HTML:
```html
<script>
  if (window.fbq) {
    fbq('track', 'PageView', {}, {
      eventID: {{DLV - Event ID}}
    });
  }
</script>
```
- Trigger: All Pages - PageView

**Tag 2: Meta Pixel - Dynamic Events**
- Type: Custom HTML
- HTML:
```html
<script>
  if (window.fbq && {{DLV - Event Name}}) {
    var eventData = {
      content_ids: {{DLV - Content IDs}} || [],
      content_type: 'product',
      value: {{DLV - Value}} || 0,
      currency: {{DLV - Currency}} || 'USD'
    };
    
    // Add custom data from dataLayer
    if ({{DLV - Custom Data}}) {
      Object.assign(eventData, {{DLV - Custom Data}});
    }
    
    fbq('track', {{DLV - Event Name}}, eventData, {
      eventID: {{DLV - Event ID}}
    });
  }
</script>
```
- Trigger: Ultra Pixels Custom Event

**Tag 3: TikTok Pixel - PageView**
- Type: Custom HTML
- HTML:
```html
<script>
  if (window.ttq) {
    ttq.track('PageView', {
      event_id: {{DLV - Event ID}}
    });
  }
</script>
```
- Trigger: All Pages - PageView

**Tag 4: TikTok Pixel - Dynamic Events**
- Type: Custom HTML
- HTML:
```html
<script>
  if (window.ttq && {{DLV - TikTok Event Name}}) {
    var eventData = {
      content_ids: {{DLV - Content IDs}} || [],
      content_type: 'product',
      value: {{DLV - Value}} || 0,
      currency: {{DLV - Currency}} || 'USD',
      event_id: {{DLV - Event ID}}
    };
    
    // Add custom data
    if ({{DLV - Custom Data}}) {
      Object.assign(eventData, {{DLV - Custom Data}});
    }
    
    ttq.track({{DLV - TikTok Event Name}}, eventData);
  }
</script>
```
- Trigger: Ultra Pixels Custom Event

**Tag 5: GA4 - Enhanced Ecommerce**
- Type: Google Analytics: GA4 Event
- Measurement ID: {{GA4 Measurement ID}}
- Event Name: {{DLV - GA4 Event Name}}
- Send Ecommerce Data: ✅ Enabled
- Trigger: Ultra Pixels Custom Event

**Tag 6: Server-Side Event Forward**
- Type: Custom HTML
- HTML:
```html
<script>
  (function() {
    // This tag forwards events to server-side GTM or WordPress CAPI endpoint
    var eventData = {
      event_name: {{DLV - Event Name}},
      event_id: {{DLV - Event ID}},
      event_time: {{DLV - Event Time}},
      source_url: {{Page URL}},
      user_data: {{DLV - User Data}} || {},
      custom_data: {{DLV - Custom Data}} || {}
    };
    
    // Send to server endpoint (configured in plugin)
    if (window.UP_CONFIG && window.UP_CONFIG.ingest_url) {
      fetch(window.UP_CONFIG.ingest_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.UP_CONFIG.nonce || ''
        },
        body: JSON.stringify(eventData),
        keepalive: true
      }).catch(function(e) {
        console.warn('UP server forward failed', e);
      });
    }
  })();
</script>
```
- Trigger: Ultra Pixels Custom Event

---

## Platform Configuration

### Meta (Facebook) Conversions API

**WordPress Plugin Settings:**
```
Meta Pixel ID: 1234567890
Enable Meta: Yes
```

**GTM Setup:**
- Ensure Meta Pixel loads via plugin (automatic when enabled)
- GTM tags will use `window.fbq` to send events
- Event deduplication via `event_id` parameter

**Access Token (for Server-Side):**
1. Go to Meta Events Manager
2. Settings → Conversions API
3. Generate Access Token
4. Add to WordPress: Ultra Pixels → CAPI Token

**Test Events:**
- Use Meta Test Events tool in Events Manager
- Verify events appear with `event_id` and match client events

### TikTok Pixel + Events API (browser + server, no GTM server-side)

**Goal:** Run the browser pixel from GTM (Custom HTML) while the plugin sends Events API calls directly from WordPress. No GTM server container or TikTok GTM template is required.

**WordPress Plugin Settings:**
```
TikTok Pixel ID: XXXXXXXXXXXXXX
Enable TikTok: Yes
Let GTM manage all client pixels: Yes (prevents plugin from injecting the browser pixel)
TikTok CAPI Token: <Events API token from TikTok>
```

**GTM prerequisites (variables + triggers):**
- Data Layer Variables: `event_name`, `event_id`, `event_time`, `custom_data`, `user_data`, `custom_data.content_ids`, `custom_data.value`, `custom_data.currency` (default USD). Optional extras if you want to display them in the tag: `custom_data.content_name`, `custom_data.content_type`, `custom_data.price`, `custom_data.description`, `custom_data.href`, `custom_data.event_source_url`, plus `dlv_event_id` for fallback deduplication.
- Custom JS Variable: TikTok event mapper that converts `view_item` → `ViewContent`, `add_to_cart` → `AddToCart`, `begin_checkout` → `InitiateCheckout`, `add_payment_info` → `AddPaymentInfo`, `purchase` → `PlaceAnOrder`, `view_item_list` → `BrowseCategory`, `whatsapp_initiate` → `Contact`, `whatsapp_click`/`whatsapp_lead_click` → `Contact` (rename to `WhatsAppLead` if you prefer a custom event).
- Triggers: **All Pages - PageView** and **Ultra Pixels Custom Event** (`up_event`).

**Default Meta/TikTok event mapping (plugin source):**
```json
{
  "purchase": {
    "meta": {"event_name": "Purchase", "include_user_data": true},
    "tiktok": {"event_name": "PlaceAnOrder", "include_user_data": true}
  },
  "add_to_cart": {
    "meta": {"event_name": "AddToCart", "include_user_data": false},
    "tiktok": {"event_name": "AddToCart", "include_user_data": false}
  },
  "view_item": {
    "meta": {"event_name": "ViewContent", "include_user_data": false},
    "tiktok": {"event_name": "ViewContent", "include_user_data": false}
  },
  "view_item_list": {
    "meta": {"event_name": "ViewCategory", "include_user_data": false},
    "tiktok": {"event_name": "BrowseCategory", "include_user_data": false}
  },
  "begin_checkout": {
    "meta": {"event_name": "InitiateCheckout", "include_user_data": false},
    "tiktok": {"event_name": "InitiateCheckout", "include_user_data": false}
  },
  "add_payment_info": {
    "meta": {"event_name": "AddPaymentInfo", "include_user_data": true},
    "tiktok": {"event_name": "AddPaymentInfo", "include_user_data": true}
  },
  "whatsapp_initiate": {
    "meta": {"event_name": "Contact", "include_user_data": true},
    "tiktok": {"event_name": "Contact", "include_user_data": true}
  },
  "whatsapp_click": {
    "meta": {"event_name": "Contact", "include_user_data": false},
    "tiktok": {"event_name": "Contact", "include_user_data": false}
  }
}
```
Use this mapping when configuring your Custom JS variable or Lookup Tables so GTM tags stay aligned with the plugin’s Events API payloads.

**GTM Tags (Custom HTML, no TikTok template):**
- **TikTok Pixel – PageView**
  - HTML:
    ```html
    <script>
      if (window.ttq) {
        ttq.track('PageView', {
          event_id: {{DLV - Event ID}},
          url: {{Page URL}}
        });
      }
    </script>
    ```
  - Trigger: All Pages - PageView.

- **TikTok Pixel – Dynamic Events**
  - HTML:
    ```html
    <script>
      if (window.ttq && {{DLV - TikTok Event Name}}) {
        var base = {{DLV - Custom Data}} || {};
        var eventData = Object.assign({
          value: {{DLV - Value}} || 0,
          currency: {{DLV - Currency}} || 'USD',
          content_ids: {{DLV - Content IDs}} || [],
          content_type: base.content_type || 'product',
          content_name: base.content_name,
          content_category: base.content_category,
          href: {{DLV - WhatsApp Href}},
          price: base.price,
          description: base.description,
          url: base.event_source_url || {{Page URL}},
          event_id: {{DLV - Event ID}} || {{DLV - Fallback Event ID}} || {{DLV - WhatsApp Label}}
        }, base);

        // Include user data for better match (email/phone/ip/user_agent from plugin where available)
        if ({{DLV - User Data}}) {
          eventData.user = {{DLV - User Data}};
        }

        ttq.track({{DLV - TikTok Event Name}}, eventData);
      }
    </script>
    ```
  - Trigger: Ultra Pixels Custom Event.

**Event mapping + payload expectations:**
- **ViewContent** (`view_item`): `value`, `currency`, `content_ids`, `content_type`, `content_name`, `content_category`, `price`, `description`, `event_time`, `url`, plus user info (`email`, `phone`, `external_id`, `ip`, `user_agent`, `ttclid`) when present.
- **AddToCart** (`add_to_cart`): same as above with `event_id` populated for deduplication.
- **ViewCategory/BrowseCategory** (`view_item_list`): `content_ids`, `content_category`, `event_time`, `url`, optional user info if available from the plugin payload.
- **InitiateCheckout** (`begin_checkout`): `value`, `currency`, `content_ids`, `content_type`, `content_name`, `event_id`, `event_time`, `url`, user info.
- **AddPaymentInfo** (`add_payment_info`): same as above with payment step context when provided by the theme/checkout.
- **Purchase** (`purchase`): `value`, `currency`, `content_ids`, `content_type`, `content_name`, `event_id`, `event_time`, `url`, user info; transaction amount will populate `value`.
- **WhatsApp Lead** (`whatsapp_initiate` or `whatsapp_click`): send as `Contact` or rename the TikTok event to `WhatsAppLead` in the mapper; payload can include `value`, `currency`, `content_name` (e.g., button label), `href`/`url`, and `event_id` or `dlv_event_id` for dedupe.

**WhatsApp buttons: step-by-step GTM wiring (browser pixel + plugin Events API)**
1. **Add attributes in Elementor/HTML** to every WhatsApp CTA so the plugin pushes an `up_event` with WhatsApp context (no GTM template needed):
   ```
   data-up-event="whatsapp_click"
   data-up-payload='{"content_name":"WhatsApp CTA","content_category":"whatsapp","href":"https://wa.me/212703914377?text=Hi"}'
   ```
   - `content_name` becomes the TikTok `content_name`/label, `href` becomes the URL. Add `value` if you want to pass revenue attribution.
2. **Data Layer Variables**: double-check GTM has `DLV - Event Name` (`event_name`), `DLV - WhatsApp Href` (`custom_data.href`), `DLV - WhatsApp Label` (`custom_data.content_name`), and `DLV - Fallback Event ID` (`dlv_event_id`). If `event_id` is missing in the payload, `dlv_event_id` keeps dedupe intact.
3. **TikTok mapper**: keep `whatsapp_click`/`whatsapp_lead_click` mapped to `Contact` (or rename to `WhatsAppLead`), and ensure the mapper returns `PageView` only when the incoming `event_name` is truly `PageView`.
4. **Dynamic TikTok tag**: include
   ```
   event_id: {{DLV - Event ID}} || {{DLV - Fallback Event ID}} || {{DLV - WhatsApp Label}}
   ```
   inside the payload. If the tag still shows `PageView`, add a blocking rule so the **TikTok Pixel – Dynamic Events** tag fires only when `{{DLV - Event Name}}` does **not** equal `PageView`.
5. **Trigger**: use `Ultra Pixels Custom Event` so the tag fires on `up_event` (the WhatsApp click). The PageView trigger should be used only by the pageview tag.
6. **Test with GTM Preview**:
   - Click the WhatsApp button and open the event in GTM preview. The **Summary → Event Name** should read `whatsapp_click` or `whatsapp_lead_click`, not `PageView`.
   - Under **Data Layer**, confirm `event_name`, `dlv_event_id`, and `custom_data.href` are present.
   - Verify only the **TikTok Pixel – Dynamic Events** tag fires (not the PageView tag). If `PageView` appears instead, the HTML lacked `data-up-event`, or a cache/minifier removed the attribute—re-add it and retest.
7. **TikTok Test Events**: perform a live click and check that the browser event uses the same Event ID the plugin sends via Events API. If the IDs differ, add `{{DLV - Fallback Event ID}}` to the payload and re-test.

**WhatsApp troubleshooting checklist (when GTM shows PageView instead of WhatsApp):**
- Confirm the rendered HTML still contains `data-up-event="whatsapp_click"` (view source or DevTools Elements). Some cache/minify plugins strip attributes; whitelist `data-up-*` if needed.
- In GTM Preview, ensure **Event Name** equals `whatsapp_click`; if it reads `PageView`, the attributes are missing on the clicked element.
- Make sure your **Ultra Pixels Custom Event** trigger listens for `up_event` and that your dynamic tag does **not** also use the PageView trigger.
- Enable the `dlv_event_id` variable in the GTM debug panel to see the fallback ID the plugin provides for WhatsApp clicks.

**Access Token (for Events API via plugin):**
1. Go to **TikTok Events Manager → Settings → Events API**.
2. Generate an **Access Token**.
3. Add it in WordPress under **Ultra Pixels → TikTok CAPI Token** and save.

**Deduplication best practice:**
- The plugin sends server Events API calls with the same `event_id` provided in `up_event` (e.g., `order_<id>` for purchases).
- In your GTM tag, set **event_id** to the `event_id` dataLayer variable so TikTok can dedupe browser vs. server events.

**Verify Events:**
- Open TikTok **Test Events** and perform a checkout. You should see browser and Events API entries sharing the same Event ID and payload fields above.
- Confirm no duplicate pixel injection (view source: only GTM loads TikTok). If you see duplicates, ensure **Let GTM manage all client pixels** stays enabled.

### Google Ads Conversion Tracking

**WordPress Plugin Settings:**
```
Google Ads Conversion ID: AW-XXXXXXXXX
Enable Google Ads: Yes
```

**GTM Setup (Manual):**
1. Add Google Ads Conversion Tracking tag
2. Conversion ID: {{Google Ads ID}} variable
3. Trigger: Purchase Event or specific conversion trigger
4. Include transaction_id and value from dataLayer

**Enhanced Conversions:**
- User data (hashed email) sent via user_data in dataLayer
- Enable Enhanced Conversions in Google Ads tag settings

### Snapchat Pixel

**WordPress Plugin Settings:**
```
Snapchat Pixel ID: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
Enable Snapchat: Yes
```

**Event Mapping:**
- Automatic via default mapping
- Custom events mapped to Snapchat standard events

### Pinterest Tag

**WordPress Plugin Settings:**
```
Pinterest Tag ID: 1234567890123
Enable Pinterest: Yes
```

**Event Tracking:**
- Automatic PageView tracking
- Checkout and AddToCart events mapped
- Custom events supported

---

## Server-Side GTM (Optional)

Server-side GTM provides enhanced privacy, better reliability, and reduced client-side load.

### Setup Steps

1. **Create Server Container**
   - In GTM, go to Admin → Create Container
   - Choose "Server" container type
   - Deploy to Google Cloud Run, App Engine, or custom server

2. **Import Server Template**
   ```
   - Download: gtm-templates/server-side-template.json
   - Import to server container
   - Configure endpoint URLs and tokens
   ```

3. **Configure WordPress Plugin**
   ```
   GTM Server Container URL: https://your-server-gtm.com
   ```

4. **Update Client Container**
   - Set Transport URL to your server container
   - Events automatically route to server-side

5. **Configure Server Tags**
   - Meta Conversions API tag
   - TikTok Events API tag
   - Google Analytics 4 tag
   - Custom CAPI endpoints

### Benefits
- ✅ Bypasses ad blockers
- ✅ First-party cookie domain
- ✅ Improved data quality
- ✅ Better attribution
- ✅ GDPR/privacy compliant
- ✅ Reduced page load time

---

## Testing & Validation

### GTM Preview Mode

1. In GTM, click "Preview"
2. Enter your website URL
3. Navigate through your site
4. Verify in GTM Debug:
   - `up_event` fires on page load
   - Event variables populate correctly
   - Tags fire as expected

### Browser DevTools

**Check DataLayer:**
```javascript
// In browser console
console.log(window.dataLayer);
```

Expected output:
```javascript
[
  {
    event: 'up_event',
    event_name: 'PageView',
    event_id: 'ev_1699900000_abc123',
    event_time: 1699900000,
    user_data: {},
    custom_data: {},
    ecommerce: { ... }
  }
]
```

**Check Network Requests:**
- Open Network tab
- Filter: `/wp-json/up/v1/ingest`
- Verify POST requests send event data
- Check response status: 200 OK

### Platform Event Managers

**Meta Events Manager:**
- Test Events tool shows real-time events
- Check event_id matches between client and server
- Verify user data (em) is hashed

**TikTok Events Manager:**
- Real-time events tab
- Test events feature
- Event deduplication working

**Google Analytics 4:**
- DebugView (with debug_mode parameter)
- Realtime events report
- Enhanced Ecommerce data present

### WordPress Admin

**Check Queue:**
```
WordPress Admin → Ultra Pixels → Settings → CAPI Queue
- Queue length should decrease after processing
- No items stuck in queue
- Dead-letter table empty
```

**Manual Test:**
1. Click "Process now" button
2. Check logs for errors
3. Verify events sent to platforms

---

## Elementor Integration

The plugin automatically tracks events on Elementor landing pages.

### Button/Link Tracking

**Method 1: Advanced Tab Attributes**
1. Edit button widget in Elementor
2. Go to Advanced → Attributes
3. Add custom attribute:
   ```
   data-up-event: cta_click
   data-up-payload: {"button_name":"hero_cta","campaign":"summer"}
   ```

**Method 2: Custom CSS ID**
1. Edit widget
2. Advanced → CSS ID: `up-track-button`
3. Add data attributes via HTML widget or custom code

**Method 3: HTML Widget**
```html
<a href="#signup" 
   data-up-event="signup_click" 
   data-up-payload='{"source":"hero","page":"home"}'>
  Sign Up Now
</a>
```

### Popup Tracking

Track Elementor popup open/close:

**Popup Open:**
```javascript
// In Custom Code widget or HTML widget
jQuery(document).on('elementor/popup/show', function(event, id, instance) {
  if (window.dataLayer) {
    window.dataLayer.push({
      event: 'up_event',
      event_name: 'popup_open',
      custom_data: { popup_id: id }
    });
  }
});
```

**Popup Close:**
```javascript
jQuery(document).on('elementor/popup/hide', function(event, id, instance) {
  if (window.dataLayer) {
    window.dataLayer.push({
      event: 'up_event',
      event_name: 'popup_close',
      custom_data: { popup_id: id }
    });
  }
});
```

### Form Tracking

Elementor forms automatically trigger `form_submit` event via GTM Form Submission trigger.

**Custom Form Tracking:**
```javascript
// Add to form's Actions After Submit → Custom Code
if (window.dataLayer) {
  window.dataLayer.push({
    event: 'up_event',
    event_name: 'form_submit',
    custom_data: {
      form_name: 'contact',
      page: window.location.pathname
    }
  });
}
```

---

## Advanced Features

### Scroll Depth Tracking

Add to GTM as Custom HTML tag (All Pages trigger):

```html
<script>
(function() {
  var depths = [25, 50, 75, 90];
  var tracked = {};
  
  window.addEventListener('scroll', function() {
    var scrolled = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
    depths.forEach(function(depth) {
      if (scrolled >= depth && !tracked[depth]) {
        tracked[depth] = true;
        window.dataLayer.push({
          event: 'up_event',
          event_name: 'scroll_depth',
          custom_data: { depth: depth + '%' }
        });
      }
    });
  });
})();
</script>
```

### Video Engagement Tracking

For YouTube videos (via GTM YouTube Video trigger):

```javascript
// Custom JavaScript variable
function() {
  return {
    video_url: {{Video URL}},
    video_title: {{Video Title}},
    video_percent: {{Video Percent}},
    video_status: {{Video Status}}
  };
}
```

### File Download Tracking

Automatic download tracking via GTM Click trigger:

**Trigger:**
- Type: Click - All Elements
- Condition: Click URL matches regex `\.(pdf|doc|docx|zip|xls|xlsx)$`

**Tag:**
```html
<script>
  window.dataLayer.push({
    event: 'up_event',
    event_name: 'file_download',
    custom_data: {
      file_url: {{Click URL}},
      file_name: {{Click URL}}.split('/').pop()
    }
  });
</script>
```

### Consent Mode v2

Integrate with consent management platforms:

```javascript
// Default consent state (before user choice)
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}

gtag('consent', 'default', {
  'ad_storage': 'denied',
  'analytics_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied'
});

// After user accepts
gtag('consent', 'update', {
  'ad_storage': 'granted',
  'analytics_storage': 'granted',
  'ad_user_data': 'granted',
  'ad_personalization': 'granted'
});
```

### Event Deduplication

Best practices for avoiding duplicate events:

1. **Use consistent event_id:**
   - For purchases: `order_{order_id}`
   - For custom events: `ev_{timestamp}_{random}`

2. **Server-side matches client:**
   - Same event_id sent from both
   - Platforms deduplicate automatically

3. **GTM tag sequencing:**
   - Server-side tag fires after client tags
   - Ensures event_id is available

---

## Troubleshooting

### Events not appearing in GTM Preview?

**Solutions:**
- Clear browser cache
- Disable ad blockers temporarily
- Check browser console for errors
- Verify GTM container ID is correct
- Ensure UP_CONFIG is present: `console.log(window.UP_CONFIG)`

### CAPI events not reaching platforms?

**Check:**
1. WordPress Admin → Ultra Pixels → Queue Status
2. Queue length increasing? Check CAPI tokens
3. Dead-letter table entries? Review error messages
4. Click "Process now" to manually trigger
5. Check PHP error logs

### Duplicate events in Meta/TikTok?

**Verify:**
- event_id is same for client and server events
- Both channels use same event_id format
- No multiple tags firing for same event
- Check GTM tag firing sequence

### GTM container not loading?

**Verify:**
- GTM Container ID format: GTM-XXXXXXX
- Plugin setting "Enable GTM" = Yes
- Check page source for GTM snippet
- Network tab shows gtm.js loaded

---

## Best Practices Summary

✅ **Do:**
- Use GTM for all pixel management
- Implement event_id for deduplication
- Test in GTM Preview before publishing
- Monitor CAPI queue regularly
- Use server-side GTM for enhanced privacy
- Document custom events and triggers
- Implement consent management

❌ **Don't:**
- Hardcode pixels directly in theme
- Mix GTM and non-GTM pixel implementations
- Expose API tokens client-side
- Skip testing in preview mode
- Ignore dead-letter queue items

---

## Additional Resources

- [GTM Templates Directory](./gtm-templates/)
- [Plugin README](./README.md)
- [Landing Pages Guide](./LANDING_PAGES.md)
- [Developer Documentation](./DEVELOPER.md)

**Official Documentation:**
- [Google Tag Manager](https://support.google.com/tagmanager)
- [Meta Conversions API](https://developers.facebook.com/docs/marketing-api/conversions-api)
- [TikTok Events API](https://ads.tiktok.com/marketing_api/docs?id=1701890979375106)
- [Google Ads Conversions](https://support.google.com/google-ads/answer/6331304)

---

**Questions or Issues?**
Submit an issue on the GitHub repository with:
- WordPress version
- GTM container export (sanitized)
- Console errors (if any)
- Expected vs actual behavior
