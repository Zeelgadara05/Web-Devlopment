/* FAQs page - loads FAQs from the MySQL database via PHP API */
const FAQS_API = "./php/api/faqs.php";

let items = [];
let page = 1;
const perPage = 5;
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

async function loadFaqs() {
    const message = document.getElementById("message");
    message.textContent = "Loading FAQs from the database...";
    message.style.color = "#666";

    try {
        const response = await fetch(FAQS_API + "?per_page=200&sort=order&t=" + Date.now(), {
            cache: "no-store",
            headers: { "Accept": "application/json" }
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || "HTTP " + response.status);
        }

        items = Array.isArray(payload.data) ? payload.data : [];
        message.textContent = "FAQs loaded successfully (" + items.length + " records)";
        message.style.color = "#2e7d32";
        displayItems();
    } catch (error) {
        console.error(error);
        message.textContent = "Could not load FAQs from the database. Make sure MySQL is running in XAMPP.";
        message.style.color = "#d32f2f";
        document.getElementById("faqs").innerHTML = "";
        document.getElementById("pageInfo").textContent = "Page 0 of 0";
        document.getElementById("prev").disabled = true;
        document.getElementById("next").disabled = true;
    }
}

/* Display FAQs with search, filter, sort and pagination */
function displayItems() {
    const search = document.getElementById("search").value.toLowerCase().trim();
    const filter = document.getElementById("filterCat").value;
    const sort = document.getElementById("sort").value;

    // Search by question or answer
    let result = items.filter(faq =>
        String(faq.question).toLowerCase().includes(search) ||
        String(faq.answer).toLowerCase().includes(search)
    );

    // Filter by category
    if (filter !== "All") {
        result = result.filter(faq => faq.category === filter);
    }

    // Sorting
    if (sort === "question") {
        result.sort((a, b) => String(a.question).localeCompare(String(b.question)));
    } else {
        result.sort((a, b) => (a.displayOrder || 0) - (b.displayOrder || 0));
    }

    // Keep the current page in range
    totalPages = Math.max(1, Math.ceil(result.length / perPage));
    if (page > totalPages) page = totalPages;

    const start = (page - 1) * perPage;
    const pageItems = result.slice(start, start + perPage);

    let output = "";

    pageItems.forEach(faq => {
        output += `
            <div class="faq-item">
                <div class="faq-question">
                    ${escapeHtml(faq.question)}
                    <span class="faq-toggle">+</span>
                </div>
                <div class="faq-answer">
                    <p>${escapeHtml(faq.answer)}</p>
                    <span class="category">${escapeHtml(faq.category)}</span>
                </div>
            </div>
        `;
    });

    if (output === "") {
        output = "<h3>No FAQs found</h3>";
    }

    document.getElementById("faqs").innerHTML = output;

    // Make every question collapsible
    document.querySelectorAll(".faq-question").forEach(question => {
        question.addEventListener("click", function () {
            const answer = this.nextElementSibling;
            const toggle = this.querySelector(".faq-toggle");

            answer.classList.toggle("active");
            toggle.textContent = answer.classList.contains("active") ? "\u2212" : "+";
        });
    });

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

loadFaqs();
