/**
 * CryptoTax Slider Block for Gutenberg Editor
 */

(function(wp) {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { 
        PanelBody, 
        Button, 
        ToggleControl, 
        RangeControl, 
        TextControl, 
        TextareaControl,
        Card,
        CardBody,
        CardHeader
    } = wp.components;
    const { useState, useEffect } = wp.element;
    const { __ } = wp.i18n;

    // Default slide data
    const defaultSlide = {
        image: 'https://i0.wp.com/blissful-euler.23-22-90-233.plesk.page/wp-content/uploads/2021/08/bg_video.jpg?fit=1020%2C584&ssl=1',
        title: 'Welcome to CryptoTax',
        description: 'Professional cryptocurrency tax solutions',
        buttonText: 'Get Started',
        buttonUrl: '#'
    };

    registerBlockType('cryptotax/slider', {
        title: __('CryptoTax Slider', 'cryptotax'),
        description: __('A beautiful, responsive slider with CryptoTax branding', 'cryptotax'),
        icon: 'slides',
        category: 'cryptotax',
        keywords: [
            __('slider', 'cryptotax'),
            __('carousel', 'cryptotax'),
            __('cryptotax', 'cryptotax'),
        ],
        attributes: {
            slides: {
                type: 'array',
                default: [defaultSlide]
            },
            autoplay: {
                type: 'boolean',
                default: true
            },
            autoplaySpeed: {
                type: 'number',
                default: 5000
            },
            showDots: {
                type: 'boolean',
                default: true
            },
            showArrows: {
                type: 'boolean',
                default: true
            }
        },
        supports: {
            align: ['wide', 'full'],
            html: false,
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { slides, autoplay, autoplaySpeed, showDots, showArrows } = attributes;

            // Add a new slide
            const addSlide = () => {
                const newSlides = [...slides, { ...defaultSlide }];
                setAttributes({ slides: newSlides });
            };

            // Remove a slide
            const removeSlide = (index) => {
                if (slides.length > 1) {
                    const newSlides = slides.filter((_, i) => i !== index);
                    setAttributes({ slides: newSlides });
                }
            };

            // Update slide data
            const updateSlide = (index, key, value) => {
                const newSlides = [...slides];
                newSlides[index][key] = value;
                setAttributes({ slides: newSlides });
            };

            // Render slide editor
            const renderSlideEditor = (slide, index) => {
                return wp.element.createElement(
                    Card,
                    { key: index, className: 'cryptotax-slide-editor' },
                    wp.element.createElement(
                        CardHeader,
                        null,
                        wp.element.createElement(
                            'div',
                            { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                            wp.element.createElement('strong', null, `Slide ${index + 1}`),
                            slides.length > 1 && wp.element.createElement(
                                Button,
                                {
                                    isDestructive: true,
                                    isSmall: true,
                                    onClick: () => removeSlide(index)
                                },
                                __('Remove', 'cryptotax')
                            )
                        )
                    ),
                    wp.element.createElement(
                        CardBody,
                        null,
                        // Image Upload
                        wp.element.createElement(
                            MediaUploadCheck,
                            null,
                            wp.element.createElement(
                                MediaUpload,
                                {
                                    onSelect: (media) => updateSlide(index, 'image', media.url),
                                    allowedTypes: ['image'],
                                    value: slide.image,
                                    render: ({ open }) => wp.element.createElement(
                                        'div',
                                        { className: 'cryptotax-media-upload', onClick: open },
                                        slide.image 
                                            ? wp.element.createElement('img', { src: slide.image, alt: slide.title })
                                            : wp.element.createElement('div', null, __('Click to select image', 'cryptotax'))
                                    )
                                }
                            )
                        ),
                        // Title
                        wp.element.createElement(
                            TextControl,
                            {
                                label: __('Title', 'cryptotax'),
                                value: slide.title,
                                onChange: (value) => updateSlide(index, 'title', value),
                                placeholder: __('Enter slide title...', 'cryptotax')
                            }
                        ),
                        // Description
                        wp.element.createElement(
                            TextareaControl,
                            {
                                label: __('Description', 'cryptotax'),
                                value: slide.description,
                                onChange: (value) => updateSlide(index, 'description', value),
                                placeholder: __('Enter slide description...', 'cryptotax')
                            }
                        ),
                        // Button Text
                        wp.element.createElement(
                            TextControl,
                            {
                                label: __('Button Text', 'cryptotax'),
                                value: slide.buttonText,
                                onChange: (value) => updateSlide(index, 'buttonText', value),
                                placeholder: __('Enter button text...', 'cryptotax')
                            }
                        ),
                        // Button URL
                        wp.element.createElement(
                            TextControl,
                            {
                                label: __('Button URL', 'cryptotax'),
                                value: slide.buttonUrl,
                                onChange: (value) => updateSlide(index, 'buttonUrl', value),
                                placeholder: __('Enter button URL...', 'cryptotax')
                            }
                        )
                    )
                );
            };

            return [
                // Inspector Controls
                wp.element.createElement(
                    InspectorControls,
                    { key: 'inspector' },
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Slider Settings', 'cryptotax'), initialOpen: true },
                        wp.element.createElement(
                            ToggleControl,
                            {
                                label: __('Autoplay', 'cryptotax'),
                                checked: autoplay,
                                onChange: (value) => setAttributes({ autoplay: value })
                            }
                        ),
                        autoplay && wp.element.createElement(
                            RangeControl,
                            {
                                label: __('Autoplay Speed (ms)', 'cryptotax'),
                                value: autoplaySpeed,
                                onChange: (value) => setAttributes({ autoplaySpeed: value }),
                                min: 1000,
                                max: 10000,
                                step: 500
                            }
                        ),
                        wp.element.createElement(
                            ToggleControl,
                            {
                                label: __('Show Navigation Dots', 'cryptotax'),
                                checked: showDots,
                                onChange: (value) => setAttributes({ showDots: value })
                            }
                        ),
                        wp.element.createElement(
                            ToggleControl,
                            {
                                label: __('Show Navigation Arrows', 'cryptotax'),
                                checked: showArrows,
                                onChange: (value) => setAttributes({ showArrows: value })
                            }
                        )
                    )
                ),
                // Block Editor Content
                wp.element.createElement(
                    'div',
                    { key: 'editor', className: 'cryptotax-slider-inspector' },
                    wp.element.createElement(
                        'h3',
                        { style: { marginBottom: '1rem', color: '#0043FF' } },
                        __('CryptoTax Slider', 'cryptotax')
                    ),
                    wp.element.createElement(
                        'p',
                        { style: { marginBottom: '1.5rem', color: '#777' } },
                        __('Configure your slides below. The slider will be displayed on the frontend.', 'cryptotax')
                    ),
                    // Render slide editors
                    slides.map((slide, index) => renderSlideEditor(slide, index)),
                    // Add Slide Button
                    wp.element.createElement(
                        Button,
                        {
                            isPrimary: true,
                            onClick: addSlide,
                            className: 'cryptotax-add-slide',
                            style: { marginTop: '1rem', width: '100%' }
                        },
                        __('+ Add Slide', 'cryptotax')
                    ),
                    // Preview Notice
                    wp.element.createElement(
                        'div',
                        { 
                            style: { 
                                marginTop: '1.5rem', 
                                padding: '1rem', 
                                background: '#f0f8ff', 
                                border: '1px solid #0043FF', 
                                borderRadius: '4px' 
                            } 
                        },
                        wp.element.createElement(
                            'p',
                            { style: { margin: 0, color: '#0043FF', fontWeight: 'bold' } },
                            __('Preview: The slider will be fully functional on the frontend with animations, navigation, and autoplay.', 'cryptotax')
                        )
                    )
                )
            ];
        },

        save: function() {
            // Server-side rendering
            return null;
        }
    });

})(window.wp);