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

// Leads sidebar interactions
document.addEventListener("DOMContentLoaded", function () {
  const leadTable = document.querySelector(".lead-table-card table");
  const leadSidebar = document.getElementById("leadSidebar");
  const sidebarOverlay = document.getElementById("leadSidebarOverlay");

  if (!leadTable || !leadSidebar || !sidebarOverlay) {
    return;
  }

  const body = document.body;
  let overlayHideTimer = null;
  let activeTrigger = null;

  const sidebarFields = {
    avatar: leadSidebar.querySelector('[data-lead-field="avatarInitial"]'),
    name: leadSidebar.querySelector('[data-lead-field="name"]'),
    stage: leadSidebar.querySelector('[data-lead-field="stage"]'),
    ratingLabel: leadSidebar.querySelector('[data-lead-field="ratingLabel"]'),
    email: leadSidebar.querySelector('[data-lead-field="email"]'),
    phone: leadSidebar.querySelector('[data-lead-field="phone"]'),
    nationality: leadSidebar.querySelector('[data-lead-field="nationality"]'),
    location: leadSidebar.querySelector('[data-lead-field="location"]'),
    propertyType: leadSidebar.querySelector('[data-lead-field="propertyType"]'),
    interestedIn: leadSidebar.querySelector('[data-lead-field="interestedIn"]'),
    budget: leadSidebar.querySelector('[data-lead-field="budget"]'),
    moveIn: leadSidebar.querySelector('[data-lead-field="moveIn"]'),
    source: leadSidebar.querySelector('[data-lead-field="source"]'),
    assignedTo: leadSidebar.querySelector('[data-lead-field="assignedTo"]'),
    createdAt: leadSidebar.querySelector('[data-lead-field="createdAt"]'),
    tags: leadSidebar.querySelector('[data-lead-field="tags"]'),
    purpose: leadSidebar.querySelector('[data-lead-field="purpose"]'),
    sizeRequired: leadSidebar.querySelector('[data-lead-field="sizeRequired"]'),
  };

  const quickActions = {
    call: leadSidebar.querySelector('[data-action="call"]'),
    email: leadSidebar.querySelector('[data-action="email"]'),
    whatsapp: leadSidebar.querySelector('[data-action="whatsapp"]'),
  };

  const sidebarForm = leadSidebar.querySelector('[data-lead-form]');
  const sidebarFeedback = leadSidebar.querySelector('[data-lead-feedback]');
  const editButton = leadSidebar.querySelector('[data-action="edit"]');
  const ratingStars = leadSidebar.querySelectorAll('[data-rating-star]');
  const remarksContainer = leadSidebar.querySelector('[data-lead-remarks]');
  const filesContainer = leadSidebar.querySelector('[data-lead-files]');
  const historyContainer = leadSidebar.querySelector('[data-lead-history]');
  const remarkForm = leadSidebar.querySelector('.lead-remark-form');
  const remarkInput = remarkForm ? remarkForm.querySelector('textarea') : null;
  const remarkFileInput = remarkForm ? remarkForm.querySelector('.lead-file-upload__input') : null;
  let highlightNextRemark = false;
  const filesUploadInput = leadSidebar.querySelector('[data-tab-panel="files"] .lead-file-upload__input');
  const tabs = Array.from(leadSidebar.querySelectorAll(".lead-sidebar-tab"));
  const panels = Array.from(leadSidebar.querySelectorAll(".lead-sidebar-panel"));
  const closeButton = leadSidebar.querySelector('[data-action="close"]');
  const deleteForm = document.getElementById("deleteLeadForm");
  const deleteInput = document.getElementById("deleteLeadInput");

  const editableFieldElements = Array.from(leadSidebar.querySelectorAll('[data-edit-field]'));
  const editableFields = editableFieldElements.reduce((accumulator, element) => {
    const key = element.getAttribute('data-edit-field');
    if (!key) {
      return accumulator;
    }

    const field = {
      container: element,
      display: element.querySelector('[data-role="display"]') || element.querySelector('[data-lead-field]'),
      input: element.querySelector('[data-role="input"]'),
    };

    if (field.input) {
      field.input.disabled = true;
    }

    accumulator[key] = field;
    return accumulator;
  }, {});

  const idInput = sidebarForm ? sidebarForm.querySelector('[data-edit-id]') : null;

  let isEditing = false;
  let isSaving = false;
  let currentLeadData = null;
  let currentLeadRow = null;

  const updateEditButtonState = () => {
    if (!editButton) {
      return;
    }

    const label = isSaving
      ? 'Saving lead details'
      : isEditing
        ? 'Save lead details'
        : 'Edit lead details';
    const iconClass = isEditing || isSaving ? 'bi bi-save' : 'bi bi-pencil-square';

    editButton.setAttribute('aria-label', label);
    editButton.setAttribute('title', label);

    const icon = editButton.querySelector('i');
    if (icon) {
      icon.className = iconClass;
    }
  };

  updateEditButtonState();

  const setOverlayVisibility = (isVisible) => {
    if (overlayHideTimer) {
      window.clearTimeout(overlayHideTimer);
      overlayHideTimer = null;
    }

    if (isVisible) {
      sidebarOverlay.hidden = false;
      requestAnimationFrame(() => {
        sidebarOverlay.classList.add("is-visible");
      });
    } else {
      sidebarOverlay.classList.remove("is-visible");
      overlayHideTimer = window.setTimeout(() => {
        sidebarOverlay.hidden = true;
      }, 260);
    }
  };

  const openSidebar = () => {
    leadSidebar.classList.add("is-open");
    leadSidebar.setAttribute("aria-hidden", "false");
    setOverlayVisibility(true);
    body.classList.add("lead-sidebar-open");
  };

  const closeSidebar = () => {
    leadSidebar.classList.remove("is-open");
    leadSidebar.setAttribute("aria-hidden", "true");
    setOverlayVisibility(false);
    body.classList.remove("lead-sidebar-open");

    setEditing(false);
    clearFeedback();
    currentLeadRow = null;

    if (activeTrigger) {
      activeTrigger.focus();
      activeTrigger = null;
    }
  };

  sidebarOverlay.addEventListener("click", closeSidebar);
  if (closeButton) {
    closeButton.addEventListener("click", closeSidebar);
  }

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && leadSidebar.classList.contains("is-open")) {
      closeSidebar();
    }
  });

  const sanitizeList = (value) => {
    if (!value) {
      return [];
    }

    if (Array.isArray(value)) {
      return value.filter((item) => String(item).trim() !== "");
    }

    return String(value)
      .split(/[,|]/)
      .map((item) => item.trim())
      .filter((item) => item !== "");
  };

  const setTextContent = (element, value, fallback = "—") => {
    if (!element) {
      return;
    }

    const displayValue = value && String(value).trim() !== "" ? value : fallback;
    element.textContent = displayValue;
    if (displayValue === fallback) {
      element.classList.add("is-empty");
    } else {
      element.classList.remove("is-empty");
    }
  };

  const setLinkField = (element, value, formatter, fallback) => {
    if (!element) {
      return;
    }

    const hasValue = value && String(value).trim() !== "";
    const textValue = hasValue ? String(value).trim() : fallback;
    element.textContent = textValue;

    if (hasValue) {
      const linkValue = formatter(String(value).trim());
      element.setAttribute("href", linkValue || "#");
      element.classList.remove("is-empty");
    } else {
      element.setAttribute("href", "#");
      element.classList.add("is-empty");
    }
  };

  const renderChips = (container, values, emptyText) => {
    if (!container) {
      return;
    }

    container.innerHTML = "";
    const items = sanitizeList(values);

    if (!items.length) {
      const empty = document.createElement("span");
      empty.className = "lead-empty-text";
      empty.textContent = emptyText || "No data available";
      container.appendChild(empty);
      return;
    }

    items.forEach((value) => {
      const chip = document.createElement("span");
      chip.className = "lead-chip";
      chip.textContent = value;
      container.appendChild(chip);
    });
  };

  const escapeHtml = (value) =>
    String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  const clearFeedback = () => {
    if (!sidebarFeedback) {
      return;
    }

    sidebarFeedback.textContent = '';
    sidebarFeedback.classList.remove('is-visible', 'is-success', 'is-error');
    sidebarFeedback.hidden = true;
  };

  const showFeedback = (message, type = 'success') => {
    if (!sidebarFeedback) {
      return;
    }

    if (!message) {
      clearFeedback();
      return;
    }

    sidebarFeedback.textContent = message;
    sidebarFeedback.hidden = false;
    sidebarFeedback.classList.remove('is-success', 'is-error');
    sidebarFeedback.classList.add('is-visible');

    if (type === 'error') {
      sidebarFeedback.classList.add('is-error');
    } else {
      sidebarFeedback.classList.add('is-success');
    }
  };

  const parseJsonResponse = (response, fallbackMessage = 'Unable to process the request.') =>
    response
      .json()
      .catch(() => ({}))
      .then((data) => {
        if (!response.ok || data?.success === false) {
          const errorMessage = data?.message || fallbackMessage;
          throw new Error(errorMessage);
        }
        return data;
      });

  const setRemarkFormSubmitting = (isSubmitting) => {
    if (!remarkForm) {
      return;
    }

    remarkForm.classList.toggle('is-loading', Boolean(isSubmitting));
    const formElements = remarkForm.querySelectorAll('textarea, input, button');
    formElements.forEach((element) => {
      element.disabled = Boolean(isSubmitting);
    });
  };

  const setFilesUploading = (isUploading) => {
    if (!filesUploadInput) {
      return;
    }

    filesUploadInput.disabled = Boolean(isUploading);
    filesUploadInput.setAttribute('aria-busy', String(Boolean(isUploading)));

    if (isUploading) {
      filesUploadInput.classList.add('is-uploading');
    } else {
      filesUploadInput.classList.remove('is-uploading');
    }
  };

  const applyLeadResponse = (leadResponse) => {
    if (!leadResponse) {
      return;
    }

    const { row, payload, json } = leadResponse;

    if (row && typeof row.id !== 'undefined') {
      const updatedRow = updateTableRowDom(row, json || leadResponse.json);
      if (updatedRow) {
        currentLeadRow = updatedRow;
      }
    } else if (json && currentLeadRow) {
      currentLeadRow.dataset.leadJson = json;
    }

    if (payload) {
      populateSidebar(payload);
    }
  };

  const formFieldMap = {
    name: 'name',
    stage: 'stage',
    rating: 'rating',
    email: 'email',
    phone: 'phone',
    nationality: 'nationality',
    location_preferences: 'locationPreferences',
    property_type: 'propertyType',
    interested_in: 'interestedIn',
    budget_range: 'budgetRange',
    urgency: 'moveInTimeline',
    size_required: 'sizeRequired',
    source: 'source',
    assigned_to: 'assignedTo',
    purpose: 'purpose',
  };

  const applyFormValues = (leadData) => {
    if (!sidebarForm) {
      return;
    }

    Object.entries(formFieldMap).forEach(([fieldKey, payloadKey]) => {
      const field = editableFields[fieldKey];
      if (!field || !field.input) {
        return;
      }

      const rawValue = leadData && payloadKey in leadData ? leadData[payloadKey] : '';
      const value = Array.isArray(rawValue) ? rawValue.join(', ') : String(rawValue ?? '');

      if (field.input.tagName === 'SELECT') {
        let matched = false;
        Array.from(field.input.options).forEach((option) => {
          const isMatch = option.value === value;
          option.selected = isMatch;
          if (isMatch) {
            matched = true;
          }
        });

        if (!matched) {
          if (value === '') {
            field.input.value = '';
          } else {
            const option = new Option(value, value, true, true);
            field.input.add(option);
          }
        }
      } else {
        field.input.value = value;
      }
    });

    if (idInput) {
      const idValue = leadData && 'id' in leadData ? leadData.id : '';
      idInput.value = idValue ?? '';
    }
  };

  const setEditing = (nextState) => {
    const shouldEdit = Boolean(nextState);
    if (shouldEdit === isEditing) {
      if (!shouldEdit && currentLeadData) {
        applyFormValues(currentLeadData);
      }
      updateEditButtonState();
      return;
    }

    isEditing = shouldEdit;
    isSaving = false;

    if (editButton) {
      editButton.disabled = false;
      editButton.removeAttribute('aria-busy');
    }

    leadSidebar.classList.toggle('is-editing', isEditing);

    Object.values(editableFields).forEach((field) => {
      if (field && field.input) {
        field.input.disabled = !isEditing;
      }
    });

    if (isEditing) {
      clearFeedback();
      const firstEditable = Object.values(editableFields).find((field) => field?.input);
      if (firstEditable?.input) {
        window.requestAnimationFrame(() => {
          firstEditable.input.focus();
          if (typeof firstEditable.input.select === 'function') {
            firstEditable.input.select();
          }
        });
      }
    } else if (currentLeadData) {
      applyFormValues(currentLeadData);
    }

    updateEditButtonState();
  };

  const setSavingState = (nextState) => {
    isSaving = Boolean(nextState);

    if (!editButton) {
      return;
    }

    editButton.disabled = isSaving;
    if (isSaving) {
      editButton.setAttribute('aria-busy', 'true');
    } else {
      editButton.removeAttribute('aria-busy');
    }

    updateEditButtonState();
  };

  const normalizeInputValue = (input) => {
    if (!input) {
      return '';
    }

    if (input instanceof HTMLInputElement) {
      if (input.type === 'checkbox' || input.type === 'radio') {
        return input.checked ? input.value : '';
      }

      return input.value ?? '';
    }

    if (input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
      return input.value ?? '';
    }

    return String(input.value ?? '');
  };

  const gatherFormPayload = () => {
    if (!sidebarForm) {
      return null;
    }

    const payload = {};

    Object.entries(formFieldMap).forEach(([fieldKey, payloadKey]) => {
      const field = editableFields[fieldKey];
      if (!field) {
        return;
      }

      if (field.input) {
        payload[fieldKey] = normalizeInputValue(field.input).trim();
        return;
      }

      if (currentLeadData && Object.prototype.hasOwnProperty.call(currentLeadData, payloadKey)) {
        const existingValue = currentLeadData[payloadKey];
        payload[fieldKey] = Array.isArray(existingValue)
          ? existingValue.join(', ').trim()
          : String(existingValue ?? '').trim();
        return;
      }

      payload[fieldKey] = '';
    });

    const idValue = idInput?.value ?? currentLeadData?.id ?? '';
    payload.id = Number(idValue || 0);

    return payload;
  };

  const updateTableRowDom = (rowInfo, encodedJson) => {
    if (!rowInfo || typeof rowInfo.id === 'undefined') {
      return;
    }

    const row = leadTable?.querySelector(`tr[data-lead-id="${rowInfo.id}"]`);
    if (!row) {
      return null;
    }

    if (encodedJson) {
      row.dataset.leadJson = encodedJson;
    }

    if (typeof rowInfo.name !== 'undefined') {
      row.dataset.leadName = rowInfo.name || 'Unnamed Lead';
      row.setAttribute('aria-label', `View details for ${row.dataset.leadName}`);
      const nameElement = row.querySelector('[data-lead-name]');
      if (nameElement) {
        nameElement.textContent = row.dataset.leadName;
      }
    }

    if (typeof rowInfo.avatarInitial !== 'undefined') {
      const avatarElement = row.querySelector('[data-lead-avatar]');
      if (avatarElement) {
        avatarElement.textContent = rowInfo.avatarInitial || '';
      }
    }

    const contactContainer = row.querySelector('[data-lead-contact]');
    if (contactContainer) {
      const fragments = [];
      if (rowInfo.email) {
        fragments.push(`<span data-lead-contact-email><i class="bi bi-envelope"></i> ${escapeHtml(rowInfo.email)}</span>`);
      }
      if (rowInfo.phone) {
        const phoneMarkup = `<span data-lead-contact-phone><i class="bi bi-telephone"></i> ${escapeHtml(rowInfo.phone)}</span>`;
        if (fragments.length) {
          fragments.push('<br>');
        }
        fragments.push(phoneMarkup);
      }
      if (!fragments.length) {
        fragments.push('<span class="text-muted" data-lead-contact-empty>No contact details</span>');
      }
      contactContainer.innerHTML = fragments.join('');
    }

    const stageBadge = row.querySelector('[data-lead-stage-pill]');
    if (stageBadge) {
      stageBadge.textContent = rowInfo.stage || 'New';
      stageBadge.className = 'stage-badge';
      if (rowInfo.stageClass) {
        stageBadge.classList.add(rowInfo.stageClass);
      }
    }

    const sourceCell = row.querySelector('[data-lead-source]');
    if (sourceCell) {
      sourceCell.textContent = rowInfo.source || '—';
    }

    const assignedSelect = row.querySelector('[data-lead-assigned-select]');
    if (assignedSelect) {
      const desiredValue = rowInfo.assigned_to || '';
      let hasMatch = false;
      Array.from(assignedSelect.options).forEach((option) => {
        const isMatch = option.value === desiredValue;
        option.selected = isMatch;
        if (isMatch) {
          hasMatch = true;
        }
      });
      if (!hasMatch) {
        assignedSelect.value = '';
      }
    }

    return row;
  };

  const buildWhatsappLink = (phoneNumber) => {
    const numeric = String(phoneNumber || "").replace(/[^0-9+]/g, "");
    if (!numeric) {
      return "#";
    }
    return `https://wa.me/${numeric.replace(/^\+/, "")}`;
  };

  const formatPhoneLink = (phoneNumber) => {
    const sanitized = String(phoneNumber || "").replace(/[^0-9+]/g, "");
    return sanitized ? `tel:${sanitized}` : "#";
  };

  const formatRatingLabel = (rawValue, numericValue, isUserUpdate = false) => {
    if (isUserUpdate && numericValue) {
      return `${numericValue} / 5`;
    }

    const cleanedRaw = rawValue && String(rawValue).trim() !== "" ? String(rawValue).trim() : null;

    if (cleanedRaw && !Number.isNaN(Number(cleanedRaw))) {
      const numericRaw = Number(cleanedRaw);
      return `${numericRaw} / 5`;
    }

    if (cleanedRaw && numericValue) {
      return `${cleanedRaw} • ${numericValue} / 5`;
    }

    if (cleanedRaw) {
      return cleanedRaw;
    }

    if (numericValue) {
      return `${numericValue} / 5`;
    }

    return "Not rated";
  };

  const ratingValueFromString = (value) => {
    if (!value) {
      return 0;
    }

    const numeric = Number(value);
    if (!Number.isNaN(numeric) && numeric >= 0) {
      return Math.max(0, Math.min(5, Math.round(numeric)));
    }

    const normalized = String(value).toLowerCase();
    if (normalized.includes("hot")) {
      return 5;
    }
    if (normalized.includes("warm")) {
      return 3;
    }
    if (normalized.includes("cold")) {
      return 1;
    }
    if (normalized.includes("new")) {
      return 2;
    }

    return 0;
  };

  const updateRatingStars = (value) => {
    ratingStars.forEach((star) => {
      const starValue = Number(star.dataset.ratingStar || 0);
      const isActive = starValue <= value && starValue > 0;
      star.classList.toggle("is-active", isActive);
      star.setAttribute("aria-pressed", String(isActive && starValue === value));
      const icon = star.querySelector("i");
      if (icon) {
        icon.classList.toggle("bi-star-fill", isActive);
        icon.classList.toggle("bi-star", !isActive);
      }
    });
  };

  const renderRemarks = (remarks, fallbackTimestamp, fallbackAuthor) => {
    if (!remarksContainer) {
      return;
    }

    remarksContainer.innerHTML = "";
    const items = Array.isArray(remarks) ? remarks : [];

    const sanitizedRemarks = items
      .map((remark) => {
        const author = remark?.author && String(remark.author).trim() !== "" ? String(remark.author).trim() : null;
        const timestamp = remark?.timestamp && String(remark.timestamp).trim() !== "" ? String(remark.timestamp).trim() : null;
        const text = remark?.text && String(remark.text).trim() !== "" ? String(remark.text).trim() : null;
        const attachments = Array.isArray(remark?.attachments)
          ? remark.attachments
              .map((file) => ({
                name: file?.name && String(file.name).trim() !== "" ? String(file.name).trim() : "Attachment",
                url: file?.url || file?.path || "",
              }))
              .filter((file) => file.url !== "")
          : [];

        return {
          author: author || "Team",
          timestamp: timestamp || "—",
          text: text || "No remark details provided.",
          attachments,
        };
      })
      .filter((remark) => remark.text || remark.attachments.length);

    if (!sanitizedRemarks.length) {
      const empty = document.createElement("p");
      empty.className = "lead-empty-state lead-remarks__empty";
      empty.textContent = "No remarks yet. Add one to keep track of updates.";
      remarksContainer.appendChild(empty);
      remarksContainer.setAttribute("data-has-remarks", "false");
      highlightNextRemark = false;
      return;
    }

    const fragment = document.createDocumentFragment();
    let highlightedRemark = null;

    sanitizedRemarks.forEach((remark, index) => {
      const remarkBlock = document.createElement("div");
      remarkBlock.className = "lead-remark";

      const meta = document.createElement("div");
      meta.className = "lead-remark__meta";

      const author = document.createElement("span");
      author.className = "lead-remark__author";
      author.textContent = remark.author;

      const separator = document.createElement("span");
      separator.className = "lead-remark__separator";
      separator.textContent = "•";

      const time = document.createElement("span");
      time.className = "lead-remark__timestamp";
      time.textContent = remark.timestamp;

      meta.append(author);
      meta.append(separator);
      meta.append(time);

      const text = document.createElement("div");
      text.className = "lead-remark__text";
      text.textContent = remark.text;

      remarkBlock.append(meta, text);

      if (remark.attachments.length) {
        const attachmentsWrapper = document.createElement("div");
        attachmentsWrapper.className = "lead-remark__attachments";

        remark.attachments.forEach((file) => {
          const attachmentLink = document.createElement("a");
          attachmentLink.className = "lead-remark__attachment";
          attachmentLink.textContent = file.name || "Attachment";
          attachmentLink.href = file.url;
          attachmentLink.target = "_blank";
          attachmentLink.rel = "noreferrer noopener";
          attachmentsWrapper.appendChild(attachmentLink);
        });

        remarkBlock.appendChild(attachmentsWrapper);
      }

      if (highlightNextRemark && index === 0) {
        remarkBlock.classList.add("is-new");
        remarkBlock.setAttribute("tabindex", "-1");
        highlightedRemark = remarkBlock;
      }

      fragment.appendChild(remarkBlock);
    });

    remarksContainer.appendChild(fragment);
    remarksContainer.setAttribute("data-has-remarks", "true");

    if (highlightedRemark) {
      requestAnimationFrame(() => {
        highlightedRemark.scrollIntoView({ behavior: "smooth", block: "nearest" });
        highlightedRemark.focus?.();
      });
    }

    highlightNextRemark = false;
  };

  const renderFiles = (files) => {
    if (!filesContainer) {
      return;
    }

    filesContainer.innerHTML = "";
    const items = Array.isArray(files) ? files : [];

    if (!items.length) {
      const empty = document.createElement("p");
      empty.className = "lead-empty-state";
      empty.textContent = "No files uploaded yet.";
      filesContainer.appendChild(empty);
      return;
    }

    items.forEach((file) => {
      const item = document.createElement("a");
      item.className = "lead-remark__attachment";
      const fileName = file?.name || "Document";
      const fileUrl = file?.url || file?.path || "#";
      item.textContent = fileName;
      item.href = fileUrl;
      item.target = "_blank";
      item.rel = "noreferrer noopener";

      const metaParts = [];
      if (file?.uploadedBy) {
        metaParts.push(String(file.uploadedBy));
      }
      if (file?.timestamp) {
        metaParts.push(String(file.timestamp));
      }
      if (metaParts.length) {
        item.title = metaParts.join(' • ');
      }

      filesContainer.appendChild(item);
    });
  };

  const renderHistory = (history) => {
    if (!historyContainer) {
      return;
    }

    historyContainer.innerHTML = "";
    const entries = Array.isArray(history) ? history : [];

    if (!entries.length) {
      const empty = document.createElement("p");
      empty.className = "lead-empty-state";
      empty.textContent = "No history available.";
      historyContainer.appendChild(empty);
      return;
    }

    entries.forEach((entry) => {
      const item = document.createElement("div");
      item.className = "lead-history__item";
      if (entry?.type) {
        item.dataset.historyType = String(entry.type);
      }

      const description = document.createElement("span");
      description.className = "lead-history__description";
      const entryDescription = entry?.description || "Update";
      const actorName = entry?.actor ? String(entry.actor).trim() : "";

      if (actorName && !entryDescription.toLowerCase().includes(actorName.toLowerCase())) {
        const actorLabel = document.createElement("strong");
        actorLabel.textContent = actorName;
        description.append(actorLabel);
        description.append(document.createTextNode(` — ${entryDescription}`));
      } else {
        description.textContent = entryDescription;
      }

      const timestamp = document.createElement("span");
      timestamp.className = "lead-history__timestamp";
      timestamp.textContent = entry.timestamp || "—";

      item.append(description, timestamp);

      const attachmentLinks = [];

      if (entry?.file) {
        const fileMeta = entry.file;
        const link = document.createElement("a");
        link.className = "lead-history__attachment";
        link.textContent = fileMeta?.name || "Document";
        link.href = fileMeta?.url || fileMeta?.path || "#";
        link.target = "_blank";
        link.rel = "noreferrer noopener";
        attachmentLinks.push(link);
      }

      const metadataAttachments = entry?.metadata?.attachments;
      if (Array.isArray(metadataAttachments)) {
        metadataAttachments.forEach((file) => {
          const link = document.createElement("a");
          link.className = "lead-history__attachment";
          link.textContent = file?.name || "Attachment";
          link.href = file?.url || file?.path || "#";
          link.target = "_blank";
          link.rel = "noreferrer noopener";
          attachmentLinks.push(link);
        });
      }

      if (attachmentLinks.length) {
        const attachmentsWrapper = document.createElement("div");
        attachmentsWrapper.className = "lead-history__attachments";
        attachmentLinks.forEach((link) => attachmentsWrapper.appendChild(link));
        item.appendChild(attachmentsWrapper);
      }

      historyContainer.appendChild(item);
    });
  };

  const setQuickAction = (action, value) => {
    const button = quickActions[action];
    if (!button) {
      return;
    }

    const hasValue = value && String(value).trim() !== "";
    button.classList.toggle("is-disabled", !hasValue);
    button.setAttribute("aria-disabled", hasValue ? "false" : "true");

    if (!hasValue) {
      button.setAttribute("href", "#");
      button.removeAttribute("target");
      button.removeAttribute("rel");
      return;
    }

    if (action === "call") {
      button.setAttribute("href", formatPhoneLink(value));
      button.removeAttribute("target");
      button.removeAttribute("rel");
    } else if (action === "email") {
      button.setAttribute("href", `mailto:${value}`);
      button.removeAttribute("target");
      button.removeAttribute("rel");
    } else if (action === "whatsapp") {
      button.setAttribute("href", buildWhatsappLink(value));
      button.setAttribute("target", "_blank");
      button.setAttribute("rel", "noreferrer noopener");
    }
  };

  const populateSidebar = (payload) => {
    const leadData = payload ? { ...payload } : {};
    currentLeadData = {
      ...leadData,
      tags: Array.isArray(leadData.tags) ? [...leadData.tags] : leadData.tags,
    };

    if (sidebarFields.avatar) {
      const avatarValue = currentLeadData.avatarInitial || currentLeadData.name || '';
      sidebarFields.avatar.textContent = avatarValue ? avatarValue.charAt(0).toUpperCase() : '';
    }

    setTextContent(sidebarFields.name, currentLeadData.name || "Unnamed Lead", "Unnamed Lead");

    if (sidebarFields.stage) {
      sidebarFields.stage.textContent = currentLeadData.stage || "New";
      sidebarFields.stage.className = "lead-stage-pill stage-badge";
      if (currentLeadData.stageClass) {
        sidebarFields.stage.classList.add(currentLeadData.stageClass);
      }
    }

    const numericRating = ratingValueFromString(currentLeadData.rating);
    updateRatingStars(numericRating);
    if (sidebarFields.ratingLabel) {
      sidebarFields.ratingLabel.textContent = formatRatingLabel(
        currentLeadData.rating,
        numericRating,
        false
      );
      sidebarFields.ratingLabel.dataset.rawRating = String(currentLeadData.rating || "");
    }

    setLinkField(
      sidebarFields.email,
      currentLeadData.email,
      (value) => `mailto:${value}`,
      sidebarFields.email?.dataset.emptyText || "No email provided"
    );

    setLinkField(
      sidebarFields.phone,
      currentLeadData.phone,
      formatPhoneLink,
      sidebarFields.phone?.dataset.emptyText || "No phone number"
    );

    setTextContent(sidebarFields.nationality, currentLeadData.nationality);
    setTextContent(
      sidebarFields.location,
      currentLeadData.locationPreferences || currentLeadData.propertiesInterestedIn
    );
    setTextContent(sidebarFields.propertyType, currentLeadData.propertyType);
    renderChips(
      sidebarFields.interestedIn,
      currentLeadData.interestedIn,
      "No property interests added."
    );
    setTextContent(sidebarFields.budget, currentLeadData.budgetRange);
    setTextContent(sidebarFields.moveIn, currentLeadData.moveInTimeline);
    setTextContent(sidebarFields.source, currentLeadData.source);
    setTextContent(sidebarFields.assignedTo, currentLeadData.assignedTo);
    setTextContent(
      sidebarFields.createdAt,
      currentLeadData.createdAtDisplay || currentLeadData.createdAt
    );
    setTextContent(sidebarFields.purpose, currentLeadData.purpose);
    setTextContent(sidebarFields.sizeRequired, currentLeadData.sizeRequired);
    renderChips(sidebarFields.tags, currentLeadData.tags, "No tags yet.");

    renderRemarks(currentLeadData.remarks, currentLeadData.createdAtDisplay, currentLeadData.assignedTo);
    renderFiles(currentLeadData.files);
    renderHistory(currentLeadData.history);

    if (remarkForm) {
      setRemarkFormSubmitting(false);
      if (typeof remarkForm.reset === 'function') {
        remarkForm.reset();
      }
      if (remarkInput) {
        remarkInput.value = '';
      }
      if (remarkFileInput) {
        remarkFileInput.value = '';
      }
    }

    if (filesUploadInput) {
      setFilesUploading(false);
      filesUploadInput.value = '';
    }

    setQuickAction("call", currentLeadData.phone || currentLeadData.alternatePhone);
    setQuickAction("email", currentLeadData.email || currentLeadData.alternateEmail);
    setQuickAction("whatsapp", currentLeadData.phone || currentLeadData.alternatePhone);

    applyFormValues(currentLeadData);
    setEditing(false);
  };

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      const target = tab.getAttribute("data-tab-target");
      if (!target) {
        return;
      }

      tabs.forEach((button) => {
        const isActive = button === tab;
        button.classList.toggle("is-active", isActive);
        button.setAttribute("aria-selected", String(isActive));
      });

      panels.forEach((panel) => {
        const isActive = panel.getAttribute("data-tab-panel") === target;
        panel.classList.toggle("is-active", isActive);
        panel.setAttribute("aria-hidden", String(!isActive));
      });
    });
  });

  ratingStars.forEach((star) => {
    star.addEventListener("click", () => {
      const value = Number(star.dataset.ratingStar || 0);
      if (!value) {
        return;
      }

      updateRatingStars(value);
      if (sidebarFields.ratingLabel) {
        sidebarFields.ratingLabel.textContent = formatRatingLabel(
          sidebarFields.ratingLabel.dataset.rawRating || value,
          value,
          true
        );
      }
    });
  });

  if (editButton) {
    editButton.addEventListener('click', () => {
      if (!currentLeadData || isSaving) {
        return;
      }

      if (!isEditing) {
        setEditing(true);
        return;
      }

      if (sidebarForm) {
        sidebarForm.requestSubmit();
      }
    });
  }

  if (sidebarForm) {
    sidebarForm.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!isEditing) {
        return;
      }

      const payload = gatherFormPayload();
      if (!payload || !payload.id) {
        showFeedback('Lead information is incomplete.', 'error');
        return;
      }

      setSavingState(true);

      fetch('all-leads.php?action=update-lead', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      })
        .then((response) => parseJsonResponse(response, 'Unable to update the lead.'))
        .then((data) => {
          applyLeadResponse(data.lead);
          if (!data.lead?.payload) {
            setEditing(false);
          }

          const successMessage = data.message || 'Lead details updated successfully.';
          showFeedback(successMessage, 'success');
          if (typeof window !== 'undefined' && typeof window.alert === 'function') {
            window.alert(successMessage);
          }
        })
        .catch((error) => {
          const message = error instanceof Error ? error.message : 'Unable to update the lead.';
          showFeedback(message, 'error');
        })
        .finally(() => {
          setSavingState(false);
        });
    });
  }

  if (remarkForm) {
    remarkForm.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!currentLeadData || !currentLeadData.id) {
        showFeedback('Please select a lead before adding a remark.', 'error');
        return;
      }

      const remarkValue = remarkInput ? remarkInput.value.trim() : '';
      const attachments = remarkFileInput?.files ? Array.from(remarkFileInput.files) : [];

      if (!remarkValue && !attachments.length) {
        showFeedback('Please add a remark or attach at least one file.', 'error');
        return;
      }

      const formData = new FormData();
      formData.append('lead_id', currentLeadData.id);
      if (remarkValue) {
        formData.append('remark', remarkValue);
      }
      attachments.forEach((file) => {
        formData.append('attachments[]', file);
      });

      setRemarkFormSubmitting(true);
      fetch('all-leads.php?action=add-remark', {
        method: 'POST',
        body: formData,
      })
        .then((response) => parseJsonResponse(response, 'Unable to save the remark.'))
        .then((data) => {
          highlightNextRemark = true;
          applyLeadResponse(data.lead);
          const successMessage = data.message || 'Remark saved successfully.';
          showFeedback(successMessage, 'success');
        })
        .catch((error) => {
          const message = error instanceof Error ? error.message : 'Unable to save the remark.';
          showFeedback(message, 'error');
        })
        .finally(() => {
          setRemarkFormSubmitting(false);
          if (remarkFileInput) {
            remarkFileInput.value = '';
          }
          if (remarkInput) {
            remarkInput.value = '';
          }
        });
    });
  }

  if (filesUploadInput) {
    filesUploadInput.addEventListener('change', () => {
      if (!currentLeadData || !currentLeadData.id) {
        showFeedback('Please select a lead before uploading files.', 'error');
        filesUploadInput.value = '';
        return;
      }

      const selectedFiles = filesUploadInput.files ? Array.from(filesUploadInput.files) : [];
      if (!selectedFiles.length) {
        return;
      }

      const formData = new FormData();
      formData.append('lead_id', currentLeadData.id);
      selectedFiles.forEach((file) => {
        formData.append('attachments[]', file);
      });

      setFilesUploading(true);
      fetch('all-leads.php?action=upload-files', {
        method: 'POST',
        body: formData,
      })
        .then((response) => parseJsonResponse(response, 'Unable to upload files.'))
        .then((data) => {
          applyLeadResponse(data.lead);
          const successMessage = data.message || 'Files uploaded successfully.';
          showFeedback(successMessage, 'success');
        })
        .catch((error) => {
          const message = error instanceof Error ? error.message : 'Unable to upload files.';
          showFeedback(message, 'error');
        })
        .finally(() => {
          setFilesUploading(false);
          filesUploadInput.value = '';
        });
    });
  }

  const interactiveSelectors = "a, button, select, input, textarea, label, [data-prevent-lead-open], .dropdown-menu";

  const closeDropdownMenu = (element) => {
    if (!element) {
      return;
    }

    const dropdown = element.closest(".dropdown");
    if (!dropdown) {
      return;
    }

    const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
    if (toggle && typeof bootstrap !== "undefined" && bootstrap.Dropdown) {
      const dropdownInstance =
        bootstrap.Dropdown.getInstance(toggle) || new bootstrap.Dropdown(toggle);
      dropdownInstance.hide();
      return;
    }

    dropdown.classList.remove("show");
    const menu = dropdown.querySelector(".dropdown-menu");
    if (menu) {
      menu.classList.remove("show");
    }
  };

  const openLeadFromRow = (row, trigger = null) => {
    if (!row) {
      return;
    }

    const payload = row.dataset.leadJson;
    if (!payload) {
      return;
    }

    try {
      const parsed = JSON.parse(payload);
      currentLeadRow = row;
      clearFeedback();
      populateSidebar(parsed);
      activeTrigger = trigger || row;
      openSidebar();
    } catch (error) {
      console.error("Failed to parse lead data", error);
    }
  };

  leadTable.querySelectorAll("tbody tr[data-lead-json]").forEach((row) => {
    row.addEventListener("click", (event) => {
      if (event.target.closest(interactiveSelectors)) {
        return;
      }

      openLeadFromRow(row);
    });

    row.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        openLeadFromRow(row);
      }
    });
  });

  const actionButtons = leadTable.querySelectorAll("[data-lead-action]");
  actionButtons.forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();

      const action = button.getAttribute("data-lead-action");
      if (!action) {
        return;
      }

      if (action === "view" || action === "edit") {
        const row = button.closest("tr[data-lead-json]");
        if (row) {
          closeDropdownMenu(button);
          openLeadFromRow(row, button);
          if (action === "edit") {
            window.requestAnimationFrame(() => {
              setEditing(true);
            });
          }
        }
        return;
      }

      if (action === "delete") {
        const leadId = Number(button.getAttribute("data-lead-id") || 0);
        if (!leadId || !deleteForm || !deleteInput) {
          closeDropdownMenu(button);
          return;
        }

        const leadName = button.getAttribute("data-lead-name") || "";
        const confirmationMessage = leadName
          ? `Are you sure you want to delete "${leadName}"? This action cannot be undone.`
          : "Are you sure you want to delete this lead? This action cannot be undone.";

        closeDropdownMenu(button);

        if (window.confirm(confirmationMessage)) {
          deleteInput.value = String(leadId);
          deleteForm.submit();
        }
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
