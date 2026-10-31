/* Results page - loads only the signed-in student's results */
(function () {
    const API = "php/api/results.php";

    function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, character => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[character]);
    }

    function percentageClass(percentage) {
        if (percentage < 40) return "low";
        if (percentage < 60) return "warn";
        return "";
    }

    const message = document.getElementById("pageMessage");
    const summary = document.getElementById("summary");
    const tableBody = document.querySelector("#resultsTable tbody");

    fetch(API + "?t=" + Date.now(), { cache: "no-store", headers: { "Accept": "application/json" } })
        .then(response => response.json().then(payload => ({ response, payload })))
        .then(({ response, payload }) => {
            if (response.status === 401) {
                window.location.href = "login.html?error=" + encodeURIComponent("Please log in to view results.");
                return;
            }
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || "Could not load results.");
            }

            const data = payload.data;
            const info = data.summary;

            message.style.display = "none";

            summary.innerHTML =
                '<article class="summary-card"><span class="summary-label">Average</span>' +
                    '<strong class="summary-value">' + info.average + '%</strong>' +
                    '<span class="summary-note">' + escapeHtml(data.student.rollNo) + '</span></article>' +
                '<article class="summary-card"><span class="summary-label">Exams</span>' +
                    '<strong class="summary-value">' + info.exams + '</strong>' +
                    '<span class="summary-note">published entries</span></article>' +
                '<article class="summary-card"><span class="summary-label">Subjects</span>' +
                    '<strong class="summary-value">' + info.subjects + '</strong>' +
                    '<span class="summary-note">semester ' + escapeHtml(data.student.semester) + '</span></article>';

            if (!data.results.length) {
                tableBody.innerHTML = '<tr><td colspan="6"><div class="empty-state">No results have been published yet.</div></td></tr>';
                return;
            }

            tableBody.innerHTML = data.results.map(result => {
                const level = percentageClass(result.percentage);
                return '<tr>' +
                    '<td><strong>' + escapeHtml(result.subjectName) + '</strong><span class="cell-sub">' + escapeHtml(result.subjectCode) + '</span></td>' +
                    '<td><span class="status-badge">' + escapeHtml(result.examLabel) + '</span></td>' +
                    '<td><strong>' + result.marks + '</strong> / ' + result.maxMarks + '</td>' +
                    '<td><span class="percentage-label ' + level + '">' + result.percentage + '%</span></td>' +
                    '<td><strong>' + escapeHtml(result.grade || "—") + '</strong></td>' +
                    '<td>' + escapeHtml(result.displayDate || "—") + '</td>' +
                '</tr>';
            }).join("");
        })
        .catch(error => {
            message.className = "page-message is-error";
            message.textContent = error.message || "Could not load results.";
        });
})();
