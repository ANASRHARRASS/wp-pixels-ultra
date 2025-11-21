### v0.4.4 - Provider-based server forwarding and security fixes
- Adds provider configuration for server-side forwarding of requests to external APIs (news, shipping rates, etc.).
- Secrets are resolved server-side from environment variables or wp-config.php constants (preferred). Admin option fallback supported but not recommended.
- Client forwarder no longer exposes API keys. Client sends provider_id + params to /wp-json/up/v1/ingest which forwards using server-side stored credentials.
- Per-provider and per-IP rate limiting and short caching to protect external API quotas.
- Improvements to PII handling (client-side stripping + optional server-side hashing) and other security fixes.
