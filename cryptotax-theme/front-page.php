<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
    <header id="masthead" class="site-header">
        <div class="container">
            <div class="site-branding">
                <?php
                if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    ?>
                    <h1 class="site-title">
                        <a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                            <?php bloginfo('name'); ?>
                        </a>
                    </h1>
                    <?php
                }
                ?>
            </div>

            <nav id="site-navigation" class="main-navigation">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'menu_id' => 'primary-menu',
                    'fallback_cb' => false,
                ));
                ?>
            </nav>
        </div>
    </header>

    <!-- Demo Slider Section -->
    <section class="hero-section">
        <div class="container-fluid">
            <?php
            // Display demo slider with the provided image
            echo do_shortcode('[cryptotax_slider]');
            
            // Or render the block directly
            echo cryptotax_render_slider_block(array(
                'slides' => array(
                    array(
                        'image' => 'https://i0.wp.com/blissful-euler.23-22-90-233.plesk.page/wp-content/uploads/2021/08/bg_video.jpg?fit=1020%2C584&ssl=1',
                        'title' => 'Welcome to CryptoTax',
                        'description' => 'Professional cryptocurrency tax solutions for individuals and businesses',
                        'buttonText' => 'Get Started Today',
                        'buttonUrl' => '#services'
                    ),
                    array(
                        'image' => 'https://i0.wp.com/blissful-euler.23-22-90-233.plesk.page/wp-content/uploads/2021/08/bg_video.jpg?fit=1020%2C584&ssl=1',
                        'title' => 'Accurate Tax Calculations',
                        'description' => 'Advanced algorithms ensure precise cryptocurrency tax calculations',
                        'buttonText' => 'Learn More',
                        'buttonUrl' => '#features'
                    ),
                    array(
                        'image' => 'https://i0.wp.com/blissful-euler.23-22-90-233.plesk.page/wp-content/uploads/2021/08/bg_video.jpg?fit=1020%2C584&ssl=1',
                        'title' => 'Expert Support',
                        'description' => '24/7 support from cryptocurrency tax professionals',
                        'buttonText' => 'Contact Us',
                        'buttonUrl' => '#contact'
                    )
                ),
                'autoplay' => true,
                'autoplaySpeed' => 6000,
                'showDots' => true,
                'showArrows' => true
            ));
            ?>
        </div>
    </section>

    <main id="primary" class="site-main">
        <div class="container">
            <?php
            if (have_posts()) :
                while (have_posts()) :
                    the_post();
                    ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                        <div class="entry-content">
                            <?php
                            the_content();
                            
                            wp_link_pages(array(
                                'before' => '<div class="page-links">' . esc_html__('Pages:', 'cryptotax'),
                                'after'  => '</div>',
                            ));
                            ?>
                        </div>
                    </article>
                    <?php
                endwhile;
            endif;
            ?>
            
            <!-- Additional content sections -->
            <section id="services" class="services-section">
                <div class="container">
                    <h2>Our Services</h2>
                    <div class="services-grid">
                        <div class="service-item">
                            <h3>Individual Tax Filing</h3>
                            <p>Comprehensive cryptocurrency tax solutions for individual investors.</p>
                        </div>
                        <div class="service-item">
                            <h3>Business Solutions</h3>
                            <p>Enterprise-grade crypto tax management for businesses and institutions.</p>
                        </div>
                        <div class="service-item">
                            <h3>Tax Planning</h3>
                            <p>Strategic tax planning to optimize your cryptocurrency investments.</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer id="colophon" class="site-footer">
        <div class="container">
            <div class="site-info">
                <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'footer',
                    'menu_id' => 'footer-menu',
                    'fallback_cb' => false,
                ));
                ?>
            </div>
        </div>
    </footer>
</div>

<style>
.container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
.container-fluid { width: 100%; padding: 0; }
.hero-section { margin-bottom: 4rem; }
.services-section { padding: 4rem 0; background: #f8f9fa; }
.services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 2rem; }
.service-item { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.service-item h3 { color: #0043FF; margin-bottom: 1rem; }
</style>

<?php wp_footer(); ?>
</body>
</html>