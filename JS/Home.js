alert("Welcome to Student Hub Portal");
console.log("Java Script link is Successfull");
let Student = "Zeel Gadara";
let Age = 20;
let Collage = "CHARUSAT University";
console.log(Student);
console.log(Age);
console.log(Collage);
console.log(document);

// session 2
let heading = document.getElementById("heading");
console.log(heading);
heading.innerHTML = "Welcome to Student Hub Portal";
heading.style.color = "blue";
heading.style.fontSize = "50px";
heading.style.textAlign = "center";

let notification = document.getElementById("notification");
let closeNotification = document.getElementById("closeNotification");

function closeNotice(){
    notification.style.display = "none";
}

closeNotification.onclick = closeNotice;

let changeNotification = document.getElementById("changeNotification");

function changeNotice(){
    notification.innerHTML = "⚠️ Important Notice: Please check the examination schedule.";
}

changeNotification.onclick = changeNotice;