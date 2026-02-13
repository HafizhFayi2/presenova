// Service Worker for PWA
const CACHE_NAME = "attendance-cache-v2";
const urlsToCache = [
  "/",
  "/index.php",
  "/login.php",
  "/assets/css/style.css",
  "/assets/js/app.js",
  "/manifest.json",
  "/assets/images/logo-192.png",
  "/assets/images/logo-512.png",
];

// Install event
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(urlsToCache);
    }),
  );
});

// Fetch event
self.addEventListener("fetch", (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      // Return cached version or fetch from network
      return response || fetch(event.request);
    }),
  );
});

// Activate event
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        }),
      );
    }),
  );
});

// Push notification handler
self.addEventListener("push", (event) => {
  let data = { title: "Notifikasi", body: "", url: "/" };
  if (event.data) {
    try {
      data = event.data.json();
    } catch (error) {
      data.body = event.data.text();
    }
  }
  const options = {
    body: data.body,
    icon: "/assets/images/logo-192.png",
    badge: "/assets/images/logo-192.png",
    vibrate: [100, 50, 100],
    data: {
      url: data.url,
    },
  };

  event.waitUntil(self.registration.showNotification(data.title, options));
});

// Notification click handler
self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data.url));
});
