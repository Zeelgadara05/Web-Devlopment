/* Profile page - renders the signed-in student's own database record */
(function () {
    const ME_API = "php/api/me.php";

    function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, character => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[character]);
    }

    function value(value) {
        return value === null || value === undefined || value === "" ? "—" : escapeHtml(value);
    }

    function initials(name) {
        const parts = String(name || "").trim().split(/\s+/).slice(0, 2);
        const letters = parts.map(part => part.charAt(0)).join("").toUpperCase();
        return letters || "ST";
    }

    function row(label, text) {
        return '<div class="profile-row">' +
            '<div class="profile-label">' + escapeHtml(label) + '</div>' +
            '<div class="profile-value">' + text + '</div>' +
            '</div>';
    }

    const message = document.getElementById("pageMessage");
    const content = document.getElementById("profileContent");

    fetch(ME_API + "?t=" + Date.now(), { cache: "no-store", headers: { "Accept": "application/json" } })
        .then(response => response.json())
        .then(payload => {
            if (!payload.success || !payload.data || !payload.data.authenticated) {
                window.location.href = "login.html?error=" + encodeURIComponent("Please log in to view your profile.");
                return;
            }

            const user = payload.data.user;

            if (user.type === "admin") {
                window.location.href = "admin_dashboard.php";
                return;
            }

            message.style.display = "none";

            const status = String(user.status || "active");
            const statusClass = status === "active" ? "present" : status;

            content.innerHTML =
                '<div class="profile-card">' +
                    '<div class="profile-header">' +
                        '<div class="profile-avatar">' + escapeHtml(initials(user.name)) + '</div>' +
                        '<h2>' + escapeHtml(user.name) + '</h2>' +
                        '<p>' + escapeHtml(user.rollNo) + ' · ' + escapeHtml(user.course || "Course not set") + '</p>' +
                        '<p style="margin-top:10px;"><span class="status-badge ' + escapeHtml(statusClass) + '">' +
                            escapeHtml(status.charAt(0).toUpperCase() + status.slice(1)) + '</span></p>' +
                    '</div>' +
                    '<div class="profile-body">' +
                        row("Roll Number", value(user.rollNo)) +
                        row("Email", value(user.email)) +
                        row("Mobile", value(user.mobile)) +
                        row("Department", value(user.department)) +
                        row("Course", value(user.course)) +
                        row("Year", value(user.year)) +
                        row("Semester", value(user.semester)) +
                        row("Gender", value(user.gender)) +
                        row("Date of Birth", value(user.dateOfBirth)) +
                        row("City", value(user.city)) +
                        row("State", value(user.state)) +
                        row("Address", value(user.address)) +
                        row("Admission Date", value(user.admissionDate)) +
                        row("Events Registered", value(user.totalEvents)) +
                        row("Last Sign-in", value(user.lastLoginAt)) +
                    '</div>' +
                '</div>';
        })
        .catch(() => {
            message.className = "page-message is-error";
            message.textContent = "Could not load your profile. Please check that MySQL is running and try again.";
        });
})();
