/* LeadStream Event Listener
 * Passive listener for dataLayer / gtag events and custom events
 */
(function (window, document) {
  'use strict';

  function postEvent(payload) {
    try {
      var fd = new FormData();
      fd.append('action', 'leadstream_log_event');
      fd.append('payload', JSON.stringify(payload));
      if (window.leadstream && window.leadstream.event_nonce) {
        fd.append('nonce', window.leadstream.event_nonce);
      }

      navigator.sendBeacon && typeof navigator.sendBeacon === 'function'
        ? navigator.sendBeacon(window.leadstream.ajax_url + '?action=leadstream_log_event', JSON.stringify(payload))
        : fetch(window.leadstream.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' });
    } catch (e) {
      // fail silently
    }
  }

  // Listen to dataLayer pushes
  try {
    if (window.dataLayer && Array.isArray(window.dataLayer)) {
      var origPush = window.dataLayer.push;
      window.dataLayer.push = function () {
        try {
          var args = Array.prototype.slice.call(arguments);
          if (args.length) {
            var evt = args[0];
            postEvent({ name: evt.event || evt[0] || 'dataLayer.push', params: evt });
          }
        } catch (e) { }
        return origPush.apply(window.dataLayer, arguments);
      };
    }
  } catch (e) { }

  // Listen to gtag if available
  try {
    if (typeof window.gtag === 'function') {
      var origGtag = window.gtag;
      window.gtag = function () {
        try {
          var args = Array.prototype.slice.call(arguments);
          postEvent({ name: args[0] || 'gtag', params: args.slice(1) });
        } catch (e) { }
        return origGtag.apply(window, arguments);
      };
    }
  } catch (e) { }

  // Generic custom event listener for 'ls_event' CustomEvent
  document.addEventListener('ls_event', function (e) {
    try {
      var detail = e && e.detail ? e.detail : {};
      postEvent({ name: detail.name || 'ls_event', params: detail });
    } catch (er) { }
  }, { passive: true });

})(window, document);
