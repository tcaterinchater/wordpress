<?php
/**
 * LuxBasic Child Theme Functions
 * 
 * This file contains all custom functionality for the child theme.
 * Sections are organized as follows:
 * 1. Theme Setup & Enqueues
 * 2. Custom Post Types
 * 3. Shortcodes & Sliders
 * 4. WooCommerce Customizations
 * 5. User Account & Authentication
 * 6. Cart & Checkout Functionality
 * 7. Utility Functions
 * 8. Plesk API Integration (at the end)
 */

// =============================================================================
// 1. THEME SETUP & ENQUEUES
// =============================================================================

function luxbasic_child_enqueue_styles() {
    $parent_style = 'luxbasic-style'; // This is 'luxbasic-style' for the LuxBasic theme.

    wp_enqueue_style($parent_style, get_template_directory_uri() . '/style.css');
    // Enqueue Font Awesome
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null);

    // Enqueue your theme's main stylesheet (if not already done)
    wp_enqueue_style('theme-style', get_stylesheet_uri());
    wp_enqueue_script('my-mobile-menu', get_stylesheet_directory_uri() . '/js/mobile-menu.js', array(), null, true);

    // Enqueue your custom script
    //wp_enqueue_script('custom-ajax-cart', get_stylesheet_directory_uri() . '/js/custom-ajax-cart.js', array('jquery'), null, true);

    // Localize script to use admin-ajax URL in JS
    wp_localize_script('custom-ajax-cart', 'custom_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'luxbasic_child_enqueue_styles');

function enqueue_slick_slider_assets() {
    wp_enqueue_style('slick-css', '//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
    wp_enqueue_script('slick-js', '//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), null, true);

    // Initialize the slider
    wp_add_inline_script('slick-js', "
        jQuery(document).ready(function($){
            $('.logo-slider').slick({
                infinite: true,
                slidesToShow: 5,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 2000,
                arrows: false,
                dots: false,
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: 4,
                            slidesToScroll: 1,
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 3,
                            slidesToScroll: 1,
                        }
                    },
                    {
                        breakpoint: 480,
                        settings: {
                            slidesToShow: 2,
                            slidesToScroll: 1,
                        }
                    }
                ]
            });
        });
    ");
}
add_action('wp_enqueue_scripts', 'enqueue_slick_slider_assets');

function enqueue_swiper_assets() {
    wp_enqueue_style('swiper-style', 'https://unpkg.com/swiper/swiper-bundle.min.css');
    wp_enqueue_script('swiper-script', 'https://unpkg.com/swiper/swiper-bundle.min.js', array(), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_swiper_assets');

function enqueue_aos_scripts() {
    wp_enqueue_style('aos-css', 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css');
    wp_enqueue_script('aos-js', 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js', array(), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_aos_scripts');

// =============================================================================
// 2. CUSTOM POST TYPES
// =============================================================================

// Logo Post Type
function create_logo_post_type() {
    $labels = array(
        'name' => 'Logos',
        'singular_name' => 'Logo',
        'menu_name' => 'Logos',
        'name_admin_bar' => 'Logo',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Logo',
        'new_item' => 'New Logo',
        'edit_item' => 'Edit Logo',
        'view_item' => 'View Logo',
        'all_items' => 'All Logos',
        'search_items' => 'Search Logos',
        'not_found' => 'No logos found.',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'supports' => array('title', 'thumbnail'),
        'menu_icon' => 'dashicons-format-image',
        'has_archive' => false,
        'rewrite' => array('slug' => 'logos'),
    );

    register_post_type('logo', $args);
}
add_action('init', 'create_logo_post_type');

// Developer Post Type
function create_developer_post_type() {
    $labels = array(
        'name' => 'Developers',
        'singular_name' => 'Developer',
        'menu_name' => 'Developers',
        'name_admin_bar' => 'Developer',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Developer',
        'new_item' => 'New Developer',
        'edit_item' => 'Edit Developer',
        'view_item' => 'View Developer',
        'all_items' => 'All Developers',
        'search_items' => 'Search Developers',
        'not_found' => 'No developers found.',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'supports' => array('title', 'editor', 'thumbnail'),
        'menu_icon' => 'dashicons-admin-users',
        'has_archive' => false,
        'rewrite' => array('slug' => 'developers'),
    );

    register_post_type('developer', $args);
}
add_action('init', 'create_developer_post_type');

// =============================================================================
// 3. SHORTCODES & SLIDERS
// =============================================================================

function trusted_platform_logo_slider() {
    ob_start(); // Start output buffering

    $logos = new WP_Query(array(
        'post_type' => 'logo',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'ASC',
    ));

    if ($logos->have_posts()) :
    ?>
    
        <div class="logo-slider">
            <?php while ($logos->have_posts()) : $logos->the_post(); ?>
                <div class="logo-slide">
                    <?php if (has_post_thumbnail()) : ?>
                        <a href="<?php the_field('link_url'); ?>" target="_blank">
                            <?php the_post_thumbnail('medium'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    
    <?php
    wp_reset_postdata();
    endif;

    return ob_get_clean(); // Return the buffered content
}
add_shortcode('trusted_platform_logos', 'trusted_platform_logo_slider');

function developers_slider_shortcode() {
    ob_start(); // Start output buffering

    $developers = new WP_Query(array(
        'post_type' => 'developer',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'ASC',
    ));

    if ($developers->have_posts()) :
    ?>
    <section class="developers-slider-section">
        <div class="swiper-container">
            <div class="swiper-wrapper">
                <?php while ($developers->have_posts()) : $developers->the_post(); ?>
                    <div class="swiper-slide">
                        <div class="slide-content">
                            <div class="slide-image">
                                <?php if (has_post_thumbnail()) : ?>
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_post_thumbnail('full'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="slide-text">
                                <h2><?php echo esc_html(get_the_title()); ?></h2>
                                <h3><?php echo esc_html(get_field('position')); ?></h3>
                                <p><?php echo esc_html(get_the_excerpt()); ?></p>
                                <div class="slide-buttons">
                                    <?php if (get_field('primary_button_text') && get_field('primary_button_url')) : ?>
                                        <a href="<?php echo esc_url(get_field('primary_button_url')); ?>" class="btn btn-primary">
                                            <?php echo esc_html(get_field('primary_button_text')); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (get_field('secondary_button_text') && get_field('secondary_button_url')) : ?>
                                        <a href="<?php echo esc_url(get_field('secondary_button_url')); ?>" class="btn btn-secondary">
                                            <?php echo esc_html(get_field('secondary_button_text')); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <!-- Add Swiper navigation buttons -->
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </section>
    <?php
    wp_reset_postdata();
    endif;

    return ob_get_clean(); // Return the buffered content
}
add_shortcode('developers_slider', 'developers_slider_shortcode');

// Shortcode for WooCommerce login and registration form
function custom_woocommerce_login_registration_form() {
    if ( is_user_logged_in() ) {
        return '<p>You are already logged in.</p>';
    }

    ob_start(); ?>
    <div class="woocommerce-login-registration">
    <div class="woocommerce-custom-authentication">
        <div class="login-form">
            <div class="login-register-container">
                <p class="welcome-text">Welcome back! We're glad to see you again. Please log in to access your account and continue your journey.</p>
            </div>
            <?php echo do_shortcode('[woocommerce_my_account]'); ?>
        </div>
    </div>
</div>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_woocommerce_login_registration', 'custom_woocommerce_login_registration_form');

// =============================================================================
// 4. WOOCOMMERCE CUSTOMIZATIONS
// =============================================================================

// Remove sidebar from WooCommerce pages
add_action('wp', 'remove_woocommerce_sidebar');
function remove_woocommerce_sidebar() {
    if ( is_product_category() ) {
        remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
    }
}

// Remove sidebar from WooCommerce single product pages
add_action('wp', 'remove_sidebar_on_product_pages');
function remove_sidebar_on_product_pages() {
    if (is_woocommerce()) {
        remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
    }
}

// Add .product-single class only on single product pages
add_filter( 'body_class', function( $classes ) {
    if ( is_product() ) {
        $classes[] = 'product-single';
    }
    return $classes;
});

// Remove related products from single product pages
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

// Redirect shop page to products page
add_action('template_redirect', function() {
    if (is_shop() && !is_admin()) {
        wp_redirect(home_url('/products/'), 301);
        exit();
    }
});

// Remove cart sidebar
add_action( 'template_redirect', function() {
    if ( is_cart() ) {
        add_filter( 'get_sidebar', '__return_false' );
    }
});

// Allow only 1 product in the WooCommerce cart
add_filter( 'woocommerce_add_to_cart_validation', 'ts_allow_only_one_product_in_cart', 10, 3 );
function ts_allow_only_one_product_in_cart( $passed, $product_id, $quantity ) {
    // Empty the cart before adding new product
    WC()->cart->empty_cart();
    return $passed;
}

// =============================================================================
// 5. USER ACCOUNT & AUTHENTICATION
// =============================================================================

function register_footer_menus() {
    register_nav_menus(array(
        'footer_menu_1' => __('Footer Menu 1'),
        'footer_menu_2' => __('Footer Menu 2'),
        'footer_menu_3' => __('Footer Menu 3'),
    ));
}
add_action('init', 'register_footer_menus');

function register_footer_sidebar() {
    register_sidebar(array(
        'name'          => __('Footer Sidebar', 'your-theme-textdomain'),
        'id'            => 'footer-sidebar',
        'description'   => __('Widgets in this area will be shown in the footer.', 'your-theme-textdomain'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
}
add_action('widgets_init', 'register_footer_sidebar');

function mytheme_customize_register($wp_customize) {
    // Add Footer Logo Setting
    $wp_customize->add_setting('footer_logo', array(
        'default' => '',
        'transport' => 'refresh',
    ));
    
    // Add Footer Logo Control
    $wp_customize->add_control(new WP_Customize_Image_Control(
        $wp_customize,
        'footer_logo',
        array(
            'label'    => __('Footer Logo', 'mytheme'),
            'section'  => 'title_tagline',
            'settings' => 'footer_logo',
        )
    ));
}
add_action('customize_register', 'mytheme_customize_register');

function custom_login_logout_menu_link( $items, $args ) {
    if( $args->theme_location == 'footer_menu_3' ) {
        if( is_user_logged_in() ) {
            $items .= '<li class="menu-item"><a href="' . get_permalink( get_option('woocommerce_myaccount_page_id') ) . '">My Account</a></li>';
            $items .= '<li class="menu-item"><a href="' . wp_logout_url( get_permalink() ) . '">Logout</a></li>';
        } else {
            $items .= '<li class="menu-item"><a href="/login/">Login / Register</a></li>';
        }
    }
    return $items;
}
add_filter( 'wp_nav_menu_items', 'custom_login_logout_menu_link', 10, 2 );

// Function to add My Account / Login / Logout links to the header
function add_account_links() {
    if (is_user_logged_in()) {
        // If user is logged in, show My Account and Logout links
        ?>
        <a href="<?php echo esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))); ?>" class="my-account-link"><?php _e('My Account', 'woocommerce'); ?></a>
        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="logout-link"><?php _e('Logout', 'woocommerce'); ?></a>
        <?php
    } else {
        // If user is not logged in, show Login link
        ?>
        <a href="<?php echo esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))); ?>" class="login-link"><?php _e('Login', 'woocommerce'); ?></a>
        <?php
    }
}

// =============================================================================
// 6. CART & CHECKOUT FUNCTIONALITY
// =============================================================================

// Add domain name field to product page
add_action('woocommerce_before_add_to_cart_button', 'add_custom_fields');
function add_custom_fields() {
    global $product;
    if (is_product() && $product->is_type('variable-subscription')) {
        echo '<div class="custom-fields">
            <label for="domain_name">Domain Name</label>
            <input type="text" id="domain_name" name="domain_name" required>
        </div>';
    }
}

// Validate before adding to cart
add_filter('woocommerce_add_to_cart_validation', 'validate_domain_name_before_cart', 10, 3);
function validate_domain_name_before_cart($passed, $product_id, $quantity) {
    if (isset($_POST['domain_name'])) {
        $domain = sanitize_text_field($_POST['domain_name']);

        // Check if it's a valid domain format
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            wc_add_notice(__('Please enter a valid domain name.', 'woocommerce'), 'error');
            return false;
        }

        // WHOIS check - see if domain is already registered
        if (is_domain_taken($domain)) {
            wc_add_notice(__('This domain is already registered. Please choose another.', 'woocommerce'), 'error');
            return false;
        }
    }
    return $passed;
}

// Save domain name into cart item
add_filter('woocommerce_add_cart_item_data', 'save_domain_name_to_cart', 10, 2);
function save_domain_name_to_cart($cart_item_data, $product_id) {
    if (isset($_POST['domain_name'])) {
        $cart_item_data['domain_name'] = sanitize_text_field($_POST['domain_name']);
    }
    return $cart_item_data;
}

add_filter('woocommerce_get_item_data', 'display_custom_fields', 10, 2);
function display_custom_fields($item_data, $cart_item) {
    if (isset($cart_item['domain_name'])) {
        $item_data[] = array(
            'name' => 'Domain Name',
            'value' => sanitize_text_field($cart_item['domain_name'])
        );
    }
    return $item_data;
}

add_action('woocommerce_checkout_create_order_line_item', 'save_custom_fields_to_order', 10, 4);
function save_custom_fields_to_order($item, $cart_item_key, $values, $order) {
    if (isset($values['domain_name'])) {
        $item->add_meta_data('Domain Name', $values['domain_name']);
    }
}

// Set COD orders to completed automatically
add_action('woocommerce_thankyou_cod', 'set_cod_orders_to_completed');
function set_cod_orders_to_completed($order_id) {
    if (!$order_id) return;
    
    $order = wc_get_order($order_id);
    if ($order->get_payment_method() == 'cod') {
        $order->update_status('completed');
    }
}

// Cart functionality
function add_woocommerce_cart_icon() {
    ?>
    <div class="header-cart-wrapper">
        <a class="cart-contents" href="<?php echo wc_get_cart_url(); ?>" title="<?php _e('View your shopping cart', 'woocommerce'); ?>">
            <span class="cart-icon">
                🛒
            </span>
            <span class="cart-count">
                <?php echo WC()->cart->get_cart_contents_count(); ?>
            </span>
        </a>

        <!-- Dropdown cart content -->
        <div class="header-cart-dropdown">
            <?php if (WC()->cart->get_cart_contents_count() > 0) : ?>
                <ul class="cart-items">
                    <?php foreach (WC()->cart->get_cart() as $cart_item) : ?>
                        <?php
                        $_product   = $cart_item['data'];
                        $product_id = $cart_item['product_id'];
                        ?>
                        <li>
                            <a href="<?php echo get_permalink($product_id); ?>">
                                <?php echo $_product->get_image('thumbnail'); ?>
                                <div class="cart-item-details">
                                    <span class="product-name"><?php echo $_product->get_name(); ?></span>
                                    <span class="product-quantity"><?php echo $cart_item['quantity']; ?> x <?php echo wc_price($_product->get_price()); ?></span>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="cart-total"><?php _e('Total: ', 'woocommerce'); ?><?php echo WC()->cart->get_cart_total(); ?></p>
                <a href="<?php echo wc_get_cart_url(); ?>" class="view-cart-button"><?php _e('View Cart', 'woocommerce'); ?></a>
            <?php else : ?>
                <p class="empty-cart"><?php _e('No products in the cart.', 'woocommerce'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function custom_cart_icon_script() {
    ?>
    <script>
        jQuery(document).ready(function($) {
            $(document).on('click', function(event) {
                if (!$(event.target).closest('.header-cart-wrapper').length) {
                    $('.header-cart-dropdown').hide();
                }
            });

            $('.header-cart-wrapper').on('mouseenter', function() {
                $(this).find('.header-cart-dropdown').show();
            }).on('mouseleave', function() {
                $(this).find('.header-cart-dropdown').hide();
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_cart_icon_script');

// Remove item from cart
add_action('wp_ajax_remove_item_from_cart', 'ts_remove_item_from_cart');
add_action('wp_ajax_nopriv_remove_item_from_cart', 'ts_remove_item_from_cart');

function ts_remove_item_from_cart() {
    if ( ! isset($_POST['cart_item_key']) ) {
        wp_send_json_error(['message' => 'No cart item key found']);
    }

    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    
    if ( WC()->cart->remove_cart_item($cart_item_key) ) {
        WC()->cart->calculate_totals();
        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();

        wp_send_json_success([
            'mini_cart' => $mini_cart,
            'cart_count' => WC()->cart->get_cart_contents_count(),
        ]);
    } else {
        wp_send_json_error(['message' => 'Could not remove item']);
    }
}

// Cart fragments
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_script('wc-cart-fragments');
}, 11);

add_filter( 'woocommerce_add_to_cart_fragments', 'update_cart_count_fragment' );
function update_cart_count_fragment( $fragments ) {
    ob_start();
    ?>
    <span class="cart-count">
        <?php echo WC()->cart->get_cart_contents_count(); ?>
    </span>
    <?php
    $fragments['.cart-count'] = ob_get_clean();
    return $fragments;
}

add_filter( 'woocommerce_add_to_cart_fragments', 'update_mini_cart_fragment' );
function update_mini_cart_fragment( $fragments ) {
    ob_start();
    woocommerce_mini_cart();
    $fragments['.header-cart-inner'] = ob_get_clean();
    return $fragments;
}

// Custom empty cart message
add_action('woocommerce_cart_is_empty', 'display_custom_empty_cart_message');
function display_custom_empty_cart_message() {
    ?>
    <div class="empty-cart-actions" style="margin-top: 40px; text-align: center;">
        <a href="<?php echo home_url('/products/'); ?>" class="browse-products-button" style="display: inline-block; padding: 12px 24px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin: 0 10px;">
            Browse Our Hosting Products
        </a>
        <a href="<?php echo home_url('/products/'); ?>" class="continue-shopping-link" style="display: inline-block; padding: 12px 24px; background: #666; color: white; text-decoration: none; border-radius: 4px; margin: 0 10px;">
            View All Services
        </a>
    </div>
    <?php
}

// Subscription redirect after checkout
add_action('woocommerce_thankyou', function($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->get_type() === 'shop_subscription') {
        wp_safe_redirect($order->get_checkout_order_received_url());
        exit;
    }
});

// =============================================================================
// 7. UTILITY FUNCTIONS
// =============================================================================

// Post duplication functionality
function duplicate_post_as_draft($post_id) {
    global $wpdb;
    if (! (isset($_GET['post']) || isset($_POST['post']) || (isset($_REQUEST['action']) && 'duplicate_post_as_draft' == $_REQUEST['action']))) {
        wp_die('No post to duplicate has been supplied!');
    }

    // Get the original post
    $post_id = (isset($_GET['post']) ? $_GET['post'] : $_POST['post']);
    $post = get_post($post_id);

    // Copy the post data
    $new_post = array(
        'post_title' => $post->post_title . ' (Copy)',
        'post_content' => $post->post_content,
        'post_status' => 'draft',
        'post_type' => $post->post_type,
        'post_author' => $post->post_author,
    );

    // Insert the post into the database
    $new_post_id = wp_insert_post($new_post);

    // Copy the taxonomies
    $taxonomies = get_object_taxonomies($post->post_type);
    foreach ($taxonomies as $taxonomy) {
        $post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
        wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
    }

    // Copy the metadata
    $post_meta = get_post_meta($post_id);
    foreach ($post_meta as $meta_key => $meta_values) {
        foreach ($meta_values as $meta_value) {
            add_post_meta($new_post_id, $meta_key, $meta_value);
        }
    }

    // Redirect to the newly created draft
    wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
    exit;
}
add_action('admin_action_duplicate_post_as_draft', 'duplicate_post_as_draft');

function duplicate_post_link($actions, $post) {
    if (current_user_can('edit_posts')) {
        $actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce') . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'duplicate_post_link', 10, 2);
add_filter('page_row_actions', 'duplicate_post_link', 10, 2);

// Custom meta box for hiding entry header
function custom_add_meta_box() {
    add_meta_box(
        'hide_entry_header',
        'Hide Entry Header',
        'custom_meta_box_callback',
        'page',
        'side'
    );
}
add_action('add_meta_boxes', 'custom_add_meta_box');

function custom_meta_box_callback($post) { 
    $value = get_post_meta($post->ID, '_hide_entry_header', true);
    ?>
    <label for="hide_entry_header">
        <input type="checkbox" name="hide_entry_header" id="hide_entry_header" value="yes" <?php checked($value, 'yes'); ?> />
        Hide entry header
    </label>
    <?php
}

function custom_save_meta_box_data($post_id) {
    if (array_key_exists('hide_entry_header', $_POST)) {
        update_post_meta($post_id, '_hide_entry_header', $_POST['hide_entry_header']);
    } else {
        delete_post_meta($post_id, '_hide_entry_header');
    }
}
add_action('save_post', 'custom_save_meta_box_data');

// WHOIS checker function (basic, may vary depending on server)
function is_domain_taken($domain) {
    $server = "whois.verisign-grs.com"; // For .com & .net domains
    $port   = 43;
    $fp = @fsockopen($server, $port);
    if (!$fp) return false;

    fwrite($fp, $domain . "\r\n");
    $response = '';
    while (!feof($fp)) {
        $response .= fgets($fp, 128);
    }
    fclose($fp);

    // If "No match" appears, the domain is available
    if (stripos($response, "No match") !== false) {
        return false; // Available
    }
    return true; // Taken
}

// JavaScript for cart and domain validation
add_action( 'wp_footer', function() { ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
  document.body.addEventListener("click", function (e) {
    const blockRemove = e.target.closest(".wc-block-cart-item__remove-link");
    if (blockRemove) {
      const classicRemove = document.querySelector(".remove_from_cart_button");
      if (classicRemove) {
        classicRemove.click();
      }
    }
  });
});
</script>
<?php });

add_action('wp_footer', function () {
    if (is_product()) : ?>
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            let form = document.querySelector("form.cart");
            if (!form) return;

            form.addEventListener("submit", function (e) {
                let domainInput = form.querySelector("#domain_name");
                if (!domainInput) return;

                let domain = domainInput.value.trim();
                // Simple domain validation regex
                let regex = /^(?!-)[A-Za-z0-9-]{1,63}(?<!-)\.[A-Za-z]{2,6}$/;

                if (!regex.test(domain)) {
                    e.preventDefault();
                    alert("Please enter a valid domain name (e.g., example.com).");
                    domainInput.focus();
                }
            });
        });
        </script>
    <?php endif;
});

add_action('wp_footer', function() {
    if ( is_cart() || is_checkout() ) return; // skip on cart/checkout
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($){
        $(document).on('click', '.mini_cart_item .remove', function(e){
            e.preventDefault();
            var cart_item_key = $(this).attr('data-cart_item_key');
            
            $.ajax({
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                type: "POST",
                data: {
                    action: "remove_item_from_cart",
                    cart_item_key: cart_item_key,
                },
                success: function(response){
                    if(response.success){
                        $(".header-cart-inner").html(response.data.mini_cart);
                        $(".cart-count").text(response.data.cart_count);
                    } else {
                        console.log(response.data.message);
                    }
                }
            });
        });
    });
    </script>
    <?php
});

// Domain hosting details in My Account
// Step 1: Add a new submenu item under "My Account"
function add_domain_hosting_menu_item($items) {
    $new_items = array();
    // Insert the new item after the "subscriptions" tab
    foreach ($items as $key => $value) {
        $new_items[$key] = $value;
        if ($key === 'subscriptions') {
            $new_items['domain-hosting'] = __('Your Domain/Hosting Details', 'text-domain');
        }
    }
    return $new_items;
}
add_filter('woocommerce_account_menu_items', 'add_domain_hosting_menu_item');

// Step 2: Define the endpoint URL for the new menu item
function add_domain_hosting_endpoint() {
    add_rewrite_endpoint('domain-hosting', EP_PAGES);
}
add_action('init', 'add_domain_hosting_endpoint');

// Step 3: Display the content for the new submenu
function display_domain_hosting_details() {
    // Get the current user
    $user_id = get_current_user_id();
    
    // Retrieve the user's active subscriptions
    $subscriptions = wcs_get_users_subscriptions($user_id);

    if (!empty($subscriptions)) {
        foreach ($subscriptions as $subscription) {
            // Fetch stored Plesk details
            $domain_name = $subscription->get_meta('plesk_domain_name');
            $ftp_username = $subscription->get_meta('plesk_ftp_username');
            $ftp_password = $subscription->get_meta('plesk_ftp_password');
            $plesk_login_url = $subscription->get_meta('plesk_login_url');
            $plesk_subscription_guid = $subscription->get_meta('plesk_subscription_guid');
            $plesk_subscription_id = $subscription->get_meta('plesk_subscription_id');

            if ($domain_name || $ftp_username || $ftp_password || $plesk_subscription_guid || $plesk_subscription_id) {
                echo '<h2>' . __('Plesk Account Details', 'text-domain') . '</h2>';
                echo '<table class="shop_table shop_table_responsive my_account_subscriptions">';
                if ($domain_name) {
                    echo '<tr>';
                    echo '<th>' . __('Domain Name', 'text-domain') . '</th>';
                    echo '<td>' . esc_html($domain_name) . '</td>';
                    echo '</tr>';
                }
                if ($ftp_username) {
                    echo '<tr>';
                    echo '<th>' . __('FTP Username', 'text-domain') . '</th>';
                    echo '<td>' . esc_html($ftp_username) . '</td>';
                    echo '</tr>';
                }
                if ($ftp_password) : ?>
    <tr>
        <th><?php _e('FTP Password', 'text-domain'); ?></th>
        <td>
            <input type="password" 
                   value="<?php echo esc_attr($ftp_password); ?>" 
                   readonly 
                   id="ftp-password-field"
                   style="width:250px; padding:5px;">
            
            <button type="button" 
                    onclick="togglePasswordVisibility()" 
                    style="margin-left:8px; cursor:pointer;">
                👁️
            </button>
        </td>
    </tr>

    <script>
        function togglePasswordVisibility() {
            const field = document.getElementById("ftp-password-field");
            field.type = field.type === "password" ? "text" : "password";
        }
    </script>
<?php endif;

                if ($plesk_login_url) {
                    echo '<tr>';
                    echo '<th>' . __('Plesk Login URL', 'text-domain') . '</th>';
                    echo '<td><a href="' . esc_url($plesk_login_url) . '" target="_blank">' . esc_html($plesk_login_url) . '</a></td>';
                    echo '</tr>';
                }
                if ($plesk_subscription_guid) {
                    echo '<tr>';
                    echo '<th>' . __('Plesk Subscription GUID', 'text-domain') . '</th>';
                    echo '<td>' . esc_html($plesk_subscription_guid) . '</td>';
                    echo '</tr>';
                }
                if ($plesk_subscription_id) {
                    echo '<tr>';
                    echo '<th>' . __('Plesk Subscription ID', 'text-domain') . '</th>';
                    echo '<td>' . esc_html($plesk_subscription_id) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>' . __('No domain/hosting details available.', 'text-domain') . '</p>';
            }
        }
    } else {
        echo '<p>' . __('No subscriptions found.', 'text-domain') . '</p>';
    }
}
add_action('woocommerce_account_domain-hosting_endpoint', 'display_domain_hosting_details');

// =============================================================================
// 8. PLESK API INTEGRATION
// =============================================================================

/**
 * Main subscription status handler - triggers Plesk account creation/deletion
 */
add_action('woocommerce_subscription_status_updated', 'handle_plesk_subscription_status_change', 10, 3);
function handle_plesk_subscription_status_change($subscription, $new_status, $old_status) {
    if ($new_status === 'active' && $old_status !== 'active') {
        // Handle creation of Plesk account when subscription becomes active
        create_plesk_account_on_subscription($subscription, $new_status, $old_status);
    } elseif ($new_status === 'cancelled' && $old_status !== 'cancelled') {
        // Handle removal of Plesk account when subscription is cancelled
        remove_plesk_account_on_subscription_cancel($subscription);
    }
}

/**
 * IMPROVED: Create Plesk account on subscription activation
 * Now handles existing users and webspaces gracefully
 */
function create_plesk_account_on_subscription($subscription, $new_status, $old_status) {
    if ($new_status === 'active' && $old_status !== 'active') {
        foreach ( $subscription->get_items() as $item_id => $item ) {
            $domain_name = $item->get_meta('Domain Name');

            if ($domain_name) {
                $email = $subscription->get_billing_email();
                
                // Generate FTP credentials
                $ftp_username = sanitize_domain_name($domain_name);
                $ftp_password = generate_random_string(12);
                $plesk_login_url = 'https://3.82.33.92:8443/login_up.php3?login_name=' . $ftp_username;
                $ip_id = '10.0.0.82';

                // Check if user already exists and handle accordingly
                $user_result = handle_plesk_user($email, $ftp_username, $ftp_password);
                
                if (!$user_result['success']) {
                    error_log('Failed to handle Plesk user: ' . $user_result['message']);
                    continue; // Skip to next item
                }

                $user_id = $user_result['user_id'];
                
                // Check if webspace already exists
                $webspace_exists = check_plesk_webspace_exists($domain_name);
                
                if ($webspace_exists) {
                    error_log("Webspace for domain {$domain_name} already exists. Skipping creation.");
                    // Update subscription meta with existing data
                    update_subscription_meta_with_existing_data($subscription, $domain_name, $ftp_username, $ftp_password, $plesk_login_url);
                    continue;
                }

                // Create webspace if it doesn't exist
                $webspace_result = create_plesk_webspace($domain_name, $ip_id, $user_id, $ftp_username, $ftp_password);
                
                if ($webspace_result['success']) {
                    $meta_data = [
                        'plesk_domain_name' => $domain_name,
                        'plesk_ftp_username' => $ftp_username,
                        'plesk_ftp_password' => $ftp_password,
                        'plesk_login_url' => $plesk_login_url,
                        'plesk_subscription_guid' => $webspace_result['guid'],
                        'plesk_subscription_id' => $webspace_result['id']
                    ];

                    foreach ($meta_data as $key => $new_value) {
                        $subscription->update_meta_data($key, $new_value);
                    }

                    $subscription->save();
                    error_log("Successfully created webspace for domain: {$domain_name}");
                } else {
                    error_log('Failed to create webspace: ' . $webspace_result['message']);
                }
            }
        }
    }
}

/**
 * Handle Plesk user - create if doesn't exist, or get existing user ID
 */
function handle_plesk_user($email, $username, $password) {
    // First check if user exists
    $existing_user_id = get_plesk_user_by_login($username);
    
    if ($existing_user_id) {
        error_log("User {$username} already exists with ID: {$existing_user_id}");
        
        // Optionally update the existing user's password
        $update_result = update_plesk_user_password($existing_user_id, $password);
        
        if ($update_result) {
            return [
                'success' => true,
                'user_id' => $existing_user_id,
                'message' => 'Existing user found and password updated',
                'action' => 'updated'
            ];
        } else {
            return [
                'success' => true,
                'user_id' => $existing_user_id,
                'message' => 'Existing user found (password update failed)',
                'action' => 'found'
            ];
        }
    }
    
    // User doesn't exist, create new one
    $new_user_id = create_plesk_user_account($email, $username, $password);
    
    if ($new_user_id) {
        return [
            'success' => true,
            'user_id' => $new_user_id,
            'message' => 'New user created successfully',
            'action' => 'created'
        ];
    } else {
        return [
            'success' => false,
            'user_id' => null,
            'message' => 'Failed to create new user',
            'action' => 'failed'
        ];
    }
}

/**
 * Check if a Plesk user exists by login name
 */
function get_plesk_user_by_login($login) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';
    
    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <customer>
            <get>
                <filter>
                    <login>' . htmlspecialchars($login) . '</login>
                </filter>
                <dataset>
                    <gen_info/>
                </dataset>
            </get>
        </customer>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('Plesk API request failed: ' . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    // Check if user exists and extract ID
    if (isset($xml_response->customer->get->result->id)) {
        return (string)$xml_response->customer->get->result->id;
    }
    
    return false;
}

/**
 * Update existing Plesk user password
 */
function update_plesk_user_password($user_id, $new_password) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';
    
    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <customer>
            <set>
                <filter>
                    <id>' . htmlspecialchars($user_id) . '</id>
                </filter>
                <values>
                    <gen_info>
                        <passwd>' . htmlspecialchars($new_password) . '</passwd>
                    </gen_info>
                </values>
            </set>
        </customer>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('Plesk password update failed: ' . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    // Check if update was successful
    return isset($xml_response->customer->set->result->status) && 
           (string)$xml_response->customer->set->result->status === 'ok';
}

/**
 * Check if a webspace (domain) already exists in Plesk
 */
function check_plesk_webspace_exists($domain_name) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';
    
    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <webspace>
            <get>
                <filter>
                    <name>' . htmlspecialchars($domain_name) . '</name>
                </filter>
                <dataset>
                    <gen_info/>
                </dataset>
            </get>
        </webspace>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('Plesk webspace check failed: ' . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    // Check if webspace exists
    return isset($xml_response->webspace->get->result->id);
}

/**
 * Create Plesk webspace (separated from user creation)
 */
function create_plesk_webspace($domain_name, $ip_id, $owner_id, $ftp_username, $ftp_password) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';

    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <webspace>
            <add>
                <gen_setup>
                    <name>'.htmlspecialchars($domain_name).'</name>
                    <ip_address>'.htmlspecialchars($ip_id).'</ip_address>
                    <owner-id>'.htmlspecialchars($owner_id).'</owner-id>
                    <htype>vrt_hst</htype>
                </gen_setup>
                <hosting>
                    <vrt_hst>
                        <property>
                            <name>ftp_login</name>
                            <value>'.htmlspecialchars($ftp_username).'</value>
                        </property>
                        <property>
                            <name>ftp_password</name>
                            <value>'.htmlspecialchars($ftp_password).'</value>
                        </property>
                        <ip_address>'.htmlspecialchars($ip_id).'</ip_address>
                    </vrt_hst>
                </hosting>
                <plan-guid>53187fa7-1f5e-211c-b41e-24bed5ed2088</plan-guid>
            </add>
        </webspace>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'message' => 'Plesk API request failed: ' . $response->get_error_message()
        ];
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    if (isset($xml_response->webspace->add->result->status) && 
        (string)$xml_response->webspace->add->result->status === 'ok') {
        
        return [
            'success' => true,
            'id' => (string)$xml_response->webspace->add->result->id,
            'guid' => (string)$xml_response->webspace->add->result->guid,
            'message' => 'Webspace created successfully'
        ];
    } else {
        $error_message = isset($xml_response->webspace->add->result->errtext) ? 
            (string)$xml_response->webspace->add->result->errtext : 'Unknown error';
        
        return [
            'success' => false,
            'message' => 'Webspace creation failed: ' . $error_message
        ];
    }
}

/**
 * Update subscription meta data with existing Plesk data
 */
function update_subscription_meta_with_existing_data($subscription, $domain_name, $ftp_username, $ftp_password, $plesk_login_url) {
    // Get existing webspace details
    $webspace_details = get_plesk_webspace_details($domain_name);
    
    if ($webspace_details) {
        $meta_data = [
            'plesk_domain_name' => $domain_name,
            'plesk_ftp_username' => $ftp_username,
            'plesk_ftp_password' => $ftp_password,
            'plesk_login_url' => $plesk_login_url,
            'plesk_subscription_guid' => $webspace_details['guid'],
            'plesk_subscription_id' => $webspace_details['id']
        ];

        foreach ($meta_data as $key => $new_value) {
            $subscription->update_meta_data($key, $new_value);
        }

        $subscription->save();
        error_log("Updated subscription meta with existing webspace data for: {$domain_name}");
    }
}

/**
 * Get details of an existing webspace
 */
function get_plesk_webspace_details($domain_name) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';
    
    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <webspace>
            <get>
                <filter>
                    <name>' . htmlspecialchars($domain_name) . '</name>
                </filter>
                <dataset>
                    <gen_info/>
                </dataset>
            </get>
        </webspace>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    if (isset($xml_response->webspace->get->result->id)) {
        return [
            'id' => (string)$xml_response->webspace->get->result->id,
            'guid' => (string)$xml_response->webspace->get->result->guid
        ];
    }
    
    return false;
}

/**
 * IMPROVED: Create Plesk user account with better error handling
 */
function create_plesk_user_account($email, $username, $password) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';
    
    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <customer>
            <add>
                <gen_info>
                    <pname>' . htmlspecialchars($username) . '</pname>
                    <login>' . htmlspecialchars($username) . '</login>
                    <passwd>' . htmlspecialchars($password) . '</passwd>
                    <email>' . htmlspecialchars($email) . '</email>
                </gen_info>
            </add>
        </customer>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('Plesk API request failed: ' . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $xml_response = simplexml_load_string($response_body);
    
    // Check for successful creation
    if (isset($xml_response->customer->add->result->status) && 
        (string)$xml_response->customer->add->result->status === 'ok') {
        
        return (string)$xml_response->customer->add->result->id;
    } else {
        $error_message = isset($xml_response->customer->add->result->errtext) ? 
            (string)$xml_response->customer->add->result->errtext : 'Unknown error';
        error_log('Plesk user creation failed: ' . $error_message);
        return false;
    }
}

/**
 * Remove Plesk account when subscription is cancelled
 */
function remove_plesk_account_on_subscription_cancel($subscription) {
    $plesk_subscription_id = $subscription->get_meta('plesk_subscription_id');
    if ($plesk_subscription_id) {
        $plesk_username = 'admin';
        $plesk_password = 'GKpJhzmqe09o%@9j';
        $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';

        $xml = '<?xml version="1.0"?>
            <packet version="1.6.3.0">
                <webspace>
                    <del>
                        <filter>
                            <id>' . htmlspecialchars($plesk_subscription_id) . '</id>
                        </filter>
                    </del>
                </webspace>
            </packet>';

        $response = wp_remote_post($plesk_endpoint, array(
            'headers' => array(
                'Content-Type' => 'text/xml',
                'HTTP_AUTH_LOGIN' => $plesk_username,
                'HTTP_AUTH_PASSWD' => $plesk_password,
            ),
            'body' => $xml,
            'timeout' => 60,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            error_log('Plesk API request to delete failed: ' . $response->get_error_message());
        } else {
            $response_body = wp_remote_retrieve_body($response);
            $xml = simplexml_load_string($response_body);
            if ($xml->webspace->del->result->status == 'ok') {
                // Successfully deleted from Plesk
                $subscription->delete_meta_data('plesk_subscription_id');
                $subscription->delete_meta_data('plesk_subscription_guid');
                $subscription->save();
                error_log("Successfully deleted Plesk webspace for subscription ID: {$plesk_subscription_id}");
            } else {
                error_log('Plesk API request to delete failed: ' . $xml->webspace->del->result->errtext);
            }
        }
    }
}

/**
 * Utility functions for Plesk integration
 */
function generate_random_string($length = 8) {
    return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}

function sanitize_domain_name($domain_name) {
    // Remove TLD from domain name
    $domain_name_without_tld = preg_replace('/\.[a-zA-Z]{2,}$/', '', $domain_name);
    // Remove non-alphanumeric characters and trim to 20 characters
    $ftp_username = substr(preg_replace('/[^a-zA-Z0-9]/', '_', trim($domain_name_without_tld)), 0, 20);
    return $ftp_username;
}

/**
 * Legacy/Additional Plesk functions (kept for compatibility)
 */

// Legacy function for direct order completion (if still needed)
add_action('woocommerce_order_status_completed', 'create_site_in_plesk', 10, 1);
function create_site_in_plesk($order_id) {
    $order = wc_get_order($order_id);
    $items = $order->get_items();

    foreach ($items as $item) {
        $product_id = $item->get_product_id();
        // Replace 'your_hosting_plan_id' with the actual ID of your hosting plan product
        if ($product_id == your_hosting_plan_id) {
            $domain = sanitize_text_field($order->get_billing_email()); // Using email as the domain for example
            $username = sanitize_text_field($order->get_billing_first_name());
            $password = wp_generate_password();
            // Call Plesk API to create the site
            plesk_create_site($domain, $username, $password);
        }
    }
}

// Legacy Plesk site creation (REST API version - kept for reference)
function plesk_create_site($domain, $username, $password) {
    $api_url = 'https://your-plesk-server:8443/api/v2/domains';
    $api_key = '1be0ef17-975d-2a21-0015-51cff020cf4c';

    $body = json_encode(array(
        'name' => $domain,
        'hosting' => array(
            'properties' => array(
                array(
                    'name' => 'ftp_login',
                    'value' => $username
                ),
                array(
                    'name' => 'ftp_password',
                    'value' => $password
                )
            )
        )
    ));

    $response = wp_remote_post($api_url, array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body' => $body
    ));

    if (is_wp_error($response)) {
        error_log('Error creating site in Plesk: ' . $response->get_error_message());
    } else {
        $response_body = wp_remote_retrieve_body($response);
        error_log('Site created in Plesk: ' . $response_body);
    }
}

// Modify customer permissions
function modify_customer_permissions($subscription_id) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';

    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <subscription>
            <update>
                <id>'.htmlspecialchars($subscription_id).'</id>
                <plan>
                    <name>customer-plan</name> <!-- Replace with your actual plan name -->
                </plan>
            </update>
        </subscription>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false
    ));

    if (is_wp_error($response)) {
        error_log('Plesk API request failed: ' . $response->get_error_message());
    } else {
        $response_body = wp_remote_retrieve_body($response);
        print_r($response_body);
    }
}

// Get service plans
function get_servie_plans(){
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';

    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <service-plan>
            <get>
                <filter/>
            </get>
        </service-plan>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('Plesk API request failed: ' . $response->get_error_message());
    } else {
        $response_body = wp_remote_retrieve_body($response);
        echo "<pre>";
        echo htmlentities($response_body);
    }
}

// Fetch Plesk subscription details
function fetch_plesk_subscription_details($plesk_subscription_guid) {
    $plesk_username = 'admin';
    $plesk_password = 'GKpJhzmqe09o%@9j';
    $plesk_endpoint = 'https://3.82.33.92:8443/enterprise/control/agent.php';

    $xml = '<?xml version="1.0"?>
    <packet version="1.6.3.0">
        <subscription>
            <get>
                <filter>
                    <guid>'.htmlspecialchars($plesk_subscription_guid).'</guid>
                </filter>
                <dataset>
                    <gen_info/>
                    <limits/>
                    <hosting/>
                    <performance/>
                    <stat/>
                    <disk_usage/>
                    <prefs/>
                </dataset>
            </get>
        </subscription>
    </packet>';

    $response = wp_remote_post($plesk_endpoint, array(
        'headers' => array(
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_LOGIN' => $plesk_username,
            'HTTP_AUTH_PASSWD' => $plesk_password,
        ),
        'body' => $xml,
        'timeout' => 60,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        error_log('Plesk API request failed: ' . $response->get_error_message());
        return false;
    } else {
        $response_body = wp_remote_retrieve_body($response);
        return $response_body;
    }
}

// Parse Plesk response
function parse_plesk_response($response_body) {
    $xml = simplexml_load_string($response_body);
    $domain_expiry_date = (string) $xml->xpath('//gen_info/expire')[0];
    $ssl_status = (string) $xml->xpath('//hosting/vrt_hst/ssl')[0] == 'true' ? 'Enabled' : 'Disabled';
    $last_backup_date = (string) $xml->xpath('//prefs/backup')['last'];
    $disk_usage = (string) $xml->xpath('//disk_usage')['total'];

    return array(
        'domain_expiry_date' => $domain_expiry_date,
        'ssl_status' => $ssl_status,
        'last_backup_date' => $last_backup_date,
        'disk_usage' => $disk_usage,
    );
}

/**
 * Testing/Debug functions for Plesk integration
 */

// Custom trigger endpoint for testing
function custom_trigger_endpoint() {
    add_rewrite_rule('^trigger-subscription-status$', 'index.php?trigger_subscription_status=1', 'top');
}
add_action('init', 'custom_trigger_endpoint');

function custom_trigger_query_vars($vars) {
    $vars[] = 'trigger_subscription_status';
    return $vars;
}
add_filter('query_vars', 'custom_trigger_query_vars');

function custom_trigger_subscription_status() {
    if (get_query_var('trigger_subscription_status')) {
        // Simulate a subscription object
        $subscription = new WC_Subscription(174); // Replace 174 with a real subscription ID from your site
        
        // Simulate new and old statuses
        $new_status = 'active';
        $old_status = 'pending';
        
        // Call the function with simulated parameters
        create_plesk_account_on_subscription($subscription, $new_status, $old_status);
        
        // Optionally, display a message or redirect
        wp_die('Subscription status change triggered.');
    }
}
add_action('template_redirect', 'custom_trigger_subscription_status');

function custom_flush_rewrite_rules() {
    custom_trigger_endpoint();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'custom_flush_rewrite_rules');

// =============================================================================
// END OF PLESK API INTEGRATION
// =============================================================================