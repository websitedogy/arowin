/* Arowin PWA service worker — installable shell + offline fallback */
/* global self, caches, fetch, Response, clients */

const SW_VERSION = 'arowin-pwa-v8';
const SHELL_CACHE = SW_VERSION + '-shell';
const RUNTIME_CACHE = SW_VERSION + '-runtime';

function appBase() {
  const path = self.location.pathname || '/sw.js';
  return path.replace(/\/sw\.js$/i, '') || '';
}

function appUrl(path) {
  const base = appBase();
  const clean = String(path || '').replace(/^\//, '');
  return base + '/' + clean;
}

const PRECACHE_URLS = [
  appUrl(''),
  appUrl('offline'),
  appUrl('manifest.json'),
  appUrl('public/css/app.css'),
  appUrl('public/css/navbar.css'),
  appUrl('public/css/footer.css'),
  appUrl('public/css/pages.css'),
  appUrl('public/js/app.js'),
  appUrl('public/js/navbar.js'),
  appUrl('public/js/pwa.js'),
  appUrl('public/css/pwa-install.css'),
  appUrl('dashboard/assets/css/dashboard.css'),
  appUrl('dashboard/assets/js/dashboard.js'),
  appUrl('dashboard/assets/js/tab-account.js'),
  appUrl('admin/assets/css/admin.css'),
  appUrl('admin/assets/js/admin.js'),
  appUrl('dashboard/guest-list/assets/guest-list.css'),
  appUrl('dashboard/guest-list/assets/guest-list.js'),
  appUrl('dashboard/history/assets/history.css'),
  appUrl('dashboard/support/assets/support.css'),
  appUrl('dashboard/direct-referrals/assets/referrals.css'),
  appUrl('dashboard/team-collaborations/assets/collaborations.css'),
  appUrl('dashboard/ppt-view/assets/ppt-view.css'),
  appUrl('dashboard/ppt-view/assets/ppt-view.js'),
  appUrl('public/assets/images/favicon.svg'),
  appUrl('public/assets/images/favicon-192x192.png'),
  appUrl('public/assets/images/favicon-512x512.png'),
  appUrl('public/assets/images/apple-touch-icon.png'),
];

function isSameOrigin(url) {
  try {
    return new URL(url, self.location.origin).origin === self.location.origin;
  } catch (e) {
    return false;
  }
}

function isStaticAsset(url) {
  return /\.(?:css|js|png|jpg|jpeg|gif|webp|svg|ico|woff2?|ttf|map)(?:\?|$)/i.test(url.pathname);
}

function isNavigationRequest(request) {
  return request.mode === 'navigate' ||
    (request.method === 'GET' && request.headers.get('accept') &&
      request.headers.get('accept').includes('text/html'));
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE)
      .then((cache) =>
        Promise.all(
          PRECACHE_URLS.map((url) =>
            cache.add(url).catch(() => undefined)
          )
        )
      )
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== SHELL_CACHE && key !== RUNTIME_CACHE)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

async function networkFirst(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  try {
    const response = await fetch(request);
    if (response && response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    const cached = await cache.match(request) || await caches.match(request);
    if (cached) return cached;
    if (isNavigationRequest(request)) {
      const offline = await caches.match(appUrl('offline'));
      if (offline) return offline;
    }
    throw err;
  }
}

function isUsableStaticResponse(request, response) {
  const href = request.url || '';
  const ct = (response.headers.get('content-type') || '').toLowerCase();
  if (/\.css(?:\?|$)/i.test(href)) {
    return ct.includes('text/css');
  }
  if (/\.js(?:\?|$)/i.test(href)) {
    return ct.includes('javascript') || ct.includes('ecmascript');
  }
  return true;
}

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached && isUsableStaticResponse(request, cached)) {
    return cached;
  }
  const response = await fetch(request);
  if (response && response.ok && isUsableStaticResponse(request, response)) {
    const cache = await caches.open(RUNTIME_CACHE);
    cache.put(request, response.clone());
  }
  return response;
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;
  if (!isSameOrigin(request.url)) return;

  const url = new URL(request.url);

  // Never cache API / auth mutations endpoints via SW strategies that write cache
  if (/\/dashboard\/api\//i.test(url.pathname) || /[?&]ajax_action=/i.test(url.search)) {
    return;
  }

  if (isNavigationRequest(request)) {
    event.respondWith(networkFirst(request));
    return;
  }

  if (isStaticAsset(url)) {
    // Versioned URLs (?v=filemtime) must hit the network so CSS/JS updates apply.
    // cacheFirst previously trapped a stale (or HTML 404) stylesheet and broke the UI.
    if (url.searchParams.has('v')) {
      return;
    }
    event.respondWith(cacheFirst(request));
  }
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
