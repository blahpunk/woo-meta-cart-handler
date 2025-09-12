<?php
/*
Plugin Name: WooCommerce Meta Cart Handler
Description: Auto-adds products to the WooCommerce cart from Meta/Facebook deep links using a `products=` query param. Supports IDs or SKUs, optional variation IDs/SKUs, quantities, and multiple items.
Version: 1.6.0
Author: BlahPunk
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Optional verbose logging. Enable in wp-config.php with: define('FBCH_VERBOSE', true);
if ( ! defined( 'FBCH_VERBOSE' ) ) {
    define( 'FBCH_VERBOSE', false );
}

add_action('template_redirect', function () {
    if ( ! function_exists( 'WC' ) ) { return; }

    // Ensure we're on the WooCommerce cart page (avoid interfering elsewhere)
    if ( ! is_cart() ) {
        return;
    }

    // Simple health check for README: /cart/?fbch=ping
    if ( isset($_GET['fbch']) && $_GET['fbch'] === 'ping' ) {
        if ( function_exists('wc_add_notice') ) {
            wc_add_notice( __('Meta Cart Handler active.'), 'success' );
        }
        if ( FBCH_VERBOSE ) {
            error_log('FBCH: ping received - handler active on cart page');
        }
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    // Normalize and retrieve products param from superglobals or parsed query string
    $products_param = '';
    if ( isset($_GET['products']) ) {
        $products_param = (string) $_GET['products'];
    } elseif ( ! empty($_SERVER['QUERY_STRING']) ) {
        parse_str($_SERVER['QUERY_STRING'], $qs);
        if ( isset($qs['products']) ) {
            $products_param = (string) $qs['products'];
        }
    }

    if ( $products_param === '' || $products_param === null ) {
        return; // nothing to do
    }

    // Prevent re-processing loops (e.g., CDN replays same URL)
    $flag_key = 'fb_cart_handled_' . md5($products_param);
    if ( get_transient($flag_key) ) {
        if ( FBCH_VERBOSE ) {
            error_log('FBCH: skip (recently handled) -> ' . $products_param);
        }
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    if ( FBCH_VERBOSE ) {
        error_log('FBCH: processing products param -> ' . $products_param);
    }

    $items = array_filter(array_map('trim', explode(',', $products_param)));
    if ( empty($items) ) {
        if ( FBCH_VERBOSE ) {
            error_log('FBCH: parse fail (no items) for -> ' . $products_param);
        }
        set_transient($flag_key, 1, 60);
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    foreach ( $items as $raw ) {
        // Pattern: token[_variationToken][:qty]
        // token and variationToken can be numeric IDs or SKUs
        // qty defaults to 1
        $m = [];
        if ( ! preg_match('/^\s*([^:,]+?)(?:_([^:,]+))?(?::(\d+))?\s*$/', $raw, $m) ) {
            if ( FBCH_VERBOSE ) error_log('FBCH: parse fail item -> ' . $raw);
            continue;
        }
        $token         = isset($m[1]) ? trim($m[1]) : '';
        $variation_tok = isset($m[2]) ? trim($m[2]) : '';
        $qty           = isset($m[3]) && (int)$m[3] > 0 ? (int)$m[3] : 1;

        $product_id = 0;
        $variation_id = 0;
        $variation_data = [];

        // Helper: add a resolved product/variation to cart
        $add_resolved = function($product_id, $qty, $variation_id = 0, $variation_data = []) {
            if ( $variation_id ) {
                return WC()->cart->add_to_cart($product_id, $qty, $variation_id, $variation_data);
            }
            return WC()->cart->add_to_cart($product_id, $qty);
        };

        // Attempt resolution path 1: explicit numeric variation ID
        if ( $variation_tok !== '' && ctype_digit($variation_tok) ) {
            $maybe_variation = wc_get_product( (int)$variation_tok );
            if ( $maybe_variation && $maybe_variation->is_type('variation') ) {
                $variation_id = (int)$variation_tok;
                $product_id   = $maybe_variation->get_parent_id();
                $variation_data = $maybe_variation->get_variation_attributes();
            }
        }

        // If not yet resolved, resolve $token (parent or direct product/variation by ID or SKU)
        if ( ! $product_id ) {
            if ( ctype_digit($token) ) {
                $pid = (int)$token;
            } else {
                $pid = wc_get_product_id_by_sku($token);
            }
            if ( $pid ) {
                $prod = wc_get_product($pid);
                if ( $prod ) {
                    if ( $prod->is_type('variation') ) {
                        $variation_id  = $pid;
                        $product_id    = $prod->get_parent_id();
                        $variation_data = $prod->get_variation_attributes();
                    } else {
                        $product_id = $pid;
                    }
                }
            }
        }

        // If variation token exists but not numeric, try to resolve as variation SKU under parent or globally
        if ( $variation_tok && ! $variation_id ) {
            $vid = ctype_digit($variation_tok) ? (int)$variation_tok : wc_get_product_id_by_sku($variation_tok);
            if ( $vid ) {
                $vprod = wc_get_product($vid);
                if ( $vprod && $vprod->is_type('variation') ) {
                    $variation_id  = $vid;
                    $product_id    = $vprod->get_parent_id();
                    $variation_data = $vprod->get_variation_attributes();
                }
            }
        }

        if ( ! $product_id ) {
            if ( FBCH_VERBOSE ) error_log('FBCH: resolution failed -> ' . $raw);
            continue;
        }

        // Ensure purchasable
        $product_obj = wc_get_product( $variation_id ? $variation_id : $product_id );
        if ( ! $product_obj || ! $product_obj->is_purchasable() ) {
            if ( FBCH_VERBOSE ) error_log('FBCH: not purchasable -> ' . $raw);
            continue;
        }

        // Add to cart
        $added = $add_resolved($product_id, $qty, $variation_id, $variation_data);

        if ( FBCH_VERBOSE ) {
            if ( $added ) {
                error_log(sprintf('FBCH: added -> product %d%s qty %d',
                    $product_id,
                    $variation_id ? (' / variation ' . $variation_id) : '',
                    $qty
                ));
            } else {
                error_log(sprintf('FBCH: add_to_cart failed -> product %d%s qty %d',
                    $product_id,
                    $variation_id ? (' / variation ' . $variation_id) : '',
                    $qty
                ));
            }
        }
    }

    // mark handled for 60s to avoid reprocessing if URL replayed
    set_transient($flag_key, 1, 60);

    // Always redirect to a clean cart URL
    wp_safe_redirect( wc_get_cart_url() );
    exit;
});
