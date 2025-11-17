# AI Coding Agent Instructions for `wp-pixels-ultra`

Concise, project-specific guidance to become productive quickly. Focus on existing patterns—do not introduce generic abstractions unless required by a concrete change.

## Big Picture
A WordPress plugin providing unified client + server tracking for WooCommerce & landing pages. Client JS captures pixel + custom interactions and forwards them to a server-side queue. The queue batches and asynchronously sends events to Meta / TikTok (or a generic CAPI endpoint). Reliability features: DB-backed queue, dead-letter table, retries with backoff, rate-limiting, admin observability, WP-CLI/manual processing.

## Core Architecture
- Entry: `wp-pixels-ultra.php` defines constants, activation hooks (creates DB tables), loads classes, registers WP-CLI command, instantiates event facade `UP_Plugin`.
- Loader: `includes/class-up-loader.php` conditionally `require_once` class files and wires hooks (admin menus, head/body pixel output, REST routes, queue processing).
- Settings/UI: `includes/class-up-settings.php` manages single option `up_settings` (JSON event mapping, rate limit config, tokens). Rendered by `UP_Settings::render_page()` via admin.
- Event Capture (WooCommerce): `includes/class-up-events.php` pushes structured events to `dataLayer`, builds normalized payload, and calls `UP_CAPI::enqueue_event()` using mapping rules.
- Queue + Delivery: `includes/class-up-capi.php` handles enqueue (`up_capi_queue`), batch processing, retry logic, dead-letter (`up_capi_deadletter`), per-platform send adapters.
- REST API: `includes/class-up-rest.php` ingestion (`/up/v1/ingest`), admin utilities (queue status/items, dead-letter, logs, processing). Ingest enforces server secret or WP REST nonce + rate limits.
- Frontend Pixels: `assets/pixel-loader.js` injects GTM/Meta/TikTok (if configured), sends PageView + custom/WhatsApp events to server, leverages `UP_CONFIG` localized in loader.
- Admin JS: `assets/admin.js` (queue/ dead-letter viewer, manual processing, JSON validation). Contains duplicated blocks & probable syntax issues—refactor carefully without changing behavior.

## Data & Flow
1. Page load → `UP_Loader` enqueues `pixel-loader.js` + localizes `UP_CONFIG` (nonce, pixel IDs, ingest URL).
2. Client JS sends events via `POST /wp-json/up/v1/ingest` with `X-WP-Nonce` (never exposes `server_secret`).
3. REST ingest normalizes payload, hashes PII, enqueues row into queue table.
4. Queue processor (`UP_CAPI::process_queue`) batches by platform, calls `send_batch()` → platform-specific HTTP calls or generic endpoint fallback.
5. Failures with >=5 attempts move to dead-letter for admin visibility.

## Conventions & Patterns
- Class naming: `class-up-*.php` defines a single class with static entry points (`::init`, `::register_routes`, etc.). Loader conditionally includes—avoid hard fatal dependencies.
- Facade: Use `UP()->register_event()` / `UP()->trigger_event()` for custom cross-plugin events; also dispatches `do_action('up_event_triggered', $name, $data)`.
- Event Mapping: Stored as JSON string in option `up_settings[event_mapping]`. Each key → `{ platform: { event_name, include_user_data } }`. Extend by updating JSON (ensure valid via admin validator).
- Security: Never expose `server_secret`. Use `wp_create_nonce('wp_rest')` client-side. Server verifies either `X-UP-SECRET` or nonce.
- Rate Limiting: Transient-based counters (`ip` + `token`). Configurable via settings (`rate_limit_ip_per_min`, `rate_limit_token_per_min`, `retry_after_seconds`). Preserve semantics if modifying ingest.
- Queue Backoff: `next_attempt = now + 60 * attempts`. Do not change without updating dead-letter policy or admin UI expectations.
- Max Attempts: 5 → then dead-letter. Keep constant unless adjusting UI & docs.
- Logging: `UP_CAPI::log()` retains last 100 entries in option `up_capi_log` + `error_log`. Prefer this for lightweight diagnostics.

## Build & Dev Workflow
- JS/CSS build: `npm run build` (webpack prod) / `npm run dev` (watch). Source assets in `assets/` only—no bundling of PHP.
- Lint/Format: `npm run lint`, `npm run lint:fix`, `npm run format`. Maintain existing code style (no large rewrites).
- Node version: `>=16`. No tests—avoid adding a framework unless requested.

## Adding / Modifying Functionality
- New tracked event (WooCommerce or custom): Implement logic → build payload → call `UP_CAPI::enqueue_event(platform, event_name, payload)` (preferred) or `send_event` for immediate send. Update mapping JSON if platform names differ.
- New platform: Add mapping keys; implement `send_to_<platform>()` function similar to Meta/TikTok; extend `send_batch()` dispatch. Keep async queue semantics.
- New REST endpoint: Add in `UP_REST::register_routes()`; follow existing permission model (nonce for ingest, capability check for admin). Sanitize + hash PII server-side.
- Admin UI extension: Use existing localization pattern in `UP_Admin::enqueue_assets()`. Avoid heavy SPA—progressively enhance.
- Background scheduling: Prefer Action Scheduler if available (code checks `function_exists('as_schedule_single_action')`); maintain fallback scheduling.

## Common Pitfalls / Guardrails
- Do NOT expose sensitive tokens or `server_secret` via `wp_localize_script`.
- Preserve JSON mapping validation flow; invalid JSON sets transient `up_event_map_error`.
- Ensure enqueue operations remain non-blocking—do not perform remote HTTP calls inline with ingest.
- When refactoring `admin.js`, keep queue pagination & dead-letter retry semantics intact; watch for missing braces (current file has structural issues).
- Avoid altering DB schema without corresponding activation/upgrade routines (`register_activation_hook`).

## Quick Reference Examples
- Enqueue custom event:
  ```php
  UP_CAPI::enqueue_event('meta', 'Lead', ['email_hash' => hash('sha256', 'user@example.com')]);
  ```
- Trigger internal event for other components:
  ```php
  UP()->trigger_event('video_play', ['video_id' => 42]);
  ```
- Ingest REST sample (client):
  ```js
  fetch(UP_CONFIG.ingest_url,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':UP_CONFIG.nonce},body:JSON.stringify({platform:'meta',event_name:'Purchase',user_data:{email:'user@example.com'},custom_data:{value:19.99,currency:'USD'}})})
  ```

## When Unsure
Inspect corresponding class: settings → `UP_Settings`, queue → `UP_CAPI`, REST → `UP_REST`. Follow existing sanitization (`sanitize_text_field`, `esc_url_raw`, `wp_json_encode`). Ask before introducing large new subsystems.

---
Provide feedback on unclear areas (e.g., adapter extension, admin JS cleanup) so instructions can be refined.
