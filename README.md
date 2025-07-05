# WooCommerce Facebook Cart Handler

A WordPress/WooCommerce plugin that **auto-populates the WooCommerce cart from Meta (Facebook/Instagram) deep-link URLs** such as `/cart/?products=...`, supporting seamless checkout transfers from Meta Shops, Instagram, and Facebook.

---

## Features

* Accepts Meta/Facebook/Instagram shop links with pre-filled products
* Parses the `products` parameter and auto-adds each product to the WooCommerce cart by SKU
* Supports both simple products and product variations (using SKU)
* Full debug logging to WordPress debug log for troubleshooting and analytics

---

## Installation

1. **Download the plugin:**
   [Download woocommerce-facebook-cart-handler.zip](woocommerce-facebook-cart-handler.zip)

2. **Upload to your WordPress site:**

   * Go to **Plugins > Add New > Upload Plugin** and select the ZIP file
   * OR extract the contents to your `/wp-content/plugins/` directory

3. **Activate the plugin:**

   * In WordPress Admin, navigate to **Plugins** and activate **WooCommerce Facebook Cart Handler**

---

## Usage

### How It Works

Meta Shop (Facebook/Instagram) checkout and ads may link users to URLs like:

```
https://yourstore.com/cart/?products=10082880026543902930_3336:1,10133825423895016647_2910:1&cart_origin=meta_shops&fbclid=XYZ
```

When this URL is visited:

* The plugin parses the `products` parameter.
* For each item, it finds a WooCommerce product or variation by **SKU** matching the Facebook Product ID.
* Each product is added to the WooCommerce cart with the specified quantity.
* User is redirected to a clean `/cart/` URL.

---

## Requirements

* WordPress 6.x+
* WooCommerce 8.x+
* PHP 7.4 or newer
* Your products' **SKU** must match the Meta/Facebook Product ID (or be synced by Facebook plugin)

---

## Debugging

* Enable debugging in `wp-config.php`:

  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  ```
* Check logs in `wp-content/debug.log` for plugin activity and troubleshooting.

---

## Security

* The handler only allows products present in WooCommerce and mapped by SKU.
* No sensitive data is exposed via the cart handler.

---

## FAQ

### Q: Why does my cart sometimes show empty after redirect?

A: Make sure your plugin runs on the `wp_loaded` action (included in this version). Early session hooks will fail to persist cart data.

### Q: My SKUs aren’t Facebook IDs, will it work?

A: No. You must map your products’ SKUs to the Meta Product IDs (sync using Facebook for WooCommerce or update manually in WooCommerce).

---

## License

MIT License

---

## Contributing

PRs and issues welcome.
See [github.com/blahpunk/woo-facebook-cart-handler](https://github.com/blahpunk/woo-facebook-cart-handler) for source.

---

## Author

BlahPunk / Eric Zeigenbein
[github.com/blahpunk](https://github.com/blahpunk)
