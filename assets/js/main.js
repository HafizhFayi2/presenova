// Add hovered class to selected list item
let list = document.querySelectorAll(".navigation li");
const navigationEl = document.querySelector(".navigation");
let defaultHovered = document.querySelector(".navigation li.hovered");

function setHovered(target) {
  list.forEach((item) => item.classList.remove("hovered"));
  if (target) {
    target.classList.add("hovered");
  }
}

function activeLink() {
  setHovered(this);
}

list.forEach((item) => item.addEventListener("mouseover", activeLink));

if (navigationEl) {
  navigationEl.addEventListener("mouseleave", function () {
    setHovered(defaultHovered);
  });
}

document.querySelectorAll(".navigation li a").forEach((link) => {
  link.addEventListener("click", function () {
    defaultHovered = this.closest("li");
    setHovered(defaultHovered);
  });
});

// Menu Toggle
let toggle = document.querySelector(".toggle");
let navigation = document.querySelector(".navigation");
let main = document.querySelector(".main");
let containerFluid = document.querySelector(".container-fluid");

if (toggle && navigation && main) {
  toggle.onclick = function () {
    navigation.classList.toggle("active");
    main.classList.toggle("active");
    containerFluid?.classList.toggle("sidebar-active");
    containerFluid?.classList.toggle("active");
  };
}

// Theme Management
function initTheme() {
  // Check for saved theme preference or default to 'light' mode
  const savedTheme = getCookie("admin_theme") || "light";
  document.documentElement.setAttribute("data-theme", savedTheme);

  // Update theme toggle icon
  updateThemeIcon(savedTheme);
}

function updateThemeIcon(theme) {
  const themeToggle = document.getElementById("themeToggle");
  if (themeToggle) {
    const icon = themeToggle.querySelector("i");
    if (icon) {
      if (theme === "dark") {
        icon.className = "fas fa-sun";
      } else {
        icon.className = "fas fa-moon";
      }
    }
  }
}

function getCookie(name) {
  const nameEQ = name + "=";
  const ca = document.cookie.split(";");
  for (let i = 0; i < ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) === " ") c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
  }
  return null;
}

function setCookie(name, value, days) {
  let expires = "";
  if (days) {
    const date = new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
    expires = "; expires=" + date.toUTCString();
  }
  document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

function applyThemeToTables() {
  const isDark = document.documentElement.getAttribute("data-theme") === "dark";
  document.querySelectorAll("table").forEach((table) => {
    table.classList.toggle("table-dark", isDark);
  });

  if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
    window.jQuery("table").each(function () {
      if (window.jQuery.fn.DataTable.isDataTable(this)) {
        window.jQuery(this).DataTable().draw(false);
      }
    });
  }
}

// Initialize theme on page load
document.addEventListener("DOMContentLoaded", function () {
  initTheme();
  applyThemeToTables();

  // Auto-adjust navigation on desktop
  if (window.innerWidth > 991) {
    if (navigation && main) {
      navigation.classList.remove("active");
      main.classList.remove("active");
      containerFluid?.classList.remove("sidebar-active");
      containerFluid?.classList.remove("active");
    }
  }

});

// Handle window resize
window.addEventListener("resize", function () {
  if (window.innerWidth > 991) {
    if (navigation && main) {
      navigation.classList.remove("active");
      main.classList.remove("active");
      containerFluid?.classList.remove("sidebar-active");
      containerFluid?.classList.remove("active");
    }
  }
});

// Close sidebar when clicking outside on mobile
document.addEventListener("click", function (event) {
  if (window.innerWidth <= 991) {
    const isClickInside =
      navigation?.contains(event.target) || toggle?.contains(event.target);

    if (!isClickInside && navigation?.classList.contains("active")) {
      navigation.classList.remove("active");
      main?.classList.remove("active");
      containerFluid?.classList.remove("sidebar-active");
      containerFluid?.classList.remove("active");
    }
  }
});

// Close sidebar when clicking a navigation link on mobile
document.querySelectorAll(".navigation a").forEach((link) => {
  link.addEventListener("click", function () {
    if (window.innerWidth <= 991) {
      navigation?.classList.remove("active");
      main?.classList.remove("active");
      containerFluid?.classList.remove("sidebar-active");
      containerFluid?.classList.remove("active");
    }
  });
});

// Smooth scroll behavior
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute("href"));
    if (target) {
      target.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }
  });
});

// Add loading animation to cards
document.querySelectorAll(".card").forEach((card) => {
  card.addEventListener("click", function () {
    this.style.transform = "scale(0.95)";
    setTimeout(() => {
      this.style.transform = "";
    }, 150);
  });
});

// Enhanced search functionality
const searchInput = document.querySelector(".search input");
if (searchInput) {
  let searchTimeout;

  searchInput.addEventListener("input", function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      const searchTerm = this.value.toLowerCase();

      // Search in visible tables
      document.querySelectorAll("table tbody tr").forEach((row) => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
          row.style.display = "";
        } else {
          row.style.display = "none";
        }
      });
    }, 300);
  });
}

// Add ripple effect to buttons
document.querySelectorAll(".btn, .card, .toggle").forEach((element) => {
  element.addEventListener("click", function (e) {
    const ripple = document.createElement("span");
    ripple.classList.add("ripple");
    this.appendChild(ripple);

    const x = e.clientX - this.offsetLeft;
    const y = e.clientY - this.offsetTop;

    ripple.style.left = x + "px";
    ripple.style.top = y + "px";

    setTimeout(() => {
      ripple.remove();
    }, 600);
  });
});

// Add CSS for ripple effect dynamically
const style = document.createElement("style");
style.textContent = `
  .ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    transform: scale(0);
    animation: ripple-animation 0.6s ease-out;
    pointer-events: none;
  }
  
  @keyframes ripple-animation {
    to {
      transform: scale(4);
      opacity: 0;
    }
  }
`;
document.head.appendChild(style);

// Lazy load images
document.querySelectorAll("img[data-src]").forEach((img) => {
  img.setAttribute("src", img.getAttribute("data-src"));
  img.onload = function () {
    img.removeAttribute("data-src");
  };
});

// Add fade-in animation for cards
const observerOptions = {
  threshold: 0.1,
  rootMargin: "0px 0px -50px 0px",
};

const observer = new IntersectionObserver(function (entries) {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = "1";
      entry.target.style.transform = "translateY(0)";
    }
  });
}, observerOptions);

document
  .querySelectorAll(".card, .recentOrders, .recentCustomers")
  .forEach((el) => {
    el.style.opacity = "0";
    el.style.transform = "translateY(20px)";
    el.style.transition = "opacity 0.5s ease, transform 0.5s ease";
    observer.observe(el);
  });

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}

// Add keyboard shortcuts
document.addEventListener("keydown", function (e) {
  // Ctrl/Cmd + K to focus search
  if ((e.ctrlKey || e.metaKey) && e.key === "k") {
    e.preventDefault();
    searchInput?.focus();
  }

  // Escape to close mobile menu
  if (e.key === "Escape") {
    if (navigation?.classList.contains("active")) {
      navigation.classList.remove("active");
      main?.classList.remove("active");
      containerFluid?.classList.remove("sidebar-active");
      containerFluid?.classList.remove("active");
    }
  }
});

// Update time in search placeholder
function updateClock() {
  const now = new Date();
  const timeString = now.toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
  });

  if (searchInput && window.innerWidth > 768) {
    searchInput.setAttribute("placeholder", `Search... (${timeString})`);
  }
}

// Update clock every minute
setInterval(updateClock, 60000);
updateClock();

console.log("Enhanced admin panel loaded successfully! 🎉");
