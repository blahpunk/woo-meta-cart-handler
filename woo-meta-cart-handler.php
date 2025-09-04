<?php
/*
Plugin Name: WooCommerce Meta Cart Handler (stable)
Description: Auto-adds products to the WooCommerce cart from Meta (Facebook/Instagram) deep links. Hardened against early cart init, fatal errors, and noisy logging.
Version: 1.5.3
Author: BlahPunk
*/

if (!defined('ABSPATH')) exit;

// Optional: set true to log details when processing ?products=
if (!defined('FBCH_VERBOSE')) {
    define('FBCH_VERBOSE', false);
}

/**
 * Process deep links on the cart page after WooCommerce/cart are initialized.
 * Runs only on front-end GET requests to the cart.
 */
add_action('template_redirect', function () {
    // Front-end only
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    // Only act on GET requests
    if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) !== 'GET') {
        return;
    }

    // Only act on the cart page
    if (!function_exists('is_cart') || !is_cart()) {
        return;
    }

    // WooCommerce must be ready and cart object available
    if (!function_exists('WC') || !WC() || !isset(WC()->cart) || !WC()->cart || !is_object(WC()->cart)) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('FBCH: WC cart not ready at template_redirect; aborting safely.');
        }
        return;
    }

    // Read products param from $_GET or raw query string
    $products_param = '';
    if (isset($_GET['products'])) {
        $products_param = (string) $_GET['products'];
    } elseif (!empty($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $qs);
        if (isset($qs['products'])) {
            $products_param = (string) $qs['products'];
        }
    }

    if ($products_param === '' || $products_param === null) {
        return; // no work to do
    }

    // Prevent reprocessing loops if edge/cache replays the same URL
    $hash = md5($products_param);
    $flag_key = 'fb_cart_handled_' . $hash;
    if (get_transient($flag_key)) {
        return;
    }

    // Decode and split: <SKU-or-ID>_<VARIANTID>:<QTY>, comma-separated
    $decoded = trim(urldecode($products_param));
    $items   = array_filter(array_map('trim', explode(',', $decoded)));

    if (FBCH_VERBOSE && defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('FBCH: processing products param -> ' . $decoded);
    }

    // Accept SKUs with letters/numbers/dash/underscore; variant & qty must be numeric
    $pattern = '/^([A-Za-z0-9\-_]+)_(\d+):(\d+)$/';

    foreach ($items as $item) {
        if (!preg_match($pattern, $item, $m)) {
            if (FBCH_VERBOSE && defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FBCH: parse fail -> ' . $item);
            }
            continue;
        }

        $sku_or_id  = $m[1];
        $variant_id = (int) $m[2]; // preserved for future mapping
        $qty        = max(1, (int) $m[3]);

        $product_id   = null;
        $variation_id = null;

        // 1) Try Woo's SKU lookup (fast/cached) when token isn't purely numeric
        if (!ctype_digit($sku_or_id) && function_exists('wc_get_product_id_by_sku')) {
            $maybe = wc_get_product_id_by_sku($sku_or_id);
            if ($maybe) {
                if ('product_variation' === get_post_type($maybe)) {
                    $variation_id = (int) $maybe;
                    $parent = wp_get_post_parent_id($variation_id);
                    if ($parent) $product_id = (int) $parent;
                } else {
                    $product_id = (int) $maybe;
                }
            }
        }

        // 2) Fallback exact meta query by _sku
        if (!$product_id && !$variation_id) {
            $posts = get_posts([
                'post_type'      => ['product', 'product_variation'],
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'     => '_sku',
                        'value'   => $sku_or_id,
                        'compare' => '='
                    ]
                ]
            ]);
            if (!empty($posts)) {
                $found_id = (int) $posts[0];
                if ('product_variation' === get_post_type($found_id)) {
                    $variation_id = $found_id;
                    $parent = wp_get_post_parent_id($found_id);
                    if ($parent) $product_id = (int) $parent;
                } else {
                    $product_id = $found_id;
                }
            }
        }

        // 3) Final fallback: if token is numeric, treat as direct product/variation ID
        if (!$product_id && !$variation_id && ctype_digit($sku_or_id)) {
            $maybe_id = (int) $sku_or_id;
            if ($maybe_id > 0 && get_post_status($maybe_id)) {
                if ('product_variation' === get_post_type($maybe_id)) {
                    $variation_id = $maybe_id;
                    $parent = wp_get_post_parent_id($maybe_id);
                    if ($parent) $product_id = (int) $parent;
                } else {
                    $product_id = $maybe_id;
                }
            }
        }

        if (!$product_id && !$variation_id) {
            if (FBCH_VERBOSE && defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FBCH: not found -> ' . $sku_or_id);
            }
            continue;
        }

        // Double-check cart object and method availability to avoid fatals
        if (!isset(WC()->cart) || !WC()->cart || !is_object(WC()->cart) || !is_callable([WC()->cart, 'add_to_cart'])) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FBCH: cart object/method unavailable; aborting add_to_cart.');
            }
            continue;
        }

        // Add to cart (no attribute mapping by variant_id in this version)
        $added = $variation_id
            ? WC()->cart->add_to_cart($product_id, $qty, $variation_id)
            : WC()->cart->add_to_cart($product_id, $qty);

        if (FBCH_VERBOSE && defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if ($added) {
                error_log('FBCH: added -> product ' . $product_id . ($variation_id ? " / variation {$variation_id}" : '') . " qty {$qty}");
            } else {
                error_log('FBCH: add_to_cart failed -> product ' . $product_id . ($variation_id ? " / variation {$variation_id}" : '') . " qty {$qty}");
            }
        }
    }

    // Mark handled for this exact param for 60 seconds (prevents repeat processing/redirects)
    set_transient($flag_key, 1, 60);

    // Redirect to clean cart URL (strip query string) whether or not items were added
    wp_safe_redirect(wc_get_cart_url());
    exit;
});
