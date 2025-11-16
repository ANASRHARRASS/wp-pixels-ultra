# Ultra Pixels Ultra

**Version**: 0.3.0

A production-ready WordPress plugin for **GTM-first tracking** with comprehensive support for Meta, TikTok, Google Ads, Snapchat, Pinterest, and custom platforms. Features server-side CAPI forwarding, Enhanced Ecommerce, Elementor integration, automatic form tracking, and enterprise-grade queue management.

## ✨ New in v0.3.0

- 🎯 **GTM-First Architecture**: Complete GTM container templates with pre-configured tags, triggers, and variables
- 🌐 **Multi-Platform Support**: Meta, TikTok, Google Ads, Snapchat, Pinterest (easily extensible)
- 📊 **Enhanced Ecommerce**: GA4-compatible dataLayer events with full ecommerce data
- 🎨 **Elementor Integration**: Automatic tracking for popups, forms, buttons, and widgets
- 📝 **Smart Form Detection**: Auto-track all form submissions and search queries
- 📏 **Scroll Depth Tracking**: Monitor user engagement at 25%, 50%, 75%, 90%
- 📖 **Comprehensive Documentation**: Step-by-step GTM setup guide with best practices

## Installation

1. Copy the `wp-pixels-ultra/` folder into `wp-content/plugins/`.
2. Activate the plugin from WordPress Admin > Plugins.
3. Go to **Ultra Pixels > Settings** to configure platforms.
4. **Import GTM Container**: Upload `gtm-templates/ultra-pixels-gtm-container.json` to your GTM account.

## Quick Start

### Option 1: GTM-First Setup (Recommended)
1. **Import GTM Container**: GTM Admin → Import → `gtm-templates/ultra-pixels-gtm-container.json`
2. **Configure Plugin Settings**:
   - GTM Container ID: `GTM-XXXXXX`
   - Enable GTM: Yes
   - Meta Pixel ID: (your pixel ID)
   - TikTok Pixel ID: (your pixel ID)
3. **Update GTM Variables**: Set your pixel IDs in GTM variables
4. **Test & Publish**: Use GTM Preview mode, then publish

📚 **Full Guide**: See [GTM-SETUP-GUIDE.md](./GTM-SETUP-GUIDE.md) for detailed instructions

### Option 2: Direct Pixel Setup
- **GTM Container ID**: Your Google Tag Manager container ID
- **Platform Pixel IDs**: Enable tracking for Meta, TikTok, Google Ads, Snapchat, Pinterest
- **CAPI Endpoint & Token**: Configure server-side event forwarding
- **Event Mapping**: Customize platform-specific event names (JSON format)

## Features

### 🎯 GTM & Multi-Platform Support
- **Pre-built GTM Container**: Import and go - includes tags, triggers, variables for all platforms
- **6+ Platforms**: Meta, TikTok, Google Ads, Snapchat, Pinterest + custom endpoints
- **Enhanced Ecommerce**: GA4-compatible dataLayer with full transaction data
- **Event Deduplication**: Consistent `event_id` across client and server
- **Server-Side GTM**: Optional server container for enhanced privacy and performance

### 🛒 WooCommerce Integration
- Auto-track: `PageView`, `view_item`, `add_to_cart`, `begin_checkout`, `purchase`
- Enhanced Ecommerce data with product details, prices, quantities
- Events sent to CAPI queue and processed asynchronously
- Hashed customer data (email) for privacy and deduplication
- Transaction-level revenue and currency tracking

### 🎨 Elementor Integration
- **Popup Tracking**: Automatic open/close events
- **Form Submissions**: Track Elementor Pro forms with field data
- **Button Clicks**: Track CTA buttons with custom attributes
- **Tab/Accordion**: Track user interactions with content
- **Widget Support**: Easy integration via data attributes

### 📝 Smart Tracking Features
- **Automatic Form Detection**: Track all form submissions (including search)
- **Scroll Depth**: Monitor engagement at 25%, 50%, 75%, 90%
- **WhatsApp Links**: Auto-detect and track WhatsApp interactions
- **Custom Events**: Use `data-up-event` attributes on any element
- **Dynamic Elements**: Works with AJAX-loaded content

### 🌐 Landing Page Tracking
- **WhatsApp Buttons**: Automatically detect and track WhatsApp clicks
- **Custom Events**: Mark buttons/links with `data-up-event` and `data-up-payload`
- **Works Everywhere**: Custom code, Elementor, page builders
- **Full Examples**: See [LANDING_PAGES.md](./LANDING_PAGES.md) for integration guide

### Async CAPI Queue
- **DB-backed**: Events stored in `{prefix}up_capi_queue` table (created on activation)
- **Admin UI**: View queued items, retry failed events, delete items from admin settings
- **Dead-letter table**: Failed events moved to `{prefix}up_capi_deadletter` after max retries
- **Manual processing**: Use "Process now" button or `wp up-capi process` (WP-CLI)
- **WP-Cron**: Automatic queue processing scheduled hourly

### Security
- Client-side requests use **WordPress REST nonce** (`X-WP-Nonce`) for authorization
- Server-to-server requests accept either `X-UP-SECRET` header or WP nonce
- `server_secret` configured in admin panel or as `UP_SERVER_SECRET` constant in `wp-config.php`
- No sensitive data exposed to client JavaScript

### Admin Dashboard
- **Queue Status**: View queue length and last processed timestamp
- **Queue Items**: Paginated list of enqueued events with retry/delete actions
- **Event Mapping Preview**: Real-time JSON validation and event summary
- **Landing Page Guides**: Built-in examples for WhatsApp and custom event tracking

## Configuration

### Event Mapping

Default mappings are provided for common events:

```json
{
  "purchase": {
    "meta": { "event_name": "Purchase", "include_user_data": true },
    "tiktok": { "event_name": "PlaceAnOrder", "include_user_data": true }
  },
  "add_to_cart": {
    "meta": { "event_name": "AddToCart" },
    "tiktok": { "event_name": "AddToCart" }
  },
  "whatsapp_initiate": {
    "meta": { "event_name": "Contact", "include_user_data": true },
    "tiktok": { "event_name": "Contact", "include_user_data": true }
  }
}
```

Edit the **Event Mapping (JSON)** field in admin settings to customize platform-specific event names.

### Server-side Setup

Set in `wp-config.php` for enhanced security:

```php
define( 'UP_SERVER_SECRET', 'your-secure-secret-key' );
define( 'UP_CAPI_ENDPOINT', 'https://your-server.com/api/events' );
```

Or configure via admin panel (Settings > CAPI Endpoint/Token).

## Usage

### Landing Page HTML Examples

**WhatsApp Button:**
```html
<a href="https://wa.me/15551234567?text=Hello" data-up-event="whatsapp_initiate">
  Contact on WhatsApp
</a>
```

**Custom Event Button:**
```html
<button data-up-event="video_play" data-up-payload='{"video_id":"demo"}'>
  Play Video
</button>
```

See [LANDING_PAGES.md](./LANDING_PAGES.md) for more examples.

### PHP Event Registration

```php
// Register custom event
UP()->register_event('my_event', function($data) {
  // Handle event
});

// Trigger event
UP()->trigger_event('my_event', ['key' => 'value']);
```

### REST Endpoints

- `POST /wp-json/up/v1/ingest` — Send event to queue
- `POST /wp-json/up/v1/test` — Blocking test send (admin-only)
- `POST /wp-json/up/v1/process-queue` — Manually process queue (admin-only)
- `GET /wp-json/up/v1/queue/status` — Check queue status (admin-only)

### WP-CLI

```bash
wp up-capi process [--limit=20]
```

Process enqueued CAPI events with optional batch limit.

## Architecture

### Key Files

- `wp-pixels-ultra.php` — Plugin bootstrap; DB table creation on activation
- `includes/class-up-loader.php` — Wiring and initialization
- `includes/class-up-events.php` — WooCommerce event hooks and mappings
- `includes/class-up-rest.php` — REST endpoints for ingest/test/queue management
- `includes/class-up-capi.php` — Event queuing and processing
- `includes/class-up-settings.php` — Admin settings and UI
- `assets/pixel-loader.js` — Client-side pixel injector and event tracker

### Database Tables (created on activation)

- `{prefix}up_capi_queue` — Pending events
- `{prefix}up_capi_deadletter` — Failed events (after max retries)

### Constants

- `UP_PLUGIN_FILE` — Full plugin file path
- `UP_PLUGIN_DIR` — Plugin directory path
- `UP_PLUGIN_URL` — Plugin URL for assets
- `UP_VERSION` — Plugin version (0.2.0)

## Changelog

### v0.2.0 (November 2025)
- **DB-backed queue**: Migrated from option-backed to persistent database queue with dead-letter support
- **Admin queue viewer**: Paginated list with retry/delete actions
- **Enhanced WooCommerce**: Added `begin_checkout` and `view_item_list` events
- **WhatsApp tracking**: Auto-detect WhatsApp links and custom event attributes on landing pages
- **Event mapping UI**: Real-time JSON preview and validation in admin settings
- **Security hardening**: Client uses WP REST nonce; server secret not exposed to JS
- **WP-CLI command**: Added `wp up-capi process` for manual queue processing

### v0.1.0 (Earlier)
- Initial plugin with REST ingest, option-backed queue, and basic pixel injection

## Development

See [DEVELOPER.md](./DEVELOPER.md) for architecture details and extension patterns.

## Best Practices

1. **Test on staging** before deploying to production
2. **Secure CAPI endpoint**: Use TLS and keep bearer tokens private
3. **Monitor queue**: Regularly check admin settings for stuck/failed events
4. **Implement consent**: Use a CMP to respect user privacy preferences
5. **Hash sensitive data**: Send hashed email/phone to CAPI (done server-side)
6. **Event deduplication**: Use `event_id` to prevent double counting

## Troubleshooting

**Events not appearing in Meta/TikTok?**
1. Check admin **Ultra Pixels → Settings** for queue length and last processed time
2. Verify CAPI endpoint and token are correct
3. Review **Queue Items** to see pending events
4. Click "Process now" to manually trigger queue processing
5. Check browser DevTools (Network tab) for `/wp-json/up/v1/ingest` requests

**WhatsApp events not tracking?**
1. Confirm the pixel loader is injected (check page source for `UP_CONFIG`)
2. Test WhatsApp link click in browser DevTools (Network tab)
3. Verify event mapping includes `whatsapp_initiate` event

**See [LANDING_PAGES.md](./LANDING_PAGES.md) for more integration help.**

## Support

For issues or feature requests, refer to:
- Admin queue/status indicators
- Browser DevTools (Network, Console)
- WordPress error logs (`wp-content/debug.log`)

---

**Maintained**: November 2025  
**License**: GPL v2 or later
