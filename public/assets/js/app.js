// app.js - JavaScript Utama

// Global Variables
let deferredPrompt = null;

function resolvePresenovaUrl(path) {
  if (typeof window.resolvePresenovaUrl === "function") {
    return window.resolvePresenovaUrl(path);
  }

  const script = Array.from(document.scripts).find((item) =>
    /assets\/js\/app\.js(?:\?.*)?$/i.test(item.src || ""),
  );

  let basePath = "";
  if (script && script.src) {
    try {
      const fullPath = new URL(script.src, window.location.origin).pathname;
      const marker = "/assets/js/";
      const markerIndex = fullPath.indexOf(marker);
      if (markerIndex !== -1) {
        basePath = fullPath.slice(0, markerIndex).replace(/\/+$/, "");
        if (basePath === "/") {
          basePath = "";
        }
      }
    } catch (error) {
      console.error("Unable to resolve app base path:", error);
    }
  }

  const rawPath = typeof path === "string" ? path.trim() : "";
  if (/^https?:\/\//i.test(rawPath)) {
    return rawPath;
  }

  const normalizedPath = rawPath.replace(/^\/+/, "");
  if (normalizedPath === "") {
    return basePath === "" ? "/" : `${basePath}/`;
  }

  return basePath === "" ? `/${normalizedPath}` : `${basePath}/${normalizedPath}`;
}

// DOM Ready
document.addEventListener("DOMContentLoaded", function () {
  // Initialize components
  initPWA();
  initScrollSpy();
  initNotifications();
  initNetworkStatus();

  // Register service worker
  if ("serviceWorker" in navigator) {
    registerServiceWorker();
  }

  // Check if app is running as PWA
  if (window.matchMedia("(display-mode: standalone)").matches) {
    document.body.classList.add("pwa-mode");
  }

  // Handle beforeinstallprompt event
  window.addEventListener("beforeinstallprompt", (e) => {
    e.preventDefault();
    deferredPrompt = e;
    showInstallPrompt();
  });

  // Handle app installed event
  window.addEventListener("appinstalled", () => {
    deferredPrompt = null;
    hideInstallPrompt();
    showToast("Aplikasi berhasil diinstall!", "success");
  });
});

// PWA Functions
function initPWA() {
  // Check if PWA is installable
  if (window.matchMedia("(display-mode: browser").matches) {
    // Running in browser, check if installable
    setTimeout(checkInstallable, 3000);
  }
}

function checkInstallable() {
  if (deferredPrompt && !localStorage.getItem("pwaPromptDismissed")) {
    showInstallPrompt();
  }
}

function showInstallPrompt() {
  const prompt = document.getElementById("pwaInstallPrompt");
  if (prompt) {
    prompt.style.display = "block";
  }
}

function hideInstallPrompt() {
  const prompt = document.getElementById("pwaInstallPrompt");
  if (prompt) {
    prompt.style.display = "none";
  }
}

function installPWA() {
  if (deferredPrompt) {
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then((choiceResult) => {
      if (choiceResult.outcome === "accepted") {
        console.log("User accepted the install prompt");
      } else {
        console.log("User dismissed the install prompt");
      }
      deferredPrompt = null;
    });
  }
}

function dismissInstallPrompt() {
  hideInstallPrompt();
  localStorage.setItem("pwaPromptDismissed", "true");
}

// Service Worker Registration
function registerServiceWorker() {
  let isRefreshing = false;

  navigator.serviceWorker
    .register(resolvePresenovaUrl("service-worker.js"))
    .then((registration) => {
      console.log("Service Worker registered with scope:", registration.scope);
      registration.update().catch(() => {});

      // Check for updates
      registration.addEventListener("updatefound", () => {
        const newWorker = registration.installing;
        newWorker.addEventListener("statechange", () => {
          if (
            newWorker.state === "installed" &&
            navigator.serviceWorker.controller
          ) {
            newWorker.postMessage({ type: "SKIP_WAITING" });
            showToast("Update tersedia! Silakan refresh halaman.", "info");
          }
        });
      });

      navigator.serviceWorker.addEventListener("controllerchange", () => {
        if (isRefreshing) {
          return;
        }

        isRefreshing = true;
        window.location.reload();
      });
    })
    .catch((error) => {
      console.error("Service Worker registration failed:", error);
    });
}

// Scroll Spy
function initScrollSpy() {
  const sections = document.querySelectorAll("section[id]");
  const navLinks = document.querySelectorAll(".nav-links-dark a");

  window.addEventListener("scroll", () => {
    let current = "";

    sections.forEach((section) => {
      const sectionTop = section.offsetTop;
      const sectionHeight = section.clientHeight;
      if (scrollY >= sectionTop - 200) {
        current = section.getAttribute("id");
      }
    });

    navLinks.forEach((link) => {
      link.classList.remove("active");
      if (link.getAttribute("href") === `#${current}`) {
        link.classList.add("active");
      }
    });
  });
}

// Notifications
function initNotifications() {
  // Request notification permission
  if ("Notification" in window && Notification.permission === "default") {
    // You might want to request permission on user action instead
    // Notification.requestPermission();
  }

  // Handle incoming notifications
  if ("serviceWorker" in navigator && "PushManager" in window) {
    navigator.serviceWorker.addEventListener("message", (event) => {
      if (event.data && event.data.type === "NOTIFICATION_CLICK") {
        // Handle notification click
        window.focus();
        window.location.href = event.data.url;
      }
    });
  }
}

// Network Status
function initNetworkStatus() {
  const statusElement = document.getElementById("networkStatus");
  if (!statusElement) return;

  function updateNetworkStatus() {
    if (navigator.onLine) {
      statusElement.className = "network-status online";
      hideOfflineNotification();
    } else {
      statusElement.className = "network-status offline";
      showOfflineNotification();
    }
  }

  window.addEventListener("online", updateNetworkStatus);
  window.addEventListener("offline", updateNetworkStatus);
  updateNetworkStatus();
}

function showOfflineNotification() {
  const notification = document.getElementById("offlineNotification");
  if (notification) {
    notification.classList.add("show");
  }
}

function hideOfflineNotification() {
  const notification = document.getElementById("offlineNotification");
  if (notification) {
    notification.classList.remove("show");
  }
}

// Toast Notification
function showToast(message, type = "info") {
  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === "success" ? "#00ff88" : type === "error" ? "#ff6b6b" : "#00d4ff"};
        color: #0a0f1e;
        border-radius: 8px;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;

  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = "slideOut 0.3s ease";
    setTimeout(() => {
      document.body.removeChild(toast);
    }, 300);
  }, 3000);
}

// Form Validation
function validateForm(formId) {
  const form = document.getElementById(formId);
  if (!form) return false;

  const inputs = form.querySelectorAll(
    "input[required], select[required], textarea[required]",
  );
  let isValid = true;

  inputs.forEach((input) => {
    if (!input.value.trim()) {
      input.classList.add("is-invalid");
      isValid = false;
    } else {
      input.classList.remove("is-invalid");
    }
  });

  return isValid;
}

// API Helper
async function callAPI(endpoint, method = "GET", data = null) {
  const options = {
    method: method,
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  };

  if (data) {
    options.body = JSON.stringify(data);
  }

  try {
    const response = await fetch(endpoint, options);
    return await response.json();
  } catch (error) {
    console.error("API Error:", error);
    return {
      success: false,
      message: "Network error. Please check your connection.",
    };
  }
}

// Image Helper
function compressImage(file, maxWidth = 800, quality = 0.8) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.readAsDataURL(file);

    reader.onload = (event) => {
      const img = new Image();
      img.src = event.target.result;

      img.onload = () => {
        const canvas = document.createElement("canvas");
        let width = img.width;
        let height = img.height;

        if (width > maxWidth) {
          height = Math.floor((height * maxWidth) / width);
          width = maxWidth;
        }

        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0, width, height);

        canvas.toBlob(
          (blob) => {
            resolve(blob);
          },
          "image/jpeg",
          quality,
        );
      };
    };

    reader.onerror = (error) => reject(error);
  });
}

// Date & Time Format
function formatDateTime(date) {
  return new Date(date).toLocaleString("id-ID", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

// Export to CSV (for reports)
function exportToCSV(data, filename) {
  const csvContent =
    "data:text/csv;charset=utf-8," +
    data.map((row) => row.join(",")).join("\n");

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", filename);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Theme Toggle (if needed)
function toggleTheme() {
  const body = document.body;
  body.classList.toggle("light-theme");
  localStorage.setItem(
    "theme",
    body.classList.contains("light-theme") ? "light" : "dark",
  );
}

// Initialize theme from localStorage
function initTheme() {
  const savedTheme = localStorage.getItem("theme");
  if (savedTheme === "light") {
    document.body.classList.add("light-theme");
  }
}

// Smooth Scroll
function smoothScroll(target, duration = 1000) {
  const targetElement = document.querySelector(target);
  if (!targetElement) return;

  const targetPosition = targetElement.offsetTop;
  const startPosition = window.pageYOffset;
  const distance = targetPosition - startPosition;
  let startTime = null;

  function animation(currentTime) {
    if (startTime === null) startTime = currentTime;
    const timeElapsed = currentTime - startTime;
    const run = easeInOutQuad(timeElapsed, startPosition, distance, duration);
    window.scrollTo(0, run);
    if (timeElapsed < duration) requestAnimationFrame(animation);
  }

  function easeInOutQuad(t, b, c, d) {
    t /= d / 2;
    if (t < 1) return (c / 2) * t * t + b;
    t--;
    return (-c / 2) * (t * (t - 2) - 1) + b;
  }

  requestAnimationFrame(animation);
}

// Initialize on load
window.addEventListener("load", () => {
  // Hide splash screen if exists
  const splash = document.getElementById("splashScreen");
  if (splash) {
    setTimeout(() => {
      splash.style.opacity = "0";
      setTimeout(() => {
        splash.style.display = "none";
      }, 300);
    }, 1000);
  }

  // Initialize theme
  initTheme();
});
