# CryptoTax WordPress Theme

A custom WordPress theme featuring a beautiful slider component with CryptoTax brand colors and modern design.

## Features

- **Custom Slider Block**: Gutenberg-compatible slider block with your brand colors
- **Responsive Design**: Works perfectly on all devices
- **Brand Colors**: Uses your CryptoTax color palette (#0043FF, #A370F1, #4BF2E6, etc.)
- **Smooth Animations**: Beautiful transitions and effects
- **Accessibility**: Keyboard navigation and ARIA support
- **Touch Support**: Swipe gestures on mobile devices
- **Autoplay Options**: Configurable autoplay with custom speeds
- **Navigation Controls**: Dots and arrow navigation

## Installation

1. **Upload Theme**: Upload the `cryptotax-theme` folder to your `/wp-content/themes/` directory
2. **Activate Theme**: Go to WordPress Admin > Appearance > Themes and activate "CryptoTax Theme"
3. **Ensure jQuery**: The theme requires jQuery (included with WordPress by default)

## Usage

### Method 1: Gutenberg Block Editor

1. Edit any page or post in the block editor
2. Add a new block and search for "CryptoTax Slider"
3. Configure your slides in the block settings:
   - Upload images
   - Add titles and descriptions
   - Set button text and URLs
   - Configure autoplay, navigation, and timing

### Method 2: Shortcode

Add the slider anywhere using the shortcode:

```php
[cryptotax_slider]
```

With custom options:
```php
[cryptotax_slider autoplay="true" autoplay_speed="6000" show_dots="true" show_arrows="true"]
```

### Method 3: PHP Function

In your theme files:

```php
<?php
echo cryptotax_render_slider_block(array(
    'slides' => array(
        array(
            'image' => 'https://your-image-url.jpg',
            'title' => 'Your Title',
            'description' => 'Your description',
            'buttonText' => 'Button Text',
            'buttonUrl' => '#your-link'
        )
    ),
    'autoplay' => true,
    'autoplaySpeed' => 5000,
    'showDots' => true,
    'showArrows' => true
));
?>
```

## Customization

### Colors

The theme uses CSS custom properties for easy color customization. Edit `assets/css/cryptotax-color-palette.css`:

```css
:root {
  --cryptotax-deep-blue: #0043FF;
  --cryptotax-purple: #A370F1;
  --cryptotax-cyan: #4BF2E6;
  --cryptotax-bright-blue: #0065FF;
  /* ... more colors */
}
```

### Slider Styles

Customize slider appearance in `assets/css/slider.css`. Key classes:

- `.cryptotax-slider-container`: Main container
- `.cryptotax-slide`: Individual slides
- `.slide-content`: Content wrapper
- `.slide-title`: Slide titles
- `.slide-description`: Slide descriptions
- `.slide-button`: Call-to-action buttons

### JavaScript Customization

Extend slider functionality in `assets/js/slider.js`. The slider exposes these events:

```javascript
$('#your-slider').on('slideChanged', function(e, data) {
    console.log('Current slide:', data.currentSlide);
});
```

## File Structure

```
cryptotax-theme/
├── assets/
│   ├── css/
│   │   ├── cryptotax-color-palette.css
│   │   ├── slider.css
│   │   └── blocks-editor.css
│   └── js/
│       ├── slider.js
│       └── blocks.js
├── blocks/
│   └── slider/
├── inc/
├── style.css
├── functions.php
├── index.php
├── front-page.php
├── demo.html
└── README.md
```

## Demo

Open `demo.html` in your browser to see the slider in action with all features demonstrated.

## Browser Support

- Chrome 60+
- Firefox 60+
- Safari 12+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Requirements

- WordPress 5.0+
- PHP 7.4+
- jQuery (included with WordPress)

## Configuration Options

### Block/Shortcode Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `slides` | Array | Default slide | Array of slide objects |
| `autoplay` | Boolean | `true` | Enable/disable autoplay |
| `autoplaySpeed` | Number | `5000` | Autoplay speed in milliseconds |
| `showDots` | Boolean | `true` | Show navigation dots |
| `showArrows` | Boolean | `true` | Show navigation arrows |

### Slide Object Structure

```javascript
{
    image: 'https://image-url.jpg',
    title: 'Slide Title',
    description: 'Slide description text',
    buttonText: 'Button Text',
    buttonUrl: '#link-url'
}
```

## Support

For support and customization requests, please contact the CryptoTax development team.

## License

This theme is proprietary to CryptoTax. All rights reserved.