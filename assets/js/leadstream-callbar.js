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
    var digits = targetNumber.replace(/\D/g, '');
    var bar = document.createElement('div');
    bar.className = 'ls-callbar ls-callbar--' + (opts.position === 'top' ? 'top' : 'bottom');
    bar.innerHTML = '<a class="ls-callbar__btn" href="tel:' + targetNumber + '" data-ls-phone="' + digits + '">\n' +
      '  <span class="ls-callbar__cta">' + (opts.cta || 'Call Now') + '</span>\n' +
      '  <span class="ls-callbar__num">' + targetNumber + '</span>\n' +
      '</a>';
    document.body.appendChild(bar);

    // Track click similar to phone-tracking.js
    var a = bar.querySelector('a');
    if (a) {
      a.addEventListener('click', function () {
        try {
          if (window.gtag) {
            gtag('event', 'phone_click', { event_category: 'Phone', event_label: digits, phone_number: targetNumber, value: 1 });
          }
          // Ajax to WP for logging
          var fd = new FormData();
          fd.append('action', 'leadstream_record_phone_click');
          fd.append('phone', digits);
          fd.append('original_phone', targetNumber);
          fd.append('element_type', 'callbar');
          fd.append('element_class', 'ls-callbar__btn');
          fd.append('element_id', '');
          fd.append('page_url', window.location.href);
          fd.append('page_title', document.title);
          fd.append('nonce', opts.nonce);
          fetch(opts.ajaxUrl, { method: 'POST', body: fd });
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
