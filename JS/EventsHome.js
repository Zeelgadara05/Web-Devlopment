/* Upcoming Events page - reads published events from the MySQL database */
(function () {
    const eventsContainer = document.getElementById("eventCards");
    const message = document.getElementById("message");

    if (!eventsContainer) {
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

    fetch("./php/api/events.php?upcoming=1&per_page=6&sort=date&t=" + Date.now(), {
        cache: "no-store",
        headers: { "Accept": "application/json" }
    })
        .then(response => response.json().then(payload => ({ response, payload })))
        .then(({ response, payload }) => {
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || "HTTP " + response.status);
            }

            const events = Array.isArray(payload.data) ? payload.data : [];

            if (events.length === 0) {
                eventsContainer.innerHTML = "";
                message.textContent = "No upcoming events right now. Check the All Events page for past events.";
                return;
            }

            message.textContent = "";

            eventsContainer.innerHTML = events.map(event => {
                const colour = event.categoryColor || "#1a237e";
                const fee = Number(event.registrationFee || 0);
                const feeLabel = fee > 0 ? "₹" + fee.toFixed(2) : "Free";
                const seats = event.seatsLeft === null
                    ? event.registeredCount + " registered"
                    : event.seatsLeft + " seats left";

                return `
                    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border-top: 4px solid ${escapeHtml(colour)};">
                        <h2 style="color: ${escapeHtml(colour)}; margin-bottom: 15px;">${escapeHtml(event.name)}</h2>
                        <p style="margin-bottom: 10px;"><strong>Date:</strong> ${escapeHtml(event.displayDate || event.date || "")}</p>
                        <p style="margin-bottom: 10px;"><strong>Venue:</strong> ${escapeHtml(event.venue)}</p>
                        <p style="margin-bottom: 10px;"><strong>Category:</strong> ${escapeHtml(event.category)}</p>
                        <p style="margin-bottom: 10px;"><strong>Fee:</strong> ${escapeHtml(feeLabel)} · ${escapeHtml(seats)}</p>
                        <p style="color: #333; margin-top: 15px;">${escapeHtml(event.description || "")}</p>
                        <p style="margin-top: 15px;"><a href="EventsList.html" style="color: ${escapeHtml(colour)}; font-weight: bold;">View all events &amp; register →</a></p>
                    </div>
                `;
            }).join("");
        })
        .catch(error => {
            console.error(error);
            message.textContent = "Could not load upcoming events. Make sure MySQL is running in XAMPP.";
            message.style.color = "#d32f2f";
        });
})();
