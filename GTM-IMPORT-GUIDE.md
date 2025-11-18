# GTM Container Import Guide - Production Ready

This guide explains how to import and use the production-ready GTM container templates included with Ultra Pixels plugin.

## Available GTM Container Templates

### 1. Ultra Pixels GTM Container (Production)
**File:** `gtm-templates/ultra-pixels-gtm-container.json`

This is the **complete, production-ready** GTM container that includes:
- ✅ **6 Pre-configured Tags**: Meta Pixel (PageView + Dynamic), TikTok Pixel (PageView + Dynamic), Server-Side Event Forward, GA4 Enhanced Ecommerce
- ✅ **5 Custom Triggers**: All Pages, Custom Events, Purchase, Form Submission, WhatsApp Click
- ✅ **13 Data Layer Variables**: All necessary variables for event tracking (event_name, event_id, value, currency, content_ids, custom_data, user_data, etc.)
- ✅ **Full Browser Compatibility**: Tested across Chrome, Firefox, Safari, Edge
- ✅ **Production-Optimized**: Includes error handling, consent management, deduplication support

**Use this for:** Production deployments, complete tracking setup, enterprise implementations

### 2. Ultra Pixels Starter Template
**File:** `gtm-container-up-template.json`

A minimal starter template for custom implementations:
- Basic structure with 2 sample tags
- 2 basic triggers
- 4 essential variables
- Designed for developers who want to build their own custom setup

**Use this for:** Custom development, learning, minimal implementations

## Import Instructions

### Step 1: Access GTM Admin
1. Log in to your Google Tag Manager account
2. Select your container (or create a new one)
3. Go to **Admin** → **Import Container**

### Step 2: Choose Template
- **For most users:** Import `gtm-templates/ultra-pixels-gtm-container.json`
- **For custom builds:** Import `gtm-container-up-template.json`

### Step 3: Import Settings
1. Click **Choose container file** and select the JSON file
2. For **Choose a workspace**: Select "New" (recommended) or existing workspace
3. For **Import option**: Choose "Merge" (keeps existing tags) or "Overwrite" (replaces all)
4. Click **Confirm**

### Step 4: Configure Variables
After import, update these constant variables with your actual IDs:

#### Required Variables (in GTM Variables section):
- **Meta Pixel ID**: Your Facebook Pixel ID (e.g., `123456789012345`)
- **TikTok Pixel ID**: Your TikTok Pixel ID (e.g., `ABCD1234EFGH5678`)
- **GA4 Measurement ID**: Your Google Analytics 4 ID (e.g., `G-XXXXXXXXXX`)

### Step 5: Configure Plugin Settings
In WordPress Admin → Ultra Pixels → Settings:

1. **GTM Container ID**: Enter your GTM container ID (e.g., `GTM-XXXXXX`)
2. **Let GTM manage all client pixels**: Set to **Yes** (recommended)
3. **Enable platforms for server-side CAPI**: Enable Meta, TikTok, etc. as needed
4. **Configure event mapping JSON**: Leave default or customize per your needs

## Production Checklist

Before going live, verify:

- [ ] All pixel IDs configured in GTM variables
- [ ] Plugin settings match GTM configuration
- [ ] Test in GTM Preview mode
- [ ] Verify events in browser console (dataLayer)
- [ ] Check server-side queue in plugin admin
- [ ] Confirm deduplication with event_id
- [ ] Test consent management (if used)
- [ ] Verify all triggers fire correctly
- [ ] Check Meta Events Manager for event reception
- [ ] Verify TikTok Events Manager for event reception

## Browser Compatibility

Both templates are tested and compatible with:
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile, Samsung Internet)

## JSON Format Standards

All GTM container JSON files follow Google Tag Manager's official format:
- `exportFormatVersion: 2` (latest GTM format)
- Proper timestamp in `exportTime`
- Valid container structure with metadata
- Standards-compliant tag, trigger, and variable definitions

## Validation

You can validate the JSON files using:

```bash
# Using Python
python3 -m json.tool gtm-templates/ultra-pixels-gtm-container.json

# Using Node.js
node -e "console.log(JSON.stringify(require('./gtm-templates/ultra-pixels-gtm-container.json'), null, 2))"
```

Both files pass validation and are ready for import into any GTM container.

## Troubleshooting Import Issues

### "Invalid JSON format"
- Ensure you're using the exact file from the plugin
- Don't edit the JSON manually unless you're familiar with GTM format
- Try re-downloading from the plugin repository

### "Container version mismatch"
- Our templates use GTM export format version 2 (latest)
- If your GTM account is very old, you may need to update it first

### "Variables not appearing"
- After import, refresh the GTM interface
- Check the Variables tab - all should be listed under "User-Defined Variables"

### "Tags not firing"
- Verify triggers are enabled
- Use GTM Preview mode to debug
- Check browser console for dataLayer events

## Advanced Configuration

### Custom Event Mapping
Edit the event mapping JSON in plugin settings to customize which events go to which platforms:

```json
{
  "purchase": {
    "meta": {"event_name": "Purchase", "include_user_data": true},
    "tiktok": {"event_name": "PlaceAnOrder", "include_user_data": true}
  },
  "add_to_cart": {
    "meta": {"event_name": "AddToCart"},
    "tiktok": {"event_name": "AddToCart"}
  }
}
```

### Enhanced Debugging
For development/testing, enable debug mode in the browser console:

```javascript
// Enable GTM debug
window.dataLayer.push({'gtm.debug': true});

// Monitor all UP events
window.dataLayer.push(function() {
  this.addEventListener('event', function(evt) {
    if (evt === 'up_event') {
      console.log('UP Event:', this);
    }
  });
});
```

## Support

For issues with:
- **GTM import**: Check GTM documentation or this guide
- **Plugin configuration**: See main README.md and GTM_SETUP.md
- **Event tracking**: Enable debug mode and check browser console
- **Server-side queue**: Check Ultra Pixels admin dashboard

## Version Compatibility

- GTM Container Format: Version 2 (latest)
- WordPress: 5.8+
- WooCommerce: 5.0+ (for ecommerce tracking)
- Modern browsers with ES5+ support

Last updated: November 2025
Compatible with: Ultra Pixels Plugin v0.4.2+
