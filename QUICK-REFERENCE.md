# Ultra Pixels Ultra - Quick Reference

Essential code snippets and configurations for common use cases.

## Table of Contents
1. [Setup](#setup)
2. [GTM Integration](#gtm-integration)
3. [Custom Events](#custom-events)
4. [Elementor](#elementor)
5. [WooCommerce](#woocommerce)
6. [Forms](#forms)
7. [Platform Configuration](#platform-configuration)
8. [Troubleshooting](#troubleshooting)

---

## Setup

### WordPress Configuration

**wp-config.php** (optional, for enhanced security):
```php
// Server secret for CAPI
define( 'UP_SERVER_SECRET', 'your-secure-secret-key' );

// CAPI endpoint (if using custom server)
define( 'UP_CAPI_ENDPOINT', 'https://your-server.com/api/events' );

// GTM server container (optional)
define( 'UP_GTM_SERVER_URL', 'https://your-server-gtm.com' );
```

### Plugin Settings

**WordPress Admin → Ultra Pixels → Settings**:
```
GTM Container ID:     GTM-XXXXXX
Meta Pixel ID:        1234567890
TikTok Pixel ID:      XXXXXXXXXXXXXX
Google Ads ID:        AW-XXXXXXXXX
Enable GTM:           Yes
Enable Meta:          Yes
Enable TikTok:        Yes
```

---

## GTM Integration

### Import Container

```bash
1. Download: gtm-templates/ultra-pixels-gtm-container.json
2. GTM Admin → Import Container
3. Choose "Merge" with existing
4. Preview and Publish
```

### Update Variables

**In GTM → Variables**:
```
Meta Pixel ID:        YOUR_META_PIXEL_ID
TikTok Pixel ID:      YOUR_TIKTOK_PIXEL_ID
GA4 Measurement ID:   G-XXXXXXXXXX
```

### Test Events

**Browser Console**:
```javascript
// Check if dataLayer is working
console.log(window.dataLayer);

// Manually push event
window.dataLayer.push({
  event: 'up_event',
  event_name: 'test_event',
  custom_data: { test: true }
});
```

---

## Custom Events

### HTML Button

```html
<button 
  data-up-event="cta_click" 
  data-up-payload='{"button_name":"hero_cta","campaign":"summer"}'>
  Click Me
</button>
```

### Link

```html
<a href="/pricing" 
   data-up-event="pricing_view" 
   data-up-payload='{"source":"header"}'>
  View Pricing
</a>
```

### JavaScript

```javascript
// Push event to dataLayer
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
  event: 'up_event',
  event_name: 'custom_action',
  event_id: 'evt_' + Date.now(),
  event_time: Math.floor(Date.now() / 1000),
  source_url: window.location.href,
  custom_data: {
    action_type: 'download',
    file_name: 'ebook.pdf'
  }
});
```

### PHP

```php
// Trigger custom event server-side
if ( class_exists( 'UP_CAPI' ) ) {
    UP_CAPI::enqueue_event( 'meta', 'CustomEvent', array(
        'custom_data' => array(
            'property' => 'value'
        ),
        'event_id' => 'custom_' . time(),
    ) );
}
```

---

## Elementor

### Button Widget

**Elementor → Edit Widget → Advanced → Attributes**:
```
data-up-event: signup_click
data-up-payload: {"button_type":"cta","location":"hero"}
```

### Popup Tracking

**Automatic** - No configuration needed!

Events fired:
- `popup_open` - When popup shows
- `popup_close` - When popup closes

### Form Widget

**In Actions After Submit → Custom Code** (optional override):
```javascript
if (window.dataLayer) {
  window.dataLayer.push({
    event: 'up_event',
    event_name: 'lead_form_submit',
    custom_data: {
      form_type: 'lead_generation',
      campaign: 'summer_sale'
    }
  });
}
```

### HTML Widget

```html
<div class="custom-cta">
  <a href="#" 
     data-up-event="video_play" 
     data-up-payload='{"video_id":"demo_v1"}'>
    Watch Demo
  </a>
</div>
```

---

## WooCommerce

### Tracked Events (Automatic)

- `PageView` - Product pages, shop pages
- `view_item` - Single product view
- `add_to_cart` - Add to cart button
- `begin_checkout` - Checkout page load
- `purchase` - Order thank you page

### Custom Product Event

```php
// In functions.php or custom plugin
add_action( 'woocommerce_single_product_summary', function() {
    global $product;
    ?>
    <button 
      data-up-event="add_to_wishlist" 
      data-up-payload='{"product_id":"<?php echo $product->get_id(); ?>"}'>
      Add to Wishlist
    </button>
    <?php
}, 35 );
```

### Enhanced Product Data

```javascript
// Add custom product attributes to dataLayer
jQuery('.single-product').on('found_variation', function(e, variation) {
  window.dataLayer.push({
    event: 'up_event',
    event_name: 'variant_selected',
    custom_data: {
      variation_id: variation.variation_id,
      price: variation.display_price,
      stock: variation.is_in_stock
    }
  });
});
```

---

## Forms

### Disable Tracking on Specific Form

```html
<form data-up-no-track>
  <!-- This form won't be tracked -->
  <input type="text" name="query">
  <button type="submit">Search</button>
</form>
```

### Contact Form 7

**Automatic tracking** via form submission listener.

Optional override:
```javascript
document.addEventListener('wpcf7mailsent', function(event) {
  window.dataLayer.push({
    event: 'up_event',
    event_name: 'contact_form_submit',
    custom_data: {
      form_id: event.detail.contactFormId,
      form_type: 'contact'
    }
  });
});
```

### Gravity Forms

```javascript
jQuery(document).on('gform_confirmation_loaded', function(event, formId) {
  window.dataLayer.push({
    event: 'up_event',
    event_name: 'gravity_form_submit',
    custom_data: {
      form_id: formId
    }
  });
});
```

---

## Platform Configuration

### Meta (Facebook)

**Get Access Token**:
1. Meta Events Manager
2. Settings → Conversions API
3. Generate Access Token
4. Add to WordPress: Ultra Pixels → CAPI Token

**Test Events**:
```
Meta Events Manager → Test Events
- Send test event from website
- Verify event_id matches
- Check deduplication working
```

### TikTok

**Get Access Token**:
1. TikTok Events Manager
2. Settings → Events API
3. Generate Access Token
4. Add to WordPress settings

**Verify**:
```
TikTok Events Manager → Events
- Real-time events tab
- Check event names match
- Verify properties present
```

### Google Ads

**Setup Enhanced Conversions**:
1. Google Ads → Tools → Conversions
2. Edit conversion action
3. Enable Enhanced Conversions
4. Add Conversion ID to plugin

**GTM Tag**:
```
Tag Type: Google Ads Conversion Tracking
Conversion ID: {{Google Ads ID}}
Conversion Label: YOUR_LABEL
Include user data: Enabled
```

### Snapchat

**API Setup**:
1. Snapchat Ads Manager → Pixels
2. Create Conversions API token
3. Add Pixel ID to plugin
4. Add token to CAPI Token field

### Pinterest

**Get Access Token**:
1. Pinterest Ads Manager → Conversions
2. API Settings
3. Generate token
4. Add Tag ID and token to plugin

---

## Troubleshooting

### Events Not Showing in GTM Preview

**Check**:
```javascript
// 1. Verify UP_CONFIG loaded
console.log(window.UP_CONFIG);

// 2. Check dataLayer
console.log(window.dataLayer);

// 3. Verify GTM container loaded
console.log(window.google_tag_manager);
```

**Fix**:
- Clear browser cache
- Disable ad blockers
- Verify GTM Container ID is correct
- Check page source for GTM snippet

### CAPI Events Not Sending

**WordPress Admin → Ultra Pixels → Settings**:
```
Check:
- Queue length (should decrease)
- Last processed time (should be recent)
- Dead-letter items (should be empty)

Actions:
- Click "Process now" button
- Check PHP error logs
- Verify access tokens valid
```

**WP-CLI**:
```bash
wp up-capi process
```

### Duplicate Events

**Check Event IDs**:
```javascript
// Client-side
console.log(window.dataLayer.filter(e => e.event_id));

// Look for same event_id in both:
// - GTM → Meta/TikTok tags
// - WordPress → CAPI queue
```

**Fix**: Ensure `event_id` is consistent across client and server.

### Form Tracking Not Working

**Verify**:
```javascript
// Check form submission listener
document.querySelector('form').addEventListener('submit', function(e) {
  console.log('Form submitted:', e.target);
});
```

**Common Issues**:
- Form has `data-up-no-track` attribute
- AJAX form (use form plugin's callback)
- JavaScript error blocking listener

### Scroll Depth Not Tracking

**Test**:
```javascript
// Scroll to bottom of page
window.scrollTo(0, document.body.scrollHeight);

// Check dataLayer
console.log(window.dataLayer.filter(e => e.event_name === 'scroll_depth'));
```

**Expected**: 4 events (25%, 50%, 75%, 90%)

---

## Quick Diagnostics

### Browser Console Check

```javascript
// Complete diagnostic
console.log('UP_CONFIG:', window.UP_CONFIG);
console.log('dataLayer:', window.dataLayer);
console.log('GTM:', window.google_tag_manager);
console.log('Meta Pixel:', typeof window.fbq);
console.log('TikTok Pixel:', typeof window.ttq);
console.log('Snapchat:', typeof window.snaptr);
console.log('Pinterest:', typeof window.pintrk);
```

### PHP Version Check

```php
<?php
// Check if classes loaded
var_dump([
    'UP_Settings' => class_exists('UP_Settings'),
    'UP_Events' => class_exists('UP_Events'),
    'UP_CAPI' => class_exists('UP_CAPI'),
    'UP_Elementor' => class_exists('UP_Elementor'),
]);

// Check queue length
if (class_exists('UP_CAPI')) {
    echo 'Queue: ' . UP_CAPI::get_queue_length();
}
?>
```

### Network Tab Check

**Filter**: `/wp-json/up/v1/ingest`

**Expected**:
- Method: POST
- Status: 200 OK
- Response: `{"success":true}` or similar

**Headers**:
- Content-Type: application/json
- X-WP-Nonce: (present)

---

## Advanced Use Cases

### Custom Platform

```php
// Add custom platform to event mapping
add_filter('up_event_mapping', function($mapping) {
    foreach ($mapping as $event => &$platforms) {
        $platforms['custom_platform'] = [
            'event_name' => strtoupper($event),
            'include_user_data' => true
        ];
    }
    return $mapping;
});

// Add custom send function
add_action('up_capi_send_custom_platform', function($events) {
    // Your custom CAPI implementation
    wp_remote_post('https://api.custom.com/events', [
        'body' => json_encode($events)
    ]);
});
```

### User Identification

```php
// Add logged-in user data to events
add_filter('up_event_user_data', function($user_data) {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $user_data['email_hash'] = hash('sha256', strtolower($user->user_email));
        $user_data['external_id'] = $user->ID;
    }
    return $user_data;
});
```

### Debug Mode

```php
// Enable verbose logging
add_filter('up_debug_mode', '__return_true');

// Check logs
$logs = UP_CAPI::get_logs();
print_r($logs);
```

---

## Keyboard Shortcuts

**GTM Preview Mode**:
- `Esc` - Close preview pane
- `Ctrl/Cmd + K` - Search tags/triggers/variables

**WordPress Admin**:
- Settings: `/wp-admin/admin.php?page=ultra-pixels`
- Queue: Scroll to "CAPI Queue" section

---

**Need More Help?**
- Full Guide: See `GTM-SETUP-GUIDE.md`
- Documentation: See `README.md`
- GitHub Issues: Submit with details

**Last Updated**: November 2025
