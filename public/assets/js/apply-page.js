(function (window, document) {
    "use strict";

    function bindValidityReporting(form, selector) {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        form.querySelectorAll(selector).forEach(function (field) {
            field.addEventListener("input", function () {
                if (typeof field.reportValidity === "function") {
                    field.reportValidity();
                }
            });
            field.addEventListener("change", function () {
                if (typeof field.reportValidity === "function") {
                    field.reportValidity();
                }
            });
        });
    }

    function initStepOne() {
        var step1Form = document.getElementById("applyStep1Form");
        var schoolSelect = document.getElementById("applySchoolNameSelect");
        var otherWrapper = document.getElementById("applyOtherSchoolWrapper");
        var otherInput = document.getElementById("applyOtherSchoolInput");
        var courseSelect = document.getElementById("applyCourseSelect");
        var otherCourseWrapper = document.getElementById("applyOtherCourseWrapper");
        var otherCourseInput = document.getElementById("applyOtherCourseInput");

        if (schoolSelect && otherWrapper && otherInput) {
            var syncOtherSchoolVisibility = function () {
                var showOther = schoolSelect.value === "__other__";
                otherWrapper.classList.toggle("d-none", !showOther);
                otherInput.required = showOther;
                if (!showOther) {
                    otherInput.value = "";
                }
            };
            schoolSelect.addEventListener("change", syncOtherSchoolVisibility);
            syncOtherSchoolVisibility();
        }

        if (courseSelect && otherCourseWrapper && otherCourseInput) {
            var syncOtherCourseVisibility = function () {
                var showOther = courseSelect.value === "__other__";
                otherCourseWrapper.classList.toggle("d-none", !showOther);
                otherCourseInput.required = showOther;
                if (!showOther) {
                    otherCourseInput.value = "";
                }
            };
            courseSelect.addEventListener("change", syncOtherCourseVisibility);
            syncOtherCourseVisibility();
        }

        bindValidityReporting(step1Form, "input, select");
    }

    function initStepTwo() {
        var step2Form = document.getElementById("applyStep2Form");
        if (window.SE_APPLY_WIZARD && typeof window.SE_APPLY_WIZARD.initBirthdateAgeSync === "function") {
            window.SE_APPLY_WIZARD.initBirthdateAgeSync({
                birthDateInputId: "birthDateInput",
                ageInputId: "ageInput"
            });
        }
        bindValidityReporting(step2Form, "input, select, textarea");
    }

    function syncToggleRequired(toggleId, fieldSelector) {
        var toggle = document.getElementById(toggleId);
        var fields = document.querySelectorAll(fieldSelector);
        if (!toggle || !fields.length) {
            return;
        }
        var sync = function () {
            fields.forEach(function (field) {
                field.required = !toggle.checked;
                if (toggle.checked && typeof field.setCustomValidity === "function") {
                    field.setCustomValidity("");
                }
            });
        };
        toggle.addEventListener("change", sync);
        sync();
    }

    function syncParentGroupRequired(toggleIds, fieldSelector) {
        var toggles = toggleIds
            .map(function (toggleId) {
                return document.getElementById(toggleId);
            })
            .filter(Boolean);
        var fields = document.querySelectorAll(fieldSelector);
        if (!toggles.length || !fields.length) {
            return;
        }
        var sync = function () {
            var bypassRequired = toggles.some(function (toggle) {
                return !!toggle.checked;
            });
            fields.forEach(function (field) {
                field.required = !bypassRequired;
                if (bypassRequired && typeof field.setCustomValidity === "function") {
                    field.setCustomValidity("");
                }
            });
        };
        toggles.forEach(function (toggle) {
            toggle.addEventListener("change", sync);
        });
        sync();
    }

    function initDynamicRows(config) {
        if (!config.body || !config.addBtn) {
            return;
        }
        var maxRows = config.maxRows || 10;
        var getNextIndex = function () {
            var current = Number(config.body.getAttribute("data-next-index") || "0");
            config.body.setAttribute("data-next-index", String(current + 1));
            return current;
        };
        var buildRow = function (index) {
            var tr = document.createElement("tr");
            tr.innerHTML = config.rowHtml(index);
            return tr;
        };
        var syncState = function () {
            var disabledByToggle = !!(config.naToggle && config.naToggle.checked);
            config.addBtn.disabled = disabledByToggle;
        };
        config.addBtn.addEventListener("click", function () {
            if (config.addBtn.disabled) {
                return;
            }
            var currentRows = config.body.querySelectorAll("tr").length;
            if (currentRows >= maxRows) {
                return;
            }
            config.body.appendChild(buildRow(getNextIndex()));
        });
        config.body.addEventListener("click", function (event) {
            var target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            var removeBtn = target.closest(config.removeSelector);
            if (!removeBtn) {
                return;
            }
            var row = removeBtn.closest("tr");
            if (!row) {
                return;
            }
            if (config.body.querySelectorAll("tr").length <= 1) {
                row.querySelectorAll("input").forEach(function (input) {
                    input.value = "";
                });
                return;
            }
            row.remove();
        });
        if (config.naToggle) {
            config.naToggle.addEventListener("change", syncState);
        }
        syncState();
    }

    function initStepThree() {
        var step3Form = document.getElementById("applyStep3Form");
        if (window.SE_APPLY_WIZARD && typeof window.SE_APPLY_WIZARD.initNaToggles === "function") {
            window.SE_APPLY_WIZARD.initNaToggles({
                toggleSelector: ".js-na-toggle[data-target]",
                honorsToggleId: "honorsNaToggle",
                honorsInputSelector: ".js-honors-input"
            });
        }

        var parentStatusToggles = Array.from(document.querySelectorAll(".js-parent-status-toggle"));
        parentStatusToggles.forEach(function (toggle) {
            toggle.addEventListener("change", function () {
                if (!toggle.checked) {
                    return;
                }
                var group = String(toggle.getAttribute("data-group") || "");
                parentStatusToggles.forEach(function (otherToggle) {
                    if (otherToggle !== toggle && String(otherToggle.getAttribute("data-group") || "") === group) {
                        otherToggle.checked = false;
                        otherToggle.dispatchEvent(new Event("change"));
                    }
                });
            });
        });

        syncParentGroupRequired(["motherNaToggle", "motherDeceasedToggle"], "#motherFields input");
        syncParentGroupRequired(["fatherNaToggle", "fatherDeceasedToggle"], "#fatherFields input");
        syncToggleRequired("siblingsNaToggle", "#siblingsFields input");
        syncToggleRequired("grantsNaToggle", "#grantsFields input");

        var siblingsBody = document.getElementById("siblingsTableBody");
        var addSiblingBtn = document.getElementById("addSiblingRowBtn");
        var siblingsNaToggle = document.getElementById("siblingsNaToggle");
        var grantsBody = document.getElementById("grantsTableBody");
        var addGrantsBtn = document.getElementById("addGrantsRowBtn");
        var grantsNaToggle = document.getElementById("grantsNaToggle");

        if (siblingsBody && addSiblingBtn) {
            var syncAddButtonState = function () {
                addSiblingBtn.disabled = !!(siblingsNaToggle && siblingsNaToggle.checked);
            };
            var getNextIndex = function () {
                var current = Number(siblingsBody.getAttribute("data-next-index") || "0");
                siblingsBody.setAttribute("data-next-index", String(current + 1));
                return current;
            };
            var buildRow = function (index) {
                var tr = document.createElement("tr");
                tr.innerHTML = [
                    '<td data-label="Name"><div class="wizard-inline-field-label">Name</div><input type="text" class="form-control form-control-sm" name="siblings[' + index + '][name]" placeholder="Full name"' + ((siblingsNaToggle && !siblingsNaToggle.checked) ? " required" : "") + "></td>",
                    '<td data-label="Age"><div class="wizard-inline-field-label">Age</div><input type="number" class="form-control form-control-sm" name="siblings[' + index + '][age]" min="0"' + ((siblingsNaToggle && !siblingsNaToggle.checked) ? " required" : "") + "></td>",
                    '<td data-label="Highest Educational Attainment"><div class="wizard-inline-field-label">Highest Educational Attainment</div><input type="text" class="form-control form-control-sm" name="siblings[' + index + '][education]" placeholder="e.g., College"' + ((siblingsNaToggle && !siblingsNaToggle.checked) ? " required" : "") + "></td>",
                    '<td data-label="Occupation"><div class="wizard-inline-field-label">Occupation</div><input type="text" class="form-control form-control-sm" name="siblings[' + index + '][occupation]" placeholder="e.g., Student"' + ((siblingsNaToggle && !siblingsNaToggle.checked) ? " required" : "") + "></td>",
                    '<td data-label="Monthly Income"><div class="wizard-inline-field-label">Monthly Income</div><input type="number" step="0.01" class="form-control form-control-sm" name="siblings[' + index + '][income]" min="0"' + ((siblingsNaToggle && !siblingsNaToggle.checked) ? " required" : "") + "></td>",
                    '<td data-label="Action" class="text-end"><button type="button" class="btn btn-sm btn-outline-danger js-remove-sibling-row" aria-label="Remove sibling row"><i class="fa-solid fa-trash-can"></i></button></td>'
                ].join("");
                return tr;
            };
            var removeRow = function (button) {
                var row = button.closest("tr");
                if (!row) {
                    return;
                }
                if (siblingsBody.querySelectorAll("tr").length <= 1) {
                    row.querySelectorAll("input").forEach(function (input) {
                        input.value = "";
                    });
                    return;
                }
                row.remove();
            };

            addSiblingBtn.addEventListener("click", function () {
                var currentRows = siblingsBody.querySelectorAll("tr").length;
                if (currentRows >= 10) {
                    return;
                }
                siblingsBody.appendChild(buildRow(getNextIndex()));
            });

            siblingsBody.addEventListener("click", function (event) {
                var target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }
                var removeBtn = target.closest(".js-remove-sibling-row");
                if (!removeBtn) {
                    return;
                }
                removeRow(removeBtn);
            });

            if (siblingsNaToggle) {
                siblingsNaToggle.addEventListener("change", syncAddButtonState);
            }
            syncAddButtonState();
        }

        initDynamicRows({
            body: grantsBody,
            addBtn: addGrantsBtn,
            naToggle: grantsNaToggle,
            removeSelector: ".js-remove-grants-row",
            maxRows: 8,
            rowHtml: function (index) {
                return [
                    '<td data-label="Scholarship Program"><div class="wizard-inline-field-label">Scholarship Program</div><input type="text" class="form-control form-control-sm" name="grants[' + index + '][program]" placeholder="Program name"' + ((grantsNaToggle && !grantsNaToggle.checked) ? " required" : "") + "></td>",
                    '<td data-label="Year/Period"><div class="wizard-inline-field-label">Year/Period</div><input type="text" class="form-control form-control-sm" name="grants[' + index + '][period]" placeholder="e.g., 2024-2025"' + ((grantsNaToggle && !grantsNaToggle.checked) ? " required" : "") + "></td>",
                    '<td data-label="Action" class="text-end"><button type="button" class="btn btn-sm btn-outline-danger js-remove-grants-row" aria-label="Remove grants row"><i class="fa-solid fa-trash-can"></i></button></td>'
                ].join("");
            }
        });

        if (step3Form) {
            step3Form.addEventListener("submit", function (event) {
                var siblingsRequired = !(siblingsNaToggle && siblingsNaToggle.checked);
                var grantsRequired = !(grantsNaToggle && grantsNaToggle.checked);
                var siblingRows = Array.from(siblingsBody ? siblingsBody.querySelectorAll("tr") : []);
                var grantRows = Array.from(grantsBody ? grantsBody.querySelectorAll("tr") : []);
                var hasFilledSibling = siblingRows.some(function (row) {
                    return Array.from(row.querySelectorAll("input")).every(function (input) {
                        return String(input.value || "").trim() !== "";
                    });
                });
                var hasFilledGrant = grantRows.some(function (row) {
                    return Array.from(row.querySelectorAll("input")).every(function (input) {
                        return String(input.value || "").trim() !== "";
                    });
                });
                if (siblingsRequired && !hasFilledSibling) {
                    event.preventDefault();
                    var firstSiblingInput = siblingsBody ? siblingsBody.querySelector("input") : null;
                    if (firstSiblingInput && typeof firstSiblingInput.reportValidity === "function") {
                        firstSiblingInput.reportValidity();
                    }
                    return;
                }
                if (grantsRequired && !hasFilledGrant) {
                    event.preventDefault();
                    var firstGrantInput = grantsBody ? grantsBody.querySelector("input") : null;
                    if (firstGrantInput && typeof firstGrantInput.reportValidity === "function") {
                        firstGrantInput.reportValidity();
                    }
                    return;
                }
                if (!step3Form.checkValidity()) {
                    event.preventDefault();
                    step3Form.reportValidity();
                }
            });
        }

        bindValidityReporting(step3Form, "input, select");
    }

    function initReviewPreview() {
        var modalEl = document.getElementById("reviewPreviewModal");
        var titleEl = document.getElementById("reviewPreviewModalLabel");
        var frameEl = document.getElementById("reviewPreviewFrame");
        var printableBtn = document.getElementById("openPrintablePreviewBtn");
        if (!modalEl || !titleEl || !frameEl || typeof bootstrap === "undefined") {
            return;
        }
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var openPreview = function (title, src) {
            titleEl.textContent = title;
            frameEl.src = src;
            modal.show();
        };
        if (printableBtn) {
            printableBtn.addEventListener("click", function () {
                openPreview("Printable Form Preview", "print-application.php?draft=1&embed=1");
            });
        }
        document.querySelectorAll(".js-open-doc-preview").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var src = btn.getAttribute("data-preview-src") || "";
                var title = btn.getAttribute("data-preview-title") || "Document Preview";
                if (!src) {
                    return;
                }
                openPreview(title, "preview-document.php?file=" + encodeURIComponent(src));
            });
        });
        modalEl.addEventListener("hidden.bs.modal", function () {
            frameEl.src = "about:blank";
        });
    }

    function initAutosave(config) {
        if (!(window.SE_APPLY_WIZARD && typeof window.SE_APPLY_WIZARD.initAutosave === "function")) {
            return;
        }
        window.SE_APPLY_WIZARD.initAutosave({
            formSelector: 'form[data-autosave-step="' + String(config.step) + '"]',
            statusId: "autosaveStatus",
            step: config.step,
            endpoint: "apply-autosave.php",
            intervalMs: 10000,
            debounceMs: 1200
        });
    }

    function initApplyPage() {
        var config = window.SE_APPLY_PAGE_CONFIG || null;
        if (!config) {
            return;
        }
        if (config.hasReviewPreview) {
            initReviewPreview();
        }
        if (config.step === 1) {
            initStepOne();
        } else if (config.step === 2) {
            initStepTwo();
        } else if (config.step === 3) {
            initStepThree();
        }
        if (config.enableAutosave) {
            initAutosave(config);
        }
    }

    document.addEventListener("DOMContentLoaded", initApplyPage);
})(window, document);
