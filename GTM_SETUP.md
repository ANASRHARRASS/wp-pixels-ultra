# GTM Setup Guide (Ultra Pixels Universal Mode)

This guide explains how to manage all client-side pixels via Google Tag Manager while the plugin handles server-side CAPI queueing and normalization.

## 1. Enable GTM Management Mode
In plugin settings set:
- GTM Container ID: `GTM-XXXXXXX`
- Let GTM manage all client pixels: **Yes**
- Enable Meta / TikTok / other platforms (server-side) as needed for CAPI dispatch.

When "manage" is Yes the plugin will not inject Meta / TikTok / Snapchat / Pinterest client base code. GTM becomes the single injection point, reducing duplication and race conditions.

## 2. Data Layer Schema
All events pushed use unified structure:
```
{
  event: 'up_event',
  event_name: 'purchase' | 'add_to_cart' | 'view_item' | ...,
  event_id: 'order_<id>' | random | deterministic hash,
  value: <number>,
  currency: 'USD',
  contents: [ { id, quantity, item_price } ],
  transaction_id: <order_id>,
  source_url: <page URL>,
  ecommerce: { // GA4 compatible helper injected server-side
    items: [ { item_id, item_name?, quantity, price } ],
    value, currency, transaction_id
  }
}
```
Additional events: whatsapp_initiate, whatsapp_click (with custom_data.whatsapp_phone), form_submit, search, scroll_depth.

## 3. Required GTM Variables
Create Data Layer Variables (DLV) with Version 2:
- `event_name` → event_name
- `event_id` → event_id
- `value` → value
- `currency` → currency
- `transaction_id` → transaction_id
- `contents` → contents
- `source_url` → source_url
- `ecommerce` → ecommerce (GA4 items access)
- `whatsapp_phone` → custom_data.whatsapp_phone (use the dot accessor via variable name: custom_data.whatsapp_phone)
- `form_name` → custom_data.form_name (optional)
- `scroll_depth` → custom_data.depth (optional)

Custom JS Variable for GA4 items (if you do not use ecommerce injected array directly):
```javascript
function(){
  var c = {{contents}} || [];
  return c.map(function(i){
    return {item_id: i.id, quantity: i.quantity, price: i.item_price};
  });
}
```

## 4. Triggers
Create one Custom Event Trigger: Event name equals `up_event`.
Then create filtered triggers (Duplicate base trigger and add condition `event_name equals <name>`):
- purchase
- add_to_cart
- begin_checkout
- view_item
- view_item_list
- whatsapp_initiate
- whatsapp_click
- form_submit
- search
- scroll_depth (optional thresholds in tag logic)

## 5. Platform Tag Mapping
Use native templates where possible:
- Meta Pixel: Event names (Purchase, AddToCart, ViewContent, InitiateCheckout, Lead, Contact). Map Value, Currency, Event ID.
- TikTok Pixel: PlaceAnOrder, AddToCart, ViewContent, InitiateCheckout, Contact, Lead, SubmitForm.
- Snapchat: PURCHASE, ADD_CART, START_CHECKOUT, VIEW_CONTENT, SIGN_UP (form/lead). Convert to uppercase if template requires.
- Pinterest: checkout (purchase), add_to_cart, page_visit.
- Google Ads: Use Conversion tag; create separate tags for key events or let GA4 send conversions.

Consider a lookup variable to translate internal `event_name` to each platform’s canonical event name when they differ.
Example Lookup Table Variable `meta_event` (Input: `event_name`):
- purchase → Purchase
- add_to_cart → AddToCart
- view_item → ViewContent
- view_item_list → ViewCategory
- begin_checkout → InitiateCheckout
- whatsapp_initiate → Contact
- whatsapp_click → Lead
- form_submit → Lead

Repeat for TikTok, Snapchat, Pinterest if needed.

## 6. Deduplication
Server-side queue sends Purchase with deterministic `event_id` (`order_<id>`). Ensure Meta & TikTok tags use this Event ID to allow Pixel + CAPI dedupe.
Other events can remain with random or hashed IDs.

## 7. Consent Management
Add Consent Initialization tag early. Gate platform tags by consent variables (e.g., `ad_storage = granted`). You can also create an extra condition on triggers requiring a DLV like `consent_granted` if you inject it yourself.

## 8. Enhanced Ecommerce (GA4)
Use GA4 Event tag for Purchase:
- Event Name: purchase
- Parameters: transaction_id, value, currency, items = {{ecommerce.items}} or items variable above.
Add separate GA4 events for add_to_cart, view_item, begin_checkout mapping parameters similarly.

## 9. Optional Multi-Dispatch Custom HTML Tag
For simpler setups you can use a single Custom HTML tag fired on `up_event` to forward to multiple pixel APIs. Recommended only during prototyping; separate tags are cleaner for debugging and consent.

## 10. Debugging
- Use GTM Preview mode: verify each pushed event object and tag firing.
- Browser extensions: Meta Pixel Helper, TikTok Pixel Helper, Pinterest Tag Helper.
- Plugin Admin: Queue length, dead-letter, logs confirm server delivery.

## 11. Adding New Platforms
1. Add enable + id fields in settings (mirroring existing ones).
2. Extend event_mapping with platform key and event_name.
3. Add adapter stub in `class-up-capi.php` (pattern of meta/tiktok/pinterest).
4. Add GTM template or custom tag mapping.

## 12. Troubleshooting
| Symptom | Cause | Fix |
|---------|-------|-----|
| Duplicate Purchase events | Missing or mismatched Event ID | Ensure both client & server use `order_<id>` and tag maps Event ID field. |
| Pixel fires but no server match | Platform disabled server-side | Enable platform in settings; verify ID present. |
| Queue grows, no sends | Cron disabled | Trigger manual process in Admin or run `wp up-capi process`. |
| Invalid JSON mapping | Syntax error in settings JSON | Fix JSON; see transient error notice. |

## 13. Production Checklist
- GTM manage flag set appropriately.
- All platform tags firing in Preview.
- Dedup IDs confirmed in Meta/TikTok diagnostics.
- Server queue processing regularly (check last processed timestamp).
- Dead-letter table empty or monitored.
- Consent flows tested in all regions.

---
Use this guide as living documentation; update when adding platforms or changing event schema.
