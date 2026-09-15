let events = [];
let page = 1;
let perPage = 5;


// Get JSON data
fetch("JSON/p6.json")

    .then(response => {

        if (!response.ok) {
            throw new Error("JSON file not found");
        }

        return response.json();

    })

    .then(data => {

        events = data;

        document.getElementById("message").innerHTML =
            "Events Loaded";

        displayEvents();

    })

    .catch(error => {

        console.log(error);

        document.getElementById("message").innerHTML =
            "Error loading JSON";

    });


// Display events
function displayEvents() {

    let search =
        document.getElementById("search").value.toLowerCase();

    let filter =
        document.getElementById("filter").value;

    let sort =
        document.getElementById("sort").value;


    // Search
    let result = events.filter(event =>
        event.name.toLowerCase().includes(search)
    );


    // Filter
    if (filter != "All") {

        result = result.filter(event =>
            event.category == filter
        );

    }


    // Sort by name
    if (sort == "name") {

        result.sort((a, b) =>
            a.name.localeCompare(b.name)
        );

    }


    // Sort by date
    if (sort == "date") {

        result.sort((a, b) =>
            new Date(a.date) - new Date(b.date)
        );

    }


    // Pagination
    let start = (page - 1) * perPage;

    let end = start + perPage;

    let pageEvents = result.slice(start, end);


    // Create HTML
    let output = "";


    pageEvents.forEach(event => {

        output += `
            <div class="card">

                <h2>${event.name}</h2>

                <p>Date: ${event.date}</p>

                <p>Category: ${event.category}</p>

                <p>Venue: ${event.venue}</p>

            </div>
        `;

    });


    if (output == "") {

        output = "<h3>No events found</h3>";

    }


    document.getElementById("events").innerHTML = output;


    // Page number
    let totalPages =
        Math.ceil(result.length / perPage);


    document.getElementById("page").innerHTML =
        "Page " + page + " of " + totalPages;

}


// Search
document.getElementById("search").addEventListener(
    "input",
    function() {

        page = 1;

        displayEvents();

    }
);


// Filter
document.getElementById("filter").addEventListener(
    "change",
    function() {

        page = 1;

        displayEvents();

    }
);


// Sort
document.getElementById("sort").addEventListener(
    "change",
    function() {

        page = 1;

        displayEvents();

    }
);


// Previous button
document.getElementById("prev").addEventListener(
    "click",
    function() {

        if (page > 1) {

            page--;

            displayEvents();

        }

    }
);


// Next button
document.getElementById("next").addEventListener(
    "click",
    function() {

        let totalPages =
            Math.ceil(events.length / perPage);


        if (page < totalPages) {

            page++;

            displayEvents();

        }

    }
);