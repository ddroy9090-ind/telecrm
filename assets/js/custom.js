// Toggle Sidebar Function
function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  sidebar.classList.toggle("collapsed");

  // Save state
  const isCollapsed = sidebar.classList.contains("collapsed");
  saveSidebarState(isCollapsed);
}

// Save sidebar state
function saveSidebarState(isCollapsed) {
  const state = {
    collapsed: isCollapsed,
    timestamp: new Date().getTime(),
  };
  // Store in memory only (no localStorage in Claude artifacts)
  window.sidebarState = state;
}

// Load sidebar state on page load
document.addEventListener("DOMContentLoaded", function () {
  // Check if state exists in memory
  if (window.sidebarState && window.sidebarState.collapsed) {
    document.getElementById("sidebar").classList.add("collapsed");
  }

  const menuItems = document.querySelectorAll(".sidebar-menu li");
  const menuLinks = document.querySelectorAll(".sidebar-menu a.sidebar-link");
  const dropdownParents = document.querySelectorAll(".sidebar-dropdown");

  // Determine active item based on current page
  const currentPath = window.location.pathname.split("/").pop() || "index.php";
  let activeItem = null;

  menuLinks.forEach((link) => {
    const linkPath = link.getAttribute("href");
    const parentItem = link.closest("li");

    if (parentItem) {
      parentItem.classList.remove("active");
    }

    const dropdownParent = link.closest(".sidebar-dropdown");
    if (dropdownParent) {
      dropdownParent.classList.remove("active");
    }

    if (linkPath === currentPath && parentItem) {
      activeItem = parentItem;
    }
  });

  if (!activeItem) {
    activeItem = document.querySelector(".sidebar-menu > li");
  }

  if (activeItem) {
    activeItem.classList.add("active");

    const dropdownParent = activeItem.closest(".sidebar-dropdown");
    if (dropdownParent) {
      dropdownParent.classList.add("active");
      dropdownParent.classList.add("open");
      const submenu = dropdownParent.querySelector(".sidebar-submenu");
      if (submenu) {
        submenu.style.maxHeight = submenu.scrollHeight + "px";
      }
    }
  }

  // Handle menu item clicks
  menuItems.forEach((item) => {
    item.addEventListener("click", function (e) {
      const link = e.target.closest("a.sidebar-link");
      if (!link) {
        return;
      }

      menuItems.forEach((li) => li.classList.remove("active"));
      dropdownParents.forEach((dropdown) => {
        if (dropdown !== this && !dropdown.contains(this)) {
          dropdown.classList.remove("active");
          dropdown.classList.remove("open");
          const submenu = dropdown.querySelector(".sidebar-submenu");
          if (submenu) {
            submenu.style.maxHeight = null;
          }
        }
      });

      this.classList.add("active");

      const dropdownParent = this.closest(".sidebar-dropdown");
      if (dropdownParent) {
        dropdownParent.classList.add("active");
        dropdownParent.classList.add("open");
        const submenu = dropdownParent.querySelector(".sidebar-submenu");
        if (submenu) {
          submenu.style.maxHeight = submenu.scrollHeight + "px";
        }
      }
    });
  });

  // Initialize Choices.js selects
  if (typeof Choices !== "undefined") {
    const choiceSelects = document.querySelectorAll("select[data-choices]");
    choiceSelects.forEach((select) => {
      new Choices(select, {
        searchEnabled: false,
        itemSelectText: "",
        shouldSort: false,
      });
    });
  }

  // Sidebar dropdown toggle
  const dropdowns = document.querySelectorAll(".sidebar-dropdown");
  dropdowns.forEach((dropdown) => {
    const toggle = dropdown.querySelector(".sidebar-dropdown-toggle");
    const submenu = dropdown.querySelector(".sidebar-submenu");

    if (toggle && submenu) {
      // Reset height
      submenu.style.maxHeight = null;

      toggle.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        dropdown.classList.toggle("open");

        if (dropdown.classList.contains("open")) {
          submenu.style.maxHeight = submenu.scrollHeight + "px";
        } else {
          submenu.style.maxHeight = null;
        }
      });

      // Open if any child is active on load
      if (submenu.querySelector("li.active")) {
        dropdown.classList.add("open");
        submenu.style.maxHeight = submenu.scrollHeight + "px";
      }
    }
  });

  // Mobile menu toggle
  if (window.innerWidth <= 768) {
    const toggleBtn = document.querySelector(".toggle-btn");
    if (toggleBtn) {
      toggleBtn.addEventListener("click", function () {
        document.getElementById("sidebar").classList.toggle("show");
      });

      // Close sidebar when clicking outside
      document.addEventListener("click", function (e) {
        const sidebar = document.getElementById("sidebar");

        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
          sidebar.classList.remove("show");
        }
      });
    }
  }
});

document.addEventListener("DOMContentLoaded", function () {
  const filterToggle = document.getElementById("filterToggle");
  const filtersSection = document.getElementById("leadFilters");

  if (!filterToggle || !filtersSection) {
    return;
  }

  const updateExpandedState = (isExpanded) => {
    filterToggle.setAttribute("aria-expanded", String(isExpanded));
    filtersSection.classList.toggle("is-expanded", isExpanded);
    filtersSection.style.maxHeight = isExpanded
      ? `${filtersSection.scrollHeight}px`
      : null;
  };

  updateExpandedState(false);

  filterToggle.addEventListener("click", function () {
    const willExpand = !filtersSection.classList.contains("is-expanded");
    updateExpandedState(willExpand);
  });

  if ("ResizeObserver" in window) {
    const resizeObserver = new ResizeObserver(() => {
      if (filtersSection.classList.contains("is-expanded")) {
        filtersSection.style.maxHeight = `${filtersSection.scrollHeight}px`;
      }
    });
    resizeObserver.observe(filtersSection);
  }
});

// Notification Bell Animation
const notificationIcon = document.querySelector(".notification-icon");
if (notificationIcon) {
  notificationIcon.addEventListener("click", function () {
    alert("You have 3 new notifications!");
  });
}

// Quick Actions Button Handlers
document.addEventListener("DOMContentLoaded", function () {
  const quickActionBtns = document.querySelectorAll(".dashboard-card .btn");
  quickActionBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const action = this.textContent.trim();
      console.log("Quick action clicked:", action);
      // Add your action logic here
    });
  });
});

// Simple enhancements for leads table
document.addEventListener("DOMContentLoaded", function () {
  const leadTable = document.querySelector(".lead-table-card table");

  if (!leadTable) {
    return;
  }

  leadTable.querySelectorAll("tbody tr").forEach((row) => {
    row.addEventListener("click", function () {
      const firstCell = this.cells?.[0];
      if (firstCell) {
        console.log("Row clicked:", firstCell.textContent.trim());
      }
    });
  });
});

// Responsive sidebar for mobile
window.addEventListener("resize", function () {
  const sidebar = document.getElementById("sidebar");
  if (window.innerWidth > 768) {
    sidebar.classList.remove("show");
  }
});

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute("href"));
    if (target) {
      target.scrollIntoView({
        behavior: "smooth",
      });
    }
  });
});

// Page loading animation
window.addEventListener("load", function () {
  document.body.style.opacity = "0";
  setTimeout(() => {
    document.body.style.transition = "opacity 0.3s";
    document.body.style.opacity = "1";
  }, 100);
});

//  Select box UI

document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".select-dropDownClass").forEach((el) => {
    new Choices(el, {
      searchEnabled: false,
      itemSelectText: "",
      shouldSort: false,
    });
  });
});

document.addEventListener("DOMContentLoaded", function () {
  const alerts = document.querySelectorAll(".alert");
  if (alerts.length) {
    setTimeout(() => {
      alerts.forEach((alert) => {
        if (typeof bootstrap !== "undefined" && bootstrap.Alert) {
          const alertInstance = bootstrap.Alert.getOrCreateInstance(alert);
          alertInstance.close();
        } else {
          alert.classList.add("d-none");
        }
      });
    }, 5000);
  }

  const addLeadForm = document.getElementById("addLeadForm");
  if (addLeadForm && addLeadForm.dataset.resetOnSuccess === "true") {
    addLeadForm.reset();
  }
});
