(function () {
  'use strict';

  // Exit if no phone numbers to track
  if (!LeadStreamPhone || !LeadStreamPhone.numbers || !LeadStreamPhone.numbers.length) {
    return;
  }

  /**
   * Clean, robust phone tracking following the "tel: links first" approach
   * No CSS selectors required - works with any tel: link automatically
   * Optional CSS selectors for enhanced accuracy when needed
   */

  // Get normalized phone numbers (digits only) from settings
  const trackedNumbers = LeadStreamPhone.numbers.map(num => num.replace(/\D/g, ''));
  const customSelectors = LeadStreamPhone.selectors ?
    LeadStreamPhone.selectors.split('\n').filter(s => s.trim()) : [];

  /**
   * Check if a phone number matches our tracked numbers
   */
  function isTrackedNumber(phoneString) {
    const normalized = phoneString.replace(/\D/g, ''); // Strip to digits only
    return trackedNumbers.some(tracked =>
      normalized.includes(tracked) || tracked.includes(normalized)
    );
  }

  /**
   * Send phone click to analytics and database
   */
  function recordPhoneClick(phoneNumber, element) {
    const normalizedPhone = phoneNumber.replace(/\D/g, '');

    // 1) Send to Google Analytics (GA4) if available
    if (window.gtag && LeadStreamPhone.ga_id) {
      gtag('event', 'phone_click', {
        event_category: 'Phone',
        event_label: normalizedPhone,
        phone_number: phoneNumber,
        value: 1
      });
    }

    // 2) Send to WordPress database via modern fetch API
    const formData = new FormData();
    formData.append('action', 'leadstream_record_phone_click');
    formData.append('phone', normalizedPhone);
    formData.append('original_phone', phoneNumber);
    formData.append('element_type', element.tagName.toLowerCase());
    formData.append('element_class', element.className || '');
    formData.append('element_id', element.id || '');
    formData.append('page_url', window.location.href);
    formData.append('page_title', document.title);
    formData.append('nonce', LeadStreamPhone.nonce);

    fetch(LeadStreamPhone.ajax_url, {
      method: 'POST',
      body: formData
    }).catch(error => {
      console.warn('LeadStream: Failed to record phone click:', error);
    });

    // 3) Visual feedback (optional)
    if (LeadStreamPhone.show_feedback) {
      element.classList.add('ls-phone-clicked');
      setTimeout(() => element.classList.remove('ls-phone-clicked'), 2000);
    }
  }

  /**
   * Setup phone tracking - clean and minimal
   */
  function initPhoneTracking() {
    // 1) Track ALL tel: links automatically (no selectors needed)
    document.querySelectorAll('a[href^="tel:"]').forEach(element => {
      const href = element.href;
      const phoneNumber = href.replace('tel:', '').trim();

      // Only track if this number is in our configuration
      if (isTrackedNumber(phoneNumber)) {
        element.addEventListener('click', () => {
          recordPhoneClick(phoneNumber, element);
        });

        // Add class for styling
        element.classList.add('ls-phone-tracked');
      }
    });

    // 2) Track custom selectors (optional for enhanced accuracy)
    customSelectors.forEach(selector => {
      try {
        document.querySelectorAll(selector).forEach(element => {
          // Extract phone number from various sources
          let phoneNumber = null;

          // Try href first
          if (element.href && element.href.startsWith('tel:')) {
            phoneNumber = element.href.replace('tel:', '').trim();
          }
          // Try data attributes
          else if (element.dataset.phone) {
            phoneNumber = element.dataset.phone;
          }
          // Try text content as fallback
          else {
            const text = element.textContent.trim();
            if (/[\d\-\(\)\+\.\s]{10,}/.test(text)) {
              phoneNumber = text;
            }
          }

          if (phoneNumber && isTrackedNumber(phoneNumber)) {
            element.addEventListener('click', () => {
              recordPhoneClick(phoneNumber, element);
            });

            element.classList.add('ls-phone-tracked');
          }
        });
      } catch (error) {
        console.warn('LeadStream: Invalid phone selector:', selector, error);
      }
    });

    // Debug logging
    if (LeadStreamPhone.debug) {
      const trackedElements = document.querySelectorAll('.ls-phone-tracked');
      console.log('LeadStream Phone Tracking initialized:', {
        configuredNumbers: trackedNumbers.length,
        customSelectors: customSelectors.length,
        trackedElements: trackedElements.length
      });
    }
  }

  /**
   * Re-scan for new phone elements (for dynamic content)
   */
  function rescanPhoneElements() {
    // Remove existing tracking
    document.querySelectorAll('.ls-phone-tracked').forEach(el => {
      el.classList.remove('ls-phone-tracked');
      // Note: Can't easily remove specific event listeners without references
      // This is a limitation, but new elements will still be tracked properly
    });

    // Re-initialize
    initPhoneTracking();
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPhoneTracking);
  } else {
    initPhoneTracking();
  }

  // Expose global function for manual re-scanning
  window.LeadStreamPhoneRescan = rescanPhoneElements;

})();
