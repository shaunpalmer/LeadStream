(function () {
  const LS = {
    VID_PUB:  'ls_vid_pub',
    FIRST:    'ls_first_seen',
    LAST:     'ls_last_seen',
    PREV:     'ls_prev',
    REF:      'ls_ref',
    UTM:      'ls_utm',
    SESSION:  'ls_session_id'
  };

  const isHTTPS = location.protocol === 'https:';
  function getCookie(name) {
    return document.cookie.split('; ').find(r => r.startsWith(name + '='))?.split('=')[1] || '';
  }
  function dec(v){ try { return decodeURIComponent(v || ''); } catch(_) { return v || ''; } }

  const ctx = {
    vid_pub:   dec(getCookie(LS.VID_PUB)),
    session:   dec(getCookie(LS.SESSION)),
    first:     dec(getCookie(LS.FIRST)),
    last:      dec(getCookie(LS.LAST)),
    prev:      dec(getCookie(LS.PREV)),
    ref:       dec(getCookie(LS.REF)),
    utm:       (() => { try { return JSON.parse(dec(getCookie(LS.UTM)) || '{}'); } catch(_) { return {}; } })()
  };

  // Handy gaps (ms) for tiles/charts
  const firstMs = Date.parse(ctx.first) || 0;
  const lastMs  = Date.parse(ctx.last)  || 0;
  ctx.gapSinceFirst = firstMs ? Date.now() - firstMs : 0;
  ctx.gapSinceLast  = lastMs  ? Date.now() - lastMs  : 0;

  // Expose to your widgets & GA dataLayer if present
  window.LEADSTREAM_COOKIES = ctx;
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ event: 'ls.cookies.ready', ls: ctx });

  // Optional: make a tiny pub/sub so other scripts can wait for this
  const ev = new CustomEvent('leadstream:cookies:ready', { detail: ctx });
  window.dispatchEvent(ev);
})();
