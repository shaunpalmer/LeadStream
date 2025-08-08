/**
 * LeadStream UTM Builder JavaScript
 * Handles UTM parameter generation via AJAX
 */

jQuery(document).ready(function ($) {
  'use strict';

  // Cache jQuery objects for better performance
  const $generateBtn = $('#generate-utm');
  const $clearBtn = $('#clear-utm');
  const $copyBtn = $('#copy-utm-url');
  const $form = $('#utm-builder-form');
  const $result = $('#utm-result');
  const $generatedUrl = $('#utm-generated-url');
  const $copyFeedback = $('#utm-copy-feedback');

  // Load saved settings on page load
  loadSavedSettings();

  // Generate UTM URL button
  $generateBtn.on('click', function (e) {
    e.preventDefault();

    // Get form data
    const formData = {
      action: 'generate_utm',
      nonce: leadstream_utm_ajax.nonce,
      base_url: $('#utm-url').val().trim(),
      utm_source: $('#utm-source').val().trim(),
      utm_medium: $('#utm-medium').val().trim(),
      utm_campaign: $('#utm-campaign').val().trim(),
      utm_term: $('#utm-term').val().trim(),
      utm_content: $('#utm-content').val().trim(),
      utm_button: $('#utm-button').val().trim()
    };    // Validate required fields
    if (!formData.base_url || !formData.utm_source || !formData.utm_medium || !formData.utm_campaign) {
      alert('Please fill in all required fields (marked with *)');
      return;
    }

    // Enhanced URL validation
    try {
      const url = new URL(formData.base_url);
      if (!['http:', 'https:'].includes(url.protocol)) {
        alert('URL must use http:// or https:// protocol');
        return;
      }
    } catch (err) {
      alert('Please enter a valid URL (including http:// or https://)');
      return;
    }

    // Validate UTM parameters for special characters
    const paramFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_button'];
    for (const field of paramFields) {
      if (formData[field] && /[<>"']/.test(formData[field])) {
        alert(`Invalid character in ${field.replace('utm_', '')}. Please avoid quotes and HTML characters.`);
        return;
      }
    }

    // Show loading state
    $generateBtn.prop('disabled', true).text('Generating...');
    $result.hide(); // Hide previous results

    // Make AJAX request
    $.post(leadstream_utm_ajax.ajax_url, formData)
      .done(function (response) {
        if (response.success && response.data) {
          $generatedUrl.val(response.data);

          // Save settings for next time
          saveCurrentSettings(formData);

          // Add to history
          addToHistory(response.data, formData);

          // Populate UTM breakdown
          populateUTMBreakdown(response.data, formData);

          $result.slideDown(300);
        } else {
          alert('Error generating UTM URL: ' + (response.data || 'Unknown error'));
        }
      })
      .fail(function (xhr, status, error) {
        console.error('AJAX Error:', status, error);
        alert('Error: Could not connect to server. Please try again.');
      })
      .always(function () {
        $generateBtn.prop('disabled', false).text('Generate UTM URL');
      });
  });

  // Copy URL button
  $copyBtn.on('click', function () {
    const urlText = $generatedUrl.val();

    if (!urlText) {
      alert('No URL to copy. Please generate a URL first.');
      return;
    }

    // Auto-select the textarea content for visual feedback
    $generatedUrl[0].select();
    $generatedUrl[0].setSelectionRange(0, 99999);

    // Modern Clipboard API (preferred)
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(urlText)
        .then(function () {
          showCopyFeedback();
        })
        .catch(function (err) {
          console.error('Clipboard API failed:', err);
          fallbackCopy(urlText);
        });
    } else {
      fallbackCopy(urlText);
    }
  });

  // Clear form button
  $clearBtn.on('click', function () {
    if (confirm('Are you sure you want to clear all fields?')) {
      $form[0].reset();
      $result.slideUp(300);
    }
  });

  // Helper function for fallback copy
  function fallbackCopy(text) {
    try {
      const urlField = $generatedUrl[0];
      urlField.select();
      urlField.setSelectionRange(0, 99999); // For mobile devices

      const successful = document.execCommand('copy');
      if (successful) {
        showCopyFeedback();
      } else {
        throw new Error('execCommand failed');
      }
    } catch (err) {
      console.error('Copy failed:', err);

      // Final fallback - show the text for manual copying
      prompt('Copy this URL manually (Ctrl+C):', text);
    }
  }

  // Test URL button
  $(document).on('click', '#open-utm-url', function () {
    const urlText = $generatedUrl.val();
    if (urlText) {
      window.open(urlText, '_blank');
    } else {
      alert('No URL to test. Please generate a URL first.');
    }
  });

  // Function to populate UTM breakdown
  function populateUTMBreakdown(fullUrl, formData) {
    const $paramsList = $('#utm-params-list');
    let html = '';

    // Parse URL to extract parameters
    try {
      const url = new URL(fullUrl);
      const params = new URLSearchParams(url.search);

      // Define parameter descriptions
      const paramDescriptions = {
        'utm_source': 'Traffic source (platform, website, or referrer)',
        'utm_medium': 'Marketing channel (paid-social, email, ppc, organic, etc.)',
        'utm_campaign': 'Campaign name (consistent across all channels)',
        'utm_term': 'Target keywords (for paid search campaigns)',
        'utm_content': 'Content type or ad variation',
        'utm_button': 'Specific call-to-action or link clicked'
      };

      // Build breakdown HTML
      params.forEach((value, key) => {
        if (key.startsWith('utm_') && value) {
          const description = paramDescriptions[key] || 'Custom UTM parameter';
          html += `<div style="margin-bottom: 8px;">
            <strong>${key}:</strong> <span style="color: #0073aa;">${value}</span>
            <br><small style="color: #666;">${description}</small>
          </div>`;
        }
      });

      if (html) {
        $paramsList.html(html);
      } else {
        $paramsList.html('<em>No UTM parameters found</em>');
      }

    } catch (e) {
      $paramsList.html('<em>Could not parse URL parameters</em>');
    }
  }

  // Helper function to show copy feedback
  function showCopyFeedback() {
    // Change button text temporarily
    const originalText = $copyBtn.text();
    $copyBtn.text('✅ Copied!').addClass('button-primary');

    // Show the feedback span
    $copyFeedback.stop(true, true).show().delay(2000).fadeOut(500);

    // Reset button after 2 seconds
    setTimeout(function () {
      $copyBtn.text(originalText).removeClass('button-primary');
    }, 2000);
  }

  // Auto-hide result when form inputs change
  $form.find('input').on('input', function () {
    if ($result.is(':visible')) {
      $result.slideUp(300);
    }
  });

  // Auto-select textarea content when clicked (better UX)
  $(document).on('click', '#utm-generated-url', function () {
    this.select();
    this.setSelectionRange(0, 99999); // For mobile devices
  });

  // Auto-select textarea content when focused (keyboard navigation)
  $(document).on('focus', '#utm-generated-url', function () {
    this.select();
  });

  // Save current form settings to localStorage
  function saveCurrentSettings(formData) {
    try {
      const settingsToSave = {
        utm_source: formData.utm_source,
        utm_medium: formData.utm_medium,
        utm_campaign: formData.utm_campaign,
        utm_term: formData.utm_term,
        utm_content: formData.utm_content,
        utm_button: formData.utm_button,
        timestamp: Date.now()
      };
      localStorage.setItem('leadstream_utm_settings', JSON.stringify(settingsToSave));
    } catch (e) {
      console.warn('Could not save UTM settings:', e);
    }
  }

  // Load saved settings from localStorage
  function loadSavedSettings() {
    try {
      const saved = localStorage.getItem('leadstream_utm_settings');
      if (saved) {
        const settings = JSON.parse(saved);

        // Only load if saved within last 30 days
        if (Date.now() - settings.timestamp < 30 * 24 * 60 * 60 * 1000) {
          $('#utm-source').val(settings.utm_source || '');
          $('#utm-medium').val(settings.utm_medium || '');
          $('#utm-campaign').val(settings.utm_campaign || '');
          $('#utm-term').val(settings.utm_term || '');
          $('#utm-content').val(settings.utm_content || '');
          $('#utm-button').val(settings.utm_button || '');
        }
      }
    } catch (e) {
      console.warn('Could not load UTM settings:', e);
    }
  }

  // Add generated URL to history
  function addToHistory(url, formData) {
    try {
      let history = JSON.parse(localStorage.getItem('leadstream_utm_history') || '[]');

      const historyItem = {
        url: url,
        source: formData.utm_source,
        medium: formData.utm_medium,
        campaign: formData.utm_campaign,
        term: formData.utm_term,
        content: formData.utm_content,
        button: formData.utm_button,
        timestamp: Date.now(),
        date: new Date().toLocaleDateString()
      };

      // Add to beginning of array
      history.unshift(historyItem);

      // Keep only last 10 items
      history = history.slice(0, 10);

      localStorage.setItem('leadstream_utm_history', JSON.stringify(history));
      updateHistoryDisplay();
    } catch (e) {
      console.warn('Could not save to history:', e);
    }
  }

  // Update history display (if history panel exists)
  function updateHistoryDisplay() {
    // This will be implemented when we add the history panel
  }
});
