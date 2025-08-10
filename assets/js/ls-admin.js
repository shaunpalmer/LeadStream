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
    // Quick chips
    $phoneWrap.on('click', '.ls-quick-chips .ls-chip', function () {
      var chip = $(this).data('chip');
      var f = $phoneWrap.find('form.js-phone-filters')[0];
      if (!f) return;
      var now = new Date();
      function fmt(d) { return d.toISOString().slice(0, 10); }
      var from = '', to = '';
      if (chip === 'p_today') {
        from = fmt(now); to = fmt(now);
      } else if (chip === 'p_last7') {
        var d = new Date(now); d.setDate(d.getDate() - 6); // include today
        from = fmt(d); to = fmt(now);
      } else if (chip === 'p_month') {
        var d2 = new Date(now.getFullYear(), now.getMonth(), 1);
        from = fmt(d2); to = fmt(now);
      }
      $(f).find('input[name="from"]').val(from);
      $(f).find('input[name="to"]').val(to);
      // reset page
      $(f).find('input[name="p"]').val('1');
      $(f).trigger('submit');
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

  // AJAXify Calls Outcomes table
  var $callsWrap = $('#ls-call-outcomes.ls-call-outcomes');
  if ($callsWrap.length) {
    function setCallsLoading(on) { $callsWrap.toggleClass('is-loading', !!on); }
    function cload(args) {
      setCallsLoading(true);
      return $.post(LSAjax.ajaxurl, $.extend({ action: 'ls_calls_table', nonce: LSAjax.nonce }, args || {}))
        .done(function (res) {
          if (res && res.success && res.data && res.data.html) {
            $callsWrap.find('table.widefat, .tablenav').remove();
            $callsWrap.append(res.data.html);
            if (res.data.url) history.pushState(args || {}, '', res.data.url);
            $callsWrap.find('a,button,input,select,textarea').filter(':visible:first').trigger('focus');
            try { document.dispatchEvent(new CustomEvent('ls:phone:loaded', { detail: { root: $callsWrap[0] } })); } catch (e) { }
          }
        })
        .always(function () { setCallsLoading(false); });
    }
    $callsWrap.on('submit', 'form.js-calls-filters', function (e) {
      e.preventDefault();
      var data = $(this).serializeArray().reduce(function (o, i) { o[i.name] = i.value; return o; }, {});
      data.c_p = 1;
      cload(data);
    });
    var t3;
    $callsWrap.on('input change', 'form.js-calls-filters input, form.js-calls-filters select', function () {
      clearTimeout(t3); var f = this.form; t3 = setTimeout(function () { $(f).trigger('submit'); }, 400);
    });
    // Quick chips
    $callsWrap.on('click', '.ls-quick-chips .ls-chip', function () {
      var chip = $(this).data('chip');
      var f = $callsWrap.find('form.js-calls-filters')[0];
      if (!f) return;
      // Reset selects
      $(f).find('select[name="c_status"]').val('');
      // Set group param
      var group = '';
      if (chip === 'c_missed') group = 'missed';
      if (chip === 'c_answered') group = 'answered';
      $(f).find('input[name="c_group"]').val(group);
      if (chip === 'c_last7') {
        var now = new Date(); var d = new Date(now); d.setDate(d.getDate() - 6);
        var fmt = function (dt) { return dt.toISOString().slice(0, 10); };
        $(f).find('input[name="c_from"]').val(fmt(d));
        $(f).find('input[name="c_to"]').val(fmt(now));
      }
      // reset page
      $(f).find('input[name="c_p"]').val('1');
      $(f).trigger('submit');
    });
    $callsWrap.on('click', '.tablenav-pages a.page-numbers, a.js-paginate', function (e) {
      e.preventDefault();
      var args = $(this).data('args');
      if (!args) { try { var u = new URL($(this).attr('href') || '', window.location.origin); args = Object.fromEntries(u.searchParams.entries()); } catch (e) { } }
      args = args || {};
      var keep = ['c_from', 'c_to', 'c_status', 'c_provider', 'c_fromnum', 'c_tonum', 'c_pp', 'c_p'];
      var clean = {}; keep.forEach(function (k) { if (args[k] != null) clean[k] = args[k]; });
      if (!clean.c_p) clean.c_p = 1;
      cload(clean);
    });
    window.addEventListener('popstate', function (e) { if (e.state) cload(e.state); });
  }

  // Danger Zone enable/disable buttons
  $(function () {
    var $chkPhone = $('#ls-confirm-phone-flush');
    var $btnPhone = $('#ls-btn-phone-flush');
    if ($chkPhone.length && $btnPhone.length) {
      $chkPhone.on('change', function () { $btnPhone.prop('disabled', !this.checked); });
      $btnPhone.prop('disabled', !$chkPhone.prop('checked'));
    }
    var $chkLinks = $('#ls-confirm-links-flush');
    var $btnClicks = $('#ls-btn-links-flush-clicks');
    var $btnLinks = $('#ls-btn-links-flush-links');
    if ($chkLinks.length) {
      function sync() { var on = $chkLinks.prop('checked'); $btnClicks && $btnClicks.prop('disabled', !on); $btnLinks && $btnLinks.prop('disabled', !on); }
      $chkLinks.on('change', sync); sync();
    }
  });

  // Soft confirm for range deletes: does a lightweight COUNT via AJAX before confirming
  window.LSConfirmRange = function (form, kind) {
    try {
      var from = $(form).find('input[name="dz_from"]').val();
      var to = $(form).find('input[name="dz_to"]').val();
      if (!from || !to) return false; // block submit if missing
      var $btn = $(form).find('button[type="submit"]');
      var old = $btn.text();
      $btn.prop('disabled', true).text('Counting…');
      $.post(LSAjax.ajaxurl, { action: 'ls_count_range', nonce: LSAjax.nonce, kind: kind, from: from, to: to })
        .done(function (res) {
          var n = res && res.success && res.data && typeof res.data.count === 'number' ? res.data.count : 0;
          var msg = 'About to delete ' + n + ' ' + (kind === 'link' ? 'link click' : 'phone click') + (n === 1 ? '' : 's') + ' between ' + from + ' and ' + to + '. Continue?';
          if (confirm(msg)) { form.submit(); }
        })
        .always(function () { $btn.prop('disabled', false).text(old); });
      return false; // always prevent default; we submit manually if confirmed
    } catch (e) {
      return confirm('Proceed with deletion?');
    }
  };

  // Nice UX for export buttons: disable while navigating and show a toast hint
  $(document).on('click', 'form[method="get"] button[name="fmt"]', function () {
    var $btn = $(this);
    var old = $btn.text();
    $btn.prop('disabled', true).text('Preparing…');
    setTimeout(function () { $btn.prop('disabled', false).text(old); }, 2000);
    toast('Export starting… You may continue using the page.');
  });
})(jQuery);
