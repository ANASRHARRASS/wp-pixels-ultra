Developer Notes & Optimization

1. Background processing
   - Move server POSTs to a background queue (Action Scheduler or WP Background Processing) to avoid blocking page loads.

2. Batching & Idempotency
   - Batch events and send periodically.
   - Use request IDs or transaction IDs on server endpoint to avoid duplicates.

3. Privacy & Consent
   - Integrate with CMPs and respect consent before firing pixels/CAPI.

4. Logging & Monitoring
   - Keep an error log (option or external) and expose a debug mode.

5. Performance
   - Minimize inline scripts; use async and defer when possible.
   - Avoid heavy PHP work on page load; compute payloads only when needed.

6. Testing
   - Use staging site, verify dataLayer contents and server endpoint responses.
   - Use pixel helper browser extensions and server logs to validate events.

7. Extending
   - Add adapters per platform (MetaAdapter, TikTokAdapter) for mapping differences.
   - Provide a UI for visual mapping, use React/Vue only if needed and enqueue assets via WP best practices.

## v0.2.0 Notes

- Security: client-side no longer receives `server_secret`. Same-origin requests from the browser use the WP REST nonce (`X-WP-Nonce`) localized into `UP_CONFIG.nonce`.
- Queueing: events are queued via `UP_CAPI::enqueue_event()` into an option-backed queue and processed with `UP_CAPI::process_queue()` (WP-Cron). Admin endpoints `/wp-json/up/v1/process-queue` and `/wp-json/up/v1/queue/status` are available for manual control and inspection.
- Admin: settings page includes a small queue inspector and a "Process now" button.

Developer recommendations for integrating Meta/TikTok CAPI:

- Map canonical event names (Purchase, AddToCart, ViewContent) in `event_mapping` to platform-specific names as needed.
- Include the following fields in server payloads for best matching:
   - `email_hash` (sha256 lowercase trimmed)
   - `phone_hash` (if available) — not recommended unless hashed
   - `transaction_id`, `value`, `currency`
   - `contents`: array of `{ id, quantity, item_price }`
- Use server-side hashing for PII and prefer deterministic keys for idempotency (event_id, transaction_id).
- For scale, replace the option-backed queue with a dedicated DB table or external queue and run processing with WP-CLI or an external worker.
