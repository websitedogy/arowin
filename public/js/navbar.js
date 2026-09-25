/* Arowin — Navbar / Sidebar */
(function () {
      var toggle = document.getElementById("menuToggle");
      var sidebar = document.getElementById("sidebar");
      var overlay = document.getElementById("sidebarOverlay");
      var closeBtn = document.getElementById("sidebarClose");
      if (!toggle || !sidebar || !overlay) return;

      function openSidebar() {
        sidebar.classList.add("open");
        overlay.classList.add("open");
        document.body.classList.add("sidebar-open");
        sidebar.setAttribute("aria-hidden", "false");
        overlay.setAttribute("aria-hidden", "false");
        toggle.setAttribute("aria-expanded", "true");
        toggle.setAttribute("aria-label", "Close menu");
      }

      function closeSidebar() {
        sidebar.classList.remove("open");
        overlay.classList.remove("open");
        document.body.classList.remove("sidebar-open");
        sidebar.setAttribute("aria-hidden", "true");
        overlay.setAttribute("aria-hidden", "true");
        toggle.setAttribute("aria-expanded", "false");
        toggle.setAttribute("aria-label", "Open menu");
      }

      function toggleSidebar() {
        if (sidebar.classList.contains("open")) closeSidebar();
        else openSidebar();
      }

      toggle.addEventListener("click", toggleSidebar);
      if (closeBtn) closeBtn.addEventListener("click", closeSidebar);
      overlay.addEventListener("click", closeSidebar);

      sidebar.querySelectorAll("a").forEach(function (link) {
        link.addEventListener("click", closeSidebar);
      });

      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && sidebar.classList.contains("open")) closeSidebar();
      });
    })();
