# Shopify Contact Form CSS

A modern, responsive contact form CSS designed specifically for Shopify stores. This includes comprehensive styling with accessibility features, dark theme support, and mobile optimization.

## Features

- 🎨 **Modern Design**: Clean, professional styling with subtle shadows and smooth transitions
- 📱 **Fully Responsive**: Optimized for desktop, tablet, and mobile devices
- ♿ **Accessible**: WCAG compliant with proper focus states and screen reader support
- 🌙 **Dark Theme**: Built-in dark theme variant
- ⚡ **Loading States**: Visual feedback during form submission
- 🛡️ **Error Handling**: Styled error messages and validation states
- 🎯 **Shopify Ready**: Works seamlessly with Shopify's form system

## Installation

### Method 1: Add to Theme Assets

1. Upload `contact-form.css` to your theme's `assets` folder
2. Include the CSS in your theme's `theme.liquid` file:

```liquid
{{ 'contact-form.css' | asset_url | stylesheet_tag }}
```

### Method 2: Add to Existing CSS File

Copy the contents of `contact-form.css` and paste it into your theme's main CSS file (usually `theme.css` or `styles.css`).

### Method 3: Section Implementation

1. Create a new section file in your `sections` folder (e.g., `contact-form.liquid`)
2. Copy the contents from `contact-form-example.liquid`
3. Add the CSS to your theme as described in Method 1

## Usage

### Basic HTML Structure

```html
<div class="contact-form">
  <div class="contact-form__header">
    <h2 class="contact-form__title">Contact Us</h2>
    <p class="contact-form__subtitle">We'd love to hear from you.</p>
  </div>
  
  <form class="contact-form__form">
    <div class="contact-form__field-group">
      <label class="contact-form__label contact-form__label--required">Name</label>
      <input type="text" class="contact-form__input" required>
    </div>
    
    <div class="contact-form__field-group">
      <label class="contact-form__label contact-form__label--required">Email</label>
      <input type="email" class="contact-form__input" required>
    </div>
    
    <div class="contact-form__field-group">
      <label class="contact-form__label">Phone Number</label>
      <input type="tel" class="contact-form__input">
    </div>
    
    <div class="contact-form__field-group">
      <label class="contact-form__label contact-form__label--required">Message</label>
      <textarea class="contact-form__textarea" required></textarea>
    </div>
    
    <button type="submit" class="contact-form__submit">Send Message</button>
  </form>
</div>
```

### Shopify Liquid Implementation

Use the provided `contact-form-example.liquid` as a template. This includes:

- Proper Shopify form integration
- Error handling
- Success messages
- Schema for theme customization

## CSS Classes Reference

### Main Container
- `.contact-form` - Main form container

### Header Elements
- `.contact-form__header` - Header section container
- `.contact-form__title` - Form title
- `.contact-form__subtitle` - Form subtitle/description

### Form Elements
- `.contact-form__field-group` - Individual field container
- `.contact-form__field-row` - Row container for side-by-side fields
- `.contact-form__label` - Field labels
- `.contact-form__label--required` - Required field indicator
- `.contact-form__input` - Text/email/tel input fields
- `.contact-form__textarea` - Textarea field
- `.contact-form__submit` - Submit button

### State Classes
- `.contact-form--loading` - Loading state for form
- `.contact-form--theme-dark` - Dark theme variant

### Message Classes
- `.contact-form__success` - Success message container
- `.contact-form__error` - Error message styling

## Customization

### Colors

The CSS uses CSS custom properties that you can override:

```css
.contact-form {
  --primary-color: #2c3e50;
  --accent-color: #3498db;
  --error-color: #e74c3c;
  --success-color: #28a745;
  --text-color: #333;
  --border-color: #e1e5e9;
}
```

### Dark Theme

To enable the dark theme, add the modifier class:

```html
<div class="contact-form contact-form--theme-dark">
  <!-- form content -->
</div>
```

### Mobile Optimization

The form automatically adapts to mobile devices:
- Fields stack vertically on screens < 768px
- Font sizes adjust to prevent zoom on iOS
- Touch-friendly button sizes

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- iOS Safari 12+
- Android Chrome 60+

## Accessibility Features

- Proper ARIA labels and roles
- Keyboard navigation support
- High contrast mode support
- Screen reader friendly
- Focus indicators
- Reduced motion support

## Performance

- Minimal CSS footprint (~8KB minified)
- Hardware-accelerated animations
- Optimized for Core Web Vitals

## Troubleshooting

### Form Not Styling Properly
1. Ensure the CSS is properly loaded
2. Check for conflicting CSS rules
3. Verify HTML structure matches the expected classes

### Mobile Issues
1. Add viewport meta tag: `<meta name="viewport" content="width=device-width, initial-scale=1">`
2. Ensure parent containers don't have fixed widths

### Shopify Integration Issues
1. Verify the form action is set to `{% form 'contact' %}`
2. Check that field names match Shopify's expected format
3. Ensure proper error handling is implemented

## License

This CSS is provided as-is for use in Shopify stores. Feel free to modify and customize as needed for your specific requirements.
