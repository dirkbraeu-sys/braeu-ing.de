(function () {
  "use strict";

  var toggle = document.querySelector(".nav-toggle");
  var nav = document.querySelector(".main-nav");
  var header = document.querySelector(".site-header");

  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var isOpen = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      toggle.textContent = isOpen ? "✕" : "☰";
      document.body.classList.toggle("nav-locked", isOpen);
    });

    // Close menu when a link is tapped
    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.textContent = "☰";
        document.body.classList.remove("nav-locked");
      });
    });
  }

  // Shrink header slightly on scroll for a tighter, modern feel
  if (header) {
    var lastState = false;
    window.addEventListener(
      "scroll",
      function () {
        var scrolled = window.scrollY > 12;
        if (scrolled !== lastState) {
          header.classList.toggle("is-scrolled", scrolled);
          lastState = scrolled;
        }
      },
      { passive: true }
    );
  }
})();
