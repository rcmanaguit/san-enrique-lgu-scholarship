document.addEventListener("DOMContentLoaded", function () {
  var themeToggle = document.querySelector("[data-theme-toggle]");
  var root = document.documentElement;
  var body = document.body;
  var sidebarDesktopToggle = document.querySelector("[data-sidebar-desktop-toggle]");
  var sidebarMobileToggle = document.querySelector("[data-sidebar-mobile-toggle]");
  var sidebar = document.getElementById("sidebarMenu");
  var sidebarBackdrop = document.querySelector("[data-app-sidebar-backdrop]");
  var desktopSidebarStorageKey = "app-sidebar-collapsed";

  function syncThemeToggle() {
    if (!themeToggle) {
      return;
    }

    var currentTheme = root.getAttribute("data-theme") || "light";
    var isDark = currentTheme === "dark";
    themeToggle.innerHTML = isDark
      ? '<i class="fa-regular fa-sun"></i>'
      : '<i class="fa-regular fa-moon"></i>';
    themeToggle.setAttribute("aria-label", isDark ? "Switch to light mode" : "Switch to dark mode");
    themeToggle.setAttribute("title", isDark ? "Switch to light mode" : "Switch to dark mode");
  }

  function applyDesktopSidebarState(collapsed) {
    if (!body) {
      return;
    }

    body.classList.toggle("app-sidebar-collapsed", collapsed);

    if (sidebarDesktopToggle) {
      sidebarDesktopToggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
      sidebarDesktopToggle.setAttribute("aria-label", collapsed ? "Expand sidebar" : "Collapse sidebar");
      sidebarDesktopToggle.setAttribute("title", collapsed ? "Expand sidebar" : "Collapse sidebar");
      sidebarDesktopToggle.innerHTML = collapsed
        ? '<i class="fa-solid fa-bars"></i>'
        : '<i class="fa-solid fa-bars-staggered"></i>';
    }
  }

  function loadDesktopSidebarPreference() {
    if (!sidebarDesktopToggle) {
      return;
    }

    var collapsed = false;
    try {
      collapsed = localStorage.getItem(desktopSidebarStorageKey) === "1";
    } catch (error) {
      collapsed = false;
    }
    applyDesktopSidebarState(collapsed);
  }

  function saveDesktopSidebarPreference(collapsed) {
    try {
      localStorage.setItem(desktopSidebarStorageKey, collapsed ? "1" : "0");
    } catch (error) {
      // Ignore localStorage failures.
    }
  }

  function bindLiveSubmitForm(form) {
    if (!form || form.dataset.liveSubmitBound === "1") {
      return;
    }

    form.dataset.liveSubmitBound = "1";
    var delay = parseInt(form.getAttribute("data-live-submit-delay") || "250", 10);
    var timeoutId = null;

    function queueSubmit() {
      window.clearTimeout(timeoutId);
      timeoutId = window.setTimeout(function () {
        if (typeof form.requestSubmit === "function") {
          form.requestSubmit();
        } else {
          form.submit();
        }
      }, delay);
    }

    form.querySelectorAll("input, select").forEach(function (field) {
      if (field.type === "hidden") {
        return;
      }

      if (field.tagName === "SELECT" || field.type === "checkbox" || field.type === "radio") {
        field.addEventListener("change", queueSubmit);
        return;
      }

      field.addEventListener("input", queueSubmit);
    });
  }

  if (themeToggle) {
    syncThemeToggle();
    themeToggle.addEventListener("click", function () {
      var currentTheme = root.getAttribute("data-theme") || "light";
      var nextTheme = currentTheme === "dark" ? "light" : "dark";
      root.setAttribute("data-theme", nextTheme);
      root.style.colorScheme = nextTheme;

      try {
        localStorage.setItem("app-theme", nextTheme);
      } catch (error) {
        // Ignore localStorage failures and still switch the theme for this session.
      }

      syncThemeToggle();
    });
  }

  loadDesktopSidebarPreference();

  document.querySelectorAll("form[data-live-submit]").forEach(bindLiveSubmitForm);

  if (sidebarDesktopToggle) {
    sidebarDesktopToggle.addEventListener("click", function () {
      var collapsed = !body.classList.contains("app-sidebar-collapsed");
      applyDesktopSidebarState(collapsed);
      saveDesktopSidebarPreference(collapsed);
    });
  }
  var searchWrap = document.querySelector("[data-navbar-search]");
  var searchToggle = document.querySelector("[data-navbar-search-toggle]");
  var searchPanel = document.querySelector("[data-navbar-search-panel]");
  var searchClear = document.querySelector("[data-navbar-search-clear]");
  var suggestionsForm = document.querySelector("[data-search-suggestions-form]");
  var suggestionsList = document.querySelector("[data-navbar-search-suggestions]");
  var notificationWidget = document.querySelector("[data-notification-widget]");

  function setMobileSidebarState(isOpen) {
    if (!sidebar || !body) {
      return;
    }

    sidebar.classList.toggle("is-open", isOpen);
    body.classList.toggle("app-sidebar-mobile-open", isOpen);
    sidebar.setAttribute("aria-hidden", isOpen ? "false" : "true");

    if (sidebarBackdrop) {
      sidebarBackdrop.classList.toggle("is-visible", isOpen);
    }

    if (sidebarMobileToggle) {
      sidebarMobileToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    }
  }

  if (sidebarMobileToggle && sidebar) {
    sidebarMobileToggle.addEventListener("click", function () {
      if (window.innerWidth >= 768) {
        return;
      }

      setMobileSidebarState(!sidebar.classList.contains("is-open"));
    });
  }

  if (sidebar) {
    sidebar.querySelectorAll("a, button").forEach(function (control) {
      control.addEventListener("click", function () {
        if (window.innerWidth < 768) {
          setMobileSidebarState(false);
        }
      });
    });
  }

  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener("click", function () {
      setMobileSidebarState(false);
    });
  }

  window.addEventListener("resize", function () {
    if (window.innerWidth >= 768) {
      setMobileSidebarState(false);
    }
  });

  function closeSearchPanel() {
    if (!searchWrap || !searchToggle || !searchPanel) {
      return;
    }
    searchWrap.classList.remove("is-open");
    searchToggle.setAttribute("aria-expanded", "false");
  }

  if (searchWrap && searchToggle && searchPanel) {
    searchToggle.addEventListener("click", function () {
      var isOpen = searchWrap.classList.toggle("is-open");
      searchToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      if (isOpen) {
        var input = searchPanel.querySelector('input[name="q"]');
        if (input) {
          input.focus();
          input.select();
        }
      }
    });

    document.addEventListener("click", function (event) {
      if (!searchWrap.contains(event.target)) {
        closeSearchPanel();
      }
    });

    if (searchClear) {
      searchClear.addEventListener("click", function () {
        var input = searchPanel.querySelector('input[name="q"]');
        if (input) {
          input.value = "";
          input.focus();
        }
        if (suggestionsList) {
          suggestionsList.innerHTML = '<div class="app-navbar-search-suggestions-empty">Start typing to see suggestions.</div>';
        }
      });
    }
  }

  if (suggestionsForm && suggestionsList) {
    var suggestionsInput = suggestionsForm.querySelector('input[name="q"]');
    var suggestionsUrl = suggestionsForm.getAttribute("data-suggestions-url") || "";
    var suggestionsAbortController = null;
    var suggestionsTimer = null;

    function escapeHtml(value) {
      return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    function renderSuggestionGroup(title, items) {
      if (!items || !items.length) {
        return "";
      }

      return (
        '<div class="app-navbar-search-group">' +
          '<div class="app-navbar-search-group-title">' + escapeHtml(title) + "</div>" +
          items.map(function (item) {
            return (
              '<a class="app-navbar-search-item" href="' + encodeURI(item.url || "#") + '">' +
                '<div class="app-navbar-search-item-title">' + escapeHtml(item.title) + "</div>" +
                (item.subtitle ? '<div class="app-navbar-search-item-subtitle">' + escapeHtml(item.subtitle) + "</div>" : "") +
                (item.meta ? '<div class="app-navbar-search-item-meta">' + escapeHtml(item.meta) + "</div>" : "") +
              "</a>"
            );
          }).join("") +
        "</div>"
      );
    }

    function renderSuggestions(payload, query) {
      var applications = payload && Array.isArray(payload.applications) ? payload.applications : [];
      var scholars = payload && Array.isArray(payload.scholars) ? payload.scholars : [];
      var html = "";

      html += renderSuggestionGroup("Applications", applications);
      html += renderSuggestionGroup("Scholars", scholars);

      if (!html) {
        suggestionsList.innerHTML = '<div class="app-navbar-search-suggestions-empty">No matches found for "' + escapeHtml(query) + '".</div>';
        return;
      }

      suggestionsList.innerHTML = html;
    }

    function loadSuggestions(query) {
      if (!suggestionsUrl) {
        return;
      }

      if (suggestionsAbortController) {
        suggestionsAbortController.abort();
      }

      suggestionsAbortController = new AbortController();
      suggestionsList.innerHTML = '<div class="app-navbar-search-suggestions-empty">Searching...</div>';

      fetch(suggestionsUrl + "?q=" + encodeURIComponent(query), {
        method: "GET",
        headers: {
          Accept: "application/json"
        },
        signal: suggestionsAbortController.signal
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error("Suggestion request failed");
          }

          return response.json();
        })
        .then(function (payload) {
          renderSuggestions(payload, query);
        })
        .catch(function (error) {
          if (error.name === "AbortError") {
            return;
          }

          suggestionsList.innerHTML = '<div class="app-navbar-search-suggestions-empty">Unable to load suggestions right now.</div>';
        });
    }

    if (suggestionsInput) {
      suggestionsInput.addEventListener("input", function () {
        var query = suggestionsInput.value.trim();
        window.clearTimeout(suggestionsTimer);

        if (query.length < 2) {
          if (suggestionsAbortController) {
            suggestionsAbortController.abort();
          }
          suggestionsList.innerHTML = '<div class="app-navbar-search-suggestions-empty">Start typing to see suggestions.</div>';
          return;
        }

        suggestionsTimer = window.setTimeout(function () {
          loadSuggestions(query);
        }, 220);
      });
    }
  }

  if (notificationWidget) {
    var notificationFeedUrl = notificationWidget.getAttribute("data-notification-feed-url") || "";
    var notificationMarkReadUrl = notificationWidget.getAttribute("data-notification-mark-read-url") || "";
    var notificationMarkAllReadUrl = notificationWidget.getAttribute("data-notification-mark-all-read-url") || "";
    var notificationBadge = notificationWidget.querySelector("[data-notification-badge]");
    var notificationList = notificationWidget.querySelector("[data-notification-list]");
    var notificationDropdown = notificationWidget.querySelector(".notification-dropdown");
    var notificationMarkAllForm = notificationWidget.querySelector("[data-notification-mark-all-form]");
    var notificationRequestInFlight = false;
    var notificationMarkAllInFlight = false;

    function escapeNotificationHtml(value) {
      return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    function renderNotificationFeed(payload) {
      if (!notificationBadge || !notificationList) {
        return;
      }

      var unreadCount = payload && typeof payload.unread_count === "number" ? payload.unread_count : 0;
      var notifications = payload && Array.isArray(payload.notifications) ? payload.notifications : [];

      if (unreadCount > 0) {
        notificationBadge.textContent = unreadCount > 99 ? "99+" : String(unreadCount);
        notificationBadge.classList.remove("d-none");
      } else {
        notificationBadge.textContent = "0";
        notificationBadge.classList.add("d-none");
      }

      if (!notifications.length) {
        notificationList.innerHTML = '<div class="notification-empty-state" data-notification-empty>No notifications yet.</div>';
        return;
      }

      notificationList.innerHTML = notifications.map(function (item) {
        var itemClasses = "notification-item text-decoration-none";
        if (!item.is_read) {
          itemClasses += " notification-item-unread";
        }

        return (
          '<a class="' + itemClasses + '" data-notification-id="' + escapeNotificationHtml(item.id || "") + '" href="' + escapeNotificationHtml(item.href || "#") + '">' +
            '<div class="notification-item-title">' + escapeNotificationHtml(item.title || "Notification") + "</div>" +
            '<div class="notification-item-message">' + escapeNotificationHtml(item.message || "") + "</div>" +
            '<div class="notification-item-time">' + escapeNotificationHtml(item.created_at || "") + "</div>" +
          "</a>"
        );
      }).join("");
    }

    function setNotificationBadgeCount(unreadCount) {
      if (!notificationBadge) {
        return;
      }

      if (unreadCount > 0) {
        notificationBadge.textContent = unreadCount > 99 ? "99+" : String(unreadCount);
        notificationBadge.classList.remove("d-none");
      } else {
        notificationBadge.textContent = "0";
        notificationBadge.classList.add("d-none");
      }
    }

    function markRenderedNotificationsRead() {
      if (!notificationList) {
        return;
      }

      notificationList.querySelectorAll(".notification-item-unread").forEach(function (item) {
        item.classList.remove("notification-item-unread");
      });
      setNotificationBadgeCount(0);
    }

    function pollNotifications() {
      if (!notificationFeedUrl || notificationRequestInFlight) {
        return;
      }

      notificationRequestInFlight = true;

      fetch(notificationFeedUrl, {
        method: "GET",
        headers: {
          Accept: "application/json"
        }
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error("Notification request failed");
          }

          return response.json();
        })
        .then(function (payload) {
          renderNotificationFeed(payload);
        })
        .catch(function () {
          // Keep the current rendered notifications if polling fails.
        })
        .finally(function () {
          notificationRequestInFlight = false;
        });
    }

    function postNotificationAction(url, body, keepalive) {
      if (!url) {
        return Promise.resolve();
      }

      return fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest"
        },
        body: body,
        keepalive: keepalive === true
      }).then(function (response) {
        if (!response.ok) {
          throw new Error("Notification action failed");
        }

        return response.json().catch(function () {
          return {};
        });
      });
    }

    function markAllNotificationsAsRead() {
      if (!notificationMarkAllReadUrl || notificationMarkAllInFlight) {
        return;
      }

      if (!notificationList || !notificationList.querySelector(".notification-item-unread")) {
        setNotificationBadgeCount(0);
        return;
      }

      notificationMarkAllInFlight = true;

      postNotificationAction(notificationMarkAllReadUrl, "redirect_to=", false)
        .then(function () {
          markRenderedNotificationsRead();
        })
        .catch(function () {
          // Keep the current state if auto-mark fails.
        })
        .finally(function () {
          notificationMarkAllInFlight = false;
        });
    }

    pollNotifications();
    window.setInterval(pollNotifications, 15000);

    if (notificationMarkAllForm) {
      notificationMarkAllForm.addEventListener("submit", function (event) {
        event.preventDefault();
        markAllNotificationsAsRead();
      });
    }

    if (notificationList) {
      notificationList.addEventListener("click", function (event) {
        var notificationLink = event.target.closest("[data-notification-id]");
        if (!notificationLink) {
          return;
        }

        var notificationId = notificationLink.getAttribute("data-notification-id") || "";
        if (!notificationId || !notificationLink.classList.contains("notification-item-unread")) {
          return;
        }

        notificationLink.classList.remove("notification-item-unread");
        setNotificationBadgeCount(Math.max(0, parseInt(notificationBadge && notificationBadge.textContent ? notificationBadge.textContent : "0", 10) - 1 || 0));
        postNotificationAction(
          notificationMarkReadUrl,
          "notification_id=" + encodeURIComponent(notificationId),
          true
        ).catch(function () {
          // Ignore single-item read failures during navigation.
        });
      });
    }

    if (notificationWidget) {
      notificationWidget.addEventListener("shown.bs.dropdown", function () {
        pollNotifications();
        window.setTimeout(markAllNotificationsAsRead, 150);
      });
    }
  }
});
