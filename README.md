# WooCommerce Meta Cart Handler

A WordPress/WooCommerce plugin that **auto-populates the WooCommerce cart from Meta (Facebook/Instagram) deep-link URLs** such as `/cart/?products=...`, supporting seamless checkout transfers from Meta Shops, Instagram, and Facebook.

---

## Features

* Accepts Meta/Facebook/Instagram shop links with pre-filled products
* Parses the `products` parameter and auto-adds each product to the WooCommerce cart by SKU or ID
* Supports both simple products and product variations (using SKU or direct ID)
* Robust parsing: supports alphanumeric SKUs with dashes/underscores
* Handles WooCommerce cart initialization timing safely
* Optional raw `QUERY_STRING` fallback parsing (for hosts where `$_GET` is unreliable)
* Full debug logging to WordPress debug log (can be silenced with `FBCH_VERBOSE` constant)
* Prevents duplicate reprocessing with transient loop guard
* Always redirects to a clean `/cart/` URL after handling

---

## Installation

1. **Download the plugin:**
   [Download woocommerce-meta-cart-handler.zip](woocommerce-meta-cart-handler.zip)

2. **Upload to your WordPress site:**

   * Go to **Plugins > Add New > Upload Plugin** and select the ZIP file
   * OR extract the contents to your `/wp-content/plugins/` directory

3. **Activate the plugin:**

   * In WordPress Admin, navigate to **Plugins** and activate **WooCommerce Meta Cart Handler**

---

## Usage

### How It Works

Meta Shop (Facebook/Instagram) checkout and ads may link users to URLs like:

```
https://yourstore.com/cart/?products=SM30RDM_60:1,CEYEPATCH_13798:2&cart_origin=meta_shops&fbclid=XYZ
```

When this URL is visited:

* The plugin parses the `products` parameter.
* For each item, it finds a WooCommerce product or variation by **SKU** (letters/numbers/dashes/underscores) or by numeric ID fallback.
* Each product is added to the WooCommerce cart with the specified quantity.
* User is redirected to a clean `/cart/` URL.

---

## Requirements

* WordPress 6.x+
* WooCommerce 8.x+
* PHP 7.4 or newer
* Your products' **SKU** must match the Meta/Facebook Product ID (or be synced by Facebook/Instagram integration plugin)

---

## Debugging

* Enable debugging in `wp-config.php`:

  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  ```
* For quieter logs, set:

  ```php
  define('FBCH_VERBOSE', false);
  ```
* Check logs in `wp-content/debug.log` for plugin activity.

---

## Security

* The handler only allows products present in WooCommerce and mapped by SKU/ID.
* No sensitive data is exposed via the cart handler.
* Transient guard prevents accidental reprocessing or redirect loops.

---

## FAQ

### Q: Why does my cart sometimes show empty after redirect?

A: Previous versions ran on `wp_loaded`, which could fire before WooCommerce initialized the cart. Fixed by running on `template_redirect` with `is_cart()` check.

### Q: My SKUs aren’t numeric Facebook IDs, will it work?

A: Yes. This version supports alphanumeric SKUs with dashes/underscores. If no SKU match is found, numeric IDs are attempted as direct product/variation IDs.

### Q: Does this work with Instagram links?

A: Yes. Both Instagram and Facebook deep links (Meta Shops) are supported.

### Q: How do I test if the plugin is active?

A: Visit `/cart/?fbch=ping` while logged in as admin. You’ll see a one-time admin notice and a log line confirming the plugin is active.

---

## Changelog

### 1.5.3 (2025-09-04)

* Hardened against cart not being ready at `template_redirect`
* Guarded against fatal errors when calling `add_to_cart`
* Logging now only triggers when `?products=` exists (to reduce overhead)
* Added optional `FBCH_VERBOSE` constant to control verbosity
* Added `/cart/?fbch=ping` health check

### 1.5 (2025-09-04)

* Changed hook from `wp_loaded` to `template_redirect` for reliable WooCommerce cart/session availability
* Broadened SKU support: alphanumeric with dashes/underscores
* Added raw `QUERY_STRING` fallback parsing for edge cases where `$_GET` is empty
* Added transient guard to prevent reprocessing/redirect loops
* Improved logging (request URI, raw query string, parsed items)
* Added support for numeric IDs if no SKU match is found
* Ensured clean cart redirect after handling

### 1.4

* Initial working version with `wp_loaded` hook and numeric-only SKU support
* Basic logging of cart URLs and product parameter

---

## License

MIT License

---

## Contributing

PRs and issues welcome.
See [github.com/blahpunk/woo-meta-cart-handler](https://github.com/blahpunk/woo-meta-cart-handler) for source.

---

## Author

BlahPunk / Eric Zeigenbein
[github.com/blahpunk](https://github.com/blahpunk)
