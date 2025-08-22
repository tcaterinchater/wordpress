/**
 * Mini Cart AJAX Functionality
 * Handles product removal and cart updates
 */

jQuery(document).ready(function($) {
    
    // Handle remove item from mini cart
    $(document).on('click', '.mini_cart_item .remove', function(e) {
        e.preventDefault();
        
        var $removeLink = $(this);
        var cartItemKey = $removeLink.data('cart_item_key') || $removeLink.attr('data-cart_item_key');
        
        // If no cart item key found, try to extract from href
        if (!cartItemKey) {
            var href = $removeLink.attr('href');
            var match = href.match(/remove_item=([^&]+)/);
            if (match) {
                cartItemKey = match[1];
            }
        }
        
        if (!cartItemKey) {
            console.error('Cart item key not found');
            return;
        }
        
        // Add loading state
        $removeLink.addClass('removing');
        $removeLink.closest('.mini_cart_item').addClass('removing');
        
        // AJAX request to remove item
        $.ajax({
            url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'remove_from_cart'),
            type: 'POST',
            data: {
                cart_item_key: cartItemKey,
                security: wc_add_to_cart_params.wc_ajax_nonce || ''
            },
            beforeSend: function() {
                // Optional: Add loading spinner
                $removeLink.html('<span class="loading-spinner">×</span>');
            },
            success: function(response) {
                if (response.error) {
                    console.error('Error removing item:', response.error);
                    return;
                }
                
                // Update mini cart content
                updateMiniCart();
                
                // Update cart fragments if available
                if (response.fragments) {
                    $.each(response.fragments, function(key, value) {
                        $(key).replaceWith(value);
                    });
                }
                
                // Trigger cart updated event
                $(document.body).trigger('wc_fragment_refresh');
                $(document.body).trigger('cart_item_removed', [cartItemKey]);
                
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                // Restore original state on error
                $removeLink.removeClass('removing');
                $removeLink.closest('.mini_cart_item').removeClass('removing');
                $removeLink.html('×');
            }
        });
    });
    
    // Handle quantity changes in mini cart
    $(document).on('change', '.mini_cart_item .qty', function() {
        var $qtyInput = $(this);
        var cartItemKey = $qtyInput.data('cart_item_key') || $qtyInput.closest('.mini_cart_item').find('.remove').data('cart_item_key');
        var newQty = $qtyInput.val();
        
        if (!cartItemKey) {
            console.error('Cart item key not found for quantity update');
            return;
        }
        
        // Update quantity via AJAX
        $.ajax({
            url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'update_cart_item'),
            type: 'POST',
            data: {
                cart_item_key: cartItemKey,
                quantity: newQty,
                security: wc_add_to_cart_params.wc_ajax_nonce || ''
            },
            success: function(response) {
                if (response.error) {
                    console.error('Error updating quantity:', response.error);
                    return;
                }
                
                // Update mini cart
                updateMiniCart();
                
                // Update fragments
                if (response.fragments) {
                    $.each(response.fragments, function(key, value) {
                        $(key).replaceWith(value);
                    });
                }
                
                $(document.body).trigger('wc_fragment_refresh');
            },
            error: function(xhr, status, error) {
                console.error('AJAX error updating quantity:', error);
            }
        });
    });
    
    // Function to update mini cart content
    function updateMiniCart() {
        $.ajax({
            url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments'),
            type: 'POST',
            data: {
                security: wc_add_to_cart_params.wc_ajax_nonce || ''
            },
            success: function(response) {
                if (response && response.fragments) {
                    $.each(response.fragments, function(key, value) {
                        $(key).replaceWith(value);
                    });
                    
                    // Update cart count in header if exists
                    if (response.cart_hash) {
                        $('.cart-count, .cart-counter, .header-cart-count').html(response.cart_count || '0');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error refreshing mini cart:', error);
            }
        });
    }
    
    // Handle mini cart toggle/close
    $(document).on('click', '.header-cart-toggle, .mini-cart-toggle', function(e) {
        e.preventDefault();
        $('.header-cart-popup').toggleClass('open');
    });
    
    // Close mini cart when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.header-cart-popup, .header-cart-toggle, .mini-cart-toggle').length) {
            $('.header-cart-popup').removeClass('open');
        }
    });
    
    // Close mini cart with escape key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) { // Escape key
            $('.header-cart-popup').removeClass('open');
        }
    });
    
    // Refresh mini cart on page load
    $(window).on('load', function() {
        if (typeof wc_add_to_cart_params !== 'undefined') {
            updateMiniCart();
        }
    });
    
    // Listen for cart updates from other parts of the site
    $(document.body).on('added_to_cart removed_from_cart', function() {
        updateMiniCart();
    });
    
    // Handle WooCommerce fragment refresh
    $(document.body).on('wc_fragment_refresh', function() {
        // Re-initialize any custom functionality after fragment refresh
        initMiniCartCustomizations();
    });
    
    // Custom mini cart initializations
    function initMiniCartCustomizations() {
        // Add any custom styling or functionality here
        $('.mini_cart_item').each(function() {
            var $item = $(this);
            if (!$item.hasClass('initialized')) {
                $item.addClass('initialized');
                // Add custom classes or event handlers here
            }
        });
    }
    
    // Initialize on document ready
    initMiniCartCustomizations();
});

// Fallback for themes that don't properly enqueue WooCommerce scripts
if (typeof wc_add_to_cart_params === 'undefined') {
    console.warn('WooCommerce add to cart params not found. Mini cart AJAX may not work properly.');
    
    // Basic fallback functionality
    jQuery(document).ready(function($) {
        $(document).on('click', '.mini_cart_item .remove', function(e) {
            e.preventDefault();
            
            var $item = $(this).closest('.mini_cart_item');
            var confirmRemoval = confirm('Are you sure you want to remove this item from your cart?');
            
            if (confirmRemoval) {
                // Fallback: redirect to remove URL
                window.location.href = $(this).attr('href');
            }
        });
    });
}