<?php
/**
 * CryptoTax Theme functions and definitions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme setup
 */
function cryptotax_theme_setup() {
    // Add theme support for various features
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));
    add_theme_support('title-tag');
    add_theme_support('custom-logo');
    
    // Add support for Gutenberg wide and full alignments
    add_theme_support('align-wide');
    
    // Add support for editor styles
    add_theme_support('editor-styles');
    add_editor_style('assets/css/editor-style.css');
    
    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'cryptotax'),
        'footer' => __('Footer Menu', 'cryptotax'),
    ));
}
add_action('after_setup_theme', 'cryptotax_theme_setup');

/**
 * Enqueue scripts and styles
 */
function cryptotax_scripts() {
    // Enqueue theme stylesheet
    wp_enqueue_style('cryptotax-style', get_stylesheet_uri(), array(), wp_get_theme()->get('Version'));
    
    // Enqueue custom slider styles
    wp_enqueue_style('cryptotax-slider', get_template_directory_uri() . '/assets/css/slider.css', array(), wp_get_theme()->get('Version'));
    
    // Enqueue custom slider script
    wp_enqueue_script('cryptotax-slider', get_template_directory_uri() . '/assets/js/slider.js', array('jquery'), wp_get_theme()->get('Version'), true);
}
add_action('wp_enqueue_scripts', 'cryptotax_scripts');

/**
 * Enqueue block editor assets
 */
function cryptotax_block_editor_assets() {
    // Enqueue block editor script
    wp_enqueue_script(
        'cryptotax-blocks',
        get_template_directory_uri() . '/assets/js/blocks.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
        wp_get_theme()->get('Version')
    );
    
    // Enqueue block editor styles
    wp_enqueue_style(
        'cryptotax-blocks-editor',
        get_template_directory_uri() . '/assets/css/blocks-editor.css',
        array('wp-edit-blocks'),
        wp_get_theme()->get('Version')
    );
}
add_action('enqueue_block_editor_assets', 'cryptotax_block_editor_assets');

/**
 * Register custom blocks
 */
function cryptotax_register_blocks() {
    // Register the slider block
    register_block_type('cryptotax/slider', array(
        'editor_script' => 'cryptotax-blocks',
        'editor_style' => 'cryptotax-blocks-editor',
        'style' => 'cryptotax-slider',
        'render_callback' => 'cryptotax_render_slider_block',
        'attributes' => array(
            'slides' => array(
                'type' => 'array',
                'default' => array(
                    array(
                        'image' => 'https://i0.wp.com/blissful-euler.23-22-90-233.plesk.page/wp-content/uploads/2021/08/bg_video.jpg?fit=1020%2C584&ssl=1',
                        'title' => 'Welcome to CryptoTax',
                        'description' => 'Professional cryptocurrency tax solutions',
                        'buttonText' => 'Get Started',
                        'buttonUrl' => '#'
                    )
                )
            ),
            'autoplay' => array(
                'type' => 'boolean',
                'default' => true
            ),
            'autoplaySpeed' => array(
                'type' => 'number',
                'default' => 5000
            ),
            'showDots' => array(
                'type' => 'boolean',
                'default' => true
            ),
            'showArrows' => array(
                'type' => 'boolean',
                'default' => true
            )
        )
    ));
}
add_action('init', 'cryptotax_register_blocks');

/**
 * Render callback for the slider block
 */
function cryptotax_render_slider_block($attributes) {
    $slides = $attributes['slides'] ?? array();
    $autoplay = $attributes['autoplay'] ?? true;
    $autoplaySpeed = $attributes['autoplaySpeed'] ?? 5000;
    $showDots = $attributes['showDots'] ?? true;
    $showArrows = $attributes['showArrows'] ?? true;
    
    if (empty($slides)) {
        return '';
    }
    
    $slider_id = 'cryptotax-slider-' . uniqid();
    
    ob_start();
    ?>
    <div class="cryptotax-slider-container" id="<?php echo esc_attr($slider_id); ?>" 
         data-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>"
         data-autoplay-speed="<?php echo esc_attr($autoplaySpeed); ?>">
        <div class="cryptotax-slider">
            <?php foreach ($slides as $index => $slide): ?>
                <div class="cryptotax-slide <?php echo $index === 0 ? 'active' : ''; ?>">
                    <?php if (!empty($slide['image'])): ?>
                        <div class="slide-background" style="background-image: url('<?php echo esc_url($slide['image']); ?>');">
                            <div class="slide-overlay"></div>
                        </div>
                    <?php endif; ?>
                    <div class="slide-content">
                        <div class="slide-content-inner">
                            <?php if (!empty($slide['title'])): ?>
                                <h2 class="slide-title"><?php echo esc_html($slide['title']); ?></h2>
                            <?php endif; ?>
                            <?php if (!empty($slide['description'])): ?>
                                <p class="slide-description"><?php echo esc_html($slide['description']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($slide['buttonText']) && !empty($slide['buttonUrl'])): ?>
                                <a href="<?php echo esc_url($slide['buttonUrl']); ?>" class="slide-button btn cryptotax-gradient">
                                    <?php echo esc_html($slide['buttonText']); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($showArrows && count($slides) > 1): ?>
            <div class="slider-arrows">
                <button class="slider-arrow slider-prev" aria-label="Previous slide">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <button class="slider-arrow slider-next" aria-label="Next slide">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($showDots && count($slides) > 1): ?>
            <div class="slider-dots">
                <?php for ($i = 0; $i < count($slides); $i++): ?>
                    <button class="slider-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-slide="<?php echo $i; ?>"></button>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Add custom block category
 */
function cryptotax_block_categories($categories) {
    return array_merge(
        $categories,
        array(
            array(
                'slug' => 'cryptotax',
                'title' => __('CryptoTax Blocks', 'cryptotax'),
                'icon' => 'slides',
            ),
        )
    );
}
add_filter('block_categories_all', 'cryptotax_block_categories', 10, 2);

/**
 * Add shortcode for the slider
 */
function cryptotax_slider_shortcode($atts) {
    $atts = shortcode_atts(array(
        'autoplay' => true,
        'autoplay_speed' => 5000,
        'show_dots' => true,
        'show_arrows' => true,
    ), $atts);
    
    $attributes = array(
        'slides' => array(
            array(
                'image' => 'https://i0.wp.com/blissful-euler.23-22-90-233.plesk.page/wp-content/uploads/2021/08/bg_video.jpg?fit=1020%2C584&ssl=1',
                'title' => 'Welcome to CryptoTax',
                'description' => 'Professional cryptocurrency tax solutions',
                'buttonText' => 'Get Started',
                'buttonUrl' => '#'
            )
        ),
        'autoplay' => filter_var($atts['autoplay'], FILTER_VALIDATE_BOOLEAN),
        'autoplaySpeed' => intval($atts['autoplay_speed']),
        'showDots' => filter_var($atts['show_dots'], FILTER_VALIDATE_BOOLEAN),
        'showArrows' => filter_var($atts['show_arrows'], FILTER_VALIDATE_BOOLEAN)
    );
    
    return cryptotax_render_slider_block($attributes);
}
add_shortcode('cryptotax_slider', 'cryptotax_slider_shortcode');