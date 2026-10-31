/* Attendance page - loads only the signed-in student's attendance */
(function () {
    const API = "php/api/attendance.php";

    function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, character => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[character]);
    }

    function levelClass(percentage) {
        if (percentage < 75) return "low";
        if (percentage < 85) return "warn";
        return "";
    }

    const message = document.getElementById("pageMessage");
    const summary = document.getElementById("summary");
    const subjectBody = document.querySelector("#subjectTable tbody");
    const recordBody = document.querySelector("#recordsTable tbody");

    fetch(API + "?t=" + Date.now(), { cache: "no-store", headers: { "Accept": "application/json" } })
        .then(response => response.json().then(payload => ({ response, payload })))
        .then(({ response, payload }) => {
            if (response.status === 401) {
                window.location.href = "login.html?error=" + encodeURIComponent("Please log in to view attendance.");
                return;
            }
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || "Could not load attendance.");
            }

            const data = payload.data;
            const overall = data.overall;

            message.style.display = "none";

            summary.innerHTML =
                '<article class="summary-card"><span class="summary-label">Overall</span>' +
                    '<strong class="summary-value">' + overall.percentage + '%</strong>' +
                    '<span class="summary-note">' + escapeHtml(data.student.rollNo) + '</span></article>' +
                '<article class="summary-card"><span class="summary-label">Classes held</span>' +
                    '<strong class="summary-value">' + overall.total + '</strong>' +
                    '<span class="summary-note">current semester</span></article>' +
                '<article class="summary-card"><span class="summary-label">Present</span>' +
                    '<strong class="summary-value">' + overall.present + '</strong>' +
                    '<span class="summary-note">' + overall.late + ' late · ' + overall.excused + ' excused</span></article>' +
                '<article class="summary-card"><span class="summary-label">Absent</span>' +
                    '<strong class="summary-value">' + overall.absent + '</strong>' +
                    '<span class="summary-note">please maintain 75%</span></article>';

            if (!data.subjects.length) {
                subjectBody.innerHTML = '<tr><td colspan="5"><div class="empty-state">No attendance has been recorded yet.</div></td></tr>';
            } else {
                subjectBody.innerHTML = data.subjects.map(subject => {
                    const level = levelClass(subject.percentage);
                    return '<tr>' +
                        '<td><strong>' + escapeHtml(subject.name) + '</strong><span class="cell-sub">' + escapeHtml(subject.code) + '</span></td>' +
                        '<td>' + subject.total + '</td>' +
                        '<td>' + subject.present + '</td>' +
                        '<td>' + subject.absent + '</td>' +
                        '<td style="min-width:170px;">' +
                            '<div style="display:flex;align-items:center;gap:10px;">' +
                                '<div class="progress-track"><div class="progress-fill ' + level + '" style="width:' + Math.min(100, subject.percentage) + '%"></div></div>' +
                                '<span class="percentage-label ' + level + '">' + subject.percentage + '%</span>' +
                            '</div>' +
                        '</td>' +
                    '</tr>';
                }).join("");
            }

            if (!data.records.length) {
                recordBody.innerHTML = '<tr><td colspan="4"><div class="empty-state">No individual attendance entries yet.</div></td></tr>';
            } else {
                recordBody.innerHTML = data.records.map(record =>
                    '<tr>' +
                        '<td>' + escapeHtml(record.displayDate || record.date) + '</td>' +
                        '<td><strong>' + escapeHtml(record.subjectName) + '</strong><span class="cell-sub">' + escapeHtml(record.subjectCode) + '</span></td>' +
                        '<td><span class="status-badge ' + escapeHtml(record.status) + '">' + escapeHtml(record.status) + '</span></td>' +
                        '<td>' + escapeHtml(record.remarks || "—") + '</td>' +
                    '</tr>'
                ).join("");
            }
        })
        .catch(error => {
            message.className = "page-message is-error";
            message.textContent = error.message || "Could not load attendance.";
        });
})();
