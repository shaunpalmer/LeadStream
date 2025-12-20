(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form[action$="admin-post.php"][name=""]') || document.querySelector('form[action*="admin-post.php"]');
    if (!form) return;

    // Disable buttons on submit to prevent double submissions
    form.addEventListener('submit', function (e) {
      var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
      buttons.forEach(function (btn) {
        btn.disabled = true;
        var spinner = document.createElement('span');
        spinner.className = 'ls-spinner';
        spinner.style.marginLeft = '8px';
        spinner.innerText = '…';
        btn.appendChild(spinner);
      });
    });

    // Confirm deactivation
    var deactivate = form.querySelector('button[name="ls_deactivate"], input[name="ls_deactivate"]');
    if (deactivate) {
      deactivate.addEventListener('click', function (ev) {
        if (!confirm('Are you sure you want to deactivate the license?')) {
          ev.preventDefault();
          ev.stopPropagation();
        }
      });
    }

    // Polite focus on license input
    var keyInput = document.getElementById('ls_license_key');
    if (keyInput) {
      keyInput.addEventListener('focus', function () { keyInput.select(); });
    }
  });
})();
