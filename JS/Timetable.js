/* Timetable page - renders the signed-in student's class schedule from MySQL */
(function () {
    const API = "php/api/timetable.php";

    function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, character => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[character]);
    }

    const message = document.getElementById("pageMessage");
    const container = document.getElementById("timetable");

    fetch(API + "?t=" + Date.now(), { cache: "no-store", headers: { "Accept": "application/json" } })
        .then(response => response.json().then(payload => ({ response, payload })))
        .then(({ response, payload }) => {
            if (response.status === 401) {
                window.location.href = "login.html?error=" + encodeURIComponent("Please log in to view your timetable.");
                return;
            }
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || "Could not load the timetable.");
            }

            const data = payload.data;
            const student = data.student;

            message.className = "page-message";
            message.innerHTML = 'Class timetable for <strong>' + escapeHtml(student.rollNo) + '</strong>' +
                (student.department ? ' · ' + escapeHtml(student.department) : '') +
                ' · Semester ' + escapeHtml(student.semester);
            message.style.display = "block";

            if (!data.days.length) {
                container.innerHTML = '<div class="records-shell"><div class="empty-state">' +
                    'No timetable has been configured for your department and semester yet.' +
                    '</div></div>';
                return;
            }

            container.innerHTML = '<div class="records-shell">' + data.days.map(day => {
                const slots = day.slots.map(slot =>
                    '<li class="slot-row">' +
                        '<div class="slot-time">' + escapeHtml(slot.startTime) + ' – ' + escapeHtml(slot.endTime) + '</div>' +
                        '<div class="slot-title">' + escapeHtml(slot.title) +
                            (slot.subjectCode ? '<small>' + escapeHtml(slot.subjectCode) + '</small>' : '') +
                        '</div>' +
                        '<div class="slot-meta">' +
                            (slot.room ? '<span>📍 ' + escapeHtml(slot.room) + '</span>' : '') +
                            (slot.faculty ? '<span>👩‍🏫 ' + escapeHtml(slot.faculty) + '</span>' : '') +
                        '</div>' +
                    '</li>'
                ).join("");

                return '<section class="day-block">' +
                    '<div class="day-heading">' + escapeHtml(day.dayName) +
                        '<span>' + day.slots.length + ' class' + (day.slots.length === 1 ? '' : 'es') + '</span>' +
                    '</div>' +
                    '<ul class="slot-list">' + slots + '</ul>' +
                '</section>';
            }).join("") + '</div>';
        })
        .catch(error => {
            message.className = "page-message is-error";
            message.textContent = error.message || "Could not load the timetable.";
        });
})();
