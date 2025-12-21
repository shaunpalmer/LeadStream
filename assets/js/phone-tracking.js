(function () {
  'use strict';

  // ==== LeadStream admin-only badge hard guard ====
  // Absolutely never show badge to public visitors.
  (function () {
    const ls = window.LeadStreamPhone || {};
    const isAdmin = Number(ls.isAdmin) === 1;
    const allowBadge = isAdmin && Number(ls.debugBadge) === 1;

    // DEBUG: Log badge decision for troubleshooting
    console.log('LS Badge Guard:', { isAdmin, debugBadge: Number(ls.debugBadge), allowBadge });

    // If something already injected the badge, nuke it unless both gates pass.
    const stray = document.getElementById('ls-phone-badge');
    if (!allowBadge && stray) {
      console.log('LS Badge Guard: Removing stray badge');
      try { stray.remove(); } catch (_) { /* noop */ }
    }

    // Global kill-switch for any badge code below.
    if (!allowBadge) {
      console.log('LS Badge Guard: Blocking badge render (not admin or debug disabled)');
      // Ensure any later accidental calls do nothing.
      window.__LS_RENDER_BADGE__ = function () { console.log('LS Badge Guard: Render blocked'); };
      return;
    }

    console.log('LS Badge Guard: Allowing badge render');

    // Only define the real renderer when allowed.
    window.__LS_RENDER_BADGE__ = function renderLsBadge() {
      console.log('LS Badge Render: Starting render...');
      if (document.getElementById('ls-phone-badge')) {
        console.log('LS Badge Render: Badge already exists, skipping');
        return;
      }

      console.log('LS Badge Render: Creating badge element');
      const wrap = document.createElement('div');
      wrap.id = 'ls-phone-badge';
      wrap.role = 'status';
      wrap.style.cssText = 'position:fixed;z-index:2147483647;bottom:12px;right:12px;background:#1d2327;color:#fff;padding:8px 10px;border-radius:6px;font:12px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.25);min-width:120px;';
      wrap.innerHTML = '<div><strong>LeadStream</strong><br><span>Phone tracking active</span></div>';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ls-phone-badge-close';
      btn.setAttribute('aria-label', 'Dismiss debug badge');
      btn.style.cssText = 'background:transparent;border:none;color:#fff;font-size:18px;cursor:pointer;padding:0;position:absolute;top:6px;right:8px;line-height:1;';
      btn.textContent = '×';
      btn.addEventListener('click', () => wrap.remove());
      wrap.appendChild(btn);

      document.body.appendChild(wrap);
      console.log('LS Badge Render: Badge created and appended');
    };

    // Render now (or let your existing code call __LS_RENDER_BADGE__ when ready)
    window.__LS_RENDER_BADGE__();
  })();

  // Resolve AJAX endpoint robustly across naming conventions
  function getAjaxUrl() {
    try {
      if (window.LeadStreamConfig && window.LeadStreamConfig.ajaxUrl) {
        return window.LeadStreamConfig.ajaxUrl;
      }
      if (window.LeadStreamPhone) {
        return LeadStreamPhone.ajaxUrl || LeadStreamPhone.ajax_url || window.ajaxurl || null;
      }
    } catch (e) { /* noop */ }
    return window.ajaxurl || null;
  }

  // Smart sender: try fetch with keepalive; fall back to sendBeacon when page hides
  function sendSmart(url, params) {
    var sent = false;
    var body = (typeof params === 'string') ? params : String(new URLSearchParams(params));
    try {
      fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body,
        credentials: 'same-origin',
        keepalive: true
      }).then(function () { sent = true; }).catch(function () { /* noop */ });
    } catch (e) { }
    var onHide = function () {
      try {
        if (!sent && navigator.sendBeacon) {
          navigator.sendBeacon(url, body);
        }
      } catch (e) { /* noop */ }
    };
    document.addEventListener('visibilitychange', onHide, { once: true });
    window.addEventListener('pagehide', onHide, { once: true });
  }
  // Beacon helper with fetch fallback
  function _lsSend(url, fd) {
    try {
      if (navigator.sendBeacon) {
        var params = new URLSearchParams();
        fd.forEach(function (v, k) { params.append(k, v); });
        return navigator.sendBeacon(url, params);
      }
    } catch (e) { /* fall through */ }
    return fetch(url, { method: 'POST', body: fd });
  }

  // Exit if no phone numbers to track
  if (!LeadStreamPhone || !LeadStreamPhone.numbers || !LeadStreamPhone.numbers.length) {
    return;
  }

  /**
   * Clean, robust phone tracking following the "tel: links first" approach
   * No CSS selectors required - works with any tel: link automatically
   * Optional CSS selectors for enhanced accuracy when needed
   */

  // Get normalized phone numbers (digits only) from settings for matching only
  const trackedNumbers = (LeadStreamPhone.numbers || []).map(num => String(num).replace(/\D/g, ''));
  // Optional alternate variants (national vs E.164) provided by PHP for better matching
  const altNumbers = (LeadStreamPhone.altNumbers || []).map(num => String(num).replace(/\D/g, ''));
  // Default to sane selectors when not provided
  const selectorString = (LeadStreamPhone.selectors && LeadStreamPhone.selectors.trim()) ? LeadStreamPhone.selectors : 'a[href^="tel:"], .ls-callbar a';
  const customSelectors = selectorString.split('\n').map(s => s.trim()).filter(Boolean);

  // ---- Minimal click context collector (attribution + environment) ----
  const LS_LANDING_URL_KEY = 'ls_landing_page_url';
  const LS_SESSION_START_KEY = 'ls_session_start_ms';

  (function initLandingAndSession() {
    try {
      if (!localStorage.getItem(LS_LANDING_URL_KEY)) {
        localStorage.setItem(LS_LANDING_URL_KEY, window.location.href);
      }
      if (!localStorage.getItem(LS_SESSION_START_KEY)) {
        localStorage.setItem(LS_SESSION_START_KEY, String(Date.now()));
      }
    } catch (_) { /* noop */ }
  })();

  function _lsParseTracking(url) {
    const out = {
      utm_source: '',
      utm_medium: '',
      utm_campaign: '',
      utm_term: '',
      utm_content: '',
      gclid: '',
      fbclid: '',
      msclkid: '',
      ttclid: ''
    };
    try {
      const u = new URL(url, window.location.origin);
      Object.keys(out).forEach(k => {
        const v = u.searchParams.get(k);
        if (v) out[k] = String(v).slice(0, 255);
      });
    } catch (_) { /* noop */ }
    return out;
  }

  function _lsGetDeviceType() {
    try {
      if (window.matchMedia && window.matchMedia('(pointer:coarse)').matches) return 'mobile';
      const ua = (navigator && navigator.userAgent) ? navigator.userAgent : '';
      if (/Mobi|Android|iPhone|iPad|iPod/i.test(ua)) return 'mobile';
    } catch (_) { /* noop */ }
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
    } catch (_) { /* noop */ }
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
    } catch (_) { /* noop */ }

    // Find any GA4 session cookie: _ga_* = GS1.1.<session_id>.<session_number>...
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
    } catch (_) { /* noop */ }
    return out;
  }

  /**
   * LeadStream Instant GTM Push
   * Fires synchronously before the browser handles the link.
   */
  function _lsBroadcastToGTM(clickContext) {
    try {
      window.dataLayer = window.dataLayer || [];
      if (!Array.isArray(window.dataLayer) || typeof window.dataLayer.push !== 'function') return;
      window.dataLayer.push({
        event: 'ls_conversion',
        ls_event_data: {
          action: String(clickContext.link_type || 'phone'),
          label: String(clickContext.link_key || ''),
          location: String(clickContext.page_url || ''),
          source: String(clickContext.utm_source || ''),
          gclid: String(clickContext.gclid || '')
        }
      });
    } catch (_) { /* noop */ }
  }

  function _lsGetClickContext() {
    let landing = '';
    let startMs = 0;
    try {
      landing = localStorage.getItem(LS_LANDING_URL_KEY) || '';
      startMs = Number(localStorage.getItem(LS_SESSION_START_KEY) || 0);
    } catch (_) { /* noop */ }

    const now = Date.now();
    const timeToClick = (startMs && now > startMs) ? (now - startMs) : 0;

    const currentTracking = _lsParseTracking(window.location.href);
    const landingTracking = landing ? _lsParseTracking(landing) : null;

    const tracking = Object.assign({}, currentTracking);
    if (landingTracking) {
      Object.keys(tracking).forEach(k => {
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

  /**
   * Check if a phone number matches our tracked numbers
   */
  function isTrackedNumber(phoneString) {
    const normalized = String(phoneString).replace(/\D/g, ''); // digits only for compare
    const haystacks = trackedNumbers.concat(altNumbers);

    const lastN = (s, n) => (s.length >= n ? s.slice(-n) : s);
    const last7 = lastN(normalized, 7);
    const last8 = lastN(normalized, 8);

    return haystacks.some(tracked => {
      if (!tracked) return false;
      if (normalized === tracked) return true;
      // Mutual containment (legacy)
      if (normalized.includes(tracked) || tracked.includes(normalized)) return true;
      // Trailing digit match (handles national vs international variants)
      const t7 = lastN(tracked, 7);
      const t8 = lastN(tracked, 8);
      return (last7 && t7 && last7 === t7) || (last8 && t8 && last8 === t8);
    });
  }

  /**
   * Send phone click to analytics and database
   */
  function recordPhoneClick(phoneNumber, element) {
    const normalizedPhone = String(phoneNumber).replace(/\D/g, '');
    const origin = (element && element.getAttribute && element.getAttribute('data-ls-origin')) || 'tel';

    // Stable-ish event id for cross-channel de-dupe (GTM + AJAX + server-side).
    // Must be synchronous so it is included in the AJAX payload.
    const eventId = (() => {
      try {
        const rand = Math.random().toString(16).slice(2);
        const base = [
          'phone_click',
          normalizedPhone,
          window.location.href,
          Date.now(),
          rand
        ].join('|');
        return base;
      } catch (e) {
        return String(Date.now());
      }
    })();

    // 0) Broadcast to GTM dataLayer immediately (sync)
    try {
      const ctx0 = _lsGetClickContext();
      _lsBroadcastToGTM(Object.assign({}, ctx0, {
        link_type: 'phone',
        link_key: normalizedPhone,
        page_url: window.location.href,
        origin: origin,
        event_id: eventId
      }));
    } catch (_) { /* noop */ }

    // 1) Client GA4 (optional): if GTM dataLayer exists, assume container can forward and skip gtag().
    const hasDataLayer = Array.isArray(window.dataLayer) && typeof window.dataLayer.push === 'function';
    if (!hasDataLayer && window.gtag && LeadStreamPhone.ga_id) {
      try {
        gtag('event', 'phone_click', {
          event_category: 'Phone',
          event_label: normalizedPhone,
          phone_number: phoneNumber,
          value: 1,
          event_id: eventId
        });
      } catch (_) { /* noop */ }
    }

    // 2) Send to WordPress database with smart sender (fetch keepalive + beacon on hide)
    const p = new URLSearchParams();
    p.append('action', 'leadstream_record_phone_click');
    p.append('phone', normalizedPhone);
    p.append('original_phone', phoneNumber);
    p.append('element_type', element.tagName.toLowerCase());
    p.append('element_class', element.className || '');
    p.append('element_id', element.id || '');
    p.append('page_url', window.location.href);
    p.append('page_title', document.title);
    p.append('origin', origin);
    // Include event_id for server-side dedupe across strategies.
    if (eventId) p.append('event_id', String(eventId));

    // Enriched attribution + environment context
    try {
      const ctx = _lsGetClickContext();
      Object.keys(ctx).forEach(k => {
        if (ctx[k] !== null && ctx[k] !== undefined && String(ctx[k]) !== '') {
          p.append(k, String(ctx[k]));
        }
      });
    } catch (_) { /* noop */ }

    if (LeadStreamPhone && LeadStreamPhone.nonce) {
      p.append('nonce', LeadStreamPhone.nonce);
      p.append('_ajax_nonce', LeadStreamPhone.nonce); // compatibility
    }
    try { sendSmart(getAjaxUrl(), p); } catch (error) { try { console.warn('LeadStream: sendSmart failed', error); } catch (e) { } }

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
    // Admin-only floating badge for quick confirmation
    // Badge rendering is now handled by the secure __LS_RENDER_BADGE__ function above
    // which requires both isAdmin=1 AND debugBadge=1 from server-side localization

    // 1) Track ALL tel: links automatically (no selectors needed)
    document.querySelectorAll('a[href^="tel:"]').forEach(element => {
      const href = element.href;
      const phoneNumber = href.replace('tel:', '').trim();

      // Only track if this number is in our configuration
      if (isTrackedNumber(phoneNumber)) {
        element.addEventListener('click', () => {
          // Tag origin for consistency
          element.setAttribute('data-ls-origin', 'tel');
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
            if (/[\d\-()\.\+\s]{10,}/.test(text)) {
              phoneNumber = text;
            }
          }

          if (phoneNumber && isTrackedNumber(phoneNumber)) {
            element.addEventListener('click', () => {
              element.setAttribute('data-ls-origin', 'tel');
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
