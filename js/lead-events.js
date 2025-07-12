// lead-events.js - Google Analytics Lead Tracking Events

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function () {

  // Track WPForms submissions
  document.addEventListener('wpformsSubmit', function (event) {
    if (typeof gtag !== 'undefined') {
      gtag('event', 'form_submit', {
        'event_category': 'Lead',
        'event_label': 'WPForms Main',
        'value': 1
      });
      console.log('GA Event: WPForms submission tracked');
    }
  }, false);

  // Track catering button clicks
  const cateringBtn = document.getElementById('order-catering-btn');
  if (cateringBtn) {
    cateringBtn.addEventListener('click', function () {
      if (typeof gtag !== 'undefined') {
        gtag('event', 'cta_quote_click', {
          'event_category': 'CTA',
          'event_label': 'Order Catering - Main Hero',
          'value': 1
        });
        console.log('GA Event: Catering button click tracked');
      }
    });
  }

  // Track contact form submissions (generic)
  const contactForms = document.querySelectorAll('form[class*="contact"], form[id*="contact"]');
  contactForms.forEach(function (form) {
    form.addEventListener('submit', function () {
      if (typeof gtag !== 'undefined') {
        gtag('event', 'contact_form_submit', {
          'event_category': 'Lead',
          'event_label': 'Contact Form',
          'value': 1
        });
        console.log('GA Event: Contact form submission tracked');
      }
    });
  });

  // Track phone number clicks
  const phoneLinks = document.querySelectorAll('a[href^="tel:"]');
  phoneLinks.forEach(function (link) {
    link.addEventListener('click', function () {
      if (typeof gtag !== 'undefined') {
        gtag('event', 'phone_click', {
          'event_category': 'Lead',
          'event_label': 'Phone Number Click',
          'value': 1
        });
        console.log('GA Event: Phone click tracked');
      }
    });
  });

  // Track email clicks
  const emailLinks = document.querySelectorAll('a[href^="mailto:"]');
  emailLinks.forEach(function (link) {
    link.addEventListener('click', function () {
      if (typeof gtag !== 'undefined') {
        gtag('event', 'email_click', {
          'event_category': 'Lead',
          'event_label': 'Email Click',
          'value': 1
        });
        console.log('GA Event: Email click tracked');
      }
    });
  });

  // Track CTA buttons (any button with "cta" in class or id)
  const ctaButtons = document.querySelectorAll('button[class*="cta"], a[class*="cta"], button[id*="cta"], a[id*="cta"]');
  ctaButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      if (typeof gtag !== 'undefined') {
        const buttonText = button.textContent.trim() || button.getAttribute('aria-label') || 'CTA Button';
        gtag('event', 'cta_click', {
          'event_category': 'CTA',
          'event_label': buttonText,
          'value': 1
        });
        console.log('GA Event: CTA button click tracked - ' + buttonText);
      }
    });
  });

  // Track scroll depth (25%, 50%, 75%, 100%)
  let scrollDepthTracked = {
    25: false,
    50: false,
    75: false,
    100: false
  };

  window.addEventListener('scroll', function () {
    const scrollPercent = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);

    Object.keys(scrollDepthTracked).forEach(function (depth) {
      if (scrollPercent >= depth && !scrollDepthTracked[depth]) {
        scrollDepthTracked[depth] = true;
        if (typeof gtag !== 'undefined') {
          gtag('event', 'scroll_depth', {
            'event_category': 'Engagement',
            'event_label': depth + '% Scrolled',
            'value': parseInt(depth)
          });
          console.log('GA Event: Scroll depth ' + depth + '% tracked');
        }
      }
    });
  });

  console.log('Lead Tracking for GA: JavaScript events initialized');
});
