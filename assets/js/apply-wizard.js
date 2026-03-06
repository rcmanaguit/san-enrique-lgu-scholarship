(function (window) {
    "use strict";

    function initBirthdateAgeSync(options) {
        var opts = options || {};
        var birthDateInput = document.getElementById(opts.birthDateInputId || "birthDateInput");
        var ageInput = document.getElementById(opts.ageInputId || "ageInput");
        if (!(birthDateInput instanceof HTMLInputElement) || !(ageInput instanceof HTMLInputElement)) {
            return;
        }

        function calculateAge(value) {
            if (!value) {
                return "";
            }
            var birthDate = new Date(value + "T00:00:00");
            if (Number.isNaN(birthDate.getTime())) {
                return "";
            }

            var today = new Date();
            var todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            if (birthDate > todayDate) {
                return "";
            }

            var age = todayDate.getFullYear() - birthDate.getFullYear();
            var monthDiff = todayDate.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && todayDate.getDate() < birthDate.getDate())) {
                age -= 1;
            }

            return age >= 0 ? String(age) : "";
        }

        function syncAge() {
            ageInput.value = calculateAge(birthDateInput.value);
        }

        birthDateInput.addEventListener("change", syncAge);
        birthDateInput.addEventListener("input", syncAge);
        syncAge();
    }

    function initNaToggles(options) {
        var opts = options || {};
        var toggles = document.querySelectorAll(opts.toggleSelector || ".js-na-toggle[data-target]");

        function applyNaState(toggle) {
            var targetSelector = toggle.getAttribute("data-target") || "";
            if (!targetSelector) {
                return;
            }
            var target = document.querySelector(targetSelector);
            if (!target) {
                return;
            }

            var disabled = !!toggle.checked;
            var fields = target.querySelectorAll("input, select, textarea, button");
            fields.forEach(function (field) {
                if (!(field instanceof HTMLElement)) {
                    return;
                }
                if (disabled) {
                    if (field instanceof HTMLInputElement) {
                        if (field.type === "checkbox" || field.type === "radio") {
                            field.checked = false;
                        } else {
                            field.value = "";
                        }
                    } else if (field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
                        field.value = "";
                    }
                    field.setAttribute("disabled", "disabled");
                } else {
                    field.removeAttribute("disabled");
                }
            });

            target.style.opacity = disabled ? "0.65" : "1";
        }

        toggles.forEach(function (toggle) {
            if (!(toggle instanceof HTMLInputElement)) {
                return;
            }
            toggle.addEventListener("change", function () {
                applyNaState(toggle);
            });
            applyNaState(toggle);
        });

        var honorsNaToggle = document.getElementById(opts.honorsToggleId || "honorsNaToggle");
        var honorsInputs = document.querySelectorAll(opts.honorsInputSelector || ".js-honors-input");
        function applyHonorsNaState() {
            if (!(honorsNaToggle instanceof HTMLInputElement) || !honorsInputs.length) {
                return;
            }
            honorsInputs.forEach(function (input) {
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }
                if (honorsNaToggle.checked) {
                    input.value = "";
                    input.setAttribute("disabled", "disabled");
                } else {
                    input.removeAttribute("disabled");
                }
            });
        }
        if (honorsNaToggle instanceof HTMLInputElement) {
            honorsNaToggle.addEventListener("change", applyHonorsNaState);
            applyHonorsNaState();
        }
    }

    function initAutosave(options) {
        var opts = options || {};
        var formSelector = String(opts.formSelector || "");
        var form = formSelector ? document.querySelector(formSelector) : null;
        var statusEl = document.getElementById(opts.statusId || "autosaveStatus");
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        var step = Number(opts.step || form.getAttribute("data-autosave-step") || 0);
        var csrfInput = form.querySelector('input[name="csrf_token"]');
        if (!(csrfInput instanceof HTMLInputElement) || !step) {
            return;
        }

        var endpoint = String(opts.endpoint || "apply-autosave.php");
        var intervalMs = Number(opts.intervalMs || 10000);
        var debounceMs = Number(opts.debounceMs || 1200);
        var lastSnapshot = "";
        var timer = null;
        var saving = false;
        var queued = false;

        function setStatus(text, className) {
            if (!statusEl) {
                return;
            }
            statusEl.className = "small mb-3 " + className;
            statusEl.textContent = text;
        }

        function buildSnapshot() {
            var formData = new FormData(form);
            formData.delete("csrf_token");
            formData.delete("action");
            var params = new URLSearchParams();
            formData.forEach(function (value, key) {
                if (value instanceof File) {
                    return;
                }
                params.append(key, String(value));
            });
            return params.toString();
        }

        function buildPayload() {
            var formData = new FormData(form);
            formData.set("csrf_token", csrfInput.value);
            formData.set("action", "autosave_step");
            formData.set("step", String(step));
            return formData;
        }

        async function saveDraft(force) {
            var snapshot = buildSnapshot();
            if (!force && snapshot === lastSnapshot) {
                return;
            }
            if (saving) {
                queued = true;
                return;
            }

            saving = true;
            setStatus("Auto-saving draft...", "text-muted");
            try {
                var response = await fetch(endpoint, {
                    method: "POST",
                    credentials: "same-origin",
                    body: buildPayload()
                });
                var data = await response.json();
                if (!response.ok || !data.ok) {
                    setStatus("Auto-save failed. Keep this page open and try again.", "text-danger");
                } else {
                    lastSnapshot = snapshot;
                    setStatus("Draft saved at " + (data.saved_time || "just now") + ".", "text-success");
                }
            } catch (error) {
                setStatus("Auto-save failed. Check your connection.", "text-danger");
            } finally {
                saving = false;
                if (queued) {
                    queued = false;
                    saveDraft(false);
                }
            }
        }

        function scheduleSave() {
            if (timer) {
                window.clearTimeout(timer);
            }
            timer = window.setTimeout(function () {
                saveDraft(false);
            }, debounceMs);
        }

        lastSnapshot = buildSnapshot();
        setStatus("Auto-save is enabled for this step.", "text-muted");

        form.addEventListener("input", scheduleSave);
        form.addEventListener("change", scheduleSave);
        form.addEventListener("submit", function () {
            if (timer) {
                window.clearTimeout(timer);
            }
        });

        window.setInterval(function () {
            saveDraft(false);
        }, intervalMs);

        window.addEventListener("beforeunload", function () {
            var snapshot = buildSnapshot();
            if (snapshot === lastSnapshot) {
                return;
            }
            if (navigator.sendBeacon) {
                navigator.sendBeacon(endpoint, buildPayload());
            }
        });
    }

    window.SE_APPLY_WIZARD = {
        initBirthdateAgeSync: initBirthdateAgeSync,
        initNaToggles: initNaToggles,
        initAutosave: initAutosave
    };
})(window);
