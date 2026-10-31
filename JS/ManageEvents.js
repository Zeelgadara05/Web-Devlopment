/* Event management page - staff-only add, view, update, delete and poster upload.
 * All data comes from the MySQL database through php/api/events.php.
 */
const EVENTS_API = "./php/api/events.php";
const DEFAULT_POSTER_MAX_BYTES = 2 * 1024 * 1024;

const state = {
    q: "",
    categoryId: "",
    status: "",
    sort: "newest",
    page: 1,
    perPage: 10,
    total: 0,
    totalPages: 1,
    items: [],
    lookups: { categories: [], statuses: [], poster: {} },
    editingId: 0,
    currentPoster: "",
    posterObjectUrl: "",
    pendingDelete: null,
    saving: false,
    flashTimer: null,
    searchTimer: null
};

const STATUS_META = {
    published: { label: "Published", className: "status-published" },
    draft: { label: "Draft", className: "status-draft" },
    cancelled: { label: "Cancelled", className: "status-cancelled" },
    completed: { label: "Completed", className: "status-completed" }
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

function safeColor(value) {
    const color = String(value || "").trim();
    return /^#[0-9a-fA-F]{3,8}$/.test(color) ? color : "#0d47a1";
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
    return event.startTime || event.endTime || "Time not set";
}

function statusMeta(status) {
    return STATUS_META[String(status || "").toLowerCase()] || {
        label: String(status || "Unknown"),
        className: "status-unknown"
    };
}

function setFlash(message, type) {
    const box = $("flash");
    const text = $("flashText");

    text.textContent = message;
    box.className = "flash flash-" + (type === "error" ? "error" : "success");
    box.hidden = false;

    if (state.flashTimer) {
        clearTimeout(state.flashTimer);
    }

    state.flashTimer = setTimeout(() => {
        box.hidden = true;
    }, 7000);
}

function hideFlash() {
    $("flash").hidden = true;
}

async function apiRequest(url, options) {
    const response = await fetch(url, {
        cache: "no-store",
        ...options,
        headers: {
            Accept: "application/json",
            ...(options && options.headers ? options.headers : {})
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

/* ------------------------------------------------------------------
 * Lookups and event list
 * ------------------------------------------------------------------ */
async function loadLookups() {
    try {
        const payload = await apiRequest(EVENTS_API + "?action=lookups&t=" + Date.now());
        state.lookups = payload.data || { categories: [], statuses: [], poster: {} };

        const categories = Array.isArray(state.lookups.categories) ? state.lookups.categories : [];
        const options = categories
            .map(category => `<option value="${category.id}">${escapeHtml(category.name)}</option>`)
            .join("");

        const formSelect = $("categoryId");
        const currentValue = formSelect.value;
        formSelect.innerHTML = '<option value="">Select a category</option>' + options;
        formSelect.value = currentValue;

        const filterSelect = $("filterCategory");
        filterSelect.innerHTML = '<option value="">All categories</option>' + options;
        filterSelect.value = state.categoryId;

        const poster = state.lookups.poster || {};
        const maxLabel = poster.maxLabel || "2 MB";
        $("posterRules").textContent = "JPG, PNG or WebP · maximum " + maxLabel + ".";
    } catch (error) {
        console.error(error);
        setFlash("Could not load the event form options: " + error.message, "error");
    }
}

async function loadEvents(silent) {
    const loading = $("loading");
    const list = $("listWrap");
    const empty = $("emptyState");

    if (!silent) {
        loading.hidden = false;
        list.hidden = true;
        empty.hidden = true;
    }

    const parameters = new URLSearchParams({
        page: String(state.page),
        per_page: String(state.perPage),
        sort: state.sort
    });

    if (state.q !== "") parameters.set("q", state.q);
    if (state.categoryId !== "") parameters.set("category_id", state.categoryId);
    if (state.status !== "") parameters.set("status", state.status);
    parameters.set("t", String(Date.now()));

    try {
        const response = await fetch(EVENTS_API + "?" + parameters.toString(), {
            cache: "no-store",
            headers: { Accept: "application/json" }
        });

        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.message || ("HTTP " + response.status));
        }

        state.items = Array.isArray(payload.data) ? payload.data : [];
        state.total = Number(payload.meta && payload.meta.total ? payload.meta.total : state.items.length);
        state.totalPages = Math.max(1, Number(payload.meta && payload.meta.total_pages ? payload.meta.total_pages : 1));

        /* If the last row on a page was deleted, move back one page. */
        if (state.items.length === 0 && state.total > 0 && state.page > 1) {
            state.page -= 1;
            return loadEvents(silent);
        }

        if (state.page > state.totalPages) {
            state.page = state.totalPages;
        }

        renderEvents();
    } catch (error) {
        console.error(error);
        state.items = [];
        state.total = 0;
        state.totalPages = 1;

        loading.hidden = true;
        list.hidden = true;
        empty.hidden = false;
        empty.querySelector("h3").textContent = "Could not load events";
        empty.querySelector("p").textContent = error.message;
        empty.querySelector("button").hidden = true;
        $("resultCount").textContent = "The event list is unavailable.";
        $("listBadge").textContent = "0 records";
        $("pagination").hidden = true;
        setFlash("Could not load events: " + error.message, "error");
    }
}

function renderEvents() {
    const body = $("eventsBody");
    const loading = $("loading");
    const list = $("listWrap");
    const empty = $("emptyState");
    const pagination = $("pagination");

    loading.hidden = true;

    if (state.items.length === 0) {
        list.hidden = true;
        empty.hidden = false;
        empty.querySelector("h3").textContent = "No events found";
        empty.querySelector("p").textContent = "Change the search or filters, or create the first event.";
        empty.querySelector("button").hidden = false;
        pagination.hidden = true;
        $("resultCount").textContent = "No events match the current view.";
        $("listBadge").textContent = "0 records";
        body.innerHTML = "";
        return;
    }

    empty.hidden = true;
    list.hidden = false;
    pagination.hidden = false;

    body.innerHTML = state.items.map(event => {
        const status = statusMeta(event.status);
        const color = safeColor(event.categoryColor);
        const poster = posterUrl(event.poster);
        const thumbnail = poster
            ? `<img class="event-thumb" src="${escapeHtml(poster)}" alt="">`
            : `<span class="event-thumb event-thumb-empty" aria-hidden="true">🗓️</span>`;
        const seats = event.maxParticipants === null
            ? `${event.registeredCount} registered`
            : `${event.registeredCount} / ${event.maxParticipants}`;
        const seatsNote = event.seatsLeft === null ? "Unlimited seats" : `${event.seatsLeft} left`;
        const featured = event.isFeatured ? '<span class="featured-pill">★ Featured</span>' : "";

        return `
            <tr>
                <td data-label="Event">
                    <div class="event-cell">
                        ${thumbnail}
                        <div class="event-copy">
                            <strong>${escapeHtml(event.title)}</strong>
                            <span class="slug">${escapeHtml(event.slug)}</span>
                            ${featured}
                        </div>
                    </div>
                </td>
                <td data-label="Category">
                    <span class="category-pill" style="--pill-color: ${escapeHtml(color)}">
                        ${escapeHtml(event.category || "General")}
                    </span>
                </td>
                <td data-label="Date & time">
                    <span>${escapeHtml(event.displayDate || event.date || "—")}</span>
                    <span class="cell-sub">${escapeHtml(eventTime(event))}</span>
                </td>
                <td data-label="Venue">${escapeHtml(event.venue || "—")}</td>
                <td data-label="Status">
                    <span class="status-pill ${status.className}">${escapeHtml(status.label)}</span>
                </td>
                <td data-label="Seats">
                    <span>${escapeHtml(seats)}</span>
                    <span class="cell-sub">${escapeHtml(seatsNote)}</span>
                </td>
                <td data-label="Actions" class="actions-col">
                    <div class="row-actions">
                        <button type="button" class="icon-btn" data-action="view" data-id="${event.id}" title="View event" aria-label="View ${escapeHtml(event.title)}">👁️</button>
                        <button type="button" class="icon-btn" data-action="edit" data-id="${event.id}" title="Edit event" aria-label="Edit ${escapeHtml(event.title)}">✏️</button>
                        <button type="button" class="icon-btn icon-btn-danger" data-action="delete" data-id="${event.id}" title="Delete event" aria-label="Delete ${escapeHtml(event.title)}">🗑️</button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");

    const first = (state.page - 1) * state.perPage + 1;
    const last = Math.min(state.page * state.perPage, state.total);
    $("resultCount").textContent = `Showing ${first}–${last} of ${state.total} event${state.total === 1 ? "" : "s"}.`;
    $("listBadge").textContent = state.total + (state.total === 1 ? " record" : " records");
    $("pageInfo").textContent = `Page ${state.page} of ${state.totalPages}`;
    $("prev").disabled = state.page <= 1;
    $("next").disabled = state.page >= state.totalPages;
}

/* ------------------------------------------------------------------
 * Add / edit form
 * ------------------------------------------------------------------ */
function openModal(id) {
    const modal = $(id);
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function closeModal(id) {
    const modal = $(id);
    if (!modal) return;

    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");

    if (id === "eventModal") {
        releasePosterPreview();
    }

    if (!document.querySelector(".modal.open")) {
        document.body.classList.remove("modal-open");
    }
}

function releasePosterPreview() {
    if (state.posterObjectUrl) {
        URL.revokeObjectURL(state.posterObjectUrl);
        state.posterObjectUrl = "";
    }
}

function setPosterPreview(src, label) {
    const preview = $("posterPreview");

    if (src) {
        preview.classList.remove("is-empty");
        preview.innerHTML = `<img src="${escapeHtml(src)}" alt="Event poster preview">`;
    } else {
        preview.classList.add("is-empty");
        preview.innerHTML = `<span>${escapeHtml(label || "No poster selected")}</span>`;
    }
}

function clearFormErrors() {
    const form = $("eventForm");
    form.querySelectorAll("[data-error-for]").forEach(box => {
        box.textContent = "";
    });
    form.querySelectorAll(".invalid").forEach(field => {
        field.classList.remove("invalid");
    });
    $("formAlert").hidden = true;
    $("formAlert").textContent = "";
}

function showFormErrors(errors) {
    const form = $("eventForm");
    let first = "";

    Object.entries(errors).forEach(([field, message]) => {
        const text = Array.isArray(message) ? String(message[0]) : String(message);
        const box = form.querySelector(`[data-error-for="${field}"]`);

        if (box) {
            box.textContent = text;
        }

        const control = form.elements.namedItem(field);
        if (control && control.classList) {
            control.classList.add("invalid");
        }

        if (first === "") {
            first = text;
        }
    });

    const alert = $("formAlert");
    alert.textContent = first || "Please correct the highlighted fields.";
    alert.hidden = false;
    alert.scrollIntoView({ block: "nearest" });
}

function showFormAlert(message) {
    const alert = $("formAlert");
    alert.textContent = message;
    alert.hidden = false;
    alert.scrollIntoView({ block: "nearest" });
}

function openCreateForm() {
    resetEventForm();
    $("formTitle").textContent = "Add event";
    $("formSubtitle").textContent = "Create a new event and upload an optional poster.";
    $("formAction").value = "create";
    $("eventId").value = "";
    $("saveEvent").textContent = "Save event";
    $("removePosterRow").hidden = true;
    setPosterPreview("", "No poster selected");
    openModal("eventModal");
    setTimeout(() => $("title").focus(), 60);
}

function openEditForm(event) {
    resetEventForm();

    state.editingId = Number(event.id);
    state.currentPoster = posterUrl(event.poster);

    $("formTitle").textContent = "Edit event";
    $("formSubtitle").textContent = "Update the event details or replace the poster.";
    $("formAction").value = "update";
    $("eventId").value = String(event.id);
    $("saveEvent").textContent = "Update event";

    $("title").value = event.title || "";
    $("description").value = event.description || "";
    $("categoryId").value = event.categoryId ? String(event.categoryId) : "";
    $("status").value = event.status || "published";
    $("eventDate").value = event.date || "";
    $("venue").value = event.venue || "";
    $("startTime").value = event.startTimeRaw || "";
    $("endTime").value = event.endTimeRaw || "";
    $("registrationDeadline").value = event.registrationDeadlineRaw || "";
    $("maxParticipants").value = event.maxParticipants === null ? "" : String(event.maxParticipants);
    $("registrationFee").value = Number(event.registrationFee || 0).toFixed(2);
    $("organizerName").value = event.organizer || "";
    $("contactEmail").value = event.contactEmail || "";
    $("contactPhone").value = event.contactPhone || "";
    $("isFeatured").checked = Boolean(event.isFeatured);
    $("removePoster").checked = false;
    $("poster").value = "";

    if (state.currentPoster) {
        setPosterPreview(state.currentPoster, "");
        $("removePosterRow").hidden = false;
    } else {
        setPosterPreview("", "No poster selected");
        $("removePosterRow").hidden = true;
    }

    openModal("eventModal");
    setTimeout(() => $("title").focus(), 60);
}

function resetEventForm() {
    releasePosterPreview();
    const form = $("eventForm");
    form.reset();
    clearFormErrors();
    state.editingId = 0;
    state.currentPoster = "";
    $("removePosterRow").hidden = true;
}

function validatePosterFile(file) {
    const poster = state.lookups.poster || {};
    const maxBytes = Number(poster.maxBytes || DEFAULT_POSTER_MAX_BYTES);
    const maxLabel = poster.maxLabel || "2 MB";
    const allowedExtensions = Array.isArray(poster.allowedExtensions)
        ? poster.allowedExtensions
        : ["jpg", "jpeg", "png", "webp"];
    const allowedTypes = ["image/jpeg", "image/png", "image/webp"];

    if (!file) {
        return "";
    }

    const extension = String(file.name || "").split(".").pop().toLowerCase();
    if (!allowedExtensions.includes(extension)) {
        return "Poster must be a JPG, PNG or WebP image.";
    }

    if (file.type && !allowedTypes.includes(file.type)) {
        return "Poster must be a JPG, PNG or WebP image.";
    }

    if (file.size > maxBytes) {
        return "Poster is too large. Maximum file size is " + maxLabel + ".";
    }

    if (file.size <= 0) {
        return "The selected poster file is empty.";
    }

    return "";
}

function clientValidation() {
    const errors = {};

    const title = $("title").value.trim();
    if (title.length < 3 || title.length > 150) {
        errors.title = "Event title must be between 3 and 150 characters.";
    }

    if ($("categoryId").value === "") {
        errors.category_id = "Please select an event category.";
    }

    if (!["published", "draft", "cancelled", "completed"].includes($("status").value)) {
        errors.status = "Please select a valid event status.";
    }

    if ($("description").value.trim().length > 5000) {
        errors.description = "Description may not exceed 5000 characters.";
    }

    const eventDate = $("eventDate").value;
    if (eventDate === "") {
        errors.event_date = "Event date is required.";
    }

    if ($("venue").value.trim() === "") {
        errors.venue = "Venue is required.";
    } else if ($("venue").value.trim().length > 150) {
        errors.venue = "Venue may not exceed 150 characters.";
    }

    const start = $("startTime").value;
    const end = $("endTime").value;
    if (start !== "" && end !== "" && end <= start) {
        errors.end_time = "End time must be later than the start time.";
    }

    const deadline = $("registrationDeadline").value;
    if (deadline !== "" && eventDate !== "" && deadline.slice(0, 10) > eventDate) {
        errors.registration_deadline = "Registration deadline cannot be after the event date.";
    }

    const participants = $("maxParticipants").value.trim();
    if (participants !== "" && (!/^\d+$/.test(participants) || Number(participants) < 1 || Number(participants) > 100000)) {
        errors.max_participants = "Maximum participants must be a whole number between 1 and 100000.";
    }

    const fee = $("registrationFee").value.trim() || "0";
    if (!/^\d{1,8}(\.\d{1,2})?$/.test(fee)) {
        errors.registration_fee = "Registration fee must be a valid amount with up to 2 decimal places.";
    }

    if ($("organizerName").value.trim().length > 100) {
        errors.organizer_name = "Organizer name may not exceed 100 characters.";
    }

    const email = $("contactEmail").value.trim();
    if (email !== "" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.contact_email = "Please enter a valid contact email address.";
    }

    const phone = $("contactPhone").value.trim();
    if (phone !== "" && !/^[6-9]\d{9}$/.test(phone)) {
        errors.contact_phone = "Contact phone must be 10 digits starting with 6-9.";
    }

    const posterError = validatePosterFile($("poster").files[0]);
    if (posterError !== "") {
        errors.poster = posterError;
    }

    return errors;
}

async function submitEventForm(formEvent) {
    formEvent.preventDefault();

    if (state.saving) {
        return;
    }

    clearFormErrors();

    const errors = clientValidation();
    if (Object.keys(errors).length > 0) {
        showFormErrors(errors);
        return;
    }

    const form = $("eventForm");
    const editing = $("formAction").value === "update";
    const button = $("saveEvent");
    const body = new FormData(form);

    body.set("action", editing ? "update" : "create");
    if (editing) {
        body.set("id", String(state.editingId));
    }

    state.saving = true;
    button.disabled = true;
    button.textContent = editing ? "Updating…" : "Saving…";

    try {
        const payload = await apiRequest(EVENTS_API, { method: "POST", body: body });
        closeModal("eventModal");
        setFlash(payload.message || "Event saved successfully.", "success");
        await loadEvents(true);
    } catch (error) {
        console.error(error);

        if (error.payload && error.payload.errors) {
            showFormErrors(error.payload.errors);
        } else {
            showFormAlert(error.message);
        }
    } finally {
        state.saving = false;
        button.disabled = false;
        button.textContent = editing ? "Update event" : "Save event";
    }
}

/* ------------------------------------------------------------------
 * View / delete
 * ------------------------------------------------------------------ */
function openView(event) {
    const status = statusMeta(event.status);
    const poster = posterUrl(event.poster);
    const categoryColor = safeColor(event.categoryColor);
    const image = poster
        ? `<img class="view-poster" src="${escapeHtml(poster)}" alt="Poster for ${escapeHtml(event.title)}">`
        : `<div class="view-poster view-poster-empty"><span>No poster uploaded</span></div>`;
    const seats = event.maxParticipants === null
        ? `${event.registeredCount} registered · unlimited seats`
        : `${event.registeredCount} registered · ${event.seatsLeft} of ${event.maxParticipants} seats left`;
    const featured = event.isFeatured ? '<span class="featured-pill">★ Featured</span>' : "";

    $("viewBody").innerHTML = `
        <div class="view-heading">
            <div>
                <span class="category-pill" style="--pill-color: ${escapeHtml(categoryColor)}">${escapeHtml(event.category || "General")}</span>
                <h2 id="viewTitle">${escapeHtml(event.title)}</h2>
                <p class="modal-subtitle">${escapeHtml(event.slug)}</p>
            </div>
            <div class="view-badges">
                <span class="status-pill ${status.className}">${escapeHtml(status.label)}</span>
                ${featured}
            </div>
        </div>

        <div class="view-layout">
            <div class="view-media">${image}</div>
            <div class="view-content">
                <dl class="detail-grid">
                    <div><dt>Date</dt><dd>${escapeHtml(event.displayDate || event.date || "—")}</dd></div>
                    <div><dt>Time</dt><dd>${escapeHtml(eventTime(event))}</dd></div>
                    <div><dt>Venue</dt><dd>${escapeHtml(event.venue || "—")}</dd></div>
                    <div><dt>Registration deadline</dt><dd>${escapeHtml(event.deadlineLabel || "No deadline")}</dd></div>
                    <div><dt>Fee</dt><dd>${escapeHtml(formatFee(event.registrationFee))}</dd></div>
                    <div><dt>Seats</dt><dd>${escapeHtml(seats)}</dd></div>
                    <div><dt>Organizer</dt><dd>${escapeHtml(event.organizer || "—")}</dd></div>
                    <div><dt>Contact</dt><dd>${escapeHtml(event.contactEmail || "—")}${event.contactPhone ? " · " + escapeHtml(event.contactPhone) : ""}</dd></div>
                </dl>

                <h3>Description</h3>
                <p class="view-description">${event.description ? escapeHtml(event.description).replace(/\n/g, "<br>") : "No description added."}</p>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-close="viewModal">Close</button>
            <button type="button" class="btn btn-danger" data-view-action="delete" data-id="${event.id}">Delete</button>
            <button type="button" class="btn btn-primary" data-view-action="edit" data-id="${event.id}">Edit event</button>
        </div>
    `;

    openModal("viewModal");
}

function openDelete(event) {
    state.pendingDelete = event;
    const registrations = Number(event.registeredCount || 0);
    const note = registrations > 0
        ? `"${event.title}" has ${registrations} active registration${registrations === 1 ? "" : "s"}.`
        : `"${event.title}" has no active registrations.`;

    $("deleteSummary").textContent = note + " This will remove the event and its poster permanently.";
    $("confirmDelete").disabled = false;
    $("confirmDelete").textContent = "Delete event";
    openModal("deleteModal");
}

async function confirmDelete() {
    if (!state.pendingDelete) {
        return;
    }

    const button = $("confirmDelete");
    const body = new FormData();
    body.set("action", "delete");
    body.set("id", String(state.pendingDelete.id));

    button.disabled = true;
    button.textContent = "Deleting…";

    try {
        const payload = await apiRequest(EVENTS_API, { method: "POST", body: body });
        closeModal("deleteModal");
        setFlash(payload.message || "Event deleted.", "success");
        state.pendingDelete = null;
        await loadEvents(true);
    } catch (error) {
        console.error(error);
        setFlash("Could not delete the event: " + error.message, "error");
        button.disabled = false;
        button.textContent = "Delete event";
    }
}

/* ------------------------------------------------------------------
 * Event wiring
 * ------------------------------------------------------------------ */
function findEvent(id) {
    return state.items.find(event => Number(event.id) === Number(id)) || null;
}

function openEventForm(id) {
    const event = findEvent(id);
    if (event) {
        openEditForm(event);
    }
}

$("search").addEventListener("input", function () {
    clearTimeout(state.searchTimer);
    state.searchTimer = setTimeout(() => {
        state.q = this.value.trim();
        state.page = 1;
        loadEvents();
    }, 300);
});

$("filterCategory").addEventListener("change", function () {
    state.categoryId = this.value;
    state.page = 1;
    loadEvents();
});

$("filterStatus").addEventListener("change", function () {
    state.status = this.value;
    state.page = 1;
    loadEvents();
});

$("sort").addEventListener("change", function () {
    state.sort = this.value;
    state.page = 1;
    loadEvents();
});

$("refresh").addEventListener("click", () => loadEvents());

$("newEvent").addEventListener("click", openCreateForm);
$("emptyCreate").addEventListener("click", openCreateForm);

$("eventsBody").addEventListener("click", function (clickEvent) {
    const button = clickEvent.target.closest("button[data-action]");
    if (!button) return;

    const event = findEvent(button.dataset.id);
    if (!event) return;

    if (button.dataset.action === "view") {
        openView(event);
    } else if (button.dataset.action === "edit") {
        openEditForm(event);
    } else if (button.dataset.action === "delete") {
        openDelete(event);
    }
});

$("viewBody").addEventListener("click", function (clickEvent) {
    const button = clickEvent.target.closest("button[data-view-action]");
    if (!button) return;

    const event = findEvent(button.dataset.id);
    if (!event) return;

    if (button.dataset.viewAction === "edit") {
        closeModal("viewModal");
        openEditForm(event);
    } else if (button.dataset.viewAction === "delete") {
        closeModal("viewModal");
        openDelete(event);
    }
});

$("eventForm").addEventListener("submit", submitEventForm);
$("confirmDelete").addEventListener("click", confirmDelete);
$("flashClose").addEventListener("click", hideFlash);

$("poster").addEventListener("change", function () {
    releasePosterPreview();
    clearFormErrors();

    const file = this.files[0];
    const error = validatePosterFile(file);

    if (error !== "") {
        this.value = "";
        showFormErrors({ poster: error });
        if (state.currentPoster) {
            setPosterPreview(state.currentPoster, "");
        } else {
            setPosterPreview("", "No poster selected");
        }
        return;
    }

    if (!file) {
        if (state.currentPoster) {
            setPosterPreview(state.currentPoster, "");
        } else {
            setPosterPreview("", "No poster selected");
        }
        return;
    }

    state.posterObjectUrl = URL.createObjectURL(file);
    setPosterPreview(state.posterObjectUrl, "");
    $("removePoster").checked = false;
});

$("removePoster").addEventListener("change", function () {
    if (this.checked) {
        setPosterPreview("", "Poster will be removed when you save");
    } else if (state.currentPoster) {
        setPosterPreview(state.currentPoster, "");
    }
});

$("pagination").addEventListener("click", function (clickEvent) {
    if (clickEvent.target.id === "prev" && state.page > 1) {
        state.page -= 1;
        loadEvents();
    } else if (clickEvent.target.id === "next" && state.page < state.totalPages) {
        state.page += 1;
        loadEvents();
    }
});

/* Close buttons and overlay clicks */
document.addEventListener("click", function (clickEvent) {
    const closer = clickEvent.target.closest("[data-close]");
    if (closer) {
        closeModal(closer.dataset.close);
        return;
    }

    if (clickEvent.target.classList.contains("modal")) {
        closeModal(clickEvent.target.id);
    }
});

document.addEventListener("keydown", function (keyEvent) {
    if (keyEvent.key !== "Escape") return;

    const open = Array.from(document.querySelectorAll(".modal.open")).pop();
    if (!open) return;
    if (open.id === "eventModal" && state.saving) return;

    closeModal(open.id);
});

/* Start the page */
(async function init() {
    await loadLookups();
    await loadEvents();
})();
