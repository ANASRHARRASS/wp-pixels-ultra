# Ultra Pixels Ultra

Version: 0.4.4

Ultra Pixels Ultra is a production-ready WordPress plugin for GTM-first tracking and server-side event forwarding. It supports Meta, TikTok, Google Ads, Snapchat, Pinterest and custom provider integrations. The plugin provides a client-side forwarder for GTM, a DB-backed CAPI queue, server-side provider forwarding helpers, and admin settings for configuration.

## ✨ Highlights
- GTM-first architecture with optional GTM server forwarding
- DB-backed CAPI queue with dead-letter support and retries
- Client-side GTM forwarder that strips PII before sending to WordPress
- Provider helpers for secure server-side forwarding (secrets resolved from env/wp-config)
- Elementor integration, auto form tracking, and landing page helpers

## Installation
1. Copy the `wp-pixels-ultra/` folder into `wp-content/plugins/`.
2. Activate the plugin from WordPress Admin → Plugins.
3. Go to **Ultra Pixels → Settings** to configure platforms and GTM options.
4. Optionally import the GTM container template found in `gtm-templates/`.

## Quick Start — GTM-First (Recommended)
1. Import the production-ready GTM container: `gtm-templates/ultra-pixels-gtm-container.json`.
2. In plugin settings set **Use GTM Server for Event Forwarding** and enter your GTM server URL.
3. Add pixel IDs (Meta, TikTok, etc.) in the settings. When GTM manages pixels, the plugin avoids injecting client pixels directly.
4. Test using GTM Preview and the plugin health endpoint.

## Features
- GTM Server Forwarder: route server-side events through your GTM server container.
- Fallback to direct platform APIs when GTM forwarding is disabled.
- Event deduplication using deterministic `event_id`.
- Consent & region gating: `window.UP_CONSENT` and `window.UP_REGION` support.
- WP-CLI command to process the CAPI queue: `wp up-capi process [--limit=50]`.

## Configuration
- Settings page: **Ultra Pixels → Settings**
- Key options:
	- `gtm_container_id` — GTM container to use for client-side operations
	- `use_gtm_forwarder` — whether to enable the GTM client forwarder
	- `gtm_server_url` — URL of your GTM server container for server forwarding
	- Platform IDs/Tokens — Store tokens server-side (env or wp-config recommended)

### Server-side constants (optional)
You may set provider secrets or server options as constants in `wp-config.php` or environment variables. This is recommended over storing API keys in the database.

## REST Endpoints
- `POST /wp-json/up/v1/ingest` — Client forwarder / GTM forwarder ingestion endpoint (supports nonce or same-origin auth)
- `POST /wp-json/up/v1/test` — Admin test route (requires capability)
- `POST /wp-json/up/v1/process-queue` — Admin-only queue processing trigger
- `GET /wp-json/up/v1/queue/status` — Admin-only queue status
- `GET /wp-json/up/v1/health` — Lightweight health information (queue lengths, recent logs)

## WP-CLI
Run the CAPI queue processor manually:

```
wp up-capi process --limit=20
```

## Security and PII handling
- The GTM client forwarder strips raw PII (email/phone) before sending to WordPress when used.
- If you call the ingest endpoint directly from custom code, you may send raw PII to the server where it will be hashed server-side — follow your privacy policy and regional requirements.
- Platform API tokens and secrets should never be exposed client-side.

## Troubleshooting
- Use the health endpoint and the admin queue UI for debugging.
- Check `wp-content/debug.log` for plugin errors when WP_DEBUG is enabled.

## Changelog
### v0.4.4 (November 2025)
- feat(rest): Public GTM client forwarder — adds `/up/v1/ingest` with nonce, secret, or same-origin auth; server-side enrichment and PII hashing.
- fix(forwarder): Strip PII from `user_data` and support `user` alias; prefer reliable `fetch` usage with `keepalive` and proper headers.
- feat(capi): Optional GTM server forwarding via `use_gtm_forwarder` + `gtm_server_url`.

---

For full developer notes and GTM forwarder guidance see `GTM-CLIENT-FORWARDER.md` and `gtm-templates/`.

License: GPL v2 or later
