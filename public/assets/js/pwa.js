// pwa.js - PWA-specific functionality

function detectAppBasePath() {
  if (
    typeof window.__PRESENOVA_APP_BASE_PATH__ === "string" &&
    window.__PRESENOVA_APP_BASE_PATH__.trim() !== ""
  ) {
    return (
      "/" +
      window.__PRESENOVA_APP_BASE_PATH__
        .trim()
        .replace(/^\/+/, "")
        .replace(/\/+$/, "")
    );
  }

  const script = Array.from(document.scripts).find((item) =>
    /assets\/js\/(?:pwa|app)\.js(?:\?.*)?$/i.test(item.src || ""),
  );
  if (!script || !script.src) {
    return "";
  }

  try {
    const path = new URL(script.src, window.location.origin).pathname;
    const marker = "/assets/js/";
    const markerIndex = path.indexOf(marker);
    if (markerIndex === -1) {
      return "";
    }
    const basePath = path.slice(0, markerIndex).replace(/\/+$/, "");
    return basePath === "/" ? "" : basePath;
  } catch (error) {
    console.error("Unable to resolve app base path:", error);
    return "";
  }
}

const APP_BASE_PATH = detectAppBasePath();
const LOCALHOST_NAMES = new Set(["localhost", "127.0.0.1", "::1"]);

function supportsSecureDeviceFeatures() {
  return (
    window.isSecureContext === true || LOCALHOST_NAMES.has(window.location.hostname)
  );
}

function secureRequirementMessage(featureName) {
  const feature = featureName || "fitur ini";
  return `Akses ${feature} membutuhkan HTTPS.`;
}

function resolvePresenovaUrl(path) {
  const rawPath = typeof path === "string" ? path.trim() : "";
  if (/^https?:\/\//i.test(rawPath)) {
    return rawPath;
  }

  const normalizedPath = rawPath.replace(/^\/+/, "");
  if (normalizedPath === "") {
    return APP_BASE_PATH === "" ? "/" : `${APP_BASE_PATH}/`;
  }

  return APP_BASE_PATH === ""
    ? `/${normalizedPath}`
    : `${APP_BASE_PATH}/${normalizedPath}`;
}

class PWAInstaller {
  constructor() {
    this.deferredPrompt = null;
    this.isPWAInstalled = false;
    this.init();
  }

  init() {
    // Check if app is already installed
    this.checkIfInstalled();

    // Listen for beforeinstallprompt event
    window.addEventListener("beforeinstallprompt", (e) => {
      e.preventDefault();
      this.deferredPrompt = e;
      this.showInstallButton();
    });

    // Listen for app installed event
    window.addEventListener("appinstalled", () => {
      this.isPWAInstalled = true;
      this.hideInstallButton();
      this.showToast("Aplikasi berhasil diinstall!", "success");
    });

    // Check display mode
    this.checkDisplayMode();

    // Initialize offline detection
    this.initOfflineDetection();
  }

  checkIfInstalled() {
    // Check various methods to determine if PWA is installed
    if (window.matchMedia("(display-mode: standalone)").matches) {
      this.isPWAInstalled = true;
      document.body.classList.add("pwa-installed");
    }

    // Check for iOS standalone mode
    if (window.navigator.standalone === true) {
      this.isPWAInstalled = true;
      document.body.classList.add("pwa-installed");
    }
  }

  checkDisplayMode() {
    const displayMode = this.getDisplayMode();
    document.body.setAttribute("data-display-mode", displayMode);

    if (displayMode === "standalone" || displayMode === "fullscreen") {
      document.body.classList.add("pwa-mode");
    }
  }

  getDisplayMode() {
    if (window.matchMedia("(display-mode: standalone)").matches) {
      return "standalone";
    }
    if (window.matchMedia("(display-mode: fullscreen)").matches) {
      return "fullscreen";
    }
    if (window.matchMedia("(display-mode: minimal-ui)").matches) {
      return "minimal-ui";
    }
    return "browser";
  }

  showInstallButton() {
    // Don't show if already installed
    if (this.isPWAInstalled) return;

    // Don't show if user dismissed recently
    const dismissed = localStorage.getItem("pwaPromptDismissed");
    if (dismissed && Date.now() - dismissed < 30 * 24 * 60 * 60 * 1000) {
      return; // Don't show for 30 days
    }

    const installButton = document.getElementById("installPWA");
    if (installButton) {
      installButton.style.display = "block";
      installButton.addEventListener("click", () => this.install());
    }

    // Show install prompt
    this.showInstallPrompt();
  }

  hideInstallButton() {
    const installButton = document.getElementById("installPWA");
    if (installButton) {
      installButton.style.display = "none";
    }

    this.hideInstallPrompt();
  }

  showInstallPrompt() {
    const prompt = document.getElementById("pwaInstallPrompt");
    if (prompt) {
      prompt.style.display = "block";
    }
  }

  hideInstallPrompt() {
    const prompt = document.getElementById("pwaInstallPrompt");
    if (prompt) {
      prompt.style.display = "none";
    }
  }

  async install() {
    if (!this.deferredPrompt) {
      return;
    }

    this.deferredPrompt.prompt();
    const { outcome } = await this.deferredPrompt.userChoice;

    if (outcome === "accepted") {
      console.log("User accepted the install prompt");
      localStorage.removeItem("pwaPromptDismissed");
    } else {
      console.log("User dismissed the install prompt");
      localStorage.setItem("pwaPromptDismissed", Date.now());
    }

    this.deferredPrompt = null;
    this.hideInstallPrompt();
  }

  dismissPrompt() {
    this.hideInstallPrompt();
    localStorage.setItem("pwaPromptDismissed", Date.now());
  }

  initOfflineDetection() {
    // Update online status
    const updateOnlineStatus = () => {
      const isOnline = navigator.onLine;
      document.body.classList.toggle("offline", !isOnline);

      if (!isOnline) {
        this.showOfflineNotification();
      } else {
        this.hideOfflineNotification();
      }
    };

    window.addEventListener("online", updateOnlineStatus);
    window.addEventListener("offline", updateOnlineStatus);
    updateOnlineStatus();
  }

  showOfflineNotification() {
    const notification = document.getElementById("offlineNotification");
    if (notification) {
      notification.classList.add("show");
    }
  }

  hideOfflineNotification() {
    const notification = document.getElementById("offlineNotification");
    if (notification) {
      notification.classList.remove("show");
    }
  }

  showToast(message, type = "info") {
    // Implementation of toast notification
    console.log(`[${type}] ${message}`);
  }
}

// Service Worker Manager
class ServiceWorkerManager {
  constructor() {
    this.registration = null;
    this.updateAvailable = false;
    this.isRefreshing = false;
  }

  async register() {
    if (!supportsSecureDeviceFeatures()) {
      console.warn(secureRequirementMessage("service worker"));
      return;
    }

    if (!("serviceWorker" in navigator)) {
      console.log("Service workers are not supported");
      return;
    }

    try {
      this.registration = await navigator.serviceWorker.register(
        resolvePresenovaUrl("service-worker.js"),
      );
      console.log("Service Worker registered:", this.registration);

      this.setupUpdateListener();
      this.checkForUpdates();
    } catch (error) {
      console.error("Service Worker registration failed:", error);
    }
  }

  setupUpdateListener() {
    if (!this.registration) return;

    this.registration.addEventListener("updatefound", () => {
      const newWorker = this.registration.installing;

      newWorker.addEventListener("statechange", () => {
        if (
          newWorker.state === "installed" &&
          navigator.serviceWorker.controller
        ) {
          this.updateAvailable = true;
          newWorker.postMessage({ type: "SKIP_WAITING" });
          this.showUpdateNotification();
        }
      });
    });

    // Listen for controller change
    navigator.serviceWorker.addEventListener("controllerchange", () => {
      if (this.isRefreshing) {
        return;
      }
      this.isRefreshing = true;
      window.location.reload();
    });
  }

  showUpdateNotification() {
    // Show update notification to user
    const notification = document.createElement("div");
    notification.className = "update-notification";
    notification.innerHTML = `
            <div class="update-content">
                <p>Update tersedia!</p>
                <button id="reloadApp" class="btn btn-sm btn-success">Refresh</button>
            </div>
        `;

    document.body.appendChild(notification);

    document.getElementById("reloadApp").addEventListener("click", () => {
      this.applyUpdate();
    });
  }

  async applyUpdate() {
    if (!this.updateAvailable) return;

    if (this.registration && this.registration.waiting) {
      this.registration.waiting.postMessage({ type: "SKIP_WAITING" });
    }
  }

  async checkForUpdates() {
    if (!this.registration) return;

    try {
      await this.registration.update();
    } catch (error) {
      console.error("Error checking for updates:", error);
    }
  }

  async unregister() {
    if (!this.registration) return;

    const unregistered = await this.registration.unregister();
    if (unregistered) {
      console.log("Service Worker unregistered");
    }
  }
}

// Push Notification Manager
class PushNotificationManager {
  constructor() {
    this.subscription = null;
    this.publicKey = null;
  }

  async init() {
    if (!supportsSecureDeviceFeatures()) {
      console.log(secureRequirementMessage("push notification"));
      return;
    }

    if (!("PushManager" in window)) {
      console.log("Push notifications are not supported");
      return;
    }

    if (!("serviceWorker" in navigator)) {
      console.log("Service Worker is required for push notifications");
      return;
    }

    // Get public key from server
    await this.getPublicKey();

    // Check current subscription
    await this.checkSubscription();
    if (this.subscription) {
      await this.sendSubscriptionToServer(this.subscription);
    }
  }

  async getPublicKey() {
    try {
      const response = await fetch(resolvePresenovaUrl("api/get-public-key.php"));
      const data = await response.json();
      this.publicKey = data.publicKey;
    } catch (error) {
      console.error("Error getting public key:", error);
    }
  }

  async checkSubscription() {
    const registration = await navigator.serviceWorker.ready;
    this.subscription = await registration.pushManager.getSubscription();
  }

  async requestPermission() {
    if (!supportsSecureDeviceFeatures()) {
      return false;
    }
    const permission = await Notification.requestPermission();
    return permission === "granted";
  }

  async subscribe() {
    if (!supportsSecureDeviceFeatures()) {
      throw new Error(secureRequirementMessage("push notification"));
    }
    if (!this.publicKey) {
      throw new Error("Public key not available");
    }

    const registration = await navigator.serviceWorker.ready;

    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: this.urlBase64ToUint8Array(this.publicKey),
    });

    this.subscription = subscription;

    // Send subscription to server
    await this.sendSubscriptionToServer(subscription);

    return subscription;
  }

  async unsubscribe() {
    if (!this.subscription) return;

    const unsubscribed = await this.subscription.unsubscribe();
    if (unsubscribed) {
      this.subscription = null;
      await this.removeSubscriptionFromServer();
    }

    return unsubscribed;
  }

  async sendSubscriptionToServer(subscription) {
    try {
      await fetch(resolvePresenovaUrl("api/save-subscription.php"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(subscription),
      });
    } catch (error) {
      console.error("Error sending subscription to server:", error);
    }
  }

  async removeSubscriptionFromServer() {
    try {
      await fetch(resolvePresenovaUrl("api/remove-subscription.php"), {
        method: "POST",
      });
    } catch (error) {
      console.error("Error removing subscription from server:", error);
    }
  }

  urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
      .replace(/\-/g, "+")
      .replace(/_/g, "/");

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
  }
}

// Initialize PWA features
document.addEventListener("DOMContentLoaded", () => {
  // Initialize PWA installer
  window.pwaInstaller = new PWAInstaller();

  // Initialize Service Worker
  window.swManager = new ServiceWorkerManager();
  window.swManager.register();

  // Initialize Push Notifications (if enabled)
  const pushEnabled = document.body && document.body.dataset.enablePush === "1";
  if (pushEnabled) {
    window.pushManager = new PushNotificationManager();
    window.pushManager.init().then(() => {
      updatePushButtonState();

      if (
        Notification.permission === "granted" &&
        !window.pushManager.subscription
      ) {
        window.pushManager
          .subscribe()
          .then(updatePushButtonState)
          .catch((error) => {
            console.error("Push subscribe failed:", error);
          });
      }
    });

    const enableButton = document.getElementById("enablePushBtn");
    if (enableButton) {
      enableButton.addEventListener("click", async () => {
        if (!window.pushManager) return;
        const granted = await window.pushManager.requestPermission();
        if (granted) {
          try {
            await window.pushManager.subscribe();
          } catch (error) {
            console.error("Push subscribe failed:", error);
          }
        }
        updatePushButtonState();
      });
    }
  }

  // Add to Home Screen instructions for iOS
  detectIOS();
});

function updatePushButtonState() {
  const button = document.getElementById("enablePushBtn");
  if (!button) return;

  if (!supportsSecureDeviceFeatures()) {
    button.disabled = true;
    button.dataset.state = "insecure";
    button.innerHTML =
      '<i class="fas fa-lock"></i><span>Notifikasi butuh HTTPS</span>';
    button.title = secureRequirementMessage("push notification");
    return;
  }

  if (!("Notification" in window)) {
    button.disabled = true;
    button.dataset.state = "unsupported";
    button.title = "";
    button.innerHTML =
      '<i class="fas fa-bell-slash"></i><span>Notifikasi tidak didukung</span>';
    return;
  }

  if (Notification.permission === "denied") {
    button.disabled = true;
    button.dataset.state = "blocked";
    button.title = "";
    button.innerHTML =
      '<i class="fas fa-bell-slash"></i><span>Notifikasi diblokir</span>';
    return;
  }

  if (Notification.permission === "granted") {
    button.disabled = false;
    button.dataset.state = "enabled";
    button.title = "";
    button.innerHTML =
      '<i class="fas fa-bell"></i><span>Notifikasi aktif</span>';
    return;
  }

  button.disabled = false;
  button.dataset.state = "prompt";
  button.title = "";
  button.innerHTML =
    '<i class="fas fa-bell"></i><span>Aktifkan Notifikasi</span>';
}

// iOS detection and instructions
function detectIOS() {
  const isIOS =
    /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

  if (isIOS && isSafari) {
    showIOSInstallInstructions();
  }
}

function showIOSInstallInstructions() {
  // Check if already installed
  if (window.navigator.standalone) return;

  // Show iOS install instructions
  const instructions = document.createElement("div");
  instructions.className = "ios-install-instructions";
  instructions.innerHTML = `
        <div class="instructions-content">
            <h5>Install Aplikasi</h5>
            <p>Tap <i class="fas fa-share"></i> lalu "Add to Home Screen"</p>
            <button class="btn btn-sm btn-outline-light" id="closeIOSInstructions">Tutup</button>
        </div>
    `;

  document.body.appendChild(instructions);

  document
    .getElementById("closeIOSInstructions")
    .addEventListener("click", () => {
      instructions.remove();
    });
}

// Handle beforeinstallprompt for analytics
window.addEventListener("beforeinstallprompt", (e) => {
  // You can log this event to analytics
  console.log("beforeinstallprompt event fired");
});

// Handle app launch
if (window.launchQueue) {
  window.launchQueue.setConsumer((launchParams) => {
    if (launchParams.targetURL) {
      // Handle PWA launch with URL
      const url = new URL(launchParams.targetURL);
      // Process URL parameters
      console.log("PWA launched with URL:", url);
    }
  });
}
