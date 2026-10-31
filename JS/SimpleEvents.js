/* Simple Events CRUD demo - all data is loaded and saved with fetch().
 * The page never submits a form to the server, so it never reloads.
 */
const SIMPLE_EVENTS_API = "./php/api/simple_events.php";

const state = {
    items: [],
    editingId: 0,
    busy: false,
    flashTimer: null
};

const STATUS_META = {
    scheduled: { label: "Scheduled", className: "status-scheduled" },
    completed: { label: "Completed", className: "status-completed" },
    cancelled: { label: "Cancelled", className: "status-cancelled" }
};

const $ = id => document.getElementById(id);

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, character => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    })[character]);
}

function statusMeta(value) {
    return STATUS_META[String(value || "").toLowerCase()] || {
        label: String(value || "Unknown"),
        className: "status-unknown"
    };
}

function setFlash(message, type) {
    const box = $("flash");
    const text = $("flashText");

    text.textContent = message;
    box.className = "flash flash-" + (type === "error" ? "error" : "success");
    box.hidden = false;

    clearTimeout(state.flashTimer);
    state.flashTimer = setTimeout(() => {
        box.hidden = true;
    }, 6000);
}

function hideFlash() {
    $("flash").hidden = true;
}

async function apiRequest(options = {}) {
    const response = await fetch(SIMPLE_EVENTS_API, {
        cache: "no-store",
        ...options,
        headers: {
            Accept: "application/json",
            ...(options.headers || {})
        }
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch (error) {
        throw new Error("The server returned an unexpected response (" + response.status + ").");
    }

    if (!response.ok || !payload.success) {
        const failure = new Error(payload.message || ("HTTP " + response.status));
        failure.payload = payload;
        failure.status = response.status;
        throw failure;
    }

    return payload;
}

function setBusy(busy) {
    state.busy = busy;
    $("saveEvent").disabled = busy;
    $("refresh").disabled = busy;

    document.querySelectorAll("#eventsBody button").forEach(button => {
        button.disabled = busy;
    });
}

/* ------------------------------------------------------------------
 * List
 * ------------------------------------------------------------------ */
async function loadEvents() {
    $("loading").hidden = false;
    $("tableWrap").hidden = true;
    $("emptyState").hidden = true;

    try {
        const payload = await apiRequest();
        state.items = Array.isArray(payload.data) ? payload.data : [];
        renderEvents();
    } catch (error) {
        console.error(error);
        state.items = [];
        renderEvents();
        setFlash("Could not load events: " + error.message, "error");
    }
}

function renderEvents() {
    const body = $("eventsBody");
    const loading = $("loading");
    const wrap = $("tableWrap");
    const empty = $("emptyState");

    loading.hidden = true;

    if (state.items.length === 0) {
        body.innerHTML = "";
        wrap.hidden = true;
        empty.hidden = false;
        $("resultCount").textContent = "0 records";
        return;
    }

    empty.hidden = true;
    wrap.hidden = false;

    body.innerHTML = state.items.map(event => {
        const status = statusMeta(event.status);
        const description = event.description
            ? `<span class="row-description">${escapeHtml(event.description)}</span>`
            : "";

        return `
            <tr>
                <td data-label="Event">
                    <strong>${escapeHtml(event.title)}</strong>
                    ${description}
                </td>
                <td data-label="Venue">${escapeHtml(event.venue)}</td>
                <td data-label="Date">${escapeHtml(event.displayDate || event.eventDate)}</td>
                <td data-label="Status">
                    <span class="status-pill ${status.className}">${escapeHtml(status.label)}</span>
                </td>
                <td data-label="Actions" class="actions-col">
                    <div class="row-actions">
                        <button type="button" class="btn btn-ghost btn-small" data-action="edit" data-id="${event.id}">Edit</button>
                        <button type="button" class="btn btn-danger-ghost btn-small" data-action="delete" data-id="${event.id}">Delete</button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");

    $("resultCount").textContent = state.items.length + (state.items.length === 1 ? " record" : " records");
}

/* ------------------------------------------------------------------
 * Add / edit form
 * ------------------------------------------------------------------ */
function clearErrors() {
    document.querySelectorAll("[data-error-for]").forEach(box => {
        box.textContent = "";
    });

    $("eventForm").querySelectorAll(".invalid").forEach(field => {
        field.classList.remove("invalid");
    });

    $("formAlert").hidden = true;
    $("formAlert").textContent = "";
}

function showErrors(errors) {
    let firstMessage = "";

    Object.entries(errors).forEach(([field, message]) => {
        const text = Array.isArray(message) ? String(message[0]) : String(message);
        const hint = document.querySelector(`[data-error-for="${field}"]`);
        const input = $("eventForm").elements.namedItem(field);

        if (hint) {
            hint.textContent = text;
        }
        if (input && input.classList) {
            input.classList.add("invalid");
        }
        if (firstMessage === "") {
            firstMessage = text;
        }
    });

    const alert = $("formAlert");
    alert.textContent = firstMessage || "Please correct the highlighted fields.";
    alert.hidden = false;
    alert.scrollIntoView({ block: "nearest" });
}

function resetForm() {
    $("eventForm").reset();
    $("eventId").value = "";
    $("formAction").value = "create";
    $("formTitle").textContent = "Add event";
    $("formHint").textContent = "Create a record with the JSON API.";
    $("formMode").textContent = "NEW";
    $("saveEvent").textContent = "Add event";
    $("cancelEdit").hidden = true;
    state.editingId = 0;
    clearErrors();
}

function startEdit(id) {
    const event = state.items.find(item => Number(item.id) === Number(id));
    if (!event) {
        return;
    }

    clearErrors();
    state.editingId = Number(event.id);

    $("eventId").value = String(event.id);
    $("formAction").value = "update";
    $("title").value = event.title || "";
    $("venue").value = event.venue || "";
    $("eventDate").value = event.eventDate || "";
    $("status").value = event.status || "scheduled";
    $("description").value = event.description || "";

    $("formTitle").textContent = "Edit event";
    $("formHint").textContent = "Update record #" + event.id + " with the JSON API.";
    $("formMode").textContent = "EDIT";
    $("saveEvent").textContent = "Update event";
    $("cancelEdit").hidden = false;

    $("title").focus();
    $("title").scrollIntoView({ behavior: "smooth", block: "center" });
}

function validateClient(data) {
    const errors = {};

    if (data.title.length < 3 || data.title.length > 150) {
        errors.title = "Event title must be between 3 and 150 characters.";
    }
    if (data.venue === "") {
        errors.venue = "Venue is required.";
    }
    if (data.event_date === "") {
        errors.event_date = "Event date is required.";
    }
    if (!["scheduled", "completed", "cancelled"].includes(data.status)) {
        errors.status = "Please select a valid status.";
    }
    if (data.description.length > 1000) {
        errors.description = "Description may not exceed 1000 characters.";
    }

    return errors;
}

async function submitForm(submitEvent) {
    submitEvent.preventDefault();

    if (state.busy) {
        return;
    }

    clearErrors();

    const editing = state.editingId > 0;
    const data = {
        action: editing ? "update" : "create",
        title: $("title").value.trim(),
        venue: $("venue").value.trim(),
        event_date: $("eventDate").value,
        status: $("status").value,
        description: $("description").value.trim()
    };

    if (editing) {
        data.id = state.editingId;
    }

    const errors = validateClient(data);
    if (Object.keys(errors).length > 0) {
        showErrors(errors);
        return;
    }

    setBusy(true);
    $("saveEvent").textContent = editing ? "Updating…" : "Saving…";

    try {
        const payload = await apiRequest({
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });

        setFlash(payload.message || "Event saved.", "success");
        resetForm();
        await loadEvents();
    } catch (error) {
        console.error(error);

        if (error.payload && error.payload.errors) {
            showErrors(error.payload.errors);
        } else {
            const alert = $("formAlert");
            alert.textContent = error.message;
            alert.hidden = false;
        }
    } finally {
        setBusy(false);
        $("saveEvent").textContent = state.editingId > 0 ? "Update event" : "Add event";
    }
}

/* ------------------------------------------------------------------
 * Delete
 * ------------------------------------------------------------------ */
async function deleteEvent(id) {
    if (state.busy) {
        return;
    }

    const event = state.items.find(item => Number(item.id) === Number(id));
    if (!event) {
        return;
    }

    if (!window.confirm(`Delete "${event.title}"? This cannot be undone.`)) {
        return;
    }

    setBusy(true);

    try {
        const payload = await apiRequest({
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "delete", id: event.id })
        });

        setFlash(payload.message || "Event deleted.", "success");

        if (state.editingId === Number(event.id)) {
            resetForm();
        }

        await loadEvents();
    } catch (error) {
        console.error(error);
        setFlash("Could not delete the event: " + error.message, "error");
    } finally {
        setBusy(false);
    }
}

/* ------------------------------------------------------------------
 * Wiring
 * ------------------------------------------------------------------ */
$("eventForm").addEventListener("submit", submitForm);
$("cancelEdit").addEventListener("click", resetForm);
$("refresh").addEventListener("click", loadEvents);
$("flashClose").addEventListener("click", hideFlash);

$("eventsBody").addEventListener("click", function (clickEvent) {
    const button = clickEvent.target.closest("button[data-action]");
    if (!button) {
        return;
    }

    if (button.dataset.action === "edit") {
        startEdit(button.dataset.id);
    } else if (button.dataset.action === "delete") {
        deleteEvent(button.dataset.id);
    }
});

/* Start the page without a full reload. */
loadEvents();
