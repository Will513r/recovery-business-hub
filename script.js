// script.js
document.addEventListener("DOMContentLoaded", () => {
  const menuToggle = document.querySelector(".menu-toggle");
  const closeMenu = document.querySelector(".close-menu");
  const navLinks = document.querySelector(".nav-links");
  const navOverlay = document.querySelector(".nav-overlay");

  // Function to open/close the drawer
  function toggleMenu() {
    if (navLinks && navOverlay) {
      navLinks.classList.toggle("open");
      navOverlay.classList.toggle("open");
      // Prevent the background page from scrolling while menu is open
      document.body.style.overflow = navLinks.classList.contains("open")
        ? "hidden"
        : "";
    }
  }

  // Trigger the function on clicks
  if (menuToggle) menuToggle.addEventListener("click", toggleMenu);
  if (closeMenu) closeMenu.addEventListener("click", toggleMenu);
  if (navOverlay) navOverlay.addEventListener("click", toggleMenu); // Tap-to-close on background
});
// Add this to your existing script.js file
document.addEventListener('DOMContentLoaded', function() {
    var filterBtn = document.getElementById('mobile-filter-toggle');
    var filterCol = document.getElementById('filter-collapse');
    if (filterBtn && filterCol) {
        filterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            filterCol.classList.toggle('open');
        });
    }
});