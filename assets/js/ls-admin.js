(function ($) {
  function toast(msg) {
    if (window.wp && wp.a11y && wp.a11y.speak) { wp.a11y.speak(msg); }
    var n = $('<div class="ls-toast" role="status" aria-live="polite"></div>').text(msg).appendTo('body');
    setTimeout(function () { n.remove(); }, 1500);
  }

  // Copy buttons
  $(document).on('click', '.ls-copy-btn', function () {
    var text = $(this).data('copy') || '';
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () { toast('Copied'); });
    } else {
      var t = $('<textarea>').val(text).appendTo('body').select();
      try { document.execCommand('copy'); toast('Copied'); } catch (e) { }
      t.remove();
    }
  });

  // Collapsible <details> persistence
  $('.ls-acc').each(function (i) {
    var el = this;
    var key = 'ls_acc_' + (el.id || i);
    var saved = localStorage.getItem(key);
    if (saved === 'open') el.open = true;
    if (saved === 'closed') el.open = false;
    $(el).on('toggle', function () { localStorage.setItem(key, el.open ? 'open' : 'closed'); });
  });

  // Lightweight accordion toggles with saved state
  $(document).on('click', '.ls-acc-toggle', function () {
    var $btn = $(this);
    var id = $btn.data('acc');
    var $panel = $('#' + id);
    if (!$panel.length) return;
    var visible = $panel.is(':visible');
    $panel.slideToggle(150, function () {
      var nowVisible = $panel.is(':visible');
      $btn.attr('aria-expanded', nowVisible ? 'true' : 'false');
      try { localStorage.setItem('ls-acc-' + id, nowVisible ? '1' : '0'); } catch (e) { }
    });
  });
  // Initialize saved states
  $('.ls-acc-toggle').each(function () {
    var id = $(this).data('acc');
    var state = null;
    try { state = localStorage.getItem('ls-acc-' + id); } catch (e) { }
    if (state === '0') {
      $('#' + id).hide();
      $(this).attr('aria-expanded', 'false');
    } else {
      $(this).attr('aria-expanded', 'true');
    }
  });

  // AJAXify Pretty Links list table
  var $linksWrap = $('.ls-links-table');
  if ($linksWrap.length) {
    function setLoading(on) { $linksWrap.toggleClass('is-loading', !!on); }
    function load(args) {
      setLoading(true);
      return $.post(LSAjax.ajaxurl, $.extend({ action: 'ls_links_table', nonce: LSAjax.nonce }, args || {}))
        .done(function (res) {
          if (res && res.success && res.data && res.data.html) {
            $linksWrap.html(res.data.html);
            if (res.data.url) { history.pushState(args || {}, '', res.data.url); }
            // Focus first interactive element
            $linksWrap.find('a,button,input,select,textarea').filter(':visible:first').trigger('focus');
            // Notify listeners (e.g., datepicker re-init)
            try { document.dispatchEvent(new CustomEvent('ls:links:loaded', { detail: { root: $linksWrap[0] } })); } catch (e) { }
          }
        })
        .always(function () { setLoading(false); });
    }

    // Intercept filter form submit
    $linksWrap.on('submit', 'form.js-links-filters', function (e) {
      e.preventDefault();
      var form = this;
      var data = $(form).serializeArray().reduce(function (o, i) { o[i.name] = i.value; return o; }, {});
      data.paged = 1;
      load(data);
    });

    // Debounce inputs for live filtering
    var t;
    $linksWrap.on('input change', 'form.js-links-filters input, form.js-links-filters select', function () {
      clearTimeout(t); var form = this.form;
      t = setTimeout(function () { $(form).trigger('submit'); }, 400);
    });

    // Pagination links: add data-args server-side if desired; fallback parse query
    $linksWrap.on('click', '.tablenav-pages a, a.js-paginate', function (e) {
      e.preventDefault();
      var args = $(this).data('args');
      if (!args) {
        try {
          var href = $(this).attr('href') || '';
          var u = new URL(href, window.location.origin);
          args = Object.fromEntries(u.searchParams.entries());
        } catch (e) { }
      }
      // Keep only our relevant keys
      args = args || {};
      var keep = ['q', 'rt', 'from', 'to', 'pp', 'paged'];
      var clean = {}; keep.forEach(function (k) { if (args[k] != null) clean[k] = args[k]; });
      if (!clean.paged) clean.paged = 1;
      load(clean);
    });

    // Back/forward navigation
    window.addEventListener('popstate', function (e) { if (e.state) load(e.state); });
  }

  // AJAXify Phone Calls table
  var $phoneWrap = $('#ls-all-calls.ls-phone-calls');
  if ($phoneWrap.length) {
    function setPhoneLoading(on) { $phoneWrap.toggleClass('is-loading', !!on); }
    function pload(args) {
      setPhoneLoading(true);
      return $.post(LSAjax.ajaxurl, $.extend({ action: 'ls_phone_table', nonce: LSAjax.nonce }, args || {}))
        .done(function (res) {
          if (res && res.success && res.data && res.data.html) {
            // Replace only the fragment (table + pager)
            $phoneWrap.find('table.widefat, .tablenav').remove();
            $phoneWrap.append(res.data.html);
            if (res.data.url) history.pushState(args || {}, '', res.data.url);
            $phoneWrap.find('a,button,input,select,textarea').filter(':visible:first').trigger('focus');
            // Notify listeners for re-inits (e.g., datepickers)
            try { document.dispatchEvent(new CustomEvent('ls:phone:loaded', { detail: { root: $phoneWrap[0] } })); } catch (e) { }
          }
        })
        .always(function () { setPhoneLoading(false); });
    }

    // Submit filters
    $phoneWrap.on('submit', 'form.js-phone-filters', function (e) {
      e.preventDefault();
      var data = $(this).serializeArray().reduce(function (o, i) { o[i.name] = i.value; return o; }, {});
      data.p = 1;
      pload(data);
    });
    // Debounce
    var t2;
    $phoneWrap.on('input change', 'form.js-phone-filters input, form.js-phone-filters select', function () {
      clearTimeout(t2); var f = this.form; t2 = setTimeout(function () { $(f).trigger('submit'); }, 400);
    });
    // Pagination
    $phoneWrap.on('click', '.tablenav-pages a.page-numbers, a.js-paginate', function (e) {
      e.preventDefault();
      var args = $(this).data('args');
      if (!args) {
        try { var u = new URL($(this).attr('href') || '', window.location.origin); args = Object.fromEntries(u.searchParams.entries()); } catch (e) { }
      }
      args = args || {}; // keep only the expected keys
      var keep = ['from', 'to', 'phone', 'q', 'elem', 'pp', 'p'];
      var clean = {}; keep.forEach(function (k) { if (args[k] != null) clean[k] = args[k]; });
      if (!clean.p) clean.p = 1;
      pload(clean);
    });
    // History nav
    window.addEventListener('popstate', function (e) { if (e.state) pload(e.state); });
  }
})(jQuery);
