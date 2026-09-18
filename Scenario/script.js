const nameInput = getElementById("name");
const namePattern = /^[A-Za-z ]{2,50}$/;
function validateName(){
    const name = nameInput.value.trim();
if(name === ""){
    document.getElementById("nameError").textContent = "Name is requierd";
    return false;
}

if(!namePattern.test(name)){
    document.getElementById("nameError").textContent = "Use only letters and space.";
    return false;
}

document.getElementById("nameError").textContent = "";
return true;
}