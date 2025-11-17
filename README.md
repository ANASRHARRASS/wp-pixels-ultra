# Ultra Pixels Ultra

**Version**: 0.4.0

A production-ready WordPress plugin for **GTM-first tracking** with comprehensive support for Meta, TikTok, Google Ads, Snapchat, Pinterest, and custom platforms. Features server-side CAPI forwarding, Enhanced Ecommerce, Elementor integration, automatic form tracking, and enterprise-grade queue management.

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

### Option 1: GTM-First Setup (Recommended)
1. Import container JSON (GTM Admin → Import → Select `gtm-container-up-template.json`).
2. Configure plugin settings:
   - GTM Container ID: `GTM-XXXXXX`
   - Let GTM manage all client pixels: Yes
   - Enter pixel IDs (Meta, TikTok, etc.) for server-side queueing.
3. Update GTM variables with your IDs if needed.
4. Test in GTM Preview & publish.

📚 Full Guide: See [GTM_SETUP.md](./GTM_SETUP.md)

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
- Optional server-side GTM container URL for middleware forwarding.

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
