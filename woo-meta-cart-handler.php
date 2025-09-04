<?php
/*
Plugin Name: WooCommerce Facebook Cart Handler with URL Logger (hardened)
Description: Logs every cart URL request and handles Meta/Facebook deep links. More robust parsing, broader SKU formats, and safer Woo/cart initialization.
Version: 1.5
Author: BlahPunk
*/

if (!defined('ABSPATH')) exit;

add_action('template_redirect', function () {
    // Only act on the cart page (works for pretty and non-pretty permalinks)
    if (!function_exists('is_cart') || !is_cart()) {
        return;
    }

    // Always log the incoming request for debugging
    if (isset($_SERVER['REQUEST_URI'])) {
        error_log('FB Cart Handler: URL accessed: ' . $_SERVER['REQUEST_URI']);
    }
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('FB Cart Handler: Raw QUERY_STRING: ' . (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '(none)'));
        error_log('FB Cart Handler: Raw $_GET: ' . json_encode($_GET));
    }

    // Ensure WooCommerce is available and the cart is ready
    if (!function_exists('WC') || !WC()->cart) {
        error_log('FB Cart Handler: WC() or cart not ready; aborting.');
        return;
    }

    // Support reading the products spec from $_GET or the raw query string (belt-and-suspenders)
    $products_param = '';
    if (isset($_GET['products'])) {
        $products_param = $_GET['products'];
    } else {
        // Fallback: parse manually from QUERY_STRING if some environment strips $_GET keys
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $qs);
            if (isset($qs['products'])) {
                $products_param = $qs['products'];
            }
        }
    }

    if ($products_param === '' || $products_param === null) {
        // Nothing to do
        return;
    }

    // Prevent reprocessing if we already handled this exact request (avoid loops on some caches)
    $hash = md5($products_param);
    $flag_key = 'fb_cart_handled_' . $hash;
    if (get_transient($flag_key)) {
        return;
    }

    // products string can be URL-encoded and comma-separated, e.g.:
    //   SM30RDM_60:1,CEYEPATCH_13798:2
    // where format is: <SKU or ID>_<VARIANTID>:<QTY>
    $decoded = urldecode($products_param);
    $decoded = trim($decoded);
    $items = array_filter(array_map('trim', explode(',', $decoded)));

    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('FB Cart Handler: Decoded products param: ' . $decoded);
    }

    // Accept SKUs with letters/numbers/dash/underscore; variant and qty numeric
    $pattern = '/^([A-Za-z0-9\-_]+)_(\d+):(\d+)$/';

    $added_any = false;

    foreach ($items as $item) {
        if (!preg_match($pattern, $item, $m)) {
            error_log("FB Cart Handler: Could not parse item string: {$item}");
            continue;
        }

        $sku_or_id   = $m[1];
        $variant_id  = (int) $m[2];
        $qty         = max(1, (int) $m[3]);

        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("FB Cart Handler: Parsed -> SKU/ID: {$sku_or_id} | Variant: {$variant_id} | Qty: {$qty}");
        }

        $product_id = null;
        $variation_id = null;

        // Try to find by exact SKU on product or variation first
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
        } else {
            // If SKU lookup failed and the token is numeric, treat it as a direct product/variation ID
            if (ctype_digit($sku_or_id)) {
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
        }

        if (!$product_id && !$variation_id) {
            error_log("FB Cart Handler: No Woo product found for SKU/ID: {$sku_or_id}");
            continue;
        }

        // If a variation is known, pass it; otherwise add the simple/parent product
        $added = false;
        if ($variation_id) {
            $added = WC()->cart->add_to_cart($product_id, $qty, $variation_id);
        } else {
            $added = WC()->cart->add_to_cart($product_id, $qty);
        }

        if ($added) {
            $added_any = true;
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log("FB Cart Handler: Added to cart -> product {$product_id}" . ($variation_id ? " / variation {$variation_id}" : '') . " qty {$qty}");
            }
        } else {
            error_log("FB Cart Handler: add_to_cart failed for product {$product_id}" . ($variation_id ? " / variation {$variation_id}" : '') . " qty {$qty}");
        }
    }

    // Mark handled for a short window to avoid repeat processing
    set_transient($flag_key, 1, 60);

    // Always redirect to clean cart URL (no query string) after handling
    // If nothing added, still redirect so the user lands on cart cleanly
    wp_safe_redirect(wc_get_cart_url());
    exit;
});
