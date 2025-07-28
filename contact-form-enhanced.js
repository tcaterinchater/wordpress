// Enhanced Contact Form JavaScript
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('.contact-form form');
  const submitButton = document.getElementById('contactFormSubmit');
  const formFields = {
    name: document.getElementById('Form-template--17974942007377__contact-form-1'),
    email: document.getElementById('Form-template--17974942007377__contact-form-2'),
    phone: document.getElementById('Form-template--17974942007377__contact-form-3'),
    message: document.getElementById('Form-template--17974942007377__contact-form-4')
  };

  // Add loading state on form submission
  if (form && submitButton) {
    form.addEventListener('submit', function(e) {
      // Add loading class
      submitButton.classList.add('btn--loading');
      submitButton.disabled = true;
      
      // Store original text
      const originalText = submitButton.textContent;
      submitButton.setAttribute('data-original-text', originalText);
    });
  }

  // Real-time validation
  function validateField(field, type) {
    const fieldContainer = field.closest('.form-field');
    let isValid = true;
    let errorMessage = '';

    // Remove existing error states
    fieldContainer.classList.remove('form-field--error', 'form-field--success');
    const existingError = fieldContainer.querySelector('.form-field__error');
    if (existingError) {
      existingError.remove();
    }

    // Validate based on field type
    switch (type) {
      case 'name':
        if (field.value.trim().length < 2) {
          isValid = false;
          errorMessage = 'Name must be at least 2 characters long';
        }
        break;
      
      case 'email':
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(field.value.trim())) {
          isValid = false;
          errorMessage = 'Please enter a valid email address';
        }
        break;
      
      case 'phone':
        if (field.value.trim() && field.value.trim().length < 10) {
          isValid = false;
          errorMessage = 'Please enter a valid phone number';
        }
        break;
      
      case 'message':
        if (field.value.trim().length < 10) {
          isValid = false;
          errorMessage = 'Message must be at least 10 characters long';
        }
        break;
    }

    // Apply validation state
    if (field.value.trim() !== '') {
      if (isValid) {
        fieldContainer.classList.add('form-field--success');
      } else {
        fieldContainer.classList.add('form-field--error');
        const errorElement = document.createElement('div');
        errorElement.className = 'form-field__error';
        errorElement.textContent = errorMessage;
        fieldContainer.appendChild(errorElement);
      }
    }

    return isValid;
  }

  // Add validation listeners
  if (formFields.name) {
    formFields.name.addEventListener('blur', () => validateField(formFields.name, 'name'));
    formFields.name.addEventListener('input', debounce(() => validateField(formFields.name, 'name'), 500));
  }

  if (formFields.email) {
    formFields.email.addEventListener('blur', () => validateField(formFields.email, 'email'));
    formFields.email.addEventListener('input', debounce(() => validateField(formFields.email, 'email'), 500));
  }

  if (formFields.phone) {
    formFields.phone.addEventListener('blur', () => validateField(formFields.phone, 'phone'));
    formFields.phone.addEventListener('input', debounce(() => validateField(formFields.phone, 'phone'), 500));
  }

  if (formFields.message) {
    formFields.message.addEventListener('blur', () => validateField(formFields.message, 'message'));
    formFields.message.addEventListener('input', debounce(() => validateField(formFields.message, 'message'), 500));
  }

  // Form submission validation
  if (form) {
    form.addEventListener('submit', function(e) {
      let isFormValid = true;
      
      // Validate all fields
      if (formFields.name && !validateField(formFields.name, 'name')) {
        isFormValid = false;
      }
      if (formFields.email && !validateField(formFields.email, 'email')) {
        isFormValid = false;
      }
      if (formFields.phone && formFields.phone.value.trim() && !validateField(formFields.phone, 'phone')) {
        isFormValid = false;
      }
      if (formFields.message && !validateField(formFields.message, 'message')) {
        isFormValid = false;
      }

      if (!isFormValid) {
        e.preventDefault();
        
        // Remove loading state if validation fails
        if (submitButton) {
          submitButton.classList.remove('btn--loading');
          submitButton.disabled = false;
        }
        
        // Focus on first error field
        const firstError = form.querySelector('.form-field--error input, .form-field--error textarea');
        if (firstError) {
          firstError.focus();
        }
        
        return false;
      }
    });
  }

  // Auto-resize textarea
  if (formFields.message) {
    formFields.message.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = Math.max(120, this.scrollHeight) + 'px';
    });
  }

  // Phone number formatting
  if (formFields.phone) {
    formFields.phone.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value.length >= 6) {
        value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
      } else if (value.length >= 3) {
        value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
      }
      e.target.value = value;
    });
  }

  // Success message handling (if form was submitted successfully)
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('success') === 'true' || window.location.hash.includes('contact_form')) {
    // Check if there's a success parameter or if we're on the contact form anchor
    setTimeout(() => {
      const existingSuccess = document.querySelector('.form__success');
      if (!existingSuccess && form) {
        const successMessage = document.createElement('div');
        successMessage.className = 'form__success';
        successMessage.innerHTML = '✓ Thank you! Your message has been sent successfully. We\'ll get back to you soon.';
        form.parentNode.insertBefore(successMessage, form);
        
        // Scroll to success message
        successMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Reset form
        form.reset();
        
        // Remove validation states
        document.querySelectorAll('.form-field--error, .form-field--success').forEach(field => {
          field.classList.remove('form-field--error', 'form-field--success');
        });
        document.querySelectorAll('.form-field__error').forEach(error => {
          error.remove();
        });
      }
    }, 100);
  }

  // Utility function for debouncing
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // Character counter for message field
  if (formFields.message) {
    const messageContainer = formFields.message.closest('.form-field');
    const counter = document.createElement('div');
    counter.className = 'form-field__counter';
    counter.style.cssText = 'font-size: 0.75rem; color: #6b7280; text-align: right; margin-top: 0.25rem;';
    messageContainer.appendChild(counter);
    
    function updateCounter() {
      const length = formFields.message.value.length;
      counter.textContent = `${length} characters`;
      
      if (length < 10) {
        counter.style.color = '#ef4444';
      } else if (length > 500) {
        counter.style.color = '#f59e0b';
      } else {
        counter.style.color = '#10b981';
      }
    }
    
    formFields.message.addEventListener('input', updateCounter);
    updateCounter(); // Initial count
  }

  // Keyboard navigation improvements
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.matches('.contactFormText, .contactFormEmail, .contactFormPhone')) {
      e.preventDefault();
      const formElements = Array.from(form.querySelectorAll('input, textarea, button'));
      const currentIndex = formElements.indexOf(e.target);
      const nextElement = formElements[currentIndex + 1];
      if (nextElement) {
        nextElement.focus();
      }
    }
  });
});