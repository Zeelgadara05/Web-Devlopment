/* College Events page - reads events from the MySQL database via PHP API */
const P6_EVENTS_API = "php/api/events.php";

let events = [];
let page = 1;
let perPage = 5;
let totalPages = 1;

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, character => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    })[character]);
}

async function loadEvents() {
    const message = document.getElementById("message");
    message.textContent = "Loading events...";

    try {
        const response = await fetch(P6_EVENTS_API + "?per_page=200&sort=date&t=" + Date.now(), {
            cache: "no-store",
            headers: { "Accept": "application/json" }
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || "HTTP " + response.status);
        }

        events = Array.isArray(payload.data) ? payload.data : [];
        message.textContent = "Events Loaded (" + events.length + " records)";
        message.style.color = "#2e7d32";
        displayEvents();
    } catch (error) {
        console.error(error);
        message.textContent = "Error loading events from the database";
        message.style.color = "#d32f2f";
    }
}

// Display events
function displayEvents() {
    const search = document.getElementById("search").value.toLowerCase();
    const filter = document.getElementById("filter").value;
    const sort = document.getElementById("sort").value;

    // Search
    let result = events.filter(event =>
        String(event.name).toLowerCase().includes(search) ||
        String(event.venue).toLowerCase().includes(search)
    );

    // Filter
    if (filter != "All") {
        result = result.filter(event => event.category == filter);
    }

    // Sort by name
    if (sort == "name") {
        result.sort((a, b) => String(a.name).localeCompare(String(b.name)));
    }

    // Sort by date
    if (sort == "date") {
        result.sort((a, b) => new Date(a.date) - new Date(b.date));
    }

    // Pagination
    totalPages = Math.max(1, Math.ceil(result.length / perPage));
    if (page > totalPages) page = totalPages;

    const start = (page - 1) * perPage;
    const end = start + perPage;
    const pageEvents = result.slice(start, end);

    // Create HTML
    let output = "";

    pageEvents.forEach(event => {
        output += `
            <div class="card">
                <h2>${escapeHtml(event.name)}</h2>
                <p>Date: ${escapeHtml(event.displayDate || event.date || "")}</p>
                <p>Category: ${escapeHtml(event.category)}</p>
                <p>Venue: ${escapeHtml(event.venue)}</p>
            </div>
        `;
    });

    if (output == "") {
        output = "<h3>No events found</h3>";
    }

    document.getElementById("events").innerHTML = output;

    // Page number
    document.getElementById("page").innerHTML =
        "Page " + page + " of " + totalPages;
}

// Search
document.getElementById("search").addEventListener("input", function () {
    page = 1;
    displayEvents();
});

// Filter
document.getElementById("filter").addEventListener("change", function () {
    page = 1;
    displayEvents();
});

// Sort
document.getElementById("sort").addEventListener("change", function () {
    page = 1;
    displayEvents();
});

// Previous button
document.getElementById("prev").addEventListener("click", function () {
    if (page > 1) {
        page--;
        displayEvents();
    }
});

// Next button
document.getElementById("next").addEventListener("click", function () {
    if (page < totalPages) {
        page++;
        displayEvents();
    }
});

loadEvents();
