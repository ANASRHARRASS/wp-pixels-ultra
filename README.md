# Ultra Pixels Ultra

**Version**: 0.4.2

A production-ready WordPress plugin for **GTM-first tracking** with comprehensive support for Meta, TikTok, Google Ads, Snapchat, Pinterest, and custom platforms. Features server-side CAPI forwarding, Enhanced Ecommerce, Elementor integration, automatic form tracking, and enterprise-grade queue management.

## ✨ New in v0.4.4

- 🚀 **GTM Server Forwarder**: Unified event forwarding through GTM server-side container for all platforms
- 🎯 **Simplified Architecture**: Route all server-side events through GTM instead of direct platform API calls
- 🔄 **Centralized Management**: Configure once in GTM, reduce WordPress configuration complexity
- ⚡ **Better Performance**: Single endpoint for all platforms, improved reliability and monitoring

## ✨ New in v0.4.0

- 🩺 **Health Endpoint**: `GET /wp-json/up/v1/health` for lightweight diagnostics (queue length, dead-letter count, platform flags, recent logs)
- 🛡️ **Consent & Region Gating**: Client script honors `window.UP_CONSENT`, `window.UP_REGION`, and `window.UP_REGION_BLOCKED` before injecting ad pixels or forwarding events
- 🧩 **Dedicated Tokens**: Settings for Snapchat API token, Pinterest access token, Google Ads conversion label
- 🔄 **Richer Adapters**: Snapchat & Pinterest Conversions API payload enhancements; Google Ads middleware forwarding stub via server container path
- ⚙️ **Settings Expansion**: New secure fields (never localized client-side)

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
4. Optionally import the GTM container template (see `GTM_SETUP.md`).

## Quick Start

### Option 1: GTM-First Setup (Recommended) ⭐ Production-Ready
1. Import the **production-ready** container JSON: `gtm-templates/ultra-pixels-gtm-container.json`
   - Includes 6 pre-configured tags (Meta, TikTok, GA4)
   - 5 custom triggers for all event types
   - 13 data layer variables ready to use
   - ✅ Full browser compatibility (Chrome, Firefox, Safari, Edge)
   - ✅ Enhanced JSON validation and error handling
2. Configure plugin settings:
   - GTM Container ID: `GTM-XXXXXX`
   - Let GTM manage all client pixels: Yes
   - Enter pixel IDs (Meta, TikTok, etc.) for server-side queueing.
3. Update GTM variables with your pixel IDs.
4. Test in GTM Preview & publish.

📚 **New**: [GTM Import Guide](./GTM-IMPORT-GUIDE.md) | [Full Setup Guide](./GTM_SETUP.md)

### Option 2: Direct Pixel Setup
- Set GTM manage flag to No.
- Enter pixel IDs; plugin injects minimal base code.
- Configure CAPI endpoint/token & event mapping JSON.

## Features

### 🎯 GTM & Multi-Platform Support
- Pre-built GTM container template.
- 6+ Platforms: Meta, TikTok, Google Ads, Snapchat, Pinterest + custom endpoints.
- Enhanced Ecommerce with full transaction data.
- Event deduplication via deterministic `event_id` for purchases & forms.
- **GTM Server Forwarder**: Route all server-side events through GTM server-side container for unified platform management.
- Optional fallback to direct platform API calls when GTM forwarding is disabled.

### 🛒 WooCommerce Integration
Auto-track: `PageView`, `view_item`, `add_to_cart`, `begin_checkout`, `purchase` with ecommerce.items & hashed user data.

### 🎨 Elementor Integration
Popups, forms, CTA buttons, tabs/accordion interactions via unified `up_event` schema.

### 📝 Smart Tracking
Automatic form detection (including search), scroll depth, WhatsApp link normalization, custom events via `data-up-event`.

### 🌐 Landing Page Tracking
WhatsApp buttons & arbitrary custom events. See [LANDING_PAGES.md](./LANDING_PAGES.md).

### Async CAPI Queue
DB-backed queue + dead-letter, retry with backoff, WP-Cron / Action Scheduler support, admin UI with logs.

### Security
REST nonce or server secret auth; hashed PII server-side; tokens never exposed client-side.

### Admin Dashboard
Queue status, items, dead-letter, logs, JSON mapping preview.

### Health & Observability
`GET /wp-json/up/v1/health`: returns `queue_length`, `deadletter_length`, `last_processed`, enabled `platforms`, recent `logs`.

### Consent & Region Gating
Define before loader executes:
```html
<script>
window.UP_CONSENT = { ads: true, analytics: true }; // set false until granted
window.UP_REGION = 'fr';
window.UP_REGION_BLOCKED = ['de','at'];
</script>
```
Behavior:
- Ads not granted or region blocked → ad pixels not injected.
- Analytics not granted → events pushed to dataLayer but not forwarded server-side.

### New Settings Fields
| Field | Purpose | Client Exposure |
|-------|---------|----------------|
| google_ads_label | Enhanced conversions label (forwarded server-side) | No |
| snapchat_api_token | Snapchat Conversions API bearer token | No |
| pinterest_access_token | Pinterest Conversions API bearer token | No |

## Configuration

### Event Mapping JSON
Example snippet:
```json
{
  "purchase": {"meta": {"event_name": "Purchase", "include_user_data": true}, "tiktok": {"event_name": "PlaceAnOrder", "include_user_data": true}},
  "add_to_cart": {"meta": {"event_name": "AddToCart"}, "tiktok": {"event_name": "AddToCart"}}
}
```

### Server-side Setup
Optional constants in `wp-config.php`:
```php
define( 'UP_SERVER_SECRET', 'your-secure-secret-key' );
define( 'UP_CAPI_ENDPOINT', 'https://example.com/capi' );
```

### GTM Server Forwarder Setup
The GTM Server Forwarder routes all server-side events through your GTM server-side container instead of making direct API calls to each platform (Meta, TikTok, etc.).

**Benefits:**
- ✅ Centralized event routing and transformation in GTM
- ✅ Single endpoint reduces WordPress configuration complexity
- ✅ Better debugging and monitoring through GTM interface
- ✅ Easier to add/remove platforms without WordPress changes
- ✅ Improved reliability with GTM's robust infrastructure

**Setup Steps:**

1. **Configure GTM Server Container**
   - Set up a Google Tag Manager server-side container
   - Configure server-side tags for each platform (Meta, TikTok, Google Ads, etc.)
   - Note your GTM server container URL (e.g., `https://your-gtm-server.com`)

2. **Enable in WordPress Plugin Settings**
   - Go to **WordPress Admin → Ultra Pixels → Settings**
   - Set **GTM Server Container URL**: `https://your-gtm-server.com`
   - Enable **Use GTM Server for Event Forwarding**: Yes
   - Enter pixel IDs for each platform (these will be sent to GTM)
   - Optionally add platform API tokens (GTM will use these for API calls)
   - Save changes

3. **Configure GTM Server Container**
   - Create a custom endpoint at `/event` (or update the path in the code)
   - Parse incoming JSON payload with structure:
     ```json
     {
       "platform": "meta|tiktok|google_ads|snapchat|pinterest",
       "events": [
         {
           "event_name": "Purchase",
           "event_id": "order_12345",
           "event_time": 1234567890,
           "user_data": {"email_hash": "...", "phone_hash": "..."},
           "custom_data": {"value": 99.99, "currency": "USD"}
         }
       ],
       "pixel_ids": {
         "meta_pixel_id": "...",
         "tiktok_pixel_id": "...",
         "google_ads_id": "...",
         "snapchat_pixel_id": "...",
         "pinterest_tag_id": "..."
       },
       "tokens": {
         "capi_token": "...",
         "snapchat_api_token": "...",
         "pinterest_access_token": "..."
       },
       "source": "wordpress",
       "site_url": "https://example.com",
       "timestamp": 1234567890
     }
     ```
   - Route events to appropriate platform tags based on `platform` field
   - Use provided tokens and pixel IDs for platform API calls

4. **Test the Integration**
   - Trigger a test event (e.g., add to cart)
   - Check **WordPress Admin → Ultra Pixels → Settings** queue
   - Monitor GTM server container logs
   - Verify events appear in platform Event Managers

**Fallback Mode:**
- If GTM forwarding is disabled, the plugin falls back to direct platform API calls
- Existing platform-specific configurations (tokens, IDs) are still used
- No events are lost during configuration changes

## Usage

### Landing Page Examples
```html
<a href="https://wa.me/15551234567?text=Hello" data-up-event="whatsapp_initiate">Contact on WhatsApp</a>
<button data-up-event="video_play" data-up-payload='{"video_id":"demo"}'>Play Video</button>
```

### Data Layer Summary
`event:'up_event'`, `event_name`, `event_id`, `value`, `currency`, `contents[]`, `transaction_id`, `source_url`, optional `ecommerce.items`.

### REST Endpoints
- `POST /wp-json/up/v1/ingest`
- `POST /wp-json/up/v1/test` (admin)
- `POST /wp-json/up/v1/process-queue` (admin)
- `GET /wp-json/up/v1/queue/status` (admin)
- `GET /wp-json/up/v1/health`

### WP-CLI
```bash
wp up-capi process [--limit=20]
```

## Architecture
Key files: loader, events, rest, capi, settings, pixel-loader.
DB tables: `up_capi_queue`, `up_capi_deadletter`.

## Changelog
### v0.4.3 (November 2025) - Production Improvements
- 🚀 **Production-Ready GTM JSON**: Enhanced both GTM container templates with proper format version, timestamps, and metadata
- 🛡️ **Enhanced Client-Side Validation**: Improved JSON parsing and error handling in pixel-loader.js
- ✅ **Browser Compatibility**: Added dataLayer validation to prevent conflicts with third-party scripts
- 📝 **Better Error Messages**: Detailed console warnings for debugging without breaking functionality
- 📚 **New Documentation**: Added comprehensive [GTM-IMPORT-GUIDE.md](./GTM-IMPORT-GUIDE.md) for production deployments
- 🔧 **Build Improvements**: Fixed linter configuration to exclude PHP-only directories

### v0.4.2 (November 2025)
- fix(git-updater): Align `Text Domain` to `wp-pixels-ultra` to match plugin folder slug and resolve Git Updater warnings (Undefined array key 'wp-pixels-ultra' and header notices). No tracking or behavioral changes.

### v0.2.0 (November 2025)
- DB-backed queue, dead-letter, enhanced WooCommerce, WhatsApp tracking, mapping UI, security hardening, WP-CLI.
### v0.1.0
- Initial release.

## Best Practices
Test on staging, secure endpoints, monitor queue, implement consent, hash PII, dedupe with consistent IDs.

## Troubleshooting
Meta/TikTok mismatch → check event_id & queue. WhatsApp not tracked → verify link & mapping. Use health endpoint for monitoring.

## Support
Admin UI, browser DevTools, server logs (`wp-content/debug.log`).

Maintained: November 2025
License: GPL v2 or later
