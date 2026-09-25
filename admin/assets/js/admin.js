(function () {
  'use strict';

  var body = document.body;
  var openBtn = document.getElementById('adminMenuBtn');
  var closeBtn = document.getElementById('adminSidebarClose');
  var overlay = document.getElementById('adminSidebarOverlay');

  function setOpen(open) {
    body.classList.toggle('admin-sidebar-open', open);
    if (openBtn) {
      openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
  }

  function openSidebar() {
    setOpen(true);
  }

  function closeSidebar() {
    setOpen(false);
  }

  if (openBtn) {
    openBtn.addEventListener('click', openSidebar);
  }
  if (closeBtn) {
    closeBtn.addEventListener('click', closeSidebar);
  }
  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }

  document.querySelectorAll('.admin-sidebar-nav a').forEach(function (link) {
    link.addEventListener('click', closeSidebar);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeSidebar();
    }
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) {
      closeSidebar();
    }
  });

  // Confirm before destructive admin actions (Block / Unblock / delete, etc.)
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || form.nodeName !== 'FORM') {
      return;
    }
    var msg = form.getAttribute('data-confirm');
    if (!msg) {
      return;
    }
    if (!window.confirm(msg)) {
      event.preventDefault();
      event.stopPropagation();
    }
  }, true);
})();
