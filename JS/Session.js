/* Session-aware navigation
 * - shows Dashboard + Logout for a signed-in user
 * - hides links the current role may not open (server-side rules still apply)
 */
(function () {
    const nav = document.getElementById("authNav");

    const staffOnlyPages = ["students.html", "manageevents.html", "simpleevents.html"];
    const studentOnlyPages = ["profile.html", "attendance.html", "result.html", "tt.html"];
    const publicOnlyPages = ["registration.html"];

    function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, character => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[character]);
    }

    function pageOf(link) {
        return String(link.getAttribute("href") || "")
            .split("?")[0]
            .split("#")[0]
            .split("/")
            .pop()
            .toLowerCase();
    }

    function applyRoleVisibility(user) {
        const isStudent = user.type === "student";
        const isStaff = user.type === "admin";

        document.querySelectorAll("a[href]").forEach(link => {
            const page = pageOf(link);

            if ((isStudent && staffOnlyPages.includes(page)) ||
                (isStaff && studentOnlyPages.includes(page)) ||
                publicOnlyPages.includes(page)) {
                const item = link.closest("li");
                if (item) {
                    item.style.display = "none";
                } else {
                    link.style.display = "none";
                }
            }
        });
    }

    fetch("php/api/me.php?t=" + Date.now(), { cache: "no-store" })
        .then(response => response.json())
        .then(payload => {
            if (!payload.success || !payload.data || !payload.data.authenticated) {
                return;
            }

            const user = payload.data.user;
            const firstName = String(user.name || "").split(" ")[0] || "Account";
            const roleLabel = user.type === "admin"
                ? (String(user.role || "staff").replace("_", " "))
                : "student";

            applyRoleVisibility(user);

            if (!nav) {
                return;
            }

            nav.innerHTML =
                '<a class="nav-dashboard" href="dashboard.php" title="' + escapeHtml(roleLabel) + '">Dashboard</a>' +
                '<a class="nav-logout" href="php/logout.php" title="' + escapeHtml(user.email) + '">' +
                "Logout (" + escapeHtml(firstName) + ")</a>";
        })
        .catch(() => {
            /* Keep the normal Login link if the session check fails. */
        });
})();
