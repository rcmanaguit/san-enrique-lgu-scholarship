document.addEventListener("DOMContentLoaded", () => {
    const textLikeSelector = 'input[type="text"], input[type="tel"], input[type="password"], input[type="email"], textarea';
    const filterSelector = "[data-live-filter]";
    const candidateSelector = "input, select, textarea";

    const isFieldEligible = (field) => {
        if (!(field instanceof HTMLElement)) {
            return false;
        }

        if (field.matches('[type="hidden"], [type="file"], [readonly]')) {
            return false;
        }

        return true;
    };

    const isFieldVisible = (field) => {
        if (!(field instanceof HTMLElement)) {
            return false;
        }

        if (field.disabled) {
            return false;
        }

        if (field.closest("[hidden], .d-none")) {
            return false;
        }

        return field.getClientRects().length > 0;
    };

    const getFieldLabel = (field) => {
        const explicitLabel = field.getAttribute("data-field-label");
        if (explicitLabel) {
            return explicitLabel;
        }

        const id = field.getAttribute("id");
        if (id) {
            const label = document.querySelector(`label[for="${CSS.escape(id)}"]`);
            if (label) {
                return label.textContent.replace(/\*/g, "").trim();
            }
        }

        const scopedLabel = field.closest(".col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-6, .col-md-8, .col-lg-4, .col-lg-6, .mb-2, .mb-3, .mb-4, td, .form-check, .application-section-card")?.querySelector(".form-label, .form-check-label");
        if (scopedLabel) {
            return scopedLabel.textContent.replace(/\*/g, "").trim();
        }

        return field.getAttribute("name") || "This field";
    };

    const getFeedbackTarget = (field) => field.closest(".input-group") || field;

    const getFeedbackElement = (field) => {
        let feedback = field._liveValidationFeedback;

        if (feedback && feedback.isConnected) {
            return feedback;
        }

        const target = getFeedbackTarget(field);
        const nextSibling = target.nextElementSibling;
        if (nextSibling && nextSibling.classList.contains("live-validation-message")) {
            feedback = nextSibling;
        } else {
            feedback = document.createElement("div");
            feedback.className = "live-validation-message";
            target.insertAdjacentElement("afterend", feedback);
        }

        field._liveValidationFeedback = feedback;
        return feedback;
    };

    const applyFieldFilter = (field) => {
        const filterType = field.getAttribute("data-live-filter");
        const maxLength = parseInt(field.getAttribute("maxlength") || "0", 10);
        let value = field.value;

        switch (filterType) {
            case "digits":
            case "otp":
                value = value.replace(/\D+/g, "");
                break;
            case "letters":
                value = value.replace(/[^A-Za-z.\-'\s]/g, "");
                value = value.replace(/\s{2,}/g, " ");
                break;
            case "decimal":
                value = value.replace(/[^0-9.]/g, "");
                if (value.startsWith(".")) {
                    value = "0" + value;
                }

                const firstDotIndex = value.indexOf(".");
                if (firstDotIndex !== -1) {
                    const integerPart = value.slice(0, firstDotIndex + 1);
                    const decimalPart = value.slice(firstDotIndex + 1).replace(/\./g, "").slice(0, 2);
                    value = integerPart + decimalPart;
                }
                break;
            case "email":
                value = value.replace(/\s+/g, "").toLowerCase();
                break;
            default:
                break;
        }

        if (maxLength > 0) {
            value = value.slice(0, maxLength);
        }

        if (field.value !== value) {
            const selectionStart = field.selectionStart;
            field.value = value;

            if (typeof selectionStart === "number" && typeof field.setSelectionRange === "function") {
                const newPosition = Math.min(selectionStart, value.length);
                field.setSelectionRange(newPosition, newPosition);
            }
        }
    };

    const getCustomValidation = (field) => {
        if (!isFieldVisible(field)) {
            return "";
        }

        const value = field.value.trim();
        const label = getFieldLabel(field);
        const required = field.required;
        const filterType = field.getAttribute("data-live-filter");
        const validateType = field.getAttribute("data-validate");
        const exactLength = parseInt(field.getAttribute("data-exact-length") || field.getAttribute("maxlength") || "0", 10);

        if (required && value === "") {
            return field.getAttribute("data-required-message") || `${label} is required.`;
        }

        if (value === "") {
            return "";
        }

        if (filterType === "digits" && exactLength > 0 && value.length !== exactLength) {
            return field.getAttribute("data-invalid-message") || `${label} must be exactly ${exactLength} digits.`;
        }

        if (filterType === "otp" && exactLength > 0 && value.length !== exactLength) {
            return field.getAttribute("data-invalid-message") || `${label} must be exactly ${exactLength} digits.`;
        }

        if (field.type === "email" || filterType === "email" || validateType === "email") {
            const isValidEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            if (!isValidEmail) {
                return field.getAttribute("data-invalid-message") || "Please enter a valid email address.";
            }
        }

        if (validateType === "password-optional") {
            const minLength = parseInt(field.getAttribute("minlength") || "0", 10);
            if (value !== "" && minLength > 0 && value.length < minLength) {
                return field.getAttribute("data-invalid-message") || `${label} must be at least ${minLength} characters.`;
            }
        }

        if (field.type === "password" || validateType === "password") {
            const minLength = parseInt(field.getAttribute("minlength") || "0", 10);
            if (minLength > 0 && value.length < minLength) {
                return field.getAttribute("data-invalid-message") || `${label} must be at least ${minLength} characters.`;
            }
        }

        const matchFieldSelector = field.getAttribute("data-match-field");
        if (matchFieldSelector) {
            const targetField = document.querySelector(matchFieldSelector);
            if (targetField && value !== targetField.value) {
                return field.getAttribute("data-match-message") || `${label} does not match.`;
            }
        }

        if (validateType === "selected" && field instanceof HTMLSelectElement && !field.value) {
            return field.getAttribute("data-invalid-message") || `Please select ${label.toLowerCase()}.`;
        }

        return "";
    };

    const setFieldState = (field, { touched = false, force = false } = {}) => {
        if (!isFieldEligible(field)) {
            return true;
        }

        if (touched) {
            field.dataset.touched = "true";
        }

        const shouldShow = force || field.dataset.touched === "true";
        const feedback = getFeedbackElement(field);
        const feedbackTarget = getFeedbackTarget(field);

        if (!isFieldVisible(field)) {
            field.setCustomValidity("");
            field.classList.remove("is-invalid");
            feedbackTarget.classList.remove("has-live-invalid");
            feedback.hidden = true;
            feedback.textContent = "";
            return true;
        }

        const customMessage = getCustomValidation(field);
        field.setCustomValidity(customMessage);

        const isValid = field.checkValidity();
        if (!shouldShow) {
            field.classList.remove("is-invalid");
            feedbackTarget.classList.remove("has-live-invalid");
            feedback.hidden = true;
            feedback.textContent = "";
            return isValid;
        }

        field.classList.toggle("is-invalid", !isValid);
        feedbackTarget.classList.toggle("has-live-invalid", !isValid);
        feedback.classList.toggle("is-invalid", !isValid);
        feedback.hidden = isValid;
        feedback.textContent = isValid ? "" : (customMessage || field.validationMessage);

        return isValid;
    };

    const validateFields = (fields, { touch = false, focusFirst = false } = {}) => {
        const list = Array.from(fields).filter(isFieldEligible);
        let firstInvalid = null;

        list.forEach((field) => {
            const isValid = setFieldState(field, { touched: touch, force: touch });
            if (!isValid && !firstInvalid && isFieldVisible(field)) {
                firstInvalid = field;
            }
        });

        if (focusFirst && firstInvalid) {
            firstInvalid.focus();
            firstInvalid.reportValidity();
        }

        return !firstInvalid;
    };

    const validateForm = (form, options = {}) => validateFields(form.querySelectorAll(candidateSelector), options);

    document.querySelectorAll(textLikeSelector).forEach((field) => {
        field.addEventListener("blur", () => {
            if (!field.hasAttribute("data-no-trim")) {
                field.value = field.value.trim();
            }
        });
    });

    document.querySelectorAll(filterSelector).forEach((field) => {
        applyFieldFilter(field);
    });

    document.querySelectorAll(candidateSelector).forEach((field) => {
        if (isFieldEligible(field)) {
            setFieldState(field);
        }
    });

    document.addEventListener("input", (event) => {
        const field = event.target.closest(candidateSelector);
        if (!field || !isFieldEligible(field)) {
            return;
        }

        if (field.matches(filterSelector)) {
            applyFieldFilter(field);
        }

        setFieldState(field, { touched: true });

        const matchFieldSelector = field.getAttribute("data-match-field");
        if (matchFieldSelector) {
            const matchedField = document.querySelector(matchFieldSelector);
            if (matchedField) {
                setFieldState(matchedField, { force: matchedField.dataset.touched === "true" });
            }
        }

        if (field.id) {
            document.querySelectorAll(`[data-match-field="#${CSS.escape(field.id)}"]`).forEach((dependentField) => {
                setFieldState(dependentField, { force: dependentField.dataset.touched === "true" });
            });
        }
    });

    document.addEventListener("change", (event) => {
        const field = event.target.closest(candidateSelector);
        if (!field || !isFieldEligible(field)) {
            return;
        }

        setFieldState(field, { touched: true });
    });

    document.addEventListener("paste", (event) => {
        const filterField = event.target.closest(filterSelector);
        if (filterField) {
            window.setTimeout(() => {
                applyFieldFilter(filterField);
                setFieldState(filterField, { touched: true });
            }, 0);
        }
    });

    document.addEventListener("blur", (event) => {
        const field = event.target.closest(candidateSelector);
        if (!field || !isFieldEligible(field)) {
            return;
        }

        if (field.matches(textLikeSelector) && !field.hasAttribute("data-no-trim")) {
            field.value = field.value.trim();
        }

        setFieldState(field, { touched: true });
    }, true);

    document.querySelectorAll("form").forEach((form) => {
        form.addEventListener("submit", (event) => {
            const isValid = validateForm(form, { touch: true, focusFirst: true });
            if (!isValid) {
                event.preventDefault();
            }
        });
    });

    window.AppLiveValidation = {
        validateForm,
        validateFields,
        validateField: (field, options = {}) => setFieldState(field, options),
    };
});
