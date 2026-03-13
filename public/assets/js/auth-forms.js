(function () {
    function setFieldState(input, feedback, status, message) {
        if (!input || !feedback) {
            return;
        }

        input.classList.remove("is-valid", "is-invalid");
        feedback.classList.remove("text-success", "text-danger", "text-muted");

        if (status === "valid") {
            input.classList.add("is-valid");
            feedback.classList.add("text-success");
        } else if (status === "invalid") {
            input.classList.add("is-invalid");
            feedback.classList.add("text-danger");
        } else {
            feedback.classList.add("text-muted");
        }

        if (typeof message === "string") {
            feedback.textContent = message;
        }
    }

    function sanitizeDigits(input, maxLength) {
        if (!input) {
            return "";
        }
        input.value = String(input.value || "").replace(/\D+/g, "").slice(0, maxLength || 12);
        return String(input.value || "").trim();
    }

    function bindNumericInput(input, maxLength) {
        if (!input) {
            return;
        }

        input.addEventListener("input", function () {
            sanitizeDigits(input, maxLength);
        });
        input.addEventListener("paste", function () {
            window.setTimeout(function () {
                sanitizeDigits(input, maxLength);
            }, 0);
        });
        input.addEventListener("keydown", function (event) {
            var allowedKeys = ["Backspace", "Delete", "Tab", "ArrowLeft", "ArrowRight", "Home", "End"];
            if (allowedKeys.indexOf(event.key) >= 0 || event.ctrlKey || event.metaKey) {
                return;
            }
            if (!/^\d$/.test(event.key)) {
                event.preventDefault();
            }
        });
    }

    function validatePhone(input, feedback, options) {
        var config = options || {};
        var emptyMessage = config.emptyMessage || "Use 11 digits starting with 09.";
        var invalidMessage = config.invalidMessage || "Use a valid mobile number in 09XXXXXXXXX format.";
        var validMessage = config.validMessage || "Mobile number format looks good.";
        var value = sanitizeDigits(input, 12);

        if (value === "") {
            setFieldState(input, feedback, "neutral", emptyMessage);
            return false;
        }

        var ok = /^09\d{9}$/.test(value);
        setFieldState(input, feedback, ok ? "valid" : "invalid", ok ? validMessage : invalidMessage);
        return ok;
    }

    function validateRequired(input, feedback, label) {
        if (!input || !feedback) {
            return true;
        }
        var value = String(input.value || "").trim();
        if (value === "") {
            setFieldState(input, feedback, "invalid", label + " is required.");
            return false;
        }
        setFieldState(input, feedback, "valid", label + " looks good.");
        return true;
    }

    function validatePassword(input, feedback, labelPrefix) {
        if (!input || !feedback) {
            return true;
        }

        var value = String(input.value || "");
        if (value === "") {
            setFieldState(input, feedback, "neutral", "Use at least 8 characters.");
            return false;
        }

        var ok = value.length >= 8;
        setFieldState(
            input,
            feedback,
            ok ? "valid" : "invalid",
            ok ? (labelPrefix || "Password") + " length looks good." : (labelPrefix || "Password") + " must be at least 8 characters."
        );
        return ok;
    }

    function validatePasswordConfirm(passwordInput, confirmInput, feedback, labelPrefix) {
        if (!passwordInput || !confirmInput || !feedback) {
            return true;
        }

        var confirmValue = String(confirmInput.value || "");
        if (confirmValue === "") {
            setFieldState(confirmInput, feedback, "neutral", "Re-enter the same password.");
            return false;
        }

        var ok = confirmValue === String(passwordInput.value || "");
        setFieldState(
            confirmInput,
            feedback,
            ok ? "valid" : "invalid",
            ok ? (labelPrefix || "Passwords") + " match." : "Confirm password must match the password."
        );
        return ok;
    }

    function validateOtp(input, feedback) {
        if (!input || !feedback) {
            return true;
        }

        var value = sanitizeDigits(input, 8);
        if (value === "") {
            setFieldState(input, feedback, "neutral", "Enter the verification code sent to your mobile number.");
            return false;
        }

        var ok = /^\d{4,8}$/.test(value);
        setFieldState(input, feedback, ok ? "valid" : "invalid", ok ? "Code format looks good." : "Use the numeric verification code only.");
        return ok;
    }

    function initLoginForm(form) {
        var phoneInput = form.querySelector("#phone");
        var passwordInput = form.querySelector("#password");
        var phoneFeedback = form.querySelector("[data-feedback='login-phone']");
        var passwordFeedback = form.querySelector("[data-feedback='login-password']");

        bindNumericInput(phoneInput, 12);

        if (phoneInput) {
            phoneInput.addEventListener("input", function () {
                validatePhone(phoneInput, phoneFeedback, {});
            });
            phoneInput.addEventListener("blur", function () {
                validatePhone(phoneInput, phoneFeedback, {});
            });
        }

        if (passwordInput) {
            passwordInput.addEventListener("input", function () {
                if (String(passwordInput.value || "") === "") {
                    setFieldState(passwordInput, passwordFeedback, "neutral", "Enter your account password.");
                    return;
                }
                setFieldState(passwordInput, passwordFeedback, "neutral", "Enter your account password.");
            });
            passwordInput.addEventListener("blur", function () {
                if (String(passwordInput.value || "") === "") {
                    setFieldState(passwordInput, passwordFeedback, "invalid", "Password is required.");
                    return;
                }
                setFieldState(passwordInput, passwordFeedback, "neutral", "Enter your account password.");
            });
        }

        form.addEventListener("submit", function (event) {
            var passwordOk = String(passwordInput && passwordInput.value || "").trim() !== "";
            if (!passwordOk) {
                setFieldState(passwordInput, passwordFeedback, "invalid", "Password is required.");
            }
            if (!(validatePhone(phoneInput, phoneFeedback, {}) && passwordOk)) {
                event.preventDefault();
            }
        });
    }

    function initRegisterForm(form) {
        var firstNameInput = form.querySelector("#registerFirstName");
        var lastNameInput = form.querySelector("#registerLastName");
        var phoneInput = form.querySelector("#registerPhone");
        var passwordInput = form.querySelector("#registerPassword");
        var confirmPasswordInput = form.querySelector("#registerConfirmPassword");
        var firstNameFeedback = form.querySelector("[data-feedback='register-first-name']");
        var lastNameFeedback = form.querySelector("[data-feedback='register-last-name']");
        var phoneFeedback = form.querySelector("[data-feedback='register-phone']");
        var passwordFeedback = form.querySelector("[data-feedback='register-password']");
        var confirmPasswordFeedback = form.querySelector("[data-feedback='register-confirm-password']");
        var phoneAvailabilityTimer = 0;
        var phoneAvailabilityController = null;
        var lastCheckedPhone = "";
        var phoneAvailabilityStatus = "unknown";

        function checkPhoneAvailability() {
            var value = String(phoneInput.value || "").trim();
            if (!/^09\d{9}$/.test(value)) {
                phoneAvailabilityStatus = "invalid";
                return;
            }
            if (value === lastCheckedPhone && phoneAvailabilityStatus !== "unknown") {
                return;
            }
            if (phoneAvailabilityController) {
                phoneAvailabilityController.abort();
            }

            phoneAvailabilityStatus = "checking";
            setFieldState(phoneInput, phoneFeedback, "neutral", "Checking mobile number availability...");
            phoneAvailabilityController = new AbortController();

            fetch("register.php?check=phone&phone=" + encodeURIComponent(value), {
                method: "GET",
                headers: { Accept: "application/json" },
                signal: phoneAvailabilityController.signal
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error("Request failed");
                    }
                    return response.json();
                })
                .then(function (payload) {
                    lastCheckedPhone = value;
                    var available = Boolean(payload && payload.ok && payload.available);
                    phoneAvailabilityStatus = available ? "available" : "taken";
                    setFieldState(
                        phoneInput,
                        phoneFeedback,
                        available ? "valid" : "invalid",
                        String((payload && payload.message) || (available ? "Mobile number is available." : "Mobile number is already registered."))
                    );
                })
                .catch(function (error) {
                    if (error && error.name === "AbortError") {
                        return;
                    }
                    phoneAvailabilityStatus = "unknown";
                    setFieldState(phoneInput, phoneFeedback, "neutral", "Could not check availability right now.");
                });
        }

        bindNumericInput(phoneInput, 12);

        if (firstNameInput) {
            firstNameInput.addEventListener("input", function () {
                validateRequired(firstNameInput, firstNameFeedback, "First name");
            });
            firstNameInput.addEventListener("blur", function () {
                validateRequired(firstNameInput, firstNameFeedback, "First name");
            });
        }

        if (lastNameInput) {
            lastNameInput.addEventListener("input", function () {
                validateRequired(lastNameInput, lastNameFeedback, "Last name");
            });
            lastNameInput.addEventListener("blur", function () {
                validateRequired(lastNameInput, lastNameFeedback, "Last name");
            });
        }

        if (phoneInput) {
            phoneInput.addEventListener("input", function () {
                var ok = validatePhone(phoneInput, phoneFeedback, {});
                window.clearTimeout(phoneAvailabilityTimer);
                if (!ok) {
                    if (phoneAvailabilityController) {
                        phoneAvailabilityController.abort();
                    }
                    return;
                }
                if (String(phoneInput.value || "").trim() !== lastCheckedPhone) {
                    phoneAvailabilityStatus = "unknown";
                }
                phoneAvailabilityTimer = window.setTimeout(checkPhoneAvailability, 350);
            });
            phoneInput.addEventListener("blur", function () {
                if (validatePhone(phoneInput, phoneFeedback, {})) {
                    checkPhoneAvailability();
                }
            });
        }

        if (passwordInput) {
            passwordInput.addEventListener("input", function () {
                validatePassword(passwordInput, passwordFeedback, "Password");
                validatePasswordConfirm(passwordInput, confirmPasswordInput, confirmPasswordFeedback, "Passwords");
            });
            passwordInput.addEventListener("blur", function () {
                validatePassword(passwordInput, passwordFeedback, "Password");
            });
        }

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener("input", function () {
                validatePasswordConfirm(passwordInput, confirmPasswordInput, confirmPasswordFeedback, "Passwords");
            });
            confirmPasswordInput.addEventListener("blur", function () {
                validatePasswordConfirm(passwordInput, confirmPasswordInput, confirmPasswordFeedback, "Passwords");
            });
        }

        form.addEventListener("submit", function (event) {
            var requiredNamesOk = validateRequired(firstNameInput, firstNameFeedback, "First name")
                && validateRequired(lastNameInput, lastNameFeedback, "Last name");
            var phoneOk = validatePhone(phoneInput, phoneFeedback, {});
            var passwordOk = validatePassword(passwordInput, passwordFeedback, "Password");
            var confirmPasswordOk = validatePasswordConfirm(passwordInput, confirmPasswordInput, confirmPasswordFeedback, "Passwords");
            var phoneAvailable = phoneAvailabilityStatus === "available";

            if (!(requiredNamesOk && phoneOk && passwordOk && confirmPasswordOk && phoneAvailable)) {
                event.preventDefault();
                if (phoneOk && (phoneAvailabilityStatus === "unknown" || phoneAvailabilityStatus === "checking")) {
                    checkPhoneAvailability();
                }
            }
        });
    }

    function initRegisterOtpForm(form) {
        var otpInput = form.querySelector("input[name='otp_code']");
        var otpFeedback = form.querySelector("[data-feedback='register-otp-code']");
        bindNumericInput(otpInput, 8);

        if (otpInput) {
            otpInput.addEventListener("input", function () {
                validateOtp(otpInput, otpFeedback);
            });
            otpInput.addEventListener("blur", function () {
                validateOtp(otpInput, otpFeedback);
            });
        }

        form.addEventListener("submit", function (event) {
            if (!validateOtp(otpInput, otpFeedback)) {
                event.preventDefault();
            }
        });
    }

    function initForgotRequestForm(form) {
        var phoneInput = form.querySelector("#forgotPhone");
        var phoneFeedback = form.querySelector("[data-feedback='forgot-phone']");
        bindNumericInput(phoneInput, 12);

        if (phoneInput) {
            phoneInput.addEventListener("input", function () {
                validatePhone(phoneInput, phoneFeedback, {
                    emptyMessage: "Use your registered 11-digit mobile number.",
                    validMessage: "Mobile number format looks good."
                });
            });
            phoneInput.addEventListener("blur", function () {
                validatePhone(phoneInput, phoneFeedback, {
                    emptyMessage: "Use your registered 11-digit mobile number.",
                    validMessage: "Mobile number format looks good."
                });
            });
        }

        form.addEventListener("submit", function (event) {
            if (!validatePhone(phoneInput, phoneFeedback, {
                emptyMessage: "Use your registered 11-digit mobile number.",
                validMessage: "Mobile number format looks good."
            })) {
                event.preventDefault();
            }
        });
    }

    function initForgotResetForm(form) {
        var otpInput = form.querySelector("input[name='otp_code']");
        var newPasswordInput = form.querySelector("#forgotNewPassword");
        var confirmPasswordInput = form.querySelector("#forgotConfirmPassword");
        var otpFeedback = form.querySelector("[data-feedback='forgot-otp-code']");
        var newPasswordFeedback = form.querySelector("[data-feedback='forgot-new-password']");
        var confirmPasswordFeedback = form.querySelector("[data-feedback='forgot-confirm-password']");

        bindNumericInput(otpInput, 8);

        if (otpInput) {
            otpInput.addEventListener("input", function () {
                validateOtp(otpInput, otpFeedback);
            });
            otpInput.addEventListener("blur", function () {
                validateOtp(otpInput, otpFeedback);
            });
        }

        if (newPasswordInput) {
            newPasswordInput.addEventListener("input", function () {
                validatePassword(newPasswordInput, newPasswordFeedback, "Password");
                validatePasswordConfirm(newPasswordInput, confirmPasswordInput, confirmPasswordFeedback, "Passwords");
            });
            newPasswordInput.addEventListener("blur", function () {
                validatePassword(newPasswordInput, newPasswordFeedback, "Password");
            });
        }

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener("input", function () {
                validatePasswordConfirm(newPasswordInput, confirmPasswordInput, confirmPasswordFeedback, "Passwords");
            });
            confirmPasswordInput.addEventListener("blur", function () {
                validatePasswordConfirm(newPasswordInput, confirmPasswordInput, confirmPasswordFeedback, "Passwords");
            });
        }

        form.addEventListener("submit", function (event) {
            if (!(validateOtp(otpInput, otpFeedback)
                && validatePassword(newPasswordInput, newPasswordFeedback, "Password")
                && validatePasswordConfirm(newPasswordInput, confirmPasswordInput, confirmPasswordFeedback, "Passwords"))) {
                event.preventDefault();
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("form[data-auth-form]").forEach(function (form) {
            var formType = form.getAttribute("data-auth-form");
            if (formType === "login") {
                initLoginForm(form);
            } else if (formType === "register") {
                initRegisterForm(form);
            } else if (formType === "register-otp") {
                initRegisterOtpForm(form);
            } else if (formType === "forgot-request") {
                initForgotRequestForm(form);
            } else if (formType === "forgot-reset") {
                initForgotResetForm(form);
            }
        });
    });
}());
