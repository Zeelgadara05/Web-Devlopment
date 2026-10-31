/* Notices page - loads published notices from the MySQL database via PHP API */
const NOTICES_API = "./php/api/notices.php";

let items = [];
let page = 1;
const perPage = 6;
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

async function loadNotices() {
    const message = document.getElementById("message");
    message.textContent = "Loading notices from the database...";
    message.style.color = "#666";

    try {
        const response = await fetch(NOTICES_API + "?per_page=200&sort=date&t=" + Date.now(), {
            cache: "no-store",
            headers: { "Accept": "application/json" }
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || "HTTP " + response.status);
        }

        items = Array.isArray(payload.data) ? payload.data : [];
        message.textContent = "Notices loaded successfully (" + items.length + " records)";
        message.style.color = "#2e7d32";
        displayItems();
    } catch (error) {
        console.error(error);
        message.textContent = "Could not load notices from the database. Make sure MySQL is running in XAMPP.";
        message.style.color = "#d32f2f";
        document.getElementById("notices").innerHTML = "";
        document.getElementById("pageInfo").textContent = "Page 0 of 0";
        document.getElementById("prev").disabled = true;
        document.getElementById("next").disabled = true;
    }
}

/* Display notices with search, filter, sort and pagination */
function displayItems() {
    const search = document.getElementById("search").value.toLowerCase().trim();
    const filter = document.getElementById("filterCat").value;
    const sort = document.getElementById("sort").value;

    // Search by title or content
    let result = items.filter(notice =>
        String(notice.title).toLowerCase().includes(search) ||
        String(notice.content).toLowerCase().includes(search)
    );

    // Filter by category
    if (filter !== "All") {
        result = result.filter(notice => notice.category === filter);
    }

    // Sorting
    if (sort === "title") {
        result.sort((a, b) => String(a.title).localeCompare(String(b.title)));
    } else if (sort === "date") {
        result.sort((a, b) => new Date(a.publishedAt) - new Date(b.publishedAt));
    } else {
        result.sort((a, b) => (b.isPinned === a.isPinned) ? 0 : (b.isPinned ? 1 : -1));
    }

    // Keep the current page in range
    totalPages = Math.max(1, Math.ceil(result.length / perPage));
    if (page > totalPages) page = totalPages;

    const start = (page - 1) * perPage;
    const pageItems = result.slice(start, start + perPage);

    let output = "";

    pageItems.forEach(notice => {
        const pinned = notice.isPinned ? '<span class="pin-badge">📌 Pinned</span>' : "";
        const content = escapeHtml(notice.content).replace(/\n/g, "<br>");

        output += `
            <div class="notice-card">
                <div class="notice-heading">
                    <h2>${escapeHtml(notice.title)} ${pinned}</h2>
                    <span class="category-badge">${escapeHtml(notice.category)}</span>
                </div>
                <p class="notice-content">${content}</p>
                <p class="notice-meta"><strong>Published:</strong> ${escapeHtml(notice.displayDate || notice.date || "")}</p>
            </div>
        `;
    });

    if (output === "") {
        output = "<h3>No notices found</h3>";
    }

    document.getElementById("notices").innerHTML = output;
    document.getElementById("pageInfo").textContent = "Page " + page + " of " + totalPages;
    document.getElementById("prev").disabled = page === 1;
    document.getElementById("next").disabled = page === totalPages;
}

// Search
document.getElementById("search").addEventListener("input", function () {
    page = 1;
    displayItems();
});

// Category filter
document.getElementById("filterCat").addEventListener("change", function () {
    page = 1;
    displayItems();
});

// Sort
document.getElementById("sort").addEventListener("change", function () {
    page = 1;
    displayItems();
});

// Pagination
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

loadNotices();
