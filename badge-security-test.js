// LeadStream Badge Security Test Snippet
// Paste this in browser console to test badge behavior

(function () {
  console.log('=== LeadStream Badge Security Test ===');

  // Check localized data
  const ls = window.LeadStreamPhone || {};
  console.log('LeadStreamPhone object:', ls);
  console.log('isAdmin:', ls.isAdmin, '(should be 0 for non-admins)');
  console.log('debugBadge:', ls.debugBadge, '(should be 0 for non-admins)');

  // Check if badge exists
  const badge = document.getElementById('ls-phone-badge');
  console.log('Badge element exists:', !!badge);
  if (badge) {
    console.log('Badge HTML:', badge.outerHTML);
  }

  // Check render function
  console.log('__LS_RENDER_BADGE__ function:', typeof window.__LS_RENDER_BADGE__);

  // Test render function (should be blocked for non-admins)
  if (window.__LS_RENDER_BADGE__) {
    console.log('Calling __LS_RENDER_BADGE__...');
    window.__LS_RENDER_BADGE__();
    setTimeout(() => {
      const badgeAfter = document.getElementById('ls-phone-badge');
      console.log('Badge after render call:', !!badgeAfter);
    }, 100);
  }

  console.log('=== Test Complete ===');
})();
