/* Secure student registration - front-end validation + live duplicate checks */
(function () {
    "use strict";

    const form = document.getElementById("registrationForm");
    if (!form) {
        return;
    }

    const AVAILABILITY_API = "./php/api/check_availability.php";

    /* The same rules are enforced again in php/register_process.php. */
    const NAME_PATTERN = /^[A-Za-z\s]{2,50}$/;
    const ROLL_PATTERN = /^[A-Z0-9]{4,15}$/;
    const EMAIL_PATTERN = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/;
    const MOBILE_PATTERN = /^[6-9][0-9]{9}$/;
    const PASSWORD_PATTERN = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/;

    const fields = {
        fullname: document.getElementById("fullname"),
        rollNo: document.getElementById("rollNo"),
        email: document.getElementById("email"),
        mobile: document.getElementById("mobile"),
        password: document.getElementById("password"),
        confirmpassword: document.getElementById("confirmpassword"),
        course: document.getElementById("course"),
        year: document.getElementById("year"),
        terms: document.getElementById("terms")
    };

    const availability = { email: null, rollNo: null };
    const requestId = { email: 0, rollNo: 0 };
    let submitting = false;

    function messageBox(name) {
        return document.getElementById(name + "Error");
    }

    function markField(name, state) {
        const field = fields[name];
        if (!field || field.type === "radio" || field.type === "checkbox") {
            return;
        }
        field.classList.remove("is-invalid", "is-valid");
        if (state) {
            field.classList.add(state);
        }
    }

    function setError(name, message) {
        const box = messageBox(name);
        if (box) {
            box.textContent = message;
            box.className = "error-message";
        }
        markField(name, "is-invalid");
        return false;
    }

    function setValid(name) {
        const box = messageBox(name);
        if (box) {
            box.textContent = "";
            box.className = "error-message";
        }
        markField(name, "is-valid");
        return true;
    }

    function clearField(name) {
        const box = messageBox(name);
        if (box) {
            box.textContent = "";
            box.className = "error-message";
        }
        markField(name, null);
    }

    function setChecking(name, message) {
        const box = messageBox(name);
        if (box) {
            box.textContent = message;
            box.className = "error-message checking";
        }
    }

    function setAvailable(name, message) {
        setAvailableBox(name, message);
        markField(name, "is-valid");
    }

    function setAvailableBox(name, message) {
        const box = messageBox(name);
        if (box) {
            box.textContent = message;
            box.className = "error-message success";
        }
    }

    function showFormMessage(message, type) {
        const box = document.getElementById("formMessage");
        if (!box) {
            return;
        }
        box.textContent = message;
        box.className = message ? "form-message show " + type : "form-message";
    }

    function focusFirstError() {
        const boxes = Array.from(document.querySelectorAll(".error-message"));

        for (const box of boxes) {
            if (box.textContent.trim() === "" ||
                box.classList.contains("success") ||
                box.classList.contains("checking")) {
                continue;
            }

            const group = box.closest(".form-group");
            const control = group ? group.querySelector("input, select, textarea") : null;
            const target = control || box;
            target.focus();
            target.scrollIntoView({ behavior: "smooth", block: "center" });
            return;
        }
    }

    /* ------------------------------------------------------------------
     * Field validators
     * ------------------------------------------------------------------ */
    function validateFullName() {
        const value = fields.fullname.value.trim();

        if (value === "") {
            return setError("fullname", "Full name is required.");
        }
        if (!NAME_PATTERN.test(value)) {
            return setError("fullname", "Full name must contain only letters and spaces (2-50 characters).");
        }
        return setValid("fullname");
    }

    function validateRollNo() {
        const value = fields.rollNo.value.trim().toUpperCase();
        fields.rollNo.value = value;

        if (value === "") {
            /* The server generates a username when this optional field is blank. */
            clearField("rollNo");
            return true;
        }
        if (!ROLL_PATTERN.test(value)) {
            return setError("rollNo", "Username must be 4-15 letters or digits (e.g. CS21009).");
        }
        if (availability.rollNo === false) {
            return setError("rollNo", "This username / roll number is already taken. Please choose another.");
        }
        return setValid("rollNo");
    }

    function validateEmail() {
        const value = fields.email.value.trim().toLowerCase();
        fields.email.value = value;

        if (value === "") {
            return setError("email", "Email address is required.");
        }
        if (!EMAIL_PATTERN.test(value)) {
            return setError("email", "Please enter a valid email address (e.g. name@example.com).");
        }
        if (availability.email === false) {
            return setError("email", "This email is already registered. Please log in or use another email.");
        }
        return setValid("email");
    }

    function validateMobile() {
        const value = fields.mobile.value.trim();

        if (value === "") {
            return setError("mobile", "Mobile number is required.");
        }
        if (!MOBILE_PATTERN.test(value)) {
            return setError("mobile", "Mobile number must be 10 digits starting with 6-9.");
        }
        return setValid("mobile");
    }

    function validatePassword() {
        const value = fields.password.value;

        if (value === "") {
            return setError("password", "Password is required.");
        }
        if (!PASSWORD_PATTERN.test(value)) {
            return setError(
                "password",
                "Password must be at least 8 characters and include a letter, a number and a special character (@$!%*#?&)."
            );
        }
        return setValid("password");
    }

    function validateConfirmPassword() {
        const value = fields.confirmpassword.value;

        if (value === "") {
            return setError("confirmpassword", "Please confirm your password.");
        }
        if (value !== fields.password.value) {
            return setError("confirmpassword", "Passwords do not match.");
        }
        return setValid("confirmpassword");
    }

    function validateCourse() {
        if (fields.course.value === "") {
            return setError("course", "Please select a course.");
        }
        return setValid("course");
    }

    function validateYear() {
        if (fields.year.value === "") {
            return setError("year", "Please select a year.");
        }
        return setValid("year");
    }

    function validateGender() {
        const selected = form.querySelector('input[name="gender"]:checked');
        if (!selected) {
            return setError("gender", "Please select a gender.");
        }
        clearField("gender");
        return true;
    }

    function validateTerms() {
        if (!fields.terms.checked) {
            return setError("terms", "You must accept the Terms and Conditions.");
        }
        clearField("terms");
        return true;
    }

    function validateField(name) {
        switch (name) {
            case "fullname":        return validateFullName();
            case "rollNo":          return validateRollNo();
            case "email":           return validateEmail();
            case "mobile":          return validateMobile();
            case "password":        return validatePassword();
            case "confirmpassword": return validateConfirmPassword();
            case "course":          return validateCourse();
            case "year":            return validateYear();
            default:                return true;
        }
    }

    /* ------------------------------------------------------------------
     * Live duplicate email / username check
     * ------------------------------------------------------------------ */
    async function checkAvailability(name) {
        const isEmail = name === "email";
        const value = isEmail
            ? fields.email.value.trim().toLowerCase()
            : fields.rollNo.value.trim().toUpperCase();

        if (value === "") {
            return null;
        }
        if (isEmail && !EMAIL_PATTERN.test(value)) {
            return null;
        }
        if (!isEmail && !ROLL_PATTERN.test(value)) {
            return null;
        }

        const currentRequest = ++requestId[name];
        setChecking(name, "Checking availability...");

        try {
            const parameter = isEmail ? "email" : "username";
            const response = await fetch(
                AVAILABILITY_API + "?" + parameter + "=" + encodeURIComponent(value) + "&t=" + Date.now(),
                { cache: "no-store", headers: { Accept: "application/json" } }
            );
            const payload = await response.json();

            /* Ignore a response that belongs to an older value. */
            if (currentRequest !== requestId[name]) {
                return null;
            }

            if (!response.ok || !payload.success) {
                /* The server re-checks on submit, so a failed check must not block the user. */
                clearField(name);
                return null;
            }

            const isAvailable = isEmail
                ? Boolean(payload.data.email_available)
                : Boolean(payload.data.username_available);

            availability[name] = isAvailable;

            if (!isAvailable) {
                return setError(
                    name,
                    isEmail
                        ? "This email is already registered. Please log in or use another email."
                        : "This username / roll number is already taken. Please choose another."
                );
            }

            setAvailable(name, isEmail ? "Email is available." : "Username is available.");
            return true;
        } catch (error) {
            if (currentRequest === requestId[name]) {
                clearField(name);
            }
            return null;
        }
    }

    /* ------------------------------------------------------------------
     * Event wiring
     * ------------------------------------------------------------------ */
    ["fullname", "email", "mobile", "password", "confirmpassword"].forEach(function (name) {
        const field = fields[name];

        field.addEventListener("blur", function () {
            const valid = validateField(name);
            if (valid && name === "email") {
                checkAvailability("email");
            }
        });

        field.addEventListener("input", function () {
            if (name === "email") {
                availability.email = null;
                requestId.email++;
            }

            if (field.classList.contains("is-invalid")) {
                validateField(name);
            }

            if (name === "password" && fields.confirmpassword.value !== "") {
                validateConfirmPassword();
            }
        });
    });

    fields.rollNo.addEventListener("blur", function () {
        if (validateRollNo()) {
            checkAvailability("rollNo");
        }
    });

    fields.rollNo.addEventListener("input", function () {
        availability.rollNo = null;
        requestId.rollNo++;

        if (fields.rollNo.value.trim() === "") {
            clearField("rollNo");
        } else if (fields.rollNo.classList.contains("is-invalid")) {
            validateRollNo();
        }
    });

    ["course", "year"].forEach(function (name) {
        fields[name].addEventListener("change", function () {
            validateField(name);
        });
    });

    form.querySelectorAll('input[name="gender"]').forEach(function (radio) {
        radio.addEventListener("change", validateGender);
    });

    fields.terms.addEventListener("change", validateTerms);

    /* ------------------------------------------------------------------
     * Submit: validate locally, check duplicates, then POST to PHP
     * ------------------------------------------------------------------ */
    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        if (submitting) {
            return;
        }

        showFormMessage("", "");

        let valid = true;
        ["fullname", "rollNo", "email", "mobile", "password", "confirmpassword", "course", "year"].forEach(function (name) {
            if (!validateField(name)) {
                valid = false;
            }
        });
        if (!validateGender()) {
            valid = false;
        }
        if (!validateTerms()) {
            valid = false;
        }

        if (!valid) {
            showFormMessage("Please fix the highlighted fields and try again.", "error");
            focusFirstError();
            return;
        }

        if (availability.email === false || availability.rollNo === false) {
            showFormMessage("That email or username is already registered. Please use different details.", "error");
            focusFirstError();
            return;
        }

        submitting = true;
        const button = document.getElementById("registerButton");
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = "Checking availability...";

        try {
            const checks = [checkAvailability("email")];
            if (fields.rollNo.value.trim() !== "") {
                checks.push(checkAvailability("rollNo"));
            }
            await Promise.all(checks);

            if (availability.email === false || availability.rollNo === false) {
                showFormMessage("That email or username is already registered. Please use different details.", "error");
                focusFirstError();
                submitting = false;
                button.disabled = false;
                button.textContent = originalText;
                return;
            }

            button.textContent = "Creating account...";
            window.setTimeout(function () {
                /* Restore the button only if navigation did not happen. */
                submitting = false;
                button.disabled = false;
                button.textContent = originalText;
            }, 8000);
            form.submit();
        } catch (error) {
            /* The PHP handler performs the final duplicate check. */
            button.textContent = "Creating account...";
            form.submit();
        }
    });
})();
