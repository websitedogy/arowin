const CACHE_NAME = 'arowin-shell-v1'

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.add('/'))
      .then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys
        .filter((key) => key.startsWith('arowin-') && key !== CACHE_NAME)
        .map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  )
})

self.addEventListener('fetch', (event) => {
  const requestUrl = new URL(event.request.url)
  if (event.request.method !== 'GET'
    || requestUrl.origin !== self.location.origin
    || event.request.mode !== 'navigate'
    || requestUrl.pathname.startsWith('/credentials/')) {
    return
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (requestUrl.pathname === '/' && response.ok) {
          const copy = response.clone()
          caches.open(CACHE_NAME).then((cache) => cache.put('/', copy))
        }
        return response
      })
      .catch(async () => {
        const cachedShell = await caches.match('/')
        if (cachedShell) return cachedShell
        throw new Error('AROWIN is not available offline.')
      }),
  )
})
