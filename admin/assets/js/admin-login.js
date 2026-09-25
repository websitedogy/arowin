(function () {
  'use strict';

  function togglePassword(btn) {
    var wrap = btn.closest('.admin-password');
    if (!wrap) return;
    var input = wrap.querySelector('input');
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.classList.toggle('is-visible', show);
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  }

  document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      togglePassword(btn);
    });
  });

  var form = document.getElementById('adminLoginForm');
  var submitBtn = document.getElementById('adminSubmitBtn');
  if (form && submitBtn) {
    form.addEventListener('submit', function () {
      // Visual only — never disable the submit control (browsers can cancel POST).
      submitBtn.setAttribute('aria-busy', 'true');
      submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Signing in';
    });
  }
})();
