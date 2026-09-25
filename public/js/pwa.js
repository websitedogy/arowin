/* Arowin PWA — service worker, install prompt, offline snapshot */
(function () {
  var SNAPSHOT_KEY = 'arowin_dash_snapshot';
  var DISMISS_KEY = 'arowin_install_dismissed_at';
  var INSTALLED_KEY = 'arowin_app_installed';
  var PROMPT_VER_KEY = 'arowin_install_prompt_ver';
  var PROMPT_VER = '5';
  var DISMISS_MS = 10 * 60 * 1000; // show again 10 minutes after "Not now"
  var deferredPrompt = null;
  var promptEl = null;
  var reshowTimer = null;

  // One-time reset — clears stuck dismiss timestamps from older builds
  try {
    if (localStorage.getItem(PROMPT_VER_KEY) !== PROMPT_VER) {
      localStorage.removeItem(DISMISS_KEY);
      localStorage.setItem(PROMPT_VER_KEY, PROMPT_VER);
    }
  } catch (e) {
    /* ignore */
  }

  function basePath() {
    if (typeof window.AROWIN_BASE_PATH === 'string') {
      return window.AROWIN_BASE_PATH.replace(/\/$/, '');
    }
    var meta = document.querySelector('meta[name="arowin-base"]');
    if (meta && meta.content) {
      return String(meta.content).replace(/\/$/, '');
    }
    return '';
  }

  function absUrl(path) {
    var base = basePath();
    var clean = String(path || '').replace(/^\//, '');
    return base + '/' + clean;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function isStandalone() {
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
      window.matchMedia('(display-mode: fullscreen)').matches ||
      window.navigator.standalone === true
    );
  }

  function isInstalled() {
    if (isStandalone()) return true;
    try {
      return localStorage.getItem(INSTALLED_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function markInstalled() {
    try {
      localStorage.setItem(INSTALLED_KEY, '1');
      localStorage.removeItem(DISMISS_KEY);
    } catch (e) {
      /* ignore */
    }
  }

  function isIos() {
    var ua = window.navigator.userAgent || '';
    var iOS = /iPad|iPhone|iPod/.test(ua);
    var iPadOs = window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1;
    return iOS || iPadOs;
  }

  function wasDismissedRecently() {
    try {
      var raw = localStorage.getItem(DISMISS_KEY);
      if (!raw) return false;
      var ts = Number(raw);
      if (!ts || !isFinite(ts)) {
        localStorage.removeItem(DISMISS_KEY);
        return false;
      }
      // Future / corrupt lock from older builds → clear
      if (ts > Date.now() + 60000) {
        localStorage.removeItem(DISMISS_KEY);
        return false;
      }
      var age = Date.now() - ts;
      if (age < 0) {
        localStorage.removeItem(DISMISS_KEY);
        return false;
      }
      return age < DISMISS_MS;
    } catch (e) {
      return false;
    }
  }

  function dismissRemainingMs() {
    try {
      var raw = localStorage.getItem(DISMISS_KEY);
      if (!raw) return 0;
      var ts = Number(raw);
      if (!ts || !isFinite(ts) || ts > Date.now() + 60000) return 0;
      var left = DISMISS_MS - (Date.now() - ts);
      return left > 0 ? left : 0;
    } catch (e) {
      return 0;
    }
  }

  function markDismissed() {
    try {
      localStorage.setItem(DISMISS_KEY, String(Date.now()));
    } catch (e) {
      /* ignore */
    }
  }

  function clearReshowTimer() {
    if (reshowTimer) {
      window.clearTimeout(reshowTimer);
      reshowTimer = null;
    }
  }

  function scheduleReshow() {
    clearReshowTimer();
    if (isInstalled()) return;
    var wait = dismissRemainingMs();
    if (wait <= 0) {
      wait = DISMISS_MS;
    }
    reshowTimer = window.setTimeout(function () {
      reshowTimer = null;
      if (isInstalled()) return;
      if (wasDismissedRecently()) {
        scheduleReshow();
        return;
      }
      ensureInstallPrompt(true);
    }, wait);
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    var swUrl = absUrl('sw.js');
    var scope = basePath() + '/';

    window.addEventListener('load', function () {
      navigator.serviceWorker.register(swUrl, { scope: scope }).then(function (reg) {
        if (reg.waiting) {
          reg.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
        reg.addEventListener('updatefound', function () {
          var worker = reg.installing;
          if (!worker) return;
          worker.addEventListener('statechange', function () {
            if (worker.state === 'installed' && navigator.serviceWorker.controller) {
              worker.postMessage({ type: 'SKIP_WAITING' });
            }
          });
        });
      }).catch(function () {
        /* silent — unsupported path / insecure context */
      });
    });
  }

  function ensureInstallPrompt(forceOpen) {
    if (isInstalled()) return;
    if (wasDismissedRecently() && !forceOpen) {
      scheduleReshow();
      return;
    }

    if (!promptEl) {
      var existing = document.getElementById('arowin-install');
      if (existing) {
        promptEl = existing;
      }
    }

    if (!promptEl) {
      var iconSrc = absUrl('public/assets/images/favicon-192x192.png');
      var wrap = document.createElement('div');
      wrap.id = 'arowin-install';
      wrap.className = 'arowin-install is-force' + (isIos() ? ' is-ios' : '');
      wrap.setAttribute('role', 'dialog');
      wrap.setAttribute('aria-label', 'Install Arowin app');
      wrap.innerHTML =
        '<div class="arowin-install-inner">' +
          '<img class="arowin-install-icon" src="' + escapeHtml(iconSrc) + '" alt="AROWIN" width="48" height="48">' +
          '<div class="arowin-install-copy">' +
            '<h3>Install Arowin App</h3>' +
            '<p>Install Arowin App</p>' +
          '</div>' +
          '<div class="arowin-install-actions">' +
            '<button type="button" class="arowin-install-btn" data-install>Install App</button>' +
            '<button type="button" class="arowin-install-dismiss" data-dismiss>Not now</button>' +
          '</div>' +
        '</div>' +
        '<div class="arowin-install-ios">' +
          '<ol>' +
            '<li>Tap <span class="arowin-install-share">Share</span> in Safari</li>' +
            '<li>Tap <strong>Install Arowin App</strong></li>' +
            '<li>Tap <strong>Add</strong> to finish</li>' +
          '</ol>' +
          '<div class="arowin-install-ios-bar">' +
            '<button type="button" class="arowin-install-dismiss" data-dismiss>Got it</button>' +
          '</div>' +
        '</div>';

      document.body.appendChild(wrap);
      promptEl = wrap;

      wrap.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Element)) return;
        if (target.closest('[data-dismiss]')) {
          hideInstallPrompt(true);
          return;
        }
        if (target.closest('[data-install]')) {
          triggerNativeInstall();
        }
      });
    }

    window.setTimeout(function () {
      if (!promptEl || isInstalled()) return;
      if (wasDismissedRecently() && !forceOpen) return;
      promptEl.classList.add('is-open');
      promptEl.classList.add('is-force');
      if (isIos()) promptEl.classList.add('is-ios');
    }, forceOpen ? 200 : 700);
  }

  function hideInstallPrompt(remember) {
    if (!promptEl) return;
    promptEl.classList.remove('is-open');
    if (remember) {
      markDismissed();
      scheduleReshow();
    }
    window.setTimeout(function () {
      if (promptEl && promptEl.parentNode) {
        promptEl.parentNode.removeChild(promptEl);
      }
      promptEl = null;
    }, 280);
  }

  function triggerNativeInstall() {
    if (!deferredPrompt) {
      if (promptEl) {
        var copy = promptEl.querySelector('.arowin-install-copy p');
        if (copy) {
          if (isIos()) {
            copy.textContent = 'Install Arowin App from the Share menu.';
            promptEl.classList.add('is-ios');
          } else {
            copy.textContent = 'Install Arowin App from your browser menu.';
          }
        }
      }
      return;
    }

    var promptEvent = deferredPrompt;
    deferredPrompt = null;
    promptEvent.prompt();
    promptEvent.userChoice.then(function () {
      markInstalled();
      hideInstallPrompt(true);
    }).catch(function () {
      hideInstallPrompt(false);
    });
  }

  function bindInstallEvents() {
    window.addEventListener('beforeinstallprompt', function (event) {
      event.preventDefault();
      deferredPrompt = event;
      ensureInstallPrompt(true);
      if (promptEl) {
        promptEl.classList.add('is-open');
        promptEl.classList.add('is-force');
        promptEl.classList.remove('is-ios');
      }
    });

    window.addEventListener('appinstalled', function () {
      deferredPrompt = null;
      clearReshowTimer();
      markInstalled();
      hideInstallPrompt(false);
    });
  }

  function readEmbeddedSnapshot() {
    var el = document.getElementById('arowin-offline-snapshot');
    if (!el) return null;
    try {
      return JSON.parse(el.textContent || '');
    } catch (e) {
      return null;
    }
  }

  function saveSnapshot(data) {
    if (!data || typeof data !== 'object') return;
    try {
      data.saved_at = data.saved_at || new Date().toISOString();
      localStorage.setItem(SNAPSHOT_KEY, JSON.stringify(data));
    } catch (e) {
      /* quota / private mode */
    }
  }

  function loadSnapshot() {
    try {
      var raw = localStorage.getItem(SNAPSHOT_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  function formatUsdt(n) {
    var num = Number(n) || 0;
    return num.toLocaleString(undefined, { maximumFractionDigits: 0 }) + ' USDT';
  }

  function renderOfflineSnapshot() {
    var root = document.getElementById('offline-snapshot');
    if (!root) return;

    var data = loadSnapshot();
    if (!data) {
      root.innerHTML =
        '<p class="offline-empty">No cached dashboard data yet. Open the dashboard once online to save a snapshot.</p>';
      return;
    }

    var when = data.saved_at ? new Date(data.saved_at) : null;
    var whenLabel = when && !isNaN(when.getTime())
      ? when.toLocaleString()
      : 'Unknown time';

    root.innerHTML =
      '<div class="offline-card">' +
        '<p class="offline-eyebrow">Last seen dashboard</p>' +
        '<h2>' + escapeHtml(data.full_name || 'Operator') + '</h2>' +
        '<p class="offline-meta">' +
          escapeHtml(data.username || '') +
          (data.rank_label ? ' · ' + escapeHtml(data.rank_label) : '') +
          (data.status_label ? ' · ' + escapeHtml(data.status_label) : '') +
        '</p>' +
        '<div class="offline-grid">' +
          row('Master Wallet', formatUsdt(data.wallet)) +
          row('Referral', formatUsdt(data.referral_income)) +
          row('Matching', formatUsdt(data.matching_income)) +
          row('Left Team', String(data.left_team != null ? data.left_team : '—')) +
          row('Right Team', String(data.right_team != null ? data.right_team : '—')) +
        '</div>' +
        '<p class="offline-saved">Cached ' + escapeHtml(whenLabel) + ' · read-only</p>' +
      '</div>';
  }

  function row(label, value) {
    return (
      '<div class="offline-stat">' +
        '<span>' + escapeHtml(label) + '</span>' +
        '<strong>' + escapeHtml(value) + '</strong>' +
      '</div>'
    );
  }

  function ensureOfflineBanner() {
    if (document.getElementById('arowin-offline-banner')) return;
    var banner = document.createElement('div');
    banner.id = 'arowin-offline-banner';
    banner.setAttribute('role', 'status');
    banner.hidden = true;
    banner.textContent = 'You are offline. Showing cached content where available.';
    banner.style.cssText =
      'position:fixed;left:0;right:0;top:0;z-index:99999;padding:10px 16px;' +
      'background:#7c2d12;color:#ffedd5;font:600 13px/1.4 "Plus Jakarta Sans",system-ui,sans-serif;' +
      'text-align:center;box-shadow:0 4px 16px rgba(0,0,0,.35)';
    document.body.appendChild(banner);

    function sync() {
      var offline = !navigator.onLine;
      banner.hidden = !offline;
      document.documentElement.classList.toggle('is-offline', offline);
    }

    window.addEventListener('online', sync);
    window.addEventListener('offline', sync);
    sync();
  }

  var embedded = readEmbeddedSnapshot();
  if (embedded) saveSnapshot(embedded);

  registerServiceWorker();
  bindInstallEvents();

  function bootUi() {
    ensureOfflineBanner();
    renderOfflineSnapshot();
    if (isInstalled()) return;
    if (wasDismissedRecently()) {
      scheduleReshow();
    } else {
      ensureInstallPrompt(false);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootUi);
  } else {
    bootUi();
  }

  window.ArowinPwa = {
    saveSnapshot: saveSnapshot,
    loadSnapshot: loadSnapshot,
    showInstallPrompt: function () {
      try {
        localStorage.removeItem(DISMISS_KEY);
      } catch (e) {
        /* ignore */
      }
      ensureInstallPrompt(true);
      if (promptEl) promptEl.classList.add('is-open');
    },
  };
})();
