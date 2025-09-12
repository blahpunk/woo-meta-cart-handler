# WooCommerce Meta Cart Handler

A WordPress/WooCommerce plugin that **auto-populates the WooCommerce cart from Meta (Facebook/Instagram) deep-link URLs** such as `/cart/?products=...`, supporting seamless checkout transfers from Meta Shops, Instagram, and Facebook.

---

## Features

* Accepts Meta/Facebook/Instagram shop links with pre-filled products.
* Parses the `products` parameter and auto-adds each product to the WooCommerce cart by **ID or SKU**.
* Supports both simple products and **product variations** (by numeric ID or SKU).
* Robust parsing: tokens may include parent ID/SKU + variation ID/SKU + quantity.
* Optional `fbch=ping` health check confirms plugin is active.
* Handles WooCommerce cart initialization timing safely.
* Raw `QUERY_STRING` fallback parsing for hosts where `$_GET` is unreliable.
* Verbose debug logging to WordPress debug log (controlled by `FBCH_VERBOSE`).
* Prevents duplicate reprocessing with transient loop guard.
* Always redirects to a clean `/cart/` URL after handling.

---

## Installation

1. **Download the plugin:**
   [Download woocommerce-meta-cart-handler.zip](woocommerce-meta-cart-handler.zip)

2. **Upload to your WordPress site:**

   * Go to **Plugins > Add New > Upload Plugin** and select the ZIP file, or
   * Extract the contents to your `/wp-content/plugins/` directory.

3. **Activate the plugin:**

   * In WordPress Admin, navigate to **Plugins** and activate **WooCommerce Meta Cart Handler**.

---

## Usage

### How It Works

Meta Shop (Facebook/Instagram) checkout and ads may link users to URLs like:

```text
https://yourstore.com/cart/?products=123_456:2,SKU123:1
```

When this URL is visited:

* The plugin parses the `products` parameter.
* Each token follows the format:

  * `token[_variationToken][:qty]`
  * `token` and `variationToken` may be numeric IDs or SKUs.
  * `qty` defaults to 1 if not specified.
* The handler resolves the correct WooCommerce product/variation.
* The item(s) are added to the cart in the requested quantity.
* User is redirected to a clean `/cart/` URL.

### Examples

* By numeric product ID:

  ```
  /cart/?products=123:1
  ```
* By SKU:

  ```
  /cart/?products=SKU123:2
  ```
* By parent + variation ID:

  ```
  /cart/?products=123_456:1
  ```
* By parent + variation SKU:

  ```
  /cart/?products=SKU_PARENT_SKU_VARIANT:1
  ```

---

## Requirements

* WordPress 6.x+
* WooCommerce 8.x+
* PHP 7.4 or newer
* Products must have IDs or SKUs mapped to Meta/Facebook (via catalog sync or manual entry).

---

## Debugging

Enable logging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('FBCH_VERBOSE', true); // optional, adds detailed logging
```

Check logs in `wp-content/debug.log`. Example log lines:

```
FBCH: processing products param -> 123_456:1
FBCH: added -> product 123 / variation 456 qty 1
```

---

## FAQ

**Q: How do I test if the plugin is active?**
A: Visit `/cart/?fbch=ping`. You’ll see a success notice on the cart page and a log entry confirming activity.

**Q: Why does my cart show empty after redirect?**
A: Ensure caching is disabled for `/cart/`. Also confirm that the product/variation is published and purchasable.

**Q: My SKUs aren’t numeric Facebook IDs. Will it work?**
A: Yes. Both alphanumeric SKUs and numeric IDs are supported.

**Q: Can I immediately retry the same link?**
A: No, the plugin prevents duplicate reprocessing for 60 seconds. Change the quantity or wait before retesting.

---

## Changelog

### 1.6.0 (2025-09-12)

* Added explicit support for variation IDs and SKUs in `products=` tokens.
* Added working `/cart/?fbch=ping` health check with notice + logging.
* Improved debug logging for parse failures and add-to-cart results.

### 1.5.3 (2025-09-04)

* Hardened against cart not being ready at `template_redirect`.
* Guarded against fatal errors when calling `add_to_cart`.
* Logging now only triggers when `?products=` exists.
* Added optional `FBCH_VERBOSE` constant.
* Added `/cart/?fbch=ping` health check.

### 1.5 (2025-09-04)

* Changed hook from `wp_loaded` to `template_redirect` for reliable WooCommerce cart/session availability.
* Broadened SKU support: alphanumeric with dashes/underscores.
* Added raw `QUERY_STRING` fallback parsing.
* Added transient guard to prevent reprocessing/redirect loops.
* Improved logging (request URI, raw query string, parsed items).
* Added support for numeric IDs if no SKU match is found.
* Ensured clean cart redirect after handling.

### 1.4

* Initial version with `wp_loaded` hook and numeric-only SKU support.

---

## License

MIT License

---

## Contributing

Pull requests and issues welcome.
[github.com/blahpunk/woo-meta-cart-handler](https://github.com/blahpunk/woo-meta-cart-handler)

---

## Author

BlahPunk / Eric Zeigenbein
[github.com/blahpunk](https://github.com/blahpunk)
