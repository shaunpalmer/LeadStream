/**
 * LeadStream Admin Settings JavaScript
 * Handles starter script loading, toggle switches, and FAQ functionality
 */

document.addEventListener('DOMContentLoaded', function () {

  // === STARTER SCRIPT FUNCTIONALITY ===
  const starterButton = document.getElementById('load-starter-script');
  if (starterButton) {
    starterButton.addEventListener('click', function () {
      var blocks = [];

      // Platforms
      if (document.getElementById('ls-ga4') && document.getElementById('ls-ga4').checked) {
        blocks.push(`// === GOOGLE ANALYTICS (GA4) ===\ndocument.addEventListener('form_submit', function(event) {\n  gtag('event', 'form_submit', {\n    'event_category': '[Your Service/Product] Lead',\n    'event_label': '[Your Service/Product] Form Submission'\n  });\n});`);
      }
      if (document.getElementById('ls-tiktok') && document.getElementById('ls-tiktok').checked) {
        blocks.push(`// === TIKTOK PIXEL ===\nif (typeof ttq !== 'undefined') {\n  ttq.track('Contact');\n}`);
      }
      if (document.getElementById('ls-meta') && document.getElementById('ls-meta').checked) {
        blocks.push(`// === META/FACEBOOK PIXEL ===
// Initialize Facebook Pixel (put this in HEADER for best results)
if (typeof fbq !== 'undefined') {
  fbq('track', 'PageView'); // Page view tracking
  fbq('track', 'Lead');     // Lead tracking event
} else {
  console.warn('Facebook Pixel (fbq) not found. Make sure FB pixel is properly installed.');
}`);
      }
      if (document.getElementById('ls-triple') && document.getElementById('ls-triple').checked) {
        blocks.push(`// === TRIPLE WHALE TRACKING ===\nwindow.triplewhale && triplewhale.track && triplewhale.track('LeadStreamEvent');`);
      }

      // Form Builders
      if (document.getElementById('ls-wpforms') && document.getElementById('ls-wpforms').checked) {
        blocks.push(`// === WPForms ===\ndocument.addEventListener('wpformsSubmit', function (event) {\n  gtag('event', 'form_submit', {\n    'event_category': '[Your Service/Product] Lead',\n    'event_label': '[Your Service/Product] WPForms Contact Form'\n  });\n});`);
      }
      if (document.getElementById('ls-cf7') && document.getElementById('ls-cf7').checked) {
        blocks.push(`// === CONTACT FORM 7 ===\ndocument.addEventListener('wpcf7mailsent', function(event) {\n  gtag('event', 'form_submit', {\n    'event_category': 'Lead Generation',\n    'event_label': 'Contact Form 7 - ' + event.detail.contactFormId,\n    'value': 1\n  });\n});`);
      }
      if (document.getElementById('ls-gravity') && document.getElementById('ls-gravity').checked) {
        blocks.push(`// === GRAVITY FORMS ===\ndocument.addEventListener('gform_confirmation_loaded', function(event) {\n  gtag('event', 'form_submit', {\n    'event_category': 'Lead Generation',\n    'event_label': 'Gravity Form - ID ' + event.detail.formId,\n    'value': 1\n  });\n});`);
      }
      if (document.getElementById('ls-ninja') && document.getElementById('ls-ninja').checked) {
        blocks.push(`// === NINJA FORMS ===\ndocument.addEventListener('nfFormSubmit', function(event) {\n  gtag('event', 'form_submit', {\n    'event_category': 'Lead Generation',\n    'event_label': 'Ninja Forms - ' + event.detail.formId,\n    'value': 1\n  });\n});`);
      }
      if (document.getElementById('ls-generic') && document.getElementById('ls-generic').checked) {
        blocks.push(`// === GENERIC FORM (Fallback) ===\ndocument.addEventListener('submit', function(event) {\n  if (event.target.tagName === 'FORM') {\n    gtag('event', 'form_submit', {\n      'event_category': 'Form Interaction',\n      'event_label': 'Generic Form Submit'\n    });\n  }\n});`);
      }

      // Determine which textarea to target based on toggle switches
      const headerToggle = document.getElementById('leadstream_inject_header');
      const footerToggle = document.getElementById('leadstream_inject_footer');
      let targetTextarea, targetName;

      if (headerToggle && headerToggle.checked) {
        targetTextarea = document.getElementById('custom_header_js');
        targetName = 'Header';
      } else {
        // Default to footer or if footer toggle is checked
        targetTextarea = document.getElementById('custom_footer_js');
        targetName = 'Footer';
      }

      if (targetTextarea) {
        // Handle both regular textareas and CodeMirror-wrapped textareas
        const codeToInsert = blocks.join('\n\n');
        let codeSet = false;

        // Method 1: Try CodeMirror API if CodeMirror has wrapped this textarea
        if (typeof CodeMirror !== 'undefined' && targetTextarea.nextSibling &&
          targetTextarea.nextSibling.classList && targetTextarea.nextSibling.classList.contains('CodeMirror')) {
          const cmWrapper = targetTextarea.nextSibling;
          if (cmWrapper.CodeMirror) {
            cmWrapper.CodeMirror.setValue(codeToInsert);
            codeSet = true;
          }
        }

        // Method 2: Fallback to regular textarea if CodeMirror method didn't work
        if (!codeSet) {
          targetTextarea.value = codeToInsert;

          // Try to sync with CodeMirror if it exists but we missed it above
          if (typeof CodeMirror !== 'undefined' && targetTextarea.nextSibling &&
            targetTextarea.nextSibling.classList && targetTextarea.nextSibling.classList.contains('CodeMirror')) {
            const cmWrapper = targetTextarea.nextSibling;
            if (cmWrapper.CodeMirror) {
              cmWrapper.CodeMirror.setValue(codeToInsert);
            }
          }
        }

        // Check if tracking pixels are selected and we're targeting Footer
        const hasTrackingPixels = [
          document.getElementById('ls-meta'),
          document.getElementById('ls-tiktok'),
          document.getElementById('ls-ga4')
        ].some(el => el && el.checked);

        let message = `Starter script loaded into ${targetName} JavaScript! Scroll down to customize and save your code.`;

        if (hasTrackingPixels && targetName === 'Footer') {
          message += '\n\n💡 TIP: Many tracking pixels (Facebook, TikTok, GA4) work best when placed in the HEADER for proper initialization. Consider switching to "Header" injection for optimal tracking performance.';
        }

        alert(message);
      } else {
        alert(`Error: Could not find ${targetName} JavaScript textarea.`);
      }
    });
  }

  // === TOGGLE SWITCHES (Header/Footer mutually exclusive) ===
  const headerToggle = document.getElementById('leadstream_inject_header');
  const footerToggle = document.getElementById('leadstream_inject_footer');

  if (headerToggle && footerToggle) {
    headerToggle.addEventListener('change', function () {
      if (headerToggle.checked) footerToggle.checked = false;
    });
    footerToggle.addEventListener('change', function () {
      if (footerToggle.checked) headerToggle.checked = false;
    });
  }
});

// === FAQ FUNCTIONALITY (requires jQuery) ===
jQuery(document).ready(function ($) {
  // Accordion toggle
  $('.ls-accordion-toggle').on('click', function () {
    $(this).toggleClass('active')
      .next('.ls-accordion-panel').slideToggle(200);
  });

  // Copy to header/footer field
  $('.ls-copy-btn').on('click', function () {
    var codeId = $(this).data('copytarget');
    var fieldId = $(this).data('copyfield');
    var code = $('#' + codeId).text().trim();
    var $field = $('#' + fieldId);

    if ($field.length) {
      var targetTextarea = $field[0]; // Get the raw DOM element
      var codeSet = false;

      // Method 1: Try CodeMirror API if CodeMirror has wrapped this textarea
      if (typeof CodeMirror !== 'undefined' && targetTextarea.nextSibling &&
        targetTextarea.nextSibling.classList && targetTextarea.nextSibling.classList.contains('CodeMirror')) {
        const cmWrapper = targetTextarea.nextSibling;
        if (cmWrapper.CodeMirror) {
          cmWrapper.CodeMirror.setValue(code);
          codeSet = true;
        }
      }

      // Method 2: Fallback to regular textarea if CodeMirror method didn't work
      if (!codeSet) {
        $field.val(code);

        // Try to sync with CodeMirror if it exists but we missed it above
        if (typeof CodeMirror !== 'undefined' && targetTextarea.nextSibling &&
          targetTextarea.nextSibling.classList && targetTextarea.nextSibling.classList.contains('CodeMirror')) {
          const cmWrapper = targetTextarea.nextSibling;
          if (cmWrapper.CodeMirror) {
            cmWrapper.CodeMirror.setValue(code);
          }
        }
      }

      $(this).text('Copied!').delay(1000).queue(function (next) {
        $(this).text($(this).data('copyfield') === 'custom_header_js' ? 'Copy to Header' : 'Copy to Footer');
        next();
      });
    } else {
      // Fallback to clipboard API
      navigator.clipboard.writeText(code).then(function () {
        $(this).text('Copied!').delay(1000).queue(function (next) {
          $(this).text('Copy Code');
          next();
        });
      });
    }
  });
});