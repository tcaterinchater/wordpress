<?php
/**
 * Enqueue Mini Cart AJAX JavaScript
 */
function enqueue_mini_cart_ajax_script() {
    // Only load on pages where cart functionality is needed
    if (is_woocommerce() || is_cart() || is_checkout() || is_shop() || is_product_category() || is_product_tag() || is_product()) {
        
        // Enqueue the mini cart script
        wp_enqueue_script(
            'mini-cart-ajax',
            get_template_directory_uri() . '/js/mini-cart-ajax.js',
            array('jquery', 'wc-add-to-cart'),
            '1.0.0',
            true
        );
        
        // Localize script with AJAX URL and nonce for security
        wp_localize_script('mini-cart-ajax', 'mini_cart_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'wc_ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
            'nonce' => wp_create_nonce('mini_cart_nonce'),
            'remove_from_cart_url' => wc_get_cart_remove_url(''),
            'cart_url' => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url(),
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_mini_cart_ajax_script');

/**
 * Ensure WooCommerce AJAX add to cart works properly
 */
function ensure_woocommerce_ajax_support() {
    // Enable AJAX add to cart on single product pages
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    
    // Ensure cart fragments are working
    if (!is_admin()) {
        // Force WooCommerce to load cart fragments
        add_action('wp_enqueue_scripts', function() {
            if (function_exists('is_woocommerce')) {
                wp_enqueue_script('wc-cart-fragments');
            }
        });
    }
}
add_action('after_setup_theme', 'ensure_woocommerce_ajax_support');

/**
 * AJAX handler for removing items from cart
 */
function ajax_remove_from_cart() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['security'], 'mini_cart_nonce') && !wp_verify_nonce($_POST['security'], 'wc_cart_nonce')) {
        wp_die('Security check failed');
    }
    
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    
    if ($cart_item_key && WC()->cart) {
        WC()->cart->remove_cart_item($cart_item_key);
        
        // Get updated cart fragments
        WC_AJAX::get_refreshed_fragments();
    } else {
        wp_send_json_error('Invalid cart item key');
    }
}
add_action('wp_ajax_remove_from_cart', 'ajax_remove_from_cart');
add_action('wp_ajax_nopriv_remove_from_cart', 'ajax_remove_from_cart');

/**
 * AJAX handler for updating cart item quantity
 */
function ajax_update_cart_item() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['security'], 'mini_cart_nonce') && !wp_verify_nonce($_POST['security'], 'wc_cart_nonce')) {
        wp_die('Security check failed');
    }
    
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    $quantity = intval($_POST['quantity']);
    
    if ($cart_item_key && WC()->cart) {
        if ($quantity <= 0) {
            WC()->cart->remove_cart_item($cart_item_key);
        } else {
            WC()->cart->set_quantity($cart_item_key, $quantity);
        }
        
        // Get updated cart fragments
        WC_AJAX::get_refreshed_fragments();
    } else {
        wp_send_json_error('Invalid cart item key or quantity');
    }
}
add_action('wp_ajax_update_cart_item', 'ajax_update_cart_item');
add_action('wp_ajax_nopriv_update_cart_item', 'ajax_update_cart_item');

/**
 * Add cart item key to remove links in mini cart
 */
function add_cart_item_key_to_remove_link($sprintf, $cart_item_key, $cart_item) {
    return sprintf(
        '<a href="%s" class="remove remove_from_cart_button" aria-label="%s" data-product_id="%s" data-product_sku="%s" data-cart_item_key="%s">&times;</a>',
        esc_url(wc_get_cart_remove_url($cart_item_key)),
        esc_attr__('Remove this item', 'woocommerce'),
        esc_attr($cart_item['product_id']),
        esc_attr($cart_item['data']->get_sku()),
        esc_attr($cart_item_key)
    );
}
add_filter('woocommerce_cart_item_remove_link', 'add_cart_item_key_to_remove_link', 10, 3);

/**
 * Customize mini cart item output to include necessary data attributes
 */
function customize_mini_cart_item($cart_item, $cart_item_key, $cart_item_data) {
    // Add data attributes to cart items for JavaScript functionality
    echo '<div class="mini-cart-item-data" data-cart-item-key="' . esc_attr($cart_item_key) . '" data-product-id="' . esc_attr($cart_item['product_id']) . '"></div>';
}
add_action('woocommerce_mini_cart_item', 'customize_mini_cart_item', 10, 3);

/**
 * Ensure mini cart updates when items are added via AJAX
 */
function refresh_mini_cart_count($fragments) {
    $cart_count = WC()->cart->get_cart_contents_count();
    
    // Add cart count to fragments
    $fragments['.cart-count'] = '<span class="cart-count">' . $cart_count . '</span>';
    $fragments['.cart-counter'] = '<span class="cart-counter">' . $cart_count . '</span>';
    $fragments['.header-cart-count'] = '<span class="header-cart-count">' . $cart_count . '</span>';
    
    return $fragments;
}
add_filter('woocommerce_add_to_cart_fragments', 'refresh_mini_cart_count');

/**
 * Add CSS classes for better styling of mini cart
 */
function add_mini_cart_css_classes() {
    ?>
    <style>
    .mini_cart_item.removing {
        opacity: 0.5;
        pointer-events: none;
    }
    
    .mini_cart_item .remove.removing {
        opacity: 0.7;
    }
    
    .loading-spinner {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .header-cart-popup {
        display: none;
        position: absolute;
        z-index: 9999;
    }
    
    .header-cart-popup.open {
        display: block;
    }
    </style>
    <?php
}
add_action('wp_head', 'add_mini_cart_css_classes');

/**
 * Debug function to check if WooCommerce AJAX is working
 */
function debug_woocommerce_ajax() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('WooCommerce AJAX URL: ' . WC_AJAX::get_endpoint('test'));
        error_log('Cart fragments enabled: ' . (wp_script_is('wc-cart-fragments', 'enqueued') ? 'Yes' : 'No'));
    }
}
add_action('wp_footer', 'debug_woocommerce_ajax');

/**
 * Ensure cart session is initialized
 */
function ensure_cart_session() {
    if (is_admin()) return;
    
    if (!WC()->session->has_session()) {
        WC()->session->set_customer_session_cookie(true);
    }
}
add_action('init', 'ensure_cart_session');
?>