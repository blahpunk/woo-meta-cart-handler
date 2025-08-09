<?php
/*
Plugin Name: WooCommerce Facebook Cart Handler with URL Logger (wp_loaded fix)
Description: Logs every URL request to the cart page and handles Meta/Facebook deep links. Corrected for session timing.
Version: 1.4
Author: BlahPunk
*/

add_action('wp_loaded', function() {
    // Log all direct cart URL requests
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/cart') !== false) {
        error_log("FB Cart Handler: URL accessed: " . $_SERVER['REQUEST_URI']);
    }

    if (!function_exists('WC') || !isset($_GET['products'])) return;

    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log("FB Cart Handler: Incoming products param: " . $_GET['products']);
    }

    $items = explode(',', $_GET['products']);
    foreach ($items as $item) {
        // Format: META_PRODUCTID_VARIANTID:QUANTITY
        if (preg_match('/^(\d+)_\d+:(\d+)$/', $item, $m)) {
            $facebook_product_id = $m[1];
            $qty = $m[2];

            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log("FB Cart Handler: Processing FB Product ID: $facebook_product_id, Quantity: $qty");
            }

            // Search for WooCommerce product by SKU (matches Facebook Product ID)
            $args = [
                'post_type'  => ['product', 'product_variation'],
                'meta_query' => [
                    [
                        'key'   => '_sku',
                        'value' => $facebook_product_id,
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => 1
            ];
            $products = get_posts($args);

            if (!empty($products)) {
                $wc_product_id = $products[0]->ID;
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log("FB Cart Handler: Adding WooCommerce Product ID: $wc_product_id (for SKU/FB ID: $facebook_product_id) Qty: $qty");
                }
                WC()->cart->add_to_cart($wc_product_id, $qty);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log("FB Cart Handler: No WooCommerce product found for SKU/FB ID: $facebook_product_id");
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log("FB Cart Handler: Could not parse item string: $item");
            }
        }
    }

    // Redirect to cart page (without query string)
    if (isset($_GET['products'])) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("FB Cart Handler: Redirecting to cart page...");
        }
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
});
