(function (window) {
    "use strict";

    function isVisible(field) {
        if (!(field instanceof HTMLElement)) {
            return false;
        }
        return field.offsetParent !== null && !field.closest(".d-none");
    }

    function getFieldLabel(field) {
        if (!(field instanceof HTMLElement)) {
            return "This field";
        }
        var label = "";
        if (field.id) {
            var explicitLabel = document.querySelector('label[for="' + field.id + '"]');
            if (explicitLabel) {
                label = explicitLabel.textContent || "";
            }
        }
        if (!label && field.closest("td")) {
            var inlineLabel = field.closest("td").querySelector(".wizard-inline-field-label");
            if (inlineLabel) {
                label = inlineLabel.textContent || "";
            }
        }
        label = String(label).replace(/\*/g, "").trim();
        return label || "This field";
    }

    function getFeedbackEl(field) {
        if (!(field instanceof HTMLElement)) {
            return null;
        }
        var wrapper = field.closest(".input-group") || field.parentElement;
        if (!wrapper) {
            return null;
        }
        var feedback = wrapper.querySelector(".js-live-feedback");
        if (feedback) {
            return feedback;
        }
        feedback = document.createElement("div");
        feedback.className = "form-text text-muted js-live-feedback";
        if (field.closest(".input-group")) {
            field.closest(".input-group").insertAdjacentElement("afterend", feedback);
        } else {
            wrapper.appendChild(feedback);
        }
        return feedback;
    }

    function setFieldState(field, status, message) {
        var feedback = getFeedbackEl(field);
        if (!(field instanceof HTMLElement) || !feedback) {
            return;
        }

        field.classList.remove("is-valid", "is-invalid");
        feedback.classList.remove("text-success", "text-danger", "text-muted");

        if (status === "valid") {
            field.classList.add("is-valid");
            feedback.classList.add("text-success");
        } else if (status === "invalid") {
            field.classList.add("is-invalid");
            feedback.classList.add("text-danger");
        } else {
            feedback.classList.add("text-muted");
        }

        feedback.textContent = message;
    }

    function validateField(field, force) {
        if (!(field instanceof HTMLElement)) {
            return true;
        }
        if (field.disabled || field.readOnly || !isVisible(field)) {
            field.classList.remove("is-valid", "is-invalid");
            return true;
        }

        var label = getFieldLabel(field);
        var required = field.hasAttribute("required");
        var value = "value" in field ? String(field.value || "").trim() : "";
        var type = field.getAttribute("type") || "";
        var shouldValidate = force || value !== "";

        if (!required && value === "") {
            setFieldState(field, "neutral", "Optional.");
            return true;
        }

        if (!shouldValidate) {
            setFieldState(field, "neutral", required ? (label + " is required.") : "Optional.");
            return !required;
        }

        if (field instanceof HTMLInputElement && type === "file") {
            var hasFile = !!(field.files && field.files.length > 0);
            if (required && !hasFile) {
                setFieldState(field, "invalid", label + " is required.");
                return false;
            }
            setFieldState(field, "valid", hasFile ? "File selected." : "Current file already saved.");
            return true;
        }

        if (required && value === "") {
            setFieldState(field, "invalid", label + " is required.");
            return false;
        }

        if (field instanceof HTMLInputElement && type === "email") {
            var emailOk = field.checkValidity();
            setFieldState(field, emailOk ? "valid" : "invalid", emailOk ? "Email format looks good." : "Enter a valid email address.");
            return emailOk;
        }

        if (field instanceof HTMLInputElement && (type === "number" || type === "date")) {
            var numberOk = field.checkValidity();
            setFieldState(field, numberOk ? "valid" : "invalid", numberOk ? (label + " looks good.") : ("Enter a valid " + label.toLowerCase() + "."));
            return numberOk;
        }

        if (field instanceof HTMLSelectElement) {
            var selectOk = value !== "" && field.checkValidity();
            setFieldState(field, selectOk ? "valid" : "invalid", selectOk ? (label + " selected.") : ("Select " + label.toLowerCase() + "."));
            return selectOk;
        }

        setFieldState(field, "valid", label + " looks good.");
        return true;
    }

    function initFormValidation(form) {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        var fields = Array.prototype.slice.call(
            form.querySelectorAll("input, select, textarea")
        ).filter(function (field) {
            return !(field instanceof HTMLInputElement && (field.type === "hidden" || field.type === "submit" || field.type === "button"));
        });

        fields.forEach(function (field) {
            var events = field instanceof HTMLSelectElement || (field instanceof HTMLInputElement && field.type === "file")
                ? ["change", "blur"]
                : ["input", "blur"];
            events.forEach(function (eventName) {
                field.addEventListener(eventName, function () {
                    validateField(field, eventName === "blur" || eventName === "change");
                });
            });
        });

        form.addEventListener("submit", function (event) {
            var allValid = true;
            fields.forEach(function (field) {
                if (!validateField(field, true)) {
                    allValid = false;
                }
            });
            if (!allValid) {
                event.preventDefault();
                var firstInvalid = form.querySelector(".is-invalid");
                if (firstInvalid && typeof firstInvalid.focus === "function") {
                    firstInvalid.focus();
                }
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("#applyStep1Form, #applyStep2Form, #applyStep3Form, #applyStep4Form").forEach(initFormValidation);
    });
})(window);
