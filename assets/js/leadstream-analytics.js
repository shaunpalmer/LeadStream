(function () {
  const C = {
    VID_PUB: 'ays_vid_pub',
    FIRST: 'ays_first_seen',
    LAST: 'ays_last_seen',
    PREV: 'ays_prev',
    REF: 'ays_ref',
    UTM: 'ays_utm',
    SESSION: 'ays_session_id'
  };

  function getCookie(name) {
    return document.cookie.split('; ').find(r => r.startsWith(name + '='))?.split('=')[1] || '';
  }
  function dec(v) { try { return decodeURIComponent(v || ''); } catch (_) { return v || ''; } }

  // Read values for charting / payloads
  const ctx = {
    vid_pub: dec(getCookie(C.VID_PUB)),
    session: dec(getCookie(C.SESSION)),
    first: dec(getCookie(C.FIRST)),
    last: dec(getCookie(C.LAST)),
    prev: dec(getCookie(C.PREV)),
    ref: dec(getCookie(C.REF)),
    utm: (() => { try { return JSON.parse(dec(getCookie(C.UTM)) || '{}'); } catch (_) { return {}; } })()
  };

  // Return-gap (ms) for quick UI tiles
  const firstMs = Date.parse(ctx.first) || 0;
  const lastMs = Date.parse(ctx.last) || 0;
  ctx.gapSinceFirst = firstMs ? Date.now() - firstMs : 0;
  ctx.gapSinceLast = lastMs ? Date.now() - lastMs : 0;

  // Expose for LeadStream widgets
  window.LEADSTREAM_ANALYTICS = ctx;

  // Example “pageview” ping (if you want JS-side logging too)
  // fetch('/wp-json/ays/v1/hit', {
  //   method: 'POST',
  //   headers: {'Content-Type':'application/json'},
  //   credentials: 'same-origin',
  //   body: JSON.stringify({
  //     type: 'pageview',
  //     path: location.pathname + location.search,
  //     ref: document.referrer || '',
  //     vid_pub: ctx.vid_pub,
  //     session: ctx.session,
  //     utm: ctx.utm
  //   })
  // });
})();
