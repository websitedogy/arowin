/**
 * Per-tab account binder (multi-ID sessions).
 * Each tab keeps its User ID in sessionStorage and stamps links/fetch/forms with as=.
 */
(function () {
  var STORAGE_KEY = "arowin_as";

  function normalizeUid(v) {
    return String(v || "")
      .trim()
      .toUpperCase()
      .replace(/\s+/g, "");
  }

  function getTabUid() {
    try {
      return normalizeUid(sessionStorage.getItem(STORAGE_KEY) || "");
    } catch (_) {
      return "";
    }
  }

  function setTabUid(uid) {
    uid = normalizeUid(uid);
    if (!uid) return;
    try {
      sessionStorage.setItem(STORAGE_KEY, uid);
    } catch (_) {
      /* private mode */
    }
    window.AROWIN_TAB_AS = uid;
  }

  function clearTabUid() {
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch (_) {}
    window.AROWIN_TAB_AS = "";
  }

  function urlHasAs(href) {
    try {
      var u = new URL(href, window.location.href);
      return u.searchParams.has("as");
    } catch (_) {
      return /[?&]as=/i.test(String(href || ""));
    }
  }

  function withAs(href, uid) {
    uid = normalizeUid(uid);
    if (!uid || !href) return href;
    try {
      var u = new URL(href, window.location.href);
      // Only same-origin navigations
      if (u.origin !== window.location.origin) return href;
      u.searchParams.set("as", uid);
      return u.pathname + u.search + u.hash;
    } catch (_) {
      return href;
    }
  }

  function readAsFromLocation() {
    try {
      return normalizeUid(new URLSearchParams(window.location.search).get("as") || "");
    } catch (_) {
      return "";
    }
  }

  function ensureUrlHasAs() {
    var fromUrl = readAsFromLocation();
    if (fromUrl) {
      setTabUid(fromUrl);
      return;
    }
    var tabUid = getTabUid();
    if (!tabUid) {
      // Server rendered active account (last login) — bind this tab once
      // Only auto-bind when a single account is in the bag, or URL already had as=
      var boot = normalizeUid(window.AROWIN_BOOT_AS || "");
      var ids = Array.isArray(window.AROWIN_ACCOUNT_IDS) ? window.AROWIN_ACCOUNT_IDS : [];
      if (boot && ids.length <= 1) {
        setTabUid(boot);
        tabUid = boot;
      } else if (boot && ids.length > 1) {
        // Multiple IDs signed in but this tab has no binder yet — bind to boot
        // only after stamping URL so refresh stays consistent for this tab.
        setTabUid(boot);
        tabUid = boot;
      }
    }
    if (!tabUid) return;
    if (urlHasAs(window.location.href)) return;
    var next = withAs(window.location.href, tabUid);
    if (next && next !== window.location.pathname + window.location.search + window.location.hash) {
      window.location.replace(next);
    }
  }

  function stampAnchors(root) {
    var uid = getTabUid();
    if (!uid) return;
    (root || document).querySelectorAll("a[href]").forEach(function (a) {
      var href = a.getAttribute("href");
      if (!href || href.charAt(0) === "#" || href.indexOf("javascript:") === 0) return;
      try {
        var u = new URL(href, window.location.href);
        if (u.origin !== window.location.origin) return;
        // Keep public login/register clean unless already has as
        if (/\/login\/?$/i.test(u.pathname) || /\/register\/?$/i.test(u.pathname)) return;
        a.setAttribute("href", withAs(href, uid));
      } catch (_) {}
    });
  }

  function ensureFormAs(form) {
    var uid = getTabUid();
    if (!uid || !form) return;
    var existing = form.querySelector('input[name="as"]');
    if (existing) {
      existing.value = uid;
      return;
    }
    var input = document.createElement("input");
    input.type = "hidden";
    input.name = "as";
    input.value = uid;
    form.appendChild(input);
  }

  // Patch fetch to send X-Arowin-As + as query on same-origin dashboard/api calls
  var nativeFetch = window.fetch;
  if (typeof nativeFetch === "function") {
    window.fetch = function (input, init) {
      var uid = getTabUid();
      init = init ? Object.assign({}, init) : {};
      var url = typeof input === "string" ? input : input && input.url;
      if (uid && url) {
        try {
          var u = new URL(url, window.location.href);
          if (u.origin === window.location.origin) {
            if (!u.searchParams.has("as")) {
              u.searchParams.set("as", uid);
              url = u.pathname + u.search + u.hash;
              if (typeof input === "string") {
                input = url;
              } else if (input && typeof Request !== "undefined") {
                input = new Request(url, input);
              }
            }
            var headers = new Headers(init.headers || (input && input.headers) || {});
            if (!headers.has("X-Arowin-As")) {
              headers.set("X-Arowin-As", uid);
            }
            init.headers = headers;
          }
        } catch (_) {}
      }
      return nativeFetch.call(this, input, init);
    };
  }

  document.addEventListener(
    "click",
    function (e) {
      var a = e.target && e.target.closest ? e.target.closest("a[href]") : null;
      if (!a) return;
      var uid = getTabUid();
      if (!uid) return;
      var href = a.getAttribute("href");
      if (!href || href.charAt(0) === "#") return;
      try {
        var u = new URL(href, window.location.href);
        if (u.origin !== window.location.origin) return;
        if (/\/login\/?$/i.test(u.pathname) || /\/register\/?$/i.test(u.pathname)) return;
        if (u.searchParams.get("as") === uid) return;
        a.setAttribute("href", withAs(href, uid));
      } catch (_) {}
    },
    true
  );

  document.addEventListener(
    "submit",
    function (e) {
      if (e.target && e.target.tagName === "FORM") {
        ensureFormAs(e.target);
      }
    },
    true
  );

  // Logout clears tab binder immediately
  document.addEventListener(
    "click",
    function (e) {
      var a = e.target && e.target.closest ? e.target.closest('a[href*="logout"]') : null;
      if (!a) return;
      clearTabUid();
      try {
        localStorage.removeItem("arowin_pwa_uid");
        localStorage.removeItem("arowin_pwa_2fa");
      } catch (_) {}
    },
    true
  );

  window.AROWIN_TAB_ACCOUNT = {
    get: getTabUid,
    set: setTabUid,
    clear: clearTabUid,
    withAs: withAs,
  };

  function boot() {
    ensureUrlHasAs();
    stampAnchors(document);
    document.querySelectorAll("form").forEach(ensureFormAs);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
