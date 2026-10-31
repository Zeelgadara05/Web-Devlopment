/* Events page - loads events from MySQL and handles event registration */
const EVENTS_API = "./php/api/events.php";
const REGISTRATIONS_API = "./php/api/event_registrations.php";
const ME_API = "./php/api/me.php";

let items = [];
let page = 1;
const perPage = 6;
let totalPages = 1;
let activeEvent = null;
let loggedInStudent = null;
let myRegistrations = [];

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, character => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    })[character]);
}

function posterUrl(value) {
    const path = String(value || "").trim();
    return /^uploads\/events\/[A-Za-z0-9._-]+$/.test(path) ? path : "";
}

function formatFee(value) {
    const amount = Number(value || 0);
    return amount > 0 ? "₹" + amount.toFixed(2) : "Free";
}

function eventTime(event) {
    if (event.startTime && event.endTime) {
        return event.startTime + " – " + event.endTime;
    }
    return event.startTime || "";
}

async function loadEvents(silent) {
    const message = document.getElementById("message");

    if (!silent) {
        message.textContent = "Loading events from the database...";
        message.style.color = "#666";
    }

    try {
        const response = await fetch(EVENTS_API + "?per_page=200&sort=date&t=" + Date.now(), {
            cache: "no-store",
            headers: { "Accept": "application/json" }
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || "HTTP " + response.status);
        }

        items = Array.isArray(payload.data) ? payload.data : [];
        message.textContent = "Events loaded successfully (" + items.length + " records)";
        message.style.color = "#2e7d32";
        displayItems();
    } catch (error) {
        console.error(error);
        message.textContent = "Could not load events from the database. Make sure MySQL is running in XAMPP.";
        message.style.color = "#d32f2f";
        document.getElementById("events").innerHTML = "";
        document.getElementById("pageInfo").textContent = "Page 0 of 0";
        document.getElementById("prev").disabled = true;
        document.getElementById("next").disabled = true;
    }
}

function eventStatus(event) {
    if (event.status === "completed" || event.isPast) {
        return { label: "Completed", className: "status-closed" };
    }
    if (event.status === "cancelled") {
        return { label: "Cancelled", className: "status-cancelled" };
    }
    if (event.isFull) {
        return { label: "Event full", className: "status-full" };
    }
    if (event.deadlinePassed) {
        return { label: "Registration closed", className: "status-closed" };
    }
    if (event.canRegister) {
        return { label: "Registration open", className: "status-open" };
    }
    return { label: event.status || "Unavailable", className: "status-closed" };
}

/* Display events with search, filter, sort and pagination */
function displayItems() {
    const search = document.getElementById("search").value.toLowerCase().trim();
    const filter = document.getElementById("filter").value;
    const sort = document.getElementById("sort").value;

    // Search by name, venue, category or description
    let result = items.filter(event =>
        String(event.name).toLowerCase().includes(search) ||
        String(event.venue).toLowerCase().includes(search) ||
        String(event.category).toLowerCase().includes(search) ||
        String(event.description).toLowerCase().includes(search)
    );

    // Filter by category
    if (filter !== "All") {
        result = result.filter(event => event.category === filter);
    }

    // Sorting
    if (sort === "name") {
        result.sort((a, b) => String(a.name).localeCompare(String(b.name)));
    } else if (sort === "date") {
        result.sort((a, b) => new Date(a.date) - new Date(b.date));
    }

    // Keep the current page in range
    totalPages = Math.max(1, Math.ceil(result.length / perPage));
    if (page > totalPages) page = totalPages;

    const start = (page - 1) * perPage;
    const pageItems = result.slice(start, start + perPage);

    let output = "";

    pageItems.forEach(event => {
        const status = eventStatus(event);
        const seats = event.maxParticipants === null
            ? event.registeredCount + " registered"
            : event.registeredCount + " / " + event.maxParticipants + " registered";
        const time = eventTime(event);
        const description = escapeHtml(event.description || "").replace(/\n/g, "<br>");
        const poster = posterUrl(event.poster);

        output += `
            <article class="event-card" style="border-left-color: ${escapeHtml(event.categoryColor || "#0d47a1")}">
                ${poster ? `<img class="event-poster" src="${escapeHtml(poster)}" alt="Poster for ${escapeHtml(event.name)}">` : ""}
                <div class="event-card-header">
                    <h2>${escapeHtml(event.name)}</h2>
                    <span class="event-badge" style="background: ${escapeHtml(event.categoryColor || "#0d47a1")}">
                        ${escapeHtml(event.category)}
                    </span>
                </div>

                <p class="event-description">${description}</p>

                <div class="event-meta">
                    <p><strong>📅 Date:</strong> ${escapeHtml(event.displayDate || event.date || "")}${time ? " · " + escapeHtml(time) : ""}</p>
                    <p><strong>📍 Venue:</strong> ${escapeHtml(event.venue)}</p>
                    <p><strong>💰 Fee:</strong> ${escapeHtml(formatFee(event.registrationFee))}</p>
                    <p><strong>🎟️ Seats:</strong> ${escapeHtml(seats)}${event.seatsLeft !== null ? " · " + event.seatsLeft + " left" : ""}</p>
                </div>

                <div class="event-footer">
                    <span class="event-status ${status.className}">${status.label}</span>
                    <button type="button"
                            class="register-btn"
                            data-event-id="${event.id}"
                            ${event.canRegister ? "" : "disabled"}>
                        ${event.canRegister ? "Register" : "Registration closed"}
                    </button>
                </div>
            </article>
        `;
    });

    if (output === "") {
        output = "<h3>No events found</h3>";
    }

    document.getElementById("events").innerHTML = output;
    document.getElementById("pageInfo").textContent = "Page " + page + " of " + totalPages;
    document.getElementById("prev").disabled = page === 1;
    document.getElementById("next").disabled = page === totalPages;
}

/* ------------------------------------------------------------------
 * Registration modal
 * ------------------------------------------------------------------ */
function showRegistrationMessage(text, type) {
    const box = document.getElementById("regMessage");
    box.textContent = text;
    box.className = "reg-message " + type;
    box.style.display = "block";
}

function hideRegistrationMessage() {
    const box = document.getElementById("regMessage");
    box.style.display = "none";
    box.textContent = "";
}

function openRegistrationModal(event) {
    activeEvent = event;

    document.getElementById("regEventId").value = event.id;
    document.getElementById("modalEventName").textContent =
        event.name + " · " + (event.displayDate || event.date || "") + " · " + event.venue;

    document.getElementById("regTeamName").value = "";
    document.getElementById("regNotes").value = "";
    hideRegistrationMessage();

    const rollInput = document.getElementById("regRollNo");
    rollInput.value = loggedInStudent ? loggedInStudent.rollNo : "";
    rollInput.readOnly = Boolean(loggedInStudent);

    const modal = document.getElementById("registrationModal");
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(() => (loggedInStudent ? document.getElementById("regTeamName") : rollInput).focus(), 50);
}

function closeRegistrationModal() {
    const modal = document.getElementById("registrationModal");
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
    activeEvent = null;
}

async function submitRegistration(formEvent) {
    formEvent.preventDefault();

    if (!activeEvent) {
        return;
    }

    const submitButton = document.getElementById("regSubmit");
    const rollNo = document.getElementById("regRollNo").value.trim();
    const teamName = document.getElementById("regTeamName").value.trim();
    const notes = document.getElementById("regNotes").value.trim();

    if (rollNo === "") {
        showRegistrationMessage("Please enter your roll number.", "error");
        return;
    }

    submitButton.disabled = true;
    submitButton.textContent = "Registering...";
    hideRegistrationMessage();

    try {
        const response = await fetch(REGISTRATIONS_API, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                event_id: activeEvent.id,
                roll_no: rollNo,
                team_name: teamName,
                notes: notes
            })
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || "Registration failed.");
        }

        showRegistrationMessage(
            "✅ " + payload.message + " Keep this code safe.",
            "success"
        );

        submitButton.textContent = "Registered";
        await loadEvents(true);

        setTimeout(() => {
            closeRegistrationModal();
            submitButton.disabled = false;
            submitButton.textContent = "Confirm Registration";
        }, 2600);
    } catch (error) {
        console.error(error);
        showRegistrationMessage("❌ " + error.message, "error");
        submitButton.disabled = false;
        submitButton.textContent = "Confirm Registration";
    }
}

/* ------------------------------------------------------------------
 * Wire up the page
 * ------------------------------------------------------------------ */
document.getElementById("search").addEventListener("input", function () {
    page = 1;
    displayItems();
});

document.getElementById("filter").addEventListener("change", function () {
    page = 1;
    displayItems();
});

document.getElementById("sort").addEventListener("change", function () {
    page = 1;
    displayItems();
});

document.getElementById("prev").addEventListener("click", function () {
    if (page > 1) {
        page--;
        displayItems();
    }
});

document.getElementById("next").addEventListener("click", function () {
    if (page < totalPages) {
        page++;
        displayItems();
    }
});

document.getElementById("events").addEventListener("click", function (clickEvent) {
    const button = clickEvent.target.closest(".register-btn");
    if (!button || button.disabled) {
        return;
    }

    const eventId = Number(button.dataset.eventId);
    const event = items.find(item => item.id === eventId);
    if (event) {
        openRegistrationModal(event);
    }
});

document.getElementById("modalClose").addEventListener("click", closeRegistrationModal);

document.getElementById("registrationModal").addEventListener("click", function (clickEvent) {
    if (clickEvent.target === this) {
        closeRegistrationModal();
    }
});

document.addEventListener("keydown", function (keyEvent) {
    if (keyEvent.key === "Escape") {
        closeRegistrationModal();
    }
});

document.getElementById("registrationForm").addEventListener("submit", submitRegistration);

/* ------------------------------------------------------------------
 * Logged-in student: show their registrations
 * ------------------------------------------------------------------ */
function registrationStatusClass(status) {
    if (status === "confirmed" || status === "attended") return "reg-status-confirmed";
    if (status === "pending") return "reg-status-pending";
    if (status === "waitlisted") return "reg-status-waitlisted";
    return "reg-status-cancelled";
}

async function loadMyRegistrations() {
    if (!loggedInStudent) {
        return;
    }

    const section = document.getElementById("myRegistrations");
    const list = document.getElementById("myRegistrationsList");
    section.style.display = "block";
    list.innerHTML = '<p class="my-reg-empty">Loading your registrations...</p>';

    try {
        const response = await fetch(
            REGISTRATIONS_API + "?student_id=" + encodeURIComponent(loggedInStudent.id) + "&per_page=50&t=" + Date.now(),
            { cache: "no-store", headers: { "Accept": "application/json" } }
        );
        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || "Could not load registrations.");
        }

        myRegistrations = Array.isArray(payload.data) ? payload.data : [];
        renderMyRegistrations();
    } catch (error) {
        console.error(error);
        list.innerHTML = '<p class="my-reg-empty">Could not load your registrations right now.</p>';
    }
}

function renderMyRegistrations() {
    const list = document.getElementById("myRegistrationsList");

    if (myRegistrations.length === 0) {
        list.innerHTML = '<p class="my-reg-empty">You have not registered for any event yet. Pick an event below and click Register.</p>';
        return;
    }

    list.innerHTML = myRegistrations.map(registration => {
        const isActive = ["pending", "confirmed", "waitlisted"].includes(registration.status);
        const cancelButton = isActive
            ? `<button type="button" class="cancel-reg-btn" data-code="${escapeHtml(registration.registrationCode)}">Cancel</button>`
            : "";

        return `
            <div class="my-reg-card ${registrationStatusClass(registration.status)}">
                <div class="my-reg-title">
                    <strong>${escapeHtml(registration.eventTitle)}</strong>
                    <span class="my-reg-code">${escapeHtml(registration.registrationCode)}</span>
                </div>
                <div class="my-reg-meta">
                    ${escapeHtml(registration.eventDateLabel || registration.eventDate || "")}
                    ${registration.venue ? " · " + escapeHtml(registration.venue) : ""}
                </div>
                <div class="my-reg-footer">
                    <span class="reg-status ${registrationStatusClass(registration.status)}">${escapeHtml(registration.status)}</span>
                    ${cancelButton}
                </div>
            </div>
        `;
    }).join("");
}

document.getElementById("myRegistrationsList").addEventListener("click", async function (clickEvent) {
    const button = clickEvent.target.closest(".cancel-reg-btn");
    if (!button || !loggedInStudent) {
        return;
    }

    if (!window.confirm("Cancel registration " + button.dataset.code + "?")) {
        return;
    }

    button.disabled = true;
    button.textContent = "Cancelling...";

    try {
        const response = await fetch(REGISTRATIONS_API, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                action: "cancel",
                registration_code: button.dataset.code,
                student_id: loggedInStudent.id
            })
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || "Could not cancel the registration.");
        }

        await loadMyRegistrations();
        await loadEvents(true);
    } catch (error) {
        console.error(error);
        window.alert(error.message);
        button.disabled = false;
        button.textContent = "Cancel";
    }
});

/* Pre-fill the roll number when a student is logged in. */
(async function loadSession() {
    try {
        const response = await fetch(ME_API + "?t=" + Date.now(), { cache: "no-store" });
        const payload = await response.json();

        if (payload.success && payload.data && payload.data.authenticated) {
            const user = payload.data.user;
            if (user.type === "student") {
                loggedInStudent = user;
                await loadMyRegistrations();
            }
        }
    } catch (error) {
        /* Registration still works by typing the roll number manually. */
    }
})();

loadEvents();
