(function () {
  'use strict';
  if (!window.LeadStreamCallBar || !LeadStreamCallBar.enabled) return;

  var opts = LeadStreamCallBar;

  // ---- Minimal click context collector (attribution + environment) ----
  var LS_LANDING_URL_KEY = 'ls_landing_page_url';
  var LS_SESSION_START_KEY = 'ls_session_start_ms';
  (function initLandingAndSession() {
    try {
      if (!localStorage.getItem(LS_LANDING_URL_KEY)) {
        localStorage.setItem(LS_LANDING_URL_KEY, window.location.href);
      }
      if (!localStorage.getItem(LS_SESSION_START_KEY)) {
        localStorage.setItem(LS_SESSION_START_KEY, String(Date.now()));
      }
    } catch (e) { /* noop */ }
  })();

  function _lsParseTracking(url) {
    var out = { utm_source: '', utm_medium: '', utm_campaign: '', utm_term: '', utm_content: '', gclid: '', fbclid: '', msclkid: '', ttclid: '' };
    try {
      var u = new URL(url, window.location.origin);
      Object.keys(out).forEach(function (k) {
        var v = u.searchParams.get(k);
        if (v) out[k] = String(v).slice(0, 255);
      });
    } catch (e) { /* noop */ }
    return out;
  }

  function _lsGetDeviceType() {
    try {
      if (window.matchMedia && window.matchMedia('(pointer:coarse)').matches) return 'mobile';
      var ua = (navigator && navigator.userAgent) ? navigator.userAgent : '';
      if (/Mobi|Android|iPhone|iPad|iPod/i.test(ua)) return 'mobile';
    } catch (e) { /* noop */ }
    return 'desktop';
  }

  function _lsReadCookie(name) {
    try {
      var all = document.cookie || '';
      var parts = all.split(';');
      for (var i = 0; i < parts.length; i++) {
        var p = parts[i].trim();
        if (!p) continue;
        if (p.indexOf(name + '=') === 0) {
          return decodeURIComponent(p.substring(name.length + 1));
        }
      }
    } catch (e) { /* noop */ }
    return '';
  }

  function _lsExtractGaKeys() {
    var out = { ga_client_id: '', ga_session_id: '', ga_session_number: '' };
    try {
      var ga = _lsReadCookie('_ga');
      if (ga) {
        var p = String(ga).split('.');
        if (p.length >= 4) {
          out.ga_client_id = String(p[p.length - 2] + '.' + p[p.length - 1]).slice(0, 64);
        }
      }
    } catch (e) { /* noop */ }

    try {
      var cookies = (document.cookie || '').split(';');
      for (var i = 0; i < cookies.length; i++) {
        var c = cookies[i].trim();
        if (!c) continue;
        if (c.indexOf('_ga_') !== 0) continue;
        var eq = c.indexOf('=');
        if (eq === -1) continue;
        var val = decodeURIComponent(c.substring(eq + 1));
        if (val.indexOf('GS1.') !== 0) continue;
        var parts = String(val).split('.');
        if (parts.length >= 4) {
          var sid = parseInt(parts[2], 10);
          var sn = parseInt(parts[3], 10);
          if (sid && sid > 0) out.ga_session_id = String(sid);
          if (sn && sn > 0) out.ga_session_number = String(sn);
        }
        break;
      }
    } catch (e) { /* noop */ }
    return out;
  }

  function _lsGetClickContext() {
    var landing = '';
    var startMs = 0;
    try {
      landing = localStorage.getItem(LS_LANDING_URL_KEY) || '';
      startMs = Number(localStorage.getItem(LS_SESSION_START_KEY) || 0);
    } catch (e) { /* noop */ }

    var now = Date.now();
    var timeToClick = (startMs && now > startMs) ? (now - startMs) : 0;
    var currentTracking = _lsParseTracking(window.location.href);
    var landingTracking = landing ? _lsParseTracking(landing) : null;
    var tracking = Object.assign({}, currentTracking);
    if (landingTracking) {
      Object.keys(tracking).forEach(function (k) {
        if (!tracking[k] && landingTracking[k]) tracking[k] = landingTracking[k];
      });
    }
    return Object.assign(tracking, {
      landing_page: landing || '',
      time_to_click_ms: timeToClick,
      client_language: (navigator && navigator.language) ? String(navigator.language).slice(0, 32) : '',
      viewport_w: window.innerWidth || 0,
      viewport_h: window.innerHeight || 0,
      device_type: _lsGetDeviceType()
    }, _lsExtractGaKeys());
  }

  function _lsBroadcastToGTM(clickContext) {
    try {
      window.dataLayer = window.dataLayer || [];
      if (!Array.isArray(window.dataLayer) || typeof window.dataLayer.push !== 'function') return;
      window.dataLayer.push({
        event: 'ls_conversion',
        event_id: String(clickContext.event_id || ''),
        ls_event_data: {
          action: String(clickContext.link_type || 'phone'),
          label: String(clickContext.link_key || ''),
          location: String(clickContext.page_url || ''),
          source: String(clickContext.utm_source || ''),
          gclid: String(clickContext.gclid || ''),
          event_id: String(clickContext.event_id || '')
        }
      });
    } catch (e) { /* noop */ }
  }

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
        } catch (e) { /* noop */ }
        return fetch(url, { method: 'POST', body: fd });
      };
      a.addEventListener('click', function () {
        try {
          // Stable-ish event id for cross-channel de-dupe (GTM + AJAX + server-side).
          var eventId = (function () {
            try {
              var rand = Math.random().toString(16).slice(2);
              return ['phone_click', String(digits || ''), String(window.location.href || ''), String(Date.now()), rand].join('|');
            } catch (e) { return String(Date.now()); }
          })();

          // Broadcast to GTM dataLayer immediately (sync)
          try {
            var ctx0 = _lsGetClickContext();
            _lsBroadcastToGTM(Object.assign({}, ctx0, {
              link_type: 'phone',
              link_key: digits,
              page_url: window.location.href,
              origin: 'callbar',
              event_id: eventId
            }));
          } catch (e) { /* noop */ }

          // Client GA4 (optional): if GTM dataLayer exists, assume container can forward and skip gtag().
          var hasDataLayer = Array.isArray(window.dataLayer) && typeof window.dataLayer.push === 'function';
          if (!hasDataLayer && window.gtag) {
            try {
              gtag('event', 'phone_click', { event_category: 'Phone', event_label: digits, phone_number: targetNumber, value: 1, event_id: eventId });
            } catch (e) { /* noop */ }
          }
          // Ajax to WP for logging
          var fd = new FormData();
          fd.append('action', 'leadstream_record_phone_click');
          fd.append('nonce', opts.nonce);
          fd.append('event_id', eventId);
          fd.append('phone', digits);
          fd.append('original_phone', targetNumber);
          fd.append('origin', 'callbar');
          fd.append('element_type', 'callbar');
          fd.append('element_class', 'ls-callbar__btn');
          fd.append('element_id', '');
          fd.append('page_url', window.location.href);
          fd.append('page_title', document.title);

          try {
            var ctx = _lsGetClickContext();
            Object.keys(ctx).forEach(function (k) {
              if (ctx[k] !== null && ctx[k] !== undefined && String(ctx[k]) !== '') {
                fd.append(k, String(ctx[k]));
              }
            });
          } catch (e) { /* noop */ }

          sendBeacon(opts.ajaxUrl, fd);
        } catch (e) { /* noop */ }
      });

      // Prevent the legacy second listener (below) from binding.
      window.__LS_CALLBAR_BOUND = true;
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
  function ready(fn) { if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }

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

        // Broadcast to GTM dataLayer immediately (sync)
        try {
          var ctx0 = _lsGetClickContext();
          _lsBroadcastToGTM(Object.assign({}, ctx0, {
            link_type: 'phone',
            link_key: digits,
            page_url: window.location.href,
            origin: origin
          }));
        } catch (e) { /* noop */ }
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

          try {
            var ctx = _lsGetClickContext();
            Object.keys(ctx).forEach(function (k) {
              if (ctx[k] !== null && ctx[k] !== undefined && String(ctx[k]) !== '') {
                fd.append(k, String(ctx[k]));
              }
            });
          } catch (e) { /* noop */ }

          sendBeacon(data.ajaxUrl, fd);
        }
      } catch (e) { /* noop */ }
    }, { passive: true });
    window.__LS_CALLBAR_BOUND = true;
  });
})();
