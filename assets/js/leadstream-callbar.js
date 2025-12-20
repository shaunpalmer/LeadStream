(function () {
  'use strict';
  if (!window.LeadStreamCallBar || !LeadStreamCallBar.enabled) return;

  var opts = LeadStreamCallBar;

  // Basic mobile detection (width) + optional toggle
  function isMobile() {
    return opts.mobileOnly ? (window.matchMedia && window.matchMedia('(max-width: 782px)').matches) : true;
  }

  function parseRules(text) {
    var rules = [];
    if (!text) return rules;
    text.split(/\r?\n/).forEach(function (line) {
      var s = line.trim();
      if (!s || s.startsWith('#')) return;
      var parts = s.split(/=>|->|:=|=/);
      if (parts.length < 2) return;
      var left = parts[0].trim();
      var number = parts.slice(1).join('=').trim();
      if (!number) return;
      // Left can be key=value or free text substring
      var key = 'any'; var val = left;
      var kv = left.split('=');
      if (kv.length === 2) { key = kv[0].trim(); val = kv[1].trim(); }
      rules.push({ key: key.toLowerCase(), val: val, number: number });
    });
    return rules;
  }

  function matchNumber() {
    var rules = parseRules(opts.dniRules);
    var url = new URL(window.location.href);
    var ref = document.referrer || '';

    for (var i = 0; i < rules.length; i++) {
      var r = rules[i];
      switch (r.key) {
        case 'utm_source':
        case 'utm_medium':
        case 'utm_campaign':
        case 'utm_term':
        case 'utm_content':
        case 'campaign':
        case 'source':
          if ((url.searchParams.get(r.key) || '').toLowerCase() === r.val.toLowerCase()) return r.number;
          break;
        case 'ref':
        case 'referrer':
          if (ref && ref.toLowerCase().indexOf(r.val.toLowerCase()) !== -1) return r.number;
          break;
        case 'path':
          if (url.pathname.toLowerCase().indexOf(r.val.toLowerCase()) !== -1) return r.number;
          break;
        case 'any':
        default:
          if (url.href.toLowerCase().indexOf(r.val.toLowerCase()) !== -1 || (ref && ref.toLowerCase().indexOf(r.val.toLowerCase()) !== -1)) return r.number;
          break;
      }
    }
    return opts.defaultNumber || '';
  }

  function createBar(targetNumber) {
    if (!targetNumber) return;
    // If a server-rendered callbar already exists, do not add another
    if (document.querySelector('.ls-callbar__btn')) return;
    var digits = targetNumber.replace(/\D/g, '');
    var bar = document.createElement('div');
    var pos = (opts.position === 'top' ? 'top' : 'bottom');
    var align = (opts.align === 'left' || opts.align === 'right') ? opts.align : 'center';
    bar.className = 'ls-callbar ls-callbar--' + pos + ' ls-callbar--align-' + align;
    bar.innerHTML = '<a id="ls-callbar__btn" class="ls-callbar__btn" href="tel:' + targetNumber + '" data-ls-phone="' + digits + '" data-ls-original="' + targetNumber + '">\n' +
      '  <span class="ls-callbar__cta">' + (opts.cta || 'Call Now') + '</span>\n' +
      '  <span class="ls-callbar__num">' + targetNumber + '</span>\n' +
      '</a>';
    document.body.appendChild(bar);

    // Track click similar to phone-tracking.js
    var a = bar.querySelector('a');
    if (a) {
      var sendBeacon = function (url, fd) {
        try {
          if (navigator.sendBeacon) {
            var params = new URLSearchParams();
            fd.forEach(function (v, k) { params.append(k, v); });
            return navigator.sendBeacon(url, params);
          }
        } catch (e) { }
        return fetch(url, { method: 'POST', body: fd });
      };
      a.addEventListener('click', function () {
        try {
          if (window.gtag) {
            gtag('event', 'phone_click', { event_category: 'Phone', event_label: digits, phone_number: targetNumber, value: 1 });
          }
          // Ajax to WP for logging
          var fd = new FormData();
          fd.append('action', 'leadstream_record_phone_click');
          fd.append('nonce', opts.nonce);
          fd.append('phone', digits);
          fd.append('original_phone', targetNumber);
          fd.append('origin', 'callbar');
          fd.append('element_type', 'callbar');
          fd.append('element_class', 'ls-callbar__btn');
          fd.append('element_id', '');
          fd.append('page_url', window.location.href);
          fd.append('page_title', document.title);
          sendBeacon(opts.ajaxUrl, fd);
        } catch (e) { }
      });
    }
  }

  function init() {
    if (!isMobile()) return;
    var num = matchNumber();
    if (!num) return;
    createBar(num);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
})();

(function () {
  var data = (window.LeadStreamCallBarData || {});
  function ready(fn) { if (document.readyState !== 'loading') { fn() } else { document.addEventListener('DOMContentLoaded', fn) } }

  ready(function () {
    if (window.__LS_CALLBAR_BOUND) return;
    var a = document.querySelector('.ls-callbar__btn');
    if (!a) return;

    a.addEventListener('click', function () {
      try {
        var digits = a.getAttribute('data-ls-phone') || '';
        var original = a.getAttribute('data-ls-original') || '';
        var origin = a.getAttribute('data-ls-origin') || 'callbar';
        var sendBeacon = function (url, fd) {
          try {
            if (navigator.sendBeacon) {
              var params = new URLSearchParams();
              fd.forEach(function (v, k) { params.append(k, v); });
              return navigator.sendBeacon(url, params);
            }
          } catch (e) { }
          return fetch(url, { method: 'POST', body: fd });
        };
        // GA4 (optional)
        if (window.gtag) {
          gtag('event', 'phone_click', {
            event_category: 'Phone', event_label: digits, value: 1, origin: origin
          });
        }
        // AJAX log
        if (data.ajaxUrl && data.nonce) {
          var fd = new FormData();
          fd.append('action', 'leadstream_record_phone_click');
          fd.append('nonce', data.nonce);
          fd.append('phone', digits);
          fd.append('original_phone', original);
          fd.append('origin', origin);
          // Provide element context for completeness
          fd.append('element_type', 'callbar');
          fd.append('element_class', 'ls-callbar__btn');
          fd.append('element_id', 'ls-callbar__btn');
          fd.append('page_url', window.location.href);
          fd.append('page_title', document.title);
          sendBeacon(data.ajaxUrl, fd);
        }
      } catch (e) { /* no-op */ }
    }, { passive: true });
    window.__LS_CALLBAR_BOUND = true;
  });
})();
