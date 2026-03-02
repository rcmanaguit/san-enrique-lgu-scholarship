document.addEventListener("DOMContentLoaded", () => {
    setCurrentYear();
    initLiveTables();
    initPasswordToggles();
    initDesktopSidebar();
    initResponsiveNavbar();
    initNavbarGlobalSearch();
    initCrudModalForms();
    initRealtimePolling();
});

function setCurrentYear() {
    const yearElement = document.querySelector("[data-current-year]");
    if (yearElement) {
        yearElement.textContent = new Date().getFullYear().toString();
    }
}

function initLiveTables() {
    const wrappers = document.querySelectorAll("[data-live-table]");
    wrappers.forEach((wrapper) => initLiveTable(wrapper));
}

function initLiveTable(wrapper) {
    const table = wrapper.querySelector("table");
    if (!table) {
        return;
    }

    const tbody = table.querySelector("tbody");
    if (!tbody) {
        return;
    }

    const allRows = Array.from(tbody.querySelectorAll("tr"));
    if (allRows.length === 0) {
        return;
    }

    const searchInput = wrapper.querySelector("[data-table-search]");
    const filterSelect = wrapper.querySelector("[data-table-filter]");
    const perPageSelect = wrapper.querySelector("[data-table-per-page]");
    const pagerContainer = wrapper.querySelector("[data-table-pager]");
    const summary = wrapper.querySelector("[data-table-summary]");

    let state = {
        page: 1,
        perPage: parseInt(perPageSelect ? perPageSelect.value : "10", 10),
    };

    function textMatch(row, query) {
        if (!query) {
            return true;
        }
        const haystack = (row.dataset.search || row.textContent || "").toLowerCase();
        return haystack.includes(query);
    }

    function filterMatch(row, filterValue) {
        if (!filterValue) {
            return true;
        }
        return (row.dataset.filter || "").toLowerCase() === filterValue;
    }

    function renderPager(totalPages) {
        if (!pagerContainer) {
            return;
        }

        pagerContainer.innerHTML = "";
        if (totalPages <= 1) {
            return;
        }

        const prev = document.createElement("button");
        prev.type = "button";
        prev.className = "pager-button";
        prev.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
        prev.disabled = state.page === 1;
        prev.addEventListener("click", () => {
            if (state.page > 1) {
                state.page -= 1;
                render();
            }
        });
        pagerContainer.appendChild(prev);

        for (let i = 1; i <= totalPages; i += 1) {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "pager-button" + (i === state.page ? " active" : "");
            btn.textContent = String(i);
            btn.addEventListener("click", () => {
                state.page = i;
                render();
            });
            pagerContainer.appendChild(btn);
        }

        const next = document.createElement("button");
        next.type = "button";
        next.className = "pager-button";
        next.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
        next.disabled = state.page === totalPages;
        next.addEventListener("click", () => {
            if (state.page < totalPages) {
                state.page += 1;
                render();
            }
        });
        pagerContainer.appendChild(next);
    }

    function render() {
        const query = (searchInput ? searchInput.value : "").trim().toLowerCase();
        const filterValue = (filterSelect ? filterSelect.value : "").trim().toLowerCase();

        const filteredRows = allRows.filter((row) => textMatch(row, query) && filterMatch(row, filterValue));
        const total = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / state.perPage));
        if (state.page > totalPages) {
            state.page = totalPages;
        }

        allRows.forEach((row) => row.classList.add("d-none"));
        const start = (state.page - 1) * state.perPage;
        const currentRows = filteredRows.slice(start, start + state.perPage);
        currentRows.forEach((row) => row.classList.remove("d-none"));

        if (summary) {
            const end = Math.min(start + currentRows.length, total);
            summary.textContent = total === 0
                ? "No records found"
                : `Showing ${start + 1}-${end} of ${total} record(s)`;
        }

        renderPager(totalPages);
    }

    if (searchInput) {
        searchInput.addEventListener("input", () => {
            state.page = 1;
            render();
        });
    }

    if (filterSelect) {
        filterSelect.addEventListener("change", () => {
            state.page = 1;
            render();
        });
    }

    if (perPageSelect) {
        perPageSelect.addEventListener("change", () => {
            state.perPage = parseInt(perPageSelect.value, 10) || 10;
            state.page = 1;
            render();
        });
    }

    render();
}

function initPasswordToggles() {
    const buttons = document.querySelectorAll("[data-password-toggle]");
    buttons.forEach((button) => {
        button.addEventListener("click", () => {
            const target = button.getAttribute("data-target");
            if (!target) {
                return;
            }

            const input = document.querySelector(target);
            if (!input) {
                return;
            }

            const icon = button.querySelector("i");
            const isPassword = input.getAttribute("type") === "password";
            input.setAttribute("type", isPassword ? "text" : "password");

            if (icon) {
                icon.classList.toggle("fa-eye", !isPassword);
                icon.classList.toggle("fa-eye-slash", isPassword);
            }

            button.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
        });
    });
}

function initResponsiveNavbar() {
    const navCollapse = document.getElementById("mainNav");
    if (!navCollapse || typeof bootstrap === "undefined") {
        return;
    }

    const collapse = bootstrap.Collapse.getOrCreateInstance(navCollapse, { toggle: false });
    const links = navCollapse.querySelectorAll("a.nav-link, a.btn");
    links.forEach((link) => {
        link.addEventListener("click", () => {
            if (window.innerWidth < 992 && navCollapse.classList.contains("show")) {
                collapse.hide();
            }
        });
    });
}

function initNavbarGlobalSearch() {
    const searchShells = document.querySelectorAll("[data-navbar-global-search]");
    if (!searchShells.length) {
        return;
    }

    searchShells.forEach((shell) => {
        if (!(shell instanceof HTMLElement) || shell.dataset.navSearchBound === "1") {
            return;
        }
        shell.dataset.navSearchBound = "1";

        const toggleButton = shell.querySelector("[data-nav-search-toggle]");
        const panel = shell.querySelector("[data-nav-search-panel]");
        const input = shell.querySelector("[data-nav-search-input]");
        const clearButton = shell.querySelector("[data-nav-search-clear]");
        const status = shell.querySelector("[data-nav-search-status]");
        const results = shell.querySelector("[data-nav-search-results]");
        const openFullLink = shell.querySelector("[data-nav-search-open-full]");
        const endpoint = String(shell.getAttribute("data-search-endpoint") || "").trim();
        const pageUrl = String(shell.getAttribute("data-search-page") || "").trim();

        if (!toggleButton || !panel || !input || !status || !results || endpoint === "") {
            return;
        }

        let debounceTimer = 0;
        let requestController = null;
        let isOpen = false;

        const setStatus = (message) => {
            status.textContent = String(message || "");
        };

        const escapeHtml = (value) => {
            const temp = document.createElement("div");
            temp.textContent = value == null ? "" : String(value);
            return temp.innerHTML;
        };

        const setFullPageLink = (query) => {
            if (!openFullLink || pageUrl === "") {
                return;
            }
            const normalized = String(query || "").trim();
            openFullLink.href = normalized.length >= 2
                ? `${pageUrl}?q=${encodeURIComponent(normalized)}`
                : pageUrl;
        };

        const renderEmpty = (message) => {
            results.innerHTML = `<div class="nav-search-empty">${escapeHtml(message)}</div>`;
        };

        const renderResults = (payload) => {
            const sections = Array.isArray(payload && payload.sections) ? payload.sections : [];
            if (!sections.length) {
                renderEmpty("No matching records found.");
                return;
            }

            const html = sections.map((section) => {
                const sectionLabel = escapeHtml(section.label || "Results");
                const sectionCount = Number(section.count || 0);
                const sectionItems = Array.isArray(section.items) ? section.items : [];
                const visibleItems = sectionItems.slice(0, 3);

                const itemsHtml = visibleItems.map((item) => {
                    const title = escapeHtml(item.title || "-");
                    const subtitle = escapeHtml(item.subtitle || "");
                    const meta = escapeHtml(item.meta || "");
                    const url = escapeHtml(item.url || "#");

                    return (
                        `<a href="${url}" class="nav-search-item">` +
                            `<div class="nav-search-item-title">${title}</div>` +
                            (subtitle !== "" ? `<div class="nav-search-item-subtitle">${subtitle}</div>` : "") +
                            (meta !== "" ? `<div class="nav-search-item-meta">${meta}</div>` : "") +
                        `</a>`
                    );
                }).join("");

                const moreCount = Math.max(0, sectionCount - visibleItems.length);
                const moreLine = moreCount > 0
                    ? `<div class="nav-search-more">+${moreCount} more in ${sectionLabel}</div>`
                    : "";

                return (
                    `<div class="nav-search-section">` +
                        `<div class="nav-search-section-head">` +
                            `<span class="nav-search-section-title">${sectionLabel}</span>` +
                            `<span class="badge text-bg-light">${sectionCount}</span>` +
                        `</div>` +
                        itemsHtml +
                        moreLine +
                    `</div>`
                );
            }).join("");

            results.innerHTML = html;
        };

        const closePanel = () => {
            isOpen = false;
            shell.classList.remove("is-open");
            panel.classList.remove("is-open");
            toggleButton.setAttribute("aria-expanded", "false");
        };

        const openPanel = () => {
            isOpen = true;
            shell.classList.add("is-open");
            panel.classList.add("is-open");
            toggleButton.setAttribute("aria-expanded", "true");
            window.setTimeout(() => {
                input.focus();
            }, 25);
        };

        const fetchResults = async (queryRaw) => {
            const query = String(queryRaw || "").trim();
            setFullPageLink(query);

            if (query.length < 2) {
                if (requestController) {
                    requestController.abort();
                }
                renderEmpty("Type at least 2 characters to search.");
                setStatus("Type at least 2 characters.");
                return;
            }

            if (requestController) {
                requestController.abort();
            }
            requestController = new AbortController();

            setStatus("Searching...");
            try {
                const separator = endpoint.includes("?") ? "&" : "?";
                const response = await fetch(
                    `${endpoint}${separator}q=${encodeURIComponent(query)}`,
                    {
                        method: "GET",
                        credentials: "same-origin",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                        signal: requestController.signal,
                    }
                );

                if (!response.ok) {
                    throw new Error("Request failed");
                }

                const payload = await response.json();
                renderResults(payload);
                const total = Number((payload && payload.total) || 0);
                setStatus(total > 0 ? `${total} result(s) found.` : "No results found.");
            } catch (error) {
                if (error && error.name === "AbortError") {
                    return;
                }
                renderEmpty("Unable to load search results right now.");
                setStatus("Search failed. Please try again.");
            }
        };

        const queueSearch = () => {
            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
            }
            debounceTimer = window.setTimeout(() => {
                fetchResults(input.value);
            }, 250);
        };

        toggleButton.addEventListener("click", (event) => {
            event.preventDefault();
            if (isOpen) {
                closePanel();
                return;
            }
            openPanel();
            queueSearch();
        });

        if (clearButton) {
            clearButton.addEventListener("click", () => {
                input.value = "";
                input.focus();
                setFullPageLink("");
                renderEmpty("Type at least 2 characters to search.");
                setStatus("Type at least 2 characters.");
            });
        }

        input.addEventListener("input", queueSearch);

        document.addEventListener("click", (event) => {
            if (!isOpen) {
                return;
            }
            const target = event.target;
            if (target instanceof Node && shell.contains(target)) {
                return;
            }
            closePanel();
        });

        document.addEventListener("keydown", (event) => {
            if (event.key !== "Escape" || !isOpen) {
                return;
            }
            closePanel();
        });

        renderEmpty("Type at least 2 characters to search.");
        setStatus("Type at least 2 characters.");
        setFullPageLink("");
    });
}

function initDesktopSidebar() {
    const navbar = document.querySelector(".navbar[data-desktop-sidebar='1']");
    const navCollapse = document.getElementById("mainNav");
    if (!navbar || !navCollapse) {
        return;
    }

    const toggleButton = navbar.querySelector("[data-sidebar-toggle]");
    if (!toggleButton) {
        return;
    }

    const storageKey = "seDesktopSidebarCollapsed";
    const desktopMedia = window.matchMedia("(min-width: 992px)");

    const updateTopbarHeight = () => {
        const height = Math.round(navbar.getBoundingClientRect().height);
        if (height > 0) {
            document.body.style.setProperty("--desktop-topbar-height", `${height}px`);
        }
    };

    const applyDesktopState = () => {
        const isDesktop = desktopMedia.matches;
        if (!isDesktop) {
            document.body.classList.remove("desktop-sidebar-enabled", "desktop-sidebar-collapsed");
            toggleButton.classList.remove("is-collapsed");
            toggleButton.setAttribute("aria-expanded", "false");
            toggleButton.setAttribute("title", "Toggle sidebar");
            document.body.style.removeProperty("--desktop-topbar-height");
            return;
        }

        document.body.classList.add("desktop-sidebar-enabled");
        let collapsed = false;
        try {
            collapsed = window.localStorage.getItem(storageKey) === "1";
        } catch (error) {
            collapsed = false;
        }

        document.body.classList.toggle("desktop-sidebar-collapsed", collapsed);
        toggleButton.classList.toggle("is-collapsed", collapsed);
        toggleButton.setAttribute("aria-expanded", collapsed ? "false" : "true");
        toggleButton.setAttribute("title", collapsed ? "Show sidebar" : "Hide sidebar");
        updateTopbarHeight();
    };

    toggleButton.addEventListener("click", () => {
        if (!desktopMedia.matches) {
            return;
        }

        const collapsed = document.body.classList.toggle("desktop-sidebar-collapsed");
        toggleButton.classList.toggle("is-collapsed", collapsed);
        toggleButton.setAttribute("aria-expanded", collapsed ? "false" : "true");
        toggleButton.setAttribute("title", collapsed ? "Show sidebar" : "Hide sidebar");
        try {
            window.localStorage.setItem(storageKey, collapsed ? "1" : "0");
        } catch (error) {
            // Ignore storage errors (private mode / disabled storage).
        }
    });

    const onViewportChange = () => {
        applyDesktopState();
    };

    if (typeof desktopMedia.addEventListener === "function") {
        desktopMedia.addEventListener("change", onViewportChange);
    } else if (typeof desktopMedia.addListener === "function") {
        desktopMedia.addListener(onViewportChange);
    }

    window.addEventListener("resize", () => {
        if (desktopMedia.matches) {
            updateTopbarHeight();
        }
    });

    applyDesktopState();
}

function initCrudModalForms() {
    if (typeof bootstrap === "undefined") {
        return;
    }

    const modalState = getCrudConfirmModalState();
    if (!modalState) {
        return;
    }

    const forms = document.querySelectorAll("form[method='post']");
    forms.forEach((form) => {
        if (form.dataset.crudModalBound === "1") {
            return;
        }
        if (form.classList.contains("js-crud-modal-skip") || form.classList.contains("js-delete-announcement-form")) {
            return;
        }
        if ((form.getAttribute("data-crud-modal") || "").trim().toLowerCase() === "off") {
            return;
        }

        form.dataset.crudModalBound = "1";
        form.addEventListener("submit", async (event) => {
            if (form.dataset.crudModalConfirmed === "1") {
                delete form.dataset.crudModalConfirmed;
                return;
            }

            const config = resolveCrudModalConfig(form, event.submitter);
            if (!config) {
                return;
            }

            event.preventDefault();
            const confirmed = await showCrudConfirmModal(modalState, config);
            if (!confirmed) {
                return;
            }

            form.dataset.crudModalConfirmed = "1";
            if (typeof form.requestSubmit === "function") {
                if (event.submitter instanceof HTMLElement) {
                    form.requestSubmit(event.submitter);
                } else {
                    form.requestSubmit();
                }
            } else {
                form.submit();
            }
        });
    });
}

function resolveCrudModalConfig(form, submitter) {
    const explicitModal = (form.getAttribute("data-crud-modal") || "").trim().toLowerCase();
    const actionInput = form.querySelector("input[name='action']");
    const actionValue = actionInput ? String(actionInput.value || "").trim().toLowerCase() : "";
    const preset = crudActionPreset(actionValue);

    if (!preset && explicitModal !== "1") {
        return null;
    }

    const context = extractCrudFormContext(form, submitter, actionValue);

    const fallbackPreset = preset || {
        title: "Confirm Action",
        message: "Proceed with this change?",
        confirmText: "Continue",
        kind: "primary",
    };

    const customTitle = form.getAttribute("data-crud-title");
    const customMessage = form.getAttribute("data-crud-message");
    const customConfirmText = form.getAttribute("data-crud-confirm-text");

    const replacements = buildCrudTemplateReplacements(context);
    let title = applyCrudTemplate(customTitle || fallbackPreset.title, replacements);
    let message = applyCrudTemplate(customMessage || fallbackPreset.message, replacements);
    let confirmText = applyCrudTemplate(customConfirmText || fallbackPreset.confirmText, replacements);

    if (customMessage === null || customMessage === "") {
        message = buildContextualCrudMessage(actionValue, message, context);
    }
    if ((customConfirmText === null || customConfirmText === "") && actionValue === "toggle" && context.toggleLabel !== "") {
        confirmText = titleCase(context.toggleLabel);
    }

    return {
        title,
        subtitle: form.getAttribute("data-crud-subtitle") || "San Enrique LGU Scholarship",
        message,
        confirmText,
        cancelText: form.getAttribute("data-crud-cancel-text") || "Cancel",
        kind: (form.getAttribute("data-crud-kind") || fallbackPreset.kind || "primary").toLowerCase(),
    };
}

function extractCrudFormContext(form, submitter, actionValue) {
    const getValue = (name) => {
        const element = form.querySelector(`[name="${name}"]`);
        if (!element) {
            return "";
        }
        if ((element.type === "checkbox" || element.type === "radio") && !element.checked) {
            return "";
        }
        return String(element.value || "").trim();
    };

    const getSelectText = (name) => {
        const element = form.querySelector(`[name="${name}"]`);
        if (!element || element.tagName !== "SELECT") {
            return "";
        }
        const option = element.options[element.selectedIndex];
        return option ? String(option.textContent || "").trim() : "";
    };

    const submitLabel = submitter instanceof HTMLElement
        ? String(submitter.textContent || "").trim()
        : "";

    const semester = getSelectText("semester") || getValue("semester");
    const academicYear = getValue("academic_year");
    const composedPeriod = [semester, academicYear].filter((item) => item !== "").join(" ").trim();
    const newStatusRaw = getValue("new_status");
    const toggleLabel = newStatusRaw === "1"
        ? (form.getAttribute("data-crud-toggle-on") || "Activate")
        : (newStatusRaw === "0"
            ? (form.getAttribute("data-crud-toggle-off") || "Archive")
            : "");

    const context = {
        action: actionValue,
        submitLabel,
        recordLabel: String(form.getAttribute("data-crud-record") || "").trim(),
        applicationNo: String(form.getAttribute("data-application-no") || getValue("application_no")).trim(),
        periodLabel: String(form.getAttribute("data-period-label") || composedPeriod).trim(),
        announcementTitle: String(form.getAttribute("data-announcement-title") || getValue("title")).trim(),
        requirementName: String(form.getAttribute("data-requirement-name") || getValue("requirement_name")).trim(),
        referenceNo: String(form.getAttribute("data-reference-no") || getValue("reference_no")).trim(),
        selectedApplication: getSelectText("application_id"),
        statusLabel: titleCase(getValue("status") || getSelectText("status")),
        toggleLabel: String(toggleLabel).trim(),
        soaDeadline: getValue("soa_submission_deadline"),
        endDate: getValue("end_date"),
        disbursementDate: getValue("disbursement_date"),
        disbursementTime: getValue("disbursement_time"),
        amount: getValue("amount"),
        semester,
        academicYear,
    };

    if (context.recordLabel === "") {
        if (context.applicationNo !== "") {
            context.recordLabel = `Application ${context.applicationNo}`;
        } else if (context.periodLabel !== "") {
            context.recordLabel = context.periodLabel;
        } else if (context.announcementTitle !== "") {
            context.recordLabel = `Announcement "${context.announcementTitle}"`;
        } else if (context.requirementName !== "") {
            context.recordLabel = `Requirement "${context.requirementName}"`;
        } else if (context.referenceNo !== "") {
            context.recordLabel = `Reference ${context.referenceNo}`;
        } else if (context.selectedApplication !== "") {
            context.recordLabel = context.selectedApplication;
        }
    }

    return context;
}

function buildCrudTemplateReplacements(context) {
    const amountValue = parseFloat(String(context.amount || "").replace(/,/g, ""));
    const formattedAmount = Number.isFinite(amountValue) && amountValue > 0
        ? amountValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : "";

    return {
        action: String(context.action || ""),
        button: String(context.submitLabel || ""),
        record: String(context.recordLabel || ""),
        application_no: String(context.applicationNo || ""),
        period: String(context.periodLabel || ""),
        title: String(context.announcementTitle || ""),
        requirement: String(context.requirementName || ""),
        reference_no: String(context.referenceNo || ""),
        applicant: String(context.selectedApplication || ""),
        status: String(context.statusLabel || ""),
        toggle: String(context.toggleLabel || ""),
        soa_deadline: formatCrudDate(String(context.soaDeadline || "")),
        end_date: formatCrudDate(String(context.endDate || "")),
        disbursement_date: formatCrudDate(String(context.disbursementDate || "")),
        disbursement_time: formatCrudTime(String(context.disbursementTime || "")),
        amount: formattedAmount,
    };
}

function applyCrudTemplate(template, replacements) {
    let output = String(template || "");
    const values = replacements || {};
    Object.keys(values).forEach((key) => {
        const value = String(values[key] || "");
        output = output.split(`{${key}}`).join(value);
    });
    return output.trim();
}

function buildContextualCrudMessage(actionValue, fallbackMessage, context) {
    const action = String(actionValue || "").trim().toLowerCase();
    const period = context.periodLabel || "";
    const appNo = context.applicationNo || "";
    const title = context.announcementTitle || "";
    const requirement = context.requirementName || "";
    const statusLabel = context.statusLabel || "";
    const deadline = formatCrudDate(context.soaDeadline || "");
    const endDate = formatCrudDate(context.endDate || "");
    const payoutDate = formatCrudDate(context.disbursementDate || "");
    const payoutTime = formatCrudTime(context.disbursementTime || "");
    const payoutSchedule = [payoutDate, payoutTime].filter((part) => part !== "").join(" ").trim();
    const referenceNo = context.referenceNo || "";
    const selectedApplication = context.selectedApplication || "";
    const amount = buildCrudTemplateReplacements(context).amount;

    if (action === "save_period" && period !== "") {
        return `Save application period ${period}?`;
    }
    if (action === "set_open" && period !== "") {
        return `Set ${period} as the active application period?`;
    }
    if (action === "extend_deadline" && period !== "" && endDate !== "") {
        return `Extend deadline for ${period} to ${endDate}?`;
    }
    if (action === "toggle" && title !== "" && context.toggleLabel !== "") {
        return `${titleCase(context.toggleLabel)} announcement "${title}"?`;
    }
    if (action === "toggle" && requirement !== "" && context.toggleLabel !== "") {
        return `${titleCase(context.toggleLabel)} requirement "${requirement}"?`;
    }
    if (action === "update_status" && appNo !== "" && statusLabel !== "") {
        return `Update application ${appNo} status to ${statusLabel}?`;
    }
    if (action === "set_soa_deadline" && appNo !== "" && deadline !== "") {
        return `Set SOA/Student Copy deadline for application ${appNo} to ${deadline}?`;
    }
    if (action === "mark_soa_submitted" && appNo !== "") {
        return `Mark SOA/Student Copy as submitted for application ${appNo}?`;
    }
    if (action === "create_disbursement" && selectedApplication !== "") {
        const detail = [amount !== "" ? `PHP ${amount}` : "", payoutSchedule !== "" ? payoutSchedule : ""].filter((part) => part !== "").join(", ");
        return detail !== ""
            ? `Create payout schedule for ${selectedApplication} (${detail})?`
            : `Create payout schedule for ${selectedApplication}?`;
    }
    if (action === "create_bulk_disbursement") {
        const detail = [amount !== "" ? `PHP ${amount}` : "", payoutSchedule !== "" ? payoutSchedule : ""].filter((part) => part !== "").join(", ");
        return detail !== ""
            ? `Create bulk payout schedule for all matching applicants (${detail})?`
            : "Create bulk payout schedule for all matching applicants?";
    }
    if (action === "update_disbursement_date" && payoutSchedule !== "") {
        if (appNo !== "" && referenceNo !== "") {
            return `Update payout schedule for application ${appNo} (Ref ${referenceNo}) to ${payoutSchedule}?`;
        }
        if (referenceNo !== "") {
            return `Update payout schedule for reference ${referenceNo} to ${payoutSchedule}?`;
        }
    }
    if (action === "create" && requirement !== "") {
        return `Create requirement "${requirement}"?`;
    }
    if (action === "create" && title !== "") {
        return `Publish announcement "${title}"?`;
    }
    if (action === "update" && title !== "") {
        return `Save changes to announcement "${title}"?`;
    }

    return fallbackMessage;
}

function formatCrudDate(value) {
    const raw = String(value || "").trim();
    if (raw === "") {
        return "";
    }
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) {
        return raw;
    }

    const year = Number(match[1]);
    const month = Number(match[2]) - 1;
    const day = Number(match[3]);
    const parsed = new Date(year, month, day);
    if (Number.isNaN(parsed.getTime())) {
        return raw;
    }

    return parsed.toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "2-digit",
    });
}

function formatCrudTime(value) {
    const raw = String(value || "").trim();
    if (raw === "") {
        return "";
    }

    const match = raw.match(/^(\d{2}):(\d{2})(?::\d{2})?$/);
    if (!match) {
        return raw;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);
    if (!Number.isFinite(hours) || !Number.isFinite(minutes) || hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
        return raw;
    }

    const parsed = new Date();
    parsed.setHours(hours, minutes, 0, 0);
    return parsed.toLocaleTimeString(undefined, {
        hour: "2-digit",
        minute: "2-digit",
    });
}

function titleCase(value) {
    const raw = String(value || "").trim();
    if (raw === "") {
        return "";
    }
    return raw
        .replace(/[_-]+/g, " ")
        .replace(/\s+/g, " ")
        .split(" ")
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
        .join(" ");
}

function crudActionPreset(actionValue) {
    const action = String(actionValue || "").trim().toLowerCase();
    if (action === "") {
        return null;
    }

    const map = {
        create: {
            title: "Create Record?",
            message: "Save this new record now?",
            confirmText: "Create",
            kind: "success",
        },
        update: {
            title: "Save Changes?",
            message: "Apply these updates now?",
            confirmText: "Save Changes",
            kind: "primary",
        },
        delete: {
            title: "Delete Record?",
            message: "This action will permanently delete this record.",
            confirmText: "Delete",
            kind: "danger",
        },
        toggle: {
            title: "Change Status?",
            message: "Apply this status change now?",
            confirmText: "Apply",
            kind: "warning",
        },
        save_period: {
            title: "Save Application Period?",
            message: "Save this application period configuration?",
            confirmText: "Save Period",
            kind: "primary",
        },
        close_all: {
            title: "Close All Application Periods?",
            message: "This will close all open application periods.",
            confirmText: "Close All",
            kind: "danger",
        },
        set_open: {
            title: "Set Period as Open?",
            message: "This will set the selected period as open.",
            confirmText: "Set Open",
            kind: "warning",
        },
        extend_deadline: {
            title: "Extend Deadline?",
            message: "Save the new deadline for this application period?",
            confirmText: "Extend Deadline",
            kind: "primary",
        },
        update_status: {
            title: "Update Application Status?",
            message: "Save this status update for the application?",
            confirmText: "Update Status",
            kind: "primary",
        },
        set_soa_deadline: {
            title: "Set SOA Deadline?",
            message: "Save this SOA/Student Copy submission deadline?",
            confirmText: "Set Deadline",
            kind: "primary",
        },
        mark_soa_submitted: {
            title: "Mark SOA as Submitted?",
            message: "Confirm SOA/Student Copy is physically submitted?",
            confirmText: "Confirm Submission",
            kind: "success",
        },
        create_disbursement: {
            title: "Create Payout Schedule?",
            message: "Save this payout schedule for the applicant?",
            confirmText: "Create Schedule",
            kind: "success",
        },
        create_bulk_disbursement: {
            title: "Create Bulk Payout Schedule?",
            message: "Create payout schedules for all matching applicants?",
            confirmText: "Create Bulk",
            kind: "success",
        },
        update_disbursement_date: {
            title: "Update Payout Schedule?",
            message: "Save the updated payout schedule?",
            confirmText: "Save Schedule",
            kind: "primary",
        },
        update_profile: {
            title: "Save Profile Changes?",
            message: "Update your profile information now?",
            confirmText: "Save Profile",
            kind: "primary",
        },
        change_password: {
            title: "Change Password?",
            message: "Update your account password now?",
            confirmText: "Change Password",
            kind: "warning",
        },
        mark_all_read: {
            title: "Mark All as Read?",
            message: "Mark all notifications as read?",
            confirmText: "Mark All Read",
            kind: "info",
        },
        mark_read: {
            title: "Mark Notification as Read?",
            message: "Mark this notification as read?",
            confirmText: "Mark Read",
            kind: "info",
        },
        final_submit: {
            title: "Submit Application?",
            message: "Submit your final application now? This will lock editing.",
            confirmText: "Submit Application",
            kind: "warning",
        },
        update_interview_schedule: {
            title: "Save Interview Schedule?",
            message: "Save interview date, time, and location for this applicant?",
            confirmText: "Save Schedule",
            kind: "primary",
        },
    };

    if (Object.prototype.hasOwnProperty.call(map, action)) {
        return map[action];
    }

    if (action.startsWith("create_")) {
        return map.create;
    }
    if (action.startsWith("update_")) {
        return map.update;
    }
    if (action.startsWith("delete_")) {
        return map.delete;
    }
    return null;
}

function getCrudConfirmModalState() {
    const modalElement = document.getElementById("crudConfirmModal");
    if (!modalElement) {
        return null;
    }

    const titleElement = document.getElementById("crudConfirmTitle");
    const subtitleElement = document.getElementById("crudConfirmSubtitle");
    const messageElement = document.getElementById("crudConfirmMessage");
    const iconElement = document.getElementById("crudConfirmIcon");
    const cancelButton = document.getElementById("crudConfirmCancel");
    const confirmButton = document.getElementById("crudConfirmSubmit");

    if (!titleElement || !subtitleElement || !messageElement || !iconElement || !cancelButton || !confirmButton) {
        return null;
    }

    return {
        element: modalElement,
        modal: bootstrap.Modal.getOrCreateInstance(modalElement),
        titleElement,
        subtitleElement,
        messageElement,
        iconElement,
        cancelButton,
        confirmButton,
    };
}

function showCrudConfirmModal(modalState, config) {
    if (!modalState || !modalState.modal) {
        return Promise.resolve(window.confirm(config && config.message ? config.message : "Proceed?"));
    }

    const opts = Object.assign({
        title: "Confirm Action",
        subtitle: "San Enrique LGU Scholarship",
        message: "Proceed with this action?",
        confirmText: "Continue",
        cancelText: "Cancel",
        kind: "primary",
    }, config || {});

    const kind = String(opts.kind || "primary").toLowerCase();
    const iconClass = {
        info: "fa-solid fa-circle-info",
        primary: "fa-solid fa-circle-info",
        warning: "fa-solid fa-triangle-exclamation",
        success: "fa-solid fa-circle-check",
        danger: "fa-solid fa-circle-xmark",
    }[kind] || "fa-solid fa-circle-info";

    const iconTone = {
        info: "is-info",
        primary: "is-info",
        warning: "is-warning",
        success: "is-success",
        danger: "is-danger",
    }[kind] || "is-info";

    const confirmButtonClass = {
        info: "btn btn-primary",
        primary: "btn btn-primary",
        warning: "btn btn-warning",
        success: "btn btn-success",
        danger: "btn btn-danger",
    }[kind] || "btn btn-primary";

    modalState.titleElement.textContent = String(opts.title);
    modalState.subtitleElement.textContent = String(opts.subtitle);
    modalState.messageElement.textContent = String(opts.message);
    modalState.cancelButton.textContent = String(opts.cancelText);
    modalState.confirmButton.textContent = String(opts.confirmText);

    modalState.iconElement.className = "modal-se-icon " + iconTone;
    modalState.iconElement.innerHTML = `<i class="${iconClass}"></i>`;
    modalState.confirmButton.className = confirmButtonClass;
    modalState.cancelButton.className = "btn btn-outline-secondary";

    return new Promise((resolve) => {
        let settled = false;

        const cleanup = () => {
            modalState.confirmButton.onclick = null;
            modalState.cancelButton.onclick = null;
            modalState.element.removeEventListener("hidden.bs.modal", hiddenHandler);
        };

        const hiddenHandler = () => {
            if (settled) {
                return;
            }
            settled = true;
            cleanup();
            resolve(false);
        };

        modalState.confirmButton.onclick = () => {
            settled = true;
            cleanup();
            modalState.modal.hide();
            resolve(true);
        };

        modalState.cancelButton.onclick = () => {
            settled = true;
            cleanup();
            modalState.modal.hide();
            resolve(false);
        };

        modalState.element.addEventListener("hidden.bs.modal", hiddenHandler);
        modalState.modal.show();
    });
}

function initRealtimePolling() {
    const config = window.SE_REALTIME_CONFIG || null;
    if (!config || String(config.enabled).toLowerCase() === "false") {
        return;
    }

    const endpoint = String(config.endpoint || "").trim();
    if (endpoint === "") {
        return;
    }

    const configuredInterval = parseInt(String(config.interval_ms || "12000"), 10);
    const intervalMs = Number.isFinite(configuredInterval) ? Math.max(5000, configuredInterval) : 12000;
    const autoReload = !(config.auto_reload === false || String(config.auto_reload).toLowerCase() === "false");

    let lastChangeToken = "";
    let hasPrimedToken = false;
    let isPolling = false;
    let pendingReload = false;

    const notificationLink = document.querySelector("[data-realtime-notification='1']");
    const notificationBadge = document.querySelector("[data-realtime-notification-badge]");

    const updateNotificationBadge = (unreadCountRaw, unreadLabelRaw) => {
        if (!notificationLink || !notificationBadge) {
            return;
        }

        const numericUnread = Number(unreadCountRaw);
        const unreadCount = Number.isFinite(numericUnread) ? Math.max(0, numericUnread) : 0;
        const unreadLabel = String(unreadLabelRaw || (unreadCount > 99 ? "99+" : unreadCount));

        notificationBadge.textContent = unreadLabel;
        notificationBadge.classList.toggle("d-none", unreadCount <= 0);

        const labelText = unreadCount > 0
            ? `Notifications (${unreadLabel} unread)`
            : "Notifications";
        notificationLink.setAttribute("aria-label", labelText);
        notificationLink.setAttribute("title", labelText);
    };

    const canReloadNow = () => {
        if (document.hidden) {
            return false;
        }

        if (document.querySelector(".modal.show")) {
            return false;
        }

        const active = document.activeElement;
        if (!(active instanceof HTMLElement)) {
            return true;
        }

        const tag = active.tagName.toLowerCase();
        const focusedField = tag === "input"
            || tag === "textarea"
            || tag === "select"
            || active.isContentEditable;

        return !focusedField;
    };

    const applyDeferredReload = () => {
        if (!pendingReload || !autoReload) {
            return;
        }

        if (!canReloadNow()) {
            return;
        }

        pendingReload = false;
        window.location.reload();
    };

    const pollRealtime = async () => {
        if (isPolling) {
            return;
        }
        isPolling = true;

        try {
            const separator = endpoint.includes("?") ? "&" : "?";
            const realtimeUrl = `${endpoint}${separator}ts=${Date.now()}`;
            const response = await fetch(realtimeUrl, {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            if (!data || data.ok !== true) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(data, "unread_notifications")) {
                updateNotificationBadge(data.unread_notifications, data.unread_label);
            }

            const changeToken = String(data.change_token || "").trim();
            if (changeToken === "") {
                return;
            }

            if (!hasPrimedToken) {
                lastChangeToken = changeToken;
                hasPrimedToken = true;
                return;
            }

            if (changeToken !== lastChangeToken) {
                lastChangeToken = changeToken;
                pendingReload = true;
                applyDeferredReload();
            }
        } catch (error) {
            // Ignore transient polling errors.
        } finally {
            isPolling = false;
        }
    };

    document.addEventListener("visibilitychange", applyDeferredReload);
    window.addEventListener("focus", applyDeferredReload);
    document.addEventListener("click", applyDeferredReload, true);
    document.addEventListener("keyup", applyDeferredReload, true);

    pollRealtime();
    window.setInterval(pollRealtime, intervalMs);
}
