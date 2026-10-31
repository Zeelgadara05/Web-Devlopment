/* College Notices page - reads published notices from the MySQL database */
(function () {
    const noticesContainer = document.getElementById("noticeCards");
    const message = document.getElementById("message");

    if (!noticesContainer) {
        return;
    }

    function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, character => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        })[character]);
    }

    fetch("./php/api/notices.php?active=1&per_page=6&sort=date&t=" + Date.now(), {
        cache: "no-store",
        headers: { "Accept": "application/json" }
    })
        .then(response => response.json().then(payload => ({ response, payload })))
        .then(({ response, payload }) => {
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || "HTTP " + response.status);
            }

            const notices = Array.isArray(payload.data) ? payload.data : [];

            if (notices.length === 0) {
                noticesContainer.innerHTML = "";
                message.textContent = "No notices are published right now.";
                return;
            }

            message.textContent = "";

            noticesContainer.innerHTML = notices.map(notice => {
                const colour = notice.categoryColor || "#d32f2f";
                const pinned = notice.isPinned ? "📌 " : "";
                const content = escapeHtml(notice.content).replace(/\n/g, "<br>");

                return `
                    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border-left: 4px solid ${escapeHtml(colour)};">
                        <h2 style="color: ${escapeHtml(colour)}; margin-bottom: 10px;">${pinned}${escapeHtml(notice.title)}</h2>
                        <p style="color: #666; margin-bottom: 8px;"><strong>Date:</strong> ${escapeHtml(notice.displayDate || notice.date || "")}</p>
                        <p style="color: #666; margin-bottom: 8px;"><strong>Category:</strong> ${escapeHtml(notice.category)}</p>
                        <p style="color: #333; margin-top: 12px;">${content}</p>
                    </div>
                `;
            }).join("");
        })
        .catch(error => {
            console.error(error);
            message.textContent = "Could not load notices. Make sure MySQL is running in XAMPP.";
            message.style.color = "#d32f2f";
        });
})();
