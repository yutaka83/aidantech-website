(function () {
  "use strict";

  function markActiveNav() {
    var page = document.body.getAttribute("data-page");
    if (!page) return;
    document.querySelectorAll("[data-nav]").forEach(function (link) {
      if (link.getAttribute("data-nav") === page) {
        link.setAttribute("aria-current", "page");
      }
    });
  }

  function initMobileNav() {
    var toggle = document.getElementById("nav-toggle");
    var close = document.getElementById("mobile-close");
    var panel = document.getElementById("mobile-nav");
    if (!toggle || !panel) return;

    function open() {
      panel.classList.add("is-open");
      toggle.setAttribute("aria-expanded", "true");
      document.body.style.overflow = "hidden";
    }
    function shut() {
      panel.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
      document.body.style.overflow = "";
    }

    toggle.addEventListener("click", open);
    if (close) close.addEventListener("click", shut);

    panel.querySelectorAll(".mobile-group-toggle").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var group = btn.closest(".mobile-group");
        var isOpen = group.classList.contains("is-open");
        panel.querySelectorAll(".mobile-group.is-open").forEach(function (g) {
          g.classList.remove("is-open");
          g.querySelector(".mobile-group-toggle").setAttribute("aria-expanded", "false");
        });
        if (!isOpen) {
          group.classList.add("is-open");
          btn.setAttribute("aria-expanded", "true");
        }
      });
    });

    panel.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", shut);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && panel.classList.contains("is-open")) shut();
    });
  }

  function initBackToTop() {
    var btn = document.getElementById("back-to-top");
    if (!btn) return;
    window.addEventListener("scroll", function () {
      btn.classList.toggle("is-visible", window.scrollY > 480);
    });
  }

  function initFooterYear() {
    var y = document.getElementById("year");
    if (y) y.textContent = new Date().getFullYear();
  }

  function initTabs() {
    document.querySelectorAll("[data-tabs]").forEach(function (group) {
      var buttons = group.querySelectorAll(".tab-btn");
      var panels = group.querySelectorAll(".tab-panel");
      buttons.forEach(function (btn) {
        btn.addEventListener("click", function () {
          buttons.forEach(function (b) { b.setAttribute("aria-selected", "false"); });
          panels.forEach(function (p) { p.classList.remove("is-active"); });
          btn.setAttribute("aria-selected", "true");
          var target = document.getElementById(btn.getAttribute("aria-controls"));
          if (target) target.classList.add("is-active");
        });
      });
    });
  }

  function loadPartial(selector, url) {
    var host = document.querySelector(selector);
    if (!host) return Promise.resolve();
    return fetch(url)
      .then(function (res) { return res.text(); })
      .then(function (html) { host.innerHTML = html; });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var depth = document.body.getAttribute("data-depth") || "";
    Promise.all([
      loadPartial("#site-header", depth + "partials/header.html"),
      loadPartial("#site-footer", depth + "partials/footer.html")
    ]).then(function () {
      markActiveNav();
      initMobileNav();
      initFooterYear();
    });

    initBackToTop();
    initTabs();
  });
})();
