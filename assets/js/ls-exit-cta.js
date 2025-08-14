(function () {
  // -------------------
  // Helpers
  // -------------------
  const start = Date.now();
  let maxScroll = 0;       // 0-100
  let formTouched = false; // typed in any form?

  function percentScroll() {
    const h = Math.max(
      document.body.scrollHeight, document.documentElement.scrollHeight,
      document.body.offsetHeight, document.documentElement.offsetHeight,
      document.body.clientHeight, document.documentElement.clientHeight
    );
    const win = window.innerHeight || document.documentElement.clientHeight;
    const y = window.scrollY || window.pageYOffset || 0;
    const pct = Math.round(100 * Math.min(1, (y + win) / h));
    return pct;
  }

  function classifyLink(a) {
    try {
      const url = new URL(a.href, location.href);
      if (url.protocol === 'tel:') return 'tel_click';
      if (url.protocol === 'mailto:') return 'mailto_click';
      const ext = (url.pathname.split('.').pop() || '').toLowerCase();
      if (['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip', 'ppt', 'pptx'].includes(ext)) return 'download';
      if (url.hostname !== location.hostname) return 'external_link';
    } catch (_) { }
    return 'internal_nav';
  }

  function beacon(path, reason, extra) {
    const payload = JSON.stringify(Object.assign({
      reason,
      path: path || (location.pathname + location.search),
      dwell_ms: Date.now() - start,
      scroll_pct: maxScroll,
      form_touched: formTouched ? 1 : 0
    }, extra || {}));
    if (navigator.sendBeacon) {
      navigator.sendBeacon('/wp-json/leadstream/v1/exit', payload);
    } else {
      // last-resort fallback (not guaranteed on unload)
      fetch('/wp-json/leadstream/v1/exit', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: payload, keepalive: true
      });
    }
  }

  // -------------------
  // Scroll/depth tracking
  // -------------------
  document.addEventListener('scroll', () => {
    const pct = percentScroll();
    if (pct > maxScroll) maxScroll = pct;
  }, { passive: true });

  // -------------------
  // Form touch tracking
  // -------------------
  document.addEventListener('input', (e) => {
    if (!formTouched && e.target && e.target.closest('form')) formTouched = true;
  }, { passive: true });

  // -------------------
  // Link classification
  // -------------------
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a) return;

    const kind = classifyLink(a);
    // If they click tel/mailto/download/external, send reason BEFORE navigation
    if (kind !== 'internal_nav') {
      beacon(a.getAttribute('href'), kind, { label: a.getAttribute('data-ls-cta') || a.textContent.trim().slice(0, 80) });
    }
  }, true);

  // -------------------
  // CTA beacons (explicit)
  // -------------------
  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-ls-cta]');
    if (!el) return;
    const label = el.getAttribute('data-ls-cta') || '';
    const type = el.getAttribute('data-ls-type') || 'cta';
    const body = JSON.stringify({ label, type });
    if (navigator.sendBeacon) {
      navigator.sendBeacon('/wp-json/leadstream/v1/cta', body);
    } else {
      fetch('/wp-json/leadstream/v1/cta', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true });
    }
  }, true);

  // -------------------
  // Exit on pagehide/hidden (unknown exit)
  // -------------------
  let sent = false;
  function sendExit(reason) {
    if (sent) return; sent = true;
    // Heuristics: “read_exit” if they got to ≥75% scroll and dwelled ≥30s and didn’t touch a form
    const dwell = Date.now() - start;
    const heuristic = (!formTouched && maxScroll >= 75 && dwell >= 30000) ? 'read_exit' : reason;
    beacon(null, heuristic);
  }
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') sendExit('unload');
  });
  window.addEventListener('pagehide', () => sendExit('unload'));

})();
