# GTM Templates for Ultra Pixels Plugin

This directory contains Google Tag Manager container templates and configuration files for seamless integration with Meta, TikTok, and other advertising platforms.

## Quick Start

1. **Import the Container Template**
   - Go to GTM Admin → Import Container
   - Upload `ultra-pixels-gtm-container.json`
   - Choose "Merge" with your existing container or create new workspace
   - Preview and publish

2. **Configure Server-Side CAPI**
   - Set up your server-side GTM container (optional but recommended)
   - Use the `server-side-template.json` for CAPI integration
   - Configure your WordPress plugin CAPI endpoint in GTM variables

3. **Platform-Specific Setup**
   - Meta (Facebook): Use `meta-capi-tag-template.tpl`
   - TikTok: Use `tiktok-events-tag-template.tpl`
   - Google Ads: Use `google-ads-tag-template.tpl`

## Container Templates

### `ultra-pixels-gtm-container.json`
Complete GTM container with pre-configured:
- **Triggers**: Page View, Custom Events, WooCommerce Events, Form Submissions
- **Variables**: Event Data, User Properties, Enhanced Ecommerce
- **Tags**: Meta Pixel, TikTok Pixel, Custom HTML for dataLayer
- **Built-in Templates**: For common tracking scenarios

### `server-side-template.json`
Server-side GTM container configuration for:
- CAPI event forwarding
- Event deduplication
- Enhanced measurement
- Privacy-compliant tracking

## Tag Templates

### Meta Conversions API Tag
File: `meta-capi-tag-template.tpl`

Supports all Meta standard events:
- PageView, ViewContent, Search, AddToCart
- InitiateCheckout, AddPaymentInfo, Purchase
- Lead, CompleteRegistration, Contact
- Custom events with custom parameters

**Setup:**
1. Import template to GTM
2. Create new tag using "Meta Conversions API" template
3. Configure Pixel ID and Access Token variables
4. Set up triggers for desired events

### TikTok Events API Tag
File: `tiktok-events-tag-template.tpl`

Supports TikTok standard events:
- PageView, ViewContent, ClickButton
- AddToCart, InitiateCheckout, PlaceAnOrder
- Contact, SubmitForm, Download
- Custom events

**Setup:**
1. Import template to GTM
2. Create tag using "TikTok Events API" template
3. Configure Pixel ID and Access Token
4. Map dataLayer variables to event parameters

### Google Ads Conversion Tag
File: `google-ads-tag-template.tpl`

Supports:
- Conversion tracking
- Dynamic remarketing
- Enhanced conversions
- Offline conversion imports

## DataLayer Event Schema

The plugin pushes events to `dataLayer` using this standardized schema:

```javascript
{
  event: 'up_event',              // GTM event trigger
  event_name: 'Purchase',         // Business event name
  event_id: 'order_12345',        // Unique event identifier
  event_time: 1699900000,         // Unix timestamp
  source_url: 'https://...',      // Page URL
  user_data: {
    email_hash: 'sha256...',      // Hashed email
    phone_hash: 'sha256...',      // Hashed phone
    // ... other user data
  },
  custom_data: {
    value: 99.99,                 // Transaction value
    currency: 'USD',              // Currency code
    content_ids: ['123', '456'],  // Product IDs
    // ... platform-specific data
  },
  ecommerce: {                    // Enhanced Ecommerce (GA4 format)
    transaction_id: '12345',
    value: 99.99,
    currency: 'USD',
    items: [
      {
        item_id: '123',
        item_name: 'Product Name',
        price: 49.99,
        quantity: 1
      }
    ]
  }
}
```

## Event Mapping

| Internal Event | Meta Event | TikTok Event | Google Ads |
|---------------|-----------|--------------|------------|
| PageView | PageView | PageView | page_view |
| view_item | ViewContent | ViewContent | view_item |
| add_to_cart | AddToCart | AddToCart | add_to_cart |
| begin_checkout | InitiateCheckout | InitiateCheckout | begin_checkout |
| purchase | Purchase | PlaceAnOrder | purchase |
| whatsapp_initiate | Contact | Contact | generate_lead |
| form_submit | Lead | SubmitForm | generate_lead |
| video_play | - | - | engagement |

## Server-Side Integration

### WordPress Plugin Configuration

```php
// In wp-config.php or plugin settings
define( 'UP_GTM_SERVER_URL', 'https://your-server-gtm.com' );
define( 'UP_CAPI_ENDPOINT', 'https://your-api-endpoint.com/events' );
```

### GTM Server Container Variables

Required variables in your server-side GTM:
- `metaPixelId` - Meta Pixel ID
- `metaAccessToken` - Meta Conversions API Access Token
- `tiktokPixelId` - TikTok Pixel ID  
- `tiktokAccessToken` - TikTok Events API Access Token
- `wpIngestEndpoint` - WordPress plugin ingest URL

## Best Practices

### 1. Event Deduplication
Use consistent `event_id` across client and server to prevent double counting:
```javascript
event_id: 'order_' + transactionId  // For purchases
event_id: 'ev_' + timestamp + '_' + random  // For other events
```

### 2. Privacy & Consent Management
- Integrate with consent management platform (CMP)
- Only fire tags when user has given consent
- Use GTM's built-in Consent Mode v2

### 3. Testing & Debugging
- Use GTM Preview mode
- Check Meta Events Manager and TikTok Events Manager
- Monitor WordPress plugin queue in admin dashboard
- Validate event_id consistency between client and server

### 4. Performance Optimization
- Use GTM's built-in tag sequencing
- Implement timeout rules for slow-loading tags
- Consider server-side GTM for improved performance

### 5. Enhanced Measurement
- Enable automatic scroll tracking
- Track file downloads
- Monitor video engagement
- Track outbound link clicks

## Elementor Integration

For Elementor landing pages:
1. Use the data attribute approach for buttons/links
2. Add `data-up-event` attribute to Elementor widgets via Advanced → Attributes
3. Use popup close event: `data-up-event="popup_close"`

Example:
```html
<a href="#" data-up-event="cta_click" data-up-payload='{"button_name":"hero_cta","campaign":"summer_sale"}'>
  Get Started
</a>
```

## Troubleshooting

### Events not showing in GTM Preview?
- Check browser console for `dataLayer.push` calls
- Verify GTM container is loaded (check page source)
- Ensure GTM container ID is correct in plugin settings

### CAPI events not reaching Meta/TikTok?
- Check WordPress admin → Ultra Pixels → Queue Status
- Verify Access Tokens are valid
- Check server error logs
- Use "Process now" button to manually trigger queue

### Duplicate events?
- Ensure `event_id` is consistent between client and server
- Check that both GTM pixel tags and server-side tags aren't firing for same event
- Review tag firing triggers in GTM Preview

## Additional Resources

- [Meta Conversions API Documentation](https://developers.facebook.com/docs/marketing-api/conversions-api)
- [TikTok Events API Documentation](https://ads.tiktok.com/marketing_api/docs?id=1701890979375106)
- [Google Tag Manager Documentation](https://support.google.com/tagmanager)
- [GA4 Enhanced Ecommerce Events](https://developers.google.com/analytics/devguides/collection/ga4/ecommerce)

## Version History

- v1.0 - Initial GTM templates with Meta and TikTok support
- v1.1 - Added Google Ads and Enhanced Ecommerce support
- v1.2 - Server-side GTM template and Elementor integration

---

**Need help?** Check the plugin's main README.md or submit an issue on GitHub.
