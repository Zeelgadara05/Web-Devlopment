/* Home page JavaScript */
let heading = document.getElementById("heading");
if (heading) {
    heading.innerHTML = "StudentHub Portal";
}

let page = document.getElementById("page");
let themeButton = document.getElementById("themeButton");

if (themeButton && page) {
    if (localStorage.getItem('theme') === 'dark') {
        page.classList.add('dark-mode');
        themeButton.innerHTML = "Light Mode";
    }
    
    themeButton.onclick = function() {
        page.classList.toggle("dark-mode");
        
        if (page.classList.contains("dark-mode")) {
            themeButton.innerHTML = "Light Mode";
            localStorage.setItem('theme', 'dark');
        } else {
            themeButton.innerHTML = "Dark Mode";
            localStorage.setItem('theme', 'light');
        }
    };
}

let menuButton = document.getElementById("menuButton");
let navMenu = document.getElementById("navMenu");

if (menuButton && navMenu) {
    menuButton.onclick = function() {
        navMenu.classList.toggle("show-menu");
        if (navMenu.classList.contains("show-menu")) {
            menuButton.innerHTML = "Close Menu";
        } else {
            menuButton.innerHTML = "Menu";
        }
    };
}
