/**
 * CryptoTax Slider JavaScript
 */

(function($) {
    'use strict';

    class CryptoTaxSlider {
        constructor(container) {
            this.container = $(container);
            this.slider = this.container.find('.cryptotax-slider');
            this.slides = this.container.find('.cryptotax-slide');
            this.dots = this.container.find('.slider-dot');
            this.prevBtn = this.container.find('.slider-prev');
            this.nextBtn = this.container.find('.slider-next');
            
            this.currentSlide = 0;
            this.totalSlides = this.slides.length;
            this.autoplay = this.container.data('autoplay') === true;
            this.autoplaySpeed = this.container.data('autoplay-speed') || 5000;
            this.autoplayTimer = null;
            this.isTransitioning = false;
            
            this.init();
        }

        init() {
            if (this.totalSlides <= 1) {
                return;
            }

            this.bindEvents();
            
            if (this.autoplay) {
                this.startAutoplay();
            }

            // Add keyboard navigation
            this.container.attr('tabindex', '0');
            this.bindKeyboardEvents();

            // Add touch/swipe support
            this.bindTouchEvents();

            // Initialize ARIA attributes
            this.updateAriaAttributes();
        }

        bindEvents() {
            // Navigation arrows
            this.prevBtn.on('click', (e) => {
                e.preventDefault();
                this.prevSlide();
            });

            this.nextBtn.on('click', (e) => {
                e.preventDefault();
                this.nextSlide();
            });

            // Dots navigation
            this.dots.on('click', (e) => {
                e.preventDefault();
                const slideIndex = parseInt($(e.target).data('slide'));
                this.goToSlide(slideIndex);
            });

            // Pause autoplay on hover
            if (this.autoplay) {
                this.container.on('mouseenter', () => {
                    this.pauseAutoplay();
                });

                this.container.on('mouseleave', () => {
                    this.startAutoplay();
                });
            }

            // Handle visibility change (pause when tab is not active)
            $(document).on('visibilitychange', () => {
                if (document.hidden) {
                    this.pauseAutoplay();
                } else if (this.autoplay) {
                    this.startAutoplay();
                }
            });
        }

        bindKeyboardEvents() {
            this.container.on('keydown', (e) => {
                switch(e.which) {
                    case 37: // Left arrow
                        e.preventDefault();
                        this.prevSlide();
                        break;
                    case 39: // Right arrow
                        e.preventDefault();
                        this.nextSlide();
                        break;
                    case 32: // Spacebar
                        e.preventDefault();
                        if (this.autoplay) {
                            this.isPlaying() ? this.pauseAutoplay() : this.startAutoplay();
                        }
                        break;
                }
            });
        }

        bindTouchEvents() {
            let startX = 0;
            let startY = 0;
            let endX = 0;
            let endY = 0;

            this.container.on('touchstart', (e) => {
                startX = e.originalEvent.touches[0].clientX;
                startY = e.originalEvent.touches[0].clientY;
            });

            this.container.on('touchend', (e) => {
                endX = e.originalEvent.changedTouches[0].clientX;
                endY = e.originalEvent.changedTouches[0].clientY;

                const diffX = startX - endX;
                const diffY = startY - endY;

                // Only handle horizontal swipes (ignore vertical scrolling)
                if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                    if (diffX > 0) {
                        this.nextSlide();
                    } else {
                        this.prevSlide();
                    }
                }
            });
        }

        nextSlide() {
            if (this.isTransitioning) return;
            
            const nextIndex = (this.currentSlide + 1) % this.totalSlides;
            this.goToSlide(nextIndex);
        }

        prevSlide() {
            if (this.isTransitioning) return;
            
            const prevIndex = this.currentSlide === 0 ? this.totalSlides - 1 : this.currentSlide - 1;
            this.goToSlide(prevIndex);
        }

        goToSlide(index) {
            if (this.isTransitioning || index === this.currentSlide || index < 0 || index >= this.totalSlides) {
                return;
            }

            this.isTransitioning = true;

            // Update current slide
            const previousSlide = this.currentSlide;
            this.currentSlide = index;

            // Remove active class from all slides and dots
            this.slides.removeClass('active');
            this.dots.removeClass('active');

            // Add active class to current slide and dot
            this.slides.eq(this.currentSlide).addClass('active');
            this.dots.eq(this.currentSlide).addClass('active');

            // Update ARIA attributes
            this.updateAriaAttributes();

            // Trigger custom event
            this.container.trigger('slideChanged', {
                currentSlide: this.currentSlide,
                previousSlide: previousSlide,
                totalSlides: this.totalSlides
            });

            // Reset transition flag after animation completes
            setTimeout(() => {
                this.isTransitioning = false;
            }, 800);

            // Restart autoplay if it was running
            if (this.autoplay && this.isPlaying()) {
                this.startAutoplay();
            }
        }

        startAutoplay() {
            if (!this.autoplay || this.totalSlides <= 1) return;

            this.pauseAutoplay();
            this.autoplayTimer = setInterval(() => {
                this.nextSlide();
            }, this.autoplaySpeed);
        }

        pauseAutoplay() {
            if (this.autoplayTimer) {
                clearInterval(this.autoplayTimer);
                this.autoplayTimer = null;
            }
        }

        isPlaying() {
            return this.autoplayTimer !== null;
        }

        updateAriaAttributes() {
            // Update slide ARIA attributes
            this.slides.each((index, slide) => {
                const $slide = $(slide);
                if (index === this.currentSlide) {
                    $slide.attr('aria-hidden', 'false');
                    $slide.find('a, button').attr('tabindex', '0');
                } else {
                    $slide.attr('aria-hidden', 'true');
                    $slide.find('a, button').attr('tabindex', '-1');
                }
            });

            // Update dot ARIA attributes
            this.dots.each((index, dot) => {
                const $dot = $(dot);
                $dot.attr('aria-label', `Go to slide ${index + 1} of ${this.totalSlides}`);
                $dot.attr('aria-pressed', index === this.currentSlide ? 'true' : 'false');
            });

            // Update arrow ARIA attributes
            this.prevBtn.attr('aria-label', `Go to previous slide. Current slide ${this.currentSlide + 1} of ${this.totalSlides}`);
            this.nextBtn.attr('aria-label', `Go to next slide. Current slide ${this.currentSlide + 1} of ${this.totalSlides}`);
        }

        // Public methods for external control
        destroy() {
            this.pauseAutoplay();
            this.container.off();
            this.prevBtn.off();
            this.nextBtn.off();
            this.dots.off();
        }

        getCurrentSlide() {
            return this.currentSlide;
        }

        getTotalSlides() {
            return this.totalSlides;
        }
    }

    // Initialize sliders when DOM is ready
    $(document).ready(function() {
        $('.cryptotax-slider-container').each(function() {
            new CryptoTaxSlider(this);
        });
    });

    // Re-initialize sliders for dynamically added content (e.g., AJAX loaded content)
    $(document).on('cryptotax:initSliders', function() {
        $('.cryptotax-slider-container').each(function() {
            if (!$(this).data('cryptotax-slider-initialized')) {
                new CryptoTaxSlider(this);
                $(this).data('cryptotax-slider-initialized', true);
            }
        });
    });

    // Expose the class globally for external use
    window.CryptoTaxSlider = CryptoTaxSlider;

})(jQuery);