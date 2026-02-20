// Service Worker for PWA
const STATIC_CACHE_NAME = "attendance-static-v20260220-1";
const RUNTIME_CACHE_NAME = "attendance-runtime-v20260220-1";

function resolveAppUrl(path) {
  const scopePath = new URL(self.registration.scope).pathname.replace(/\/+$/, "");
  const raw = typeof path === "string" ? path.trim() : "";

  if (/^https?:\/\//i.test(raw)) {
    return raw;
  }

  const normalized = raw.replace(/^\.?\//, "");
  if (normalized === "") {
    return scopePath + "/";
  }

  return scopePath + "/" + normalized;
}

const staticUrlsToCache = [
  "assets/css/style.css",
  "assets/css/siswa.css",
  "assets/css/app-dialog.css",
  "assets/css/sections/face_recognition.css",
  "assets/js/app.js",
  "assets/js/pwa.js",
  "assets/js/app-dialog.js",
  "assets/js/schedule-print-dialog.js",
  "face/faces_logics/face-api.min.js",
  "manifest.json",
  "assets/images/logo-192.png",
  "assets/images/logo-512.png",
  "assets/images/presenova.png",
].map(resolveAppUrl);

function isSameOrigin(requestUrl) {
  try {
    return new URL(requestUrl).origin === self.location.origin;
  } catch (error) {
    return false;
  }
}

function getLocalPathname(requestUrl) {
  const scopePath = new URL(self.registration.scope).pathname.replace(/\/+$/, "");
  const url = new URL(requestUrl);
  if (scopePath && url.pathname.startsWith(scopePath + "/")) {
    return url.pathname.slice(scopePath.length);
  }
  if (scopePath && url.pathname === scopePath) {
    return "/";
  }
  return url.pathname;
}

function isDynamicRoute(request) {
  const pathname = getLocalPathname(request.url).toLowerCase();
  if (request.mode === "navigate") {
    return true;
  }

  if (pathname.endsWith(".php")) {
    return true;
  }

  return (
    pathname.startsWith("/dashboard/") ||
    pathname.startsWith("/api/") ||
    pathname.startsWith("/login") ||
    pathname.startsWith("/logout") ||
    pathname.startsWith("/register") ||
    pathname.startsWith("/forgot-password") ||
    pathname.startsWith("/reset-password")
  );
}

function isDashboardRoute(request) {
  const pathname = getLocalPathname(request.url).toLowerCase();
  return pathname.startsWith("/dashboard/");
}

function isAuthRoute(request) {
  const pathname = getLocalPathname(request.url).toLowerCase();
  return (
    pathname.endsWith("/login.php") ||
    pathname.endsWith("/logout.php") ||
    pathname.endsWith("/register.php") ||
    pathname.endsWith("/forgot-password.php") ||
    pathname.endsWith("/reset_password.php") ||
    pathname.startsWith("/login") ||
    pathname.startsWith("/logout") ||
    pathname.startsWith("/register") ||
    pathname.startsWith("/forgot-password") ||
    pathname.startsWith("/reset-password")
  );
}

function isApiRoute(request) {
  const pathname = getLocalPathname(request.url).toLowerCase();
  return pathname.startsWith("/api/");
}

function isStaticAsset(request) {
  const pathname = getLocalPathname(request.url).toLowerCase();
  if (pathname.startsWith("/face/faces_logics/models/")) {
    return true;
  }

  return /\.(?:css|js|mjs|map|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot|json|txt|xml|webmanifest|pdf|mp4|webm|ogg|wav|wasm|pkl|csv)$/i.test(
    pathname,
  );
}

async function networkFirst(
  request,
  { cacheName = null, cacheResponse = false, forceNoStore = false, allowCacheFallback = true } = {},
) {
  try {
    const response = await fetch(request, forceNoStore ? { cache: "no-store" } : undefined);

    if (cacheName && cacheResponse && response && response.ok && shouldCacheDynamicResponse(response)) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }

    return response;
  } catch (error) {
    if (allowCacheFallback) {
      const cachedResponse = await caches.match(request);
      if (cachedResponse) {
        return cachedResponse;
      }
    }
    return Response.error();
  }
}

async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cachedResponse = await cache.match(request);

  const networkResponsePromise = fetch(request)
    .then((response) => {
      if (response && response.ok) {
        cache.put(request, response.clone());
      }
      return response;
    })
    .catch(() => null);

  if (cachedResponse) {
    return cachedResponse;
  }

  const networkResponse = await networkResponsePromise;
  if (networkResponse) {
    return networkResponse;
  }

  return Response.error();
}

async function networkFirstWithTimeout(
  request,
  { cacheName = RUNTIME_CACHE_NAME, timeoutMs = 1200, forceNoStore = false } = {},
) {
  const cache = await caches.open(cacheName);
  const cachedResponse = await cache.match(request);

  const networkPromise = fetch(request, forceNoStore ? { cache: "no-store" } : undefined)
    .then((response) => {
      if (response && response.ok && shouldCacheDynamicResponse(response)) {
        cache.put(request, response.clone());
      }
      return response;
    })
    .catch(() => null);

  const timeoutPromise = new Promise((resolve) => {
    setTimeout(() => resolve(cachedResponse), timeoutMs);
  });

  const first = await Promise.race([networkPromise, timeoutPromise]);
  if (first) {
    return first;
  }

  const networkResponse = await networkPromise;
  if (networkResponse) {
    return networkResponse;
  }

  if (cachedResponse) {
    return cachedResponse;
  }

  return Response.error();
}

function shouldCacheDynamicResponse(response) {
  if (!response || !response.ok) {
    return false;
  }

  if (response.redirected) {
    return false;
  }

  try {
    const responseUrl = (response.url || "").toLowerCase();
    if (
      responseUrl.includes("/dashboard/") ||
      responseUrl.includes("/api/") ||
      responseUrl.includes("/login.php") ||
      responseUrl.includes("/logout.php") ||
      responseUrl.includes("/forgot-password.php")
    ) {
      return false;
    }
  } catch (error) {
    return false;
  }

  return true;
}

// Install event
self.addEventListener("install", (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(STATIC_CACHE_NAME).then((cache) => {
      return cache.addAll(staticUrlsToCache);
    }),
  );
});

// Fetch event
self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") {
    return;
  }

  if (!isSameOrigin(event.request.url)) {
    return;
  }

  if (isDynamicRoute(event.request)) {
    if (isDashboardRoute(event.request) || isAuthRoute(event.request) || isApiRoute(event.request)) {
      event.respondWith(networkFirst(event.request, { forceNoStore: true, allowCacheFallback: false }));
      return;
    }

    if (event.request.mode === "navigate") {
      event.respondWith(networkFirstWithTimeout(event.request));
      return;
    }

    event.respondWith(
      networkFirst(event.request, {
        cacheName: RUNTIME_CACHE_NAME,
        cacheResponse: true,
      }),
    );
    return;
  }

  if (isStaticAsset(event.request)) {
    event.respondWith(staleWhileRevalidate(event.request, STATIC_CACHE_NAME));
    return;
  }

  event.respondWith(
    networkFirst(event.request, {
      cacheName: RUNTIME_CACHE_NAME,
      cacheResponse: true,
    }),
  );
});

// Activate event
self.addEventListener("activate", (event) => {
  self.clients.claim();
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== STATIC_CACHE_NAME && cacheName !== RUNTIME_CACHE_NAME) {
            return caches.delete(cacheName);
          }
          return Promise.resolve();
        }),
      );
    }),
  );
});

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

// Push notification handler
self.addEventListener("push", (event) => {
  let data = { title: "Notifikasi", body: "", url: "dashboard/siswa.php?page=jadwal" };
  if (event.data) {
    try {
      data = event.data.json();
    } catch (error) {
      data.body = event.data.text();
    }
  }
  const rawUrl = typeof data.url === "string" ? data.url.trim() : "";
  const targetUrl = rawUrl !== "" ? rawUrl : "dashboard/siswa.php?page=jadwal";
  const title = typeof data.title === "string" && data.title.trim() !== "" ? data.title : "Notifikasi";
  const body = typeof data.body === "string" ? data.body : "";
  const tag =
    typeof data.tag === "string" && data.tag.trim() !== ""
      ? data.tag
      : `presenova-${new Date().toISOString().slice(0, 16)}`;
  const options = {
    body,
    icon: resolveAppUrl("assets/images/logo-192.png"),
    badge: resolveAppUrl("assets/images/logo-192.png"),
    vibrate: [100, 50, 100],
    tag,
    renotify: false,
    data: {
      url: targetUrl,
    },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// Notification click handler
self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  event.waitUntil(
    (async () => {
      const targetUrl = resolveAppUrl((event.notification && event.notification.data && event.notification.data.url) || "");
      const allClients = await clients.matchAll({
        type: "window",
        includeUncontrolled: true,
      });

      for (const client of allClients) {
        if (!client || !client.url) {
          continue;
        }
        try {
          const clientUrl = new URL(client.url);
          const intendedUrl = new URL(targetUrl, self.location.origin);
          if (clientUrl.origin === intendedUrl.origin) {
            if ("focus" in client) {
              await client.focus();
            }
            if ("navigate" in client) {
              await client.navigate(targetUrl);
            }
            return;
          }
        } catch (error) {
          // continue
        }
      }

      if (clients.openWindow) {
        await clients.openWindow(targetUrl);
      }
    })(),
  );
});
