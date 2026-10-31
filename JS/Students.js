/* Student management page - staff-only add, view, edit, delete, search and filter.
 * All data comes from the MySQL database through php/api/students.php.
 */
const STUDENTS_API = "./php/api/students.php";

const state = {
    q: "",
    departmentId: "",
    courseId: "",
    year: "",
    status: "",
    sort: "newest",
    page: 1,
    perPage: 10,
    totalPages: 1,
    total: 0,
    lookups: { departments: [], courses: [], semesters: [] },
    students: [],
    activeStudent: null,
    pendingDelete: null
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

function formatDate(value) {
    if (!value) return "—";
    const text = String(value);
    const date = new Date(text.length === 10 ? text + "T00:00:00" : text.replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return text;
    return date.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
}

function showMessage(text, type = "info", details = []) {
    const box = $("message");
    box.className = "message is-" + type;
    box.textContent = text;

    if (Array.isArray(details) && details.length > 0) {
        const list = document.createElement("ul");
        list.className = "message-list";
        details.forEach(detail => {
            const item = document.createElement("li");
            item.textContent = String(detail);
            list.appendChild(item);
        });
        box.appendChild(list);
    }
}

function clearFieldErrors() {
    document.querySelectorAll("#studentForm .field-error").forEach(node => {
        node.textContent = "";
    });
    document.querySelectorAll("#studentForm .is-invalid").forEach(node => {
        node.classList.remove("is-invalid");
        node.removeAttribute("aria-invalid");
    });
}

function showFieldErrors(errors) {
    clearFieldErrors();
    if (!errors || typeof errors !== "object") return;

    Object.entries(errors).forEach(([field, message]) => {
        const errorNode = document.querySelector('[data-error-for="' + field + '"]');
        if (errorNode) errorNode.textContent = String(message);

        const input = document.getElementById(field);
        if (input) {
            input.classList.add("is-invalid");
            input.setAttribute("aria-invalid", "true");
        }
    });
}

function errorList(payload) {
    if (!payload || !payload.errors) return [];
    return Array.isArray(payload.errors) ? payload.errors : Object.values(payload.errors);
}

async function apiRequest(url, options = {}) {
    const response = await fetch(url, {
        cache: "no-store",
        headers: {
            "Accept": "application/json",
            ...(options.body ? { "Content-Type": "application/json" } : {})
        },
        ...options
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch (error) {
        payload = null;
    }

    if (!response.ok || !payload || !payload.success) {
        const message = payload && payload.message
            ? payload.message
            : "Request failed (HTTP " + response.status + ").";
        const failure = new Error(message);
        failure.payload = payload;
        failure.status = response.status;
        throw failure;
    }

    return payload;
}

/* ------------------------------ lookups ------------------------------ */

function fillSelect(select, items, placeholder, valueOf, labelOf, selectedValue = "") {
    select.innerHTML = "";

    if (placeholder !== null) {
        const option = document.createElement("option");
        option.value = "";
        option.textContent = placeholder;
        select.appendChild(option);
    }

    items.forEach(item => {
        const option = document.createElement("option");
        option.value = String(valueOf(item));
        option.textContent = labelOf(item);
        if (String(selectedValue) === option.value) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

function coursesFor(departmentId) {
    if (!departmentId) return state.lookups.courses;
    return state.lookups.courses.filter(course => String(course.departmentId) === String(departmentId));
}

function populateFilterDepartments() {
    fillSelect(
        $("filterDepartment"),
        state.lookups.departments,
        "All Departments",
        department => department.id,
        department => department.name,
        state.departmentId
    );
}

function populateFilterCourses() {
    fillSelect(
        $("filterCourse"),
        coursesFor(state.departmentId),
        "All Courses",
        course => course.id,
        course => course.code + " — " + course.name,
        state.courseId
    );
}

function populateFormDepartments(selectedId = "") {
    fillSelect(
        $("department_id"),
        state.lookups.departments,
        "Select department",
        department => department.id,
        department => department.code + " — " + department.name,
        selectedId
    );
}

function populateFormCourses(departmentId, selectedId = "") {
    fillSelect(
        $("course_id"),
        coursesFor(departmentId),
        "Select course",
        course => course.id,
        course => course.code + " — " + course.name,
        selectedId
    );
}

function populateSemesters(selected = 1) {
    fillSelect(
        $("current_semester"),
        state.lookups.semesters,
        null,
        semester => semester,
        semester => "Semester " + semester,
        selected
    );
}

async function loadLookups() {
    const payload = await apiRequest(STUDENTS_API + "?action=lookups&t=" + Date.now());
    state.lookups = payload.data;

    populateFilterDepartments();
    populateFilterCourses();
    populateFormDepartments();
    populateFormCourses("");
    populateSemesters(1);
}

/* ------------------------------ list ------------------------------ */

function currentQuery() {
    const params = new URLSearchParams();
    if (state.q) params.set("q", state.q);
    if (state.departmentId) params.set("department_id", state.departmentId);
    if (state.courseId) params.set("course_id", state.courseId);
    if (state.year) params.set("year", state.year);
    if (state.status) params.set("status", state.status);
    params.set("sort", state.sort);
    params.set("page", String(state.page));
    params.set("per_page", String(state.perPage));
    params.set("t", String(Date.now()));
    return params.toString();
}

async function loadStudents(silent = false) {
    if (!silent) showMessage("Loading students…", "info");

    try {
        const payload = await apiRequest(STUDENTS_API + "?" + currentQuery());
        const meta = payload.meta || {};

        const totalPages = Math.max(1, Number(meta.total_pages || 1));
        if (state.page > totalPages) {
            state.page = totalPages;
            return loadStudents(silent);
        }

        state.students = Array.isArray(payload.data) ? payload.data : [];
        state.total = Number(meta.total || state.students.length);
        state.perPage = Number(meta.per_page || state.perPage);
        state.totalPages = totalPages;

        renderTable();
        renderPagination();

        if (!silent) {
            showMessage(
                state.total === 0
                    ? "No students match the current search or filters."
                    : state.total + " student" + (state.total === 1 ? "" : "s") + " found.",
                state.total === 0 ? "info" : "success"
            );
        }

        return true;
    } catch (error) {
        state.students = [];
        state.total = 0;
        state.totalPages = 1;
        renderTable();
        renderPagination();

        const message = error.status === 401 || error.status === 403
            ? "Staff sign-in is required to manage students."
            : error.message || "Could not load students.";
        showMessage(message, "error");
        return false;
    }
}

function statusClass(status) {
    return ["active", "inactive", "graduated", "suspended"].includes(status) ? status : "inactive";
}

function renderTable() {
    const body = $("studentsTableBody");

    if (state.students.length === 0) {
        body.innerHTML = '<tr><td colspan="7" class="table-empty">No student records found.</td></tr>';
        return;
    }

    body.innerHTML = state.students.map(student => {
        const course = student.course
            ? escapeHtml(student.course)
            : '<span class="muted">Not specified</span>';

        return `
            <tr>
                <td><strong>${escapeHtml(student.rollNo)}</strong></td>
                <td>
                    <span class="student-name">${escapeHtml(student.name)}</span>
                    <span class="cell-sub">${escapeHtml(student.email)}</span>
                </td>
                <td>${escapeHtml(student.department)}</td>
                <td>${course}</td>
                <td>Year ${escapeHtml(student.year)} · Sem ${escapeHtml(student.semester)}</td>
                <td><span class="status-badge status-${escapeHtml(statusClass(student.status))}">${escapeHtml(student.status)}</span></td>
                <td class="actions-col">
                    <div class="row-actions">
                        <button type="button" class="btn btn-small btn-ghost" data-action="view" data-id="${student.id}" title="View student">View</button>
                        <button type="button" class="btn btn-small btn-ghost" data-action="edit" data-id="${student.id}" title="Edit student">Edit</button>
                        <button type="button" class="btn btn-small btn-danger-ghost" data-action="delete" data-id="${student.id}" title="Delete student">Delete</button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");
}

function renderPagination() {
    const start = state.total === 0 ? 0 : (state.page - 1) * state.perPage + 1;
    const end = Math.min(state.page * state.perPage, state.total);

    $("resultCount").textContent = state.total === 0
        ? "0 students"
        : "Showing " + start + "–" + end + " of " + state.total + " students";
    $("pageInfo").textContent = "Page " + state.page + " of " + state.totalPages;
    $("prev").disabled = state.page <= 1;
    $("next").disabled = state.page >= state.totalPages;
}

/* ------------------------------ modals ------------------------------ */

function openModal(id) {
    $(id).hidden = false;
    document.body.classList.add("modal-open");

    const focusTarget = $(id).querySelector("input:not([type=hidden]), select, button");
    if (focusTarget) focusTarget.focus();
}

function closeModal(id) {
    $(id).hidden = true;
    if (!document.querySelector(".modal:not([hidden])")) {
        document.body.classList.remove("modal-open");
    }
}

function closeAllModals() {
    document.querySelectorAll(".modal:not([hidden])").forEach(modal => {
        modal.hidden = true;
    });
    document.body.classList.remove("modal-open");
}

/* ------------------------------ form ------------------------------ */

function setPasswordMode(creating) {
    $("passwordRequired").style.display = creating ? "inline" : "none";
    $("passwordHint").textContent = creating
        ? "Required for a new student. At least 8 characters with a letter, number and symbol."
        : "Leave blank to keep the current password.";
}

function openForm(student = null) {
    clearFieldErrors();

    const form = $("studentForm");
    form.reset();

    $("studentId").value = student ? student.id : "";
    $("formTitle").textContent = student ? "Edit Student" : "Add Student";
    $("saveStudent").textContent = student ? "Save Changes" : "Add Student";
    setPasswordMode(!student);

    populateFormDepartments(student ? student.departmentId : "");
    populateFormCourses(student ? student.departmentId : "", student ? student.courseId : "");
    populateSemesters(student ? student.semester : 1);
    $("current_year").value = String(student ? student.year : 1);
    $("gender").value = student ? student.gender : "Other";
    $("status").value = student ? student.status : "active";

    if (student) {
        $("roll_no").value = student.rollNo || "";
        $("full_name").value = student.name || "";
        $("email").value = student.email || "";
        $("mobile").value = student.mobile || "";
        $("date_of_birth").value = student.dateOfBirth || "";
        $("address").value = student.address || "";
        $("city").value = student.city || "";
        $("state").value = student.state || "";
        $("pincode").value = student.pincode || "";
        $("admission_date").value = student.admissionDate || "";
    } else {
        $("roll_no").value = "";
        $("full_name").value = "";
        $("email").value = "";
        $("mobile").value = "";
        $("date_of_birth").value = "";
        $("address").value = "";
        $("city").value = "";
        $("state").value = "";
        $("pincode").value = "";
        $("admission_date").value = "";
    }

    state.activeStudent = student;
    openModal("formModal");
    $("roll_no").focus();
}

function formPayload() {
    const form = $("studentForm");
    return {
        id: $("studentId").value,
        roll_no: form.elements.roll_no.value.trim(),
        full_name: form.elements.full_name.value.trim(),
        email: form.elements.email.value.trim(),
        mobile: form.elements.mobile.value.trim(),
        password: form.elements.password.value,
        department_id: form.elements.department_id.value,
        course_id: form.elements.course_id.value,
        current_year: form.elements.current_year.value,
        current_semester: form.elements.current_semester.value,
        gender: form.elements.gender.value,
        date_of_birth: form.elements.date_of_birth.value,
        address: form.elements.address.value.trim(),
        city: form.elements.city.value.trim(),
        state: form.elements.state.value.trim(),
        pincode: form.elements.pincode.value.trim(),
        admission_date: form.elements.admission_date.value,
        status: form.elements.status.value
    };
}

async function submitStudent(event) {
    event.preventDefault();
    clearFieldErrors();

    const payload = formPayload();
    const creating = !payload.id;
    const saveButton = $("saveStudent");
    const originalText = saveButton.textContent;

    saveButton.disabled = true;
    saveButton.textContent = "Saving…";

    try {
        const response = await apiRequest(STUDENTS_API, {
            method: "POST",
            body: JSON.stringify({
                ...payload,
                action: creating ? "create" : "update"
            })
        });

        closeModal("formModal");
        showMessage(response.message, "success");
        await loadStudents(true);
    } catch (error) {
        const payloadErrors = error.payload && error.payload.errors ? error.payload.errors : null;
        if (payloadErrors) {
            showFieldErrors(payloadErrors);
            showMessage(error.message, "error", errorList(error.payload));
        } else {
            showMessage(error.message || "Could not save the student.", "error");
        }
    } finally {
        saveButton.disabled = false;
        saveButton.textContent = originalText;
    }
}

/* ------------------------------ view / delete ------------------------------ */

function detailRow(label, value) {
    return '<div class="detail-item"><dt>' + escapeHtml(label) + "</dt><dd>" +
        (value === null || value === "" ? "—" : value) + "</dd></div>";
}

async function viewStudent(id) {
    try {
        const payload = await apiRequest(STUDENTS_API + "?id=" + encodeURIComponent(id) + "&t=" + Date.now());
        const student = payload.data;
        state.activeStudent = student;

        const address = [student.address, student.city, student.state, student.pincode]
            .filter(Boolean)
            .join(", ");

        $("viewTitle").textContent = student.name + " · " + student.rollNo;
        $("viewBody").innerHTML = '<dl class="detail-grid">' +
            detailRow("Roll No", escapeHtml(student.rollNo)) +
            detailRow("Full Name", escapeHtml(student.name)) +
            detailRow("Email", escapeHtml(student.email)) +
            detailRow("Mobile", escapeHtml(student.mobile)) +
            detailRow("Department", escapeHtml(student.department)) +
            detailRow("Course", escapeHtml(student.course || "Not specified")) +
            detailRow("Year / Semester", "Year " + escapeHtml(student.year) + " / Semester " + escapeHtml(student.semester)) +
            detailRow("Gender", escapeHtml(student.gender)) +
            detailRow("Date of Birth", formatDate(student.dateOfBirth)) +
            detailRow("Admission Date", formatDate(student.admissionDate)) +
            detailRow("Status", '<span class="status-badge status-' + escapeHtml(statusClass(student.status)) + '">' + escapeHtml(student.status) + "</span>") +
            detailRow("Address", escapeHtml(address || "—")) +
            detailRow("Events Registered", escapeHtml(student.totalEvents || 0)) +
            detailRow("Last Sign-in", student.lastLoginAt ? formatDate(student.lastLoginAt) : "Never") +
            detailRow("Joined", formatDate(student.joinedAt)) +
            "</dl>";

        openModal("viewModal");
    } catch (error) {
        showMessage(error.message || "Could not load the student.", "error");
    }
}

async function askDelete(id) {
    try {
        const payload = await apiRequest(STUDENTS_API + "?id=" + encodeURIComponent(id) + "&t=" + Date.now());
        state.pendingDelete = payload.data;

        $("deleteText").textContent = 'Delete "' + payload.data.name + '" (' + payload.data.rollNo + ')?';
        openModal("deleteModal");
    } catch (error) {
        showMessage(error.message || "Could not load the student.", "error");
    }
}

async function confirmDelete() {
    if (!state.pendingDelete) return;

    const button = $("confirmDelete");
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = "Deleting…";

    try {
        const payload = await apiRequest(STUDENTS_API, {
            method: "POST",
            body: JSON.stringify({ action: "delete", id: state.pendingDelete.id })
        });

        state.pendingDelete = null;
        closeModal("deleteModal");
        showMessage(payload.message, "success");
        await loadStudents(true);
    } catch (error) {
        showMessage(error.message || "Could not delete the student.", "error");
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
}

/* ------------------------------ events ------------------------------ */

function bindEvents() {
    /* Search with a short debounce, so typing does not hammer the API. */
    let searchTimer = null;
    $("search").addEventListener("input", () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.q = $("search").value.trim();
            state.page = 1;
            loadStudents();
        }, 300);
    });

    $("filterDepartment").addEventListener("change", () => {
        state.departmentId = $("filterDepartment").value;
        state.courseId = "";
        populateFilterCourses();
        state.page = 1;
        loadStudents();
    });

    $("filterCourse").addEventListener("change", () => {
        state.courseId = $("filterCourse").value;
        state.page = 1;
        loadStudents();
    });

    $("filterYear").addEventListener("change", () => {
        state.year = $("filterYear").value;
        state.page = 1;
        loadStudents();
    });

    $("filterStatus").addEventListener("change", () => {
        state.status = $("filterStatus").value;
        state.page = 1;
        loadStudents();
    });

    $("sort").addEventListener("change", () => {
        state.sort = $("sort").value;
        state.page = 1;
        loadStudents();
    });

    $("resetFilters").addEventListener("click", () => {
        state.q = "";
        state.departmentId = "";
        state.courseId = "";
        state.year = "";
        state.status = "";
        state.sort = "newest";
        state.page = 1;

        $("search").value = "";
        $("filterDepartment").value = "";
        $("filterYear").value = "";
        $("filterStatus").value = "";
        $("sort").value = "newest";
        populateFilterCourses();
        loadStudents();
    });

    $("prev").addEventListener("click", () => {
        if (state.page > 1) {
            state.page--;
            loadStudents();
        }
    });

    $("next").addEventListener("click", () => {
        if (state.page < state.totalPages) {
            state.page++;
            loadStudents();
        }
    });

    $("addStudent").addEventListener("click", () => openForm(null));
    $("studentForm").addEventListener("submit", submitStudent);

    $("department_id").addEventListener("change", () => {
        populateFormCourses($("department_id").value, "");
    });

    $("viewEditBtn").addEventListener("click", () => {
        const student = state.activeStudent;
        closeModal("viewModal");
        if (student) openForm(student);
    });

    $("confirmDelete").addEventListener("click", confirmDelete);

    $("studentsTableBody").addEventListener("click", event => {
        const button = event.target.closest("button[data-action]");
        if (!button) return;

        const id = Number(button.dataset.id);
        if (!id) return;

        if (button.dataset.action === "view") viewStudent(id);
        if (button.dataset.action === "edit") {
            const rowStudent = state.students.find(student => student.id === id);
            if (rowStudent) openForm(rowStudent);
        }
        if (button.dataset.action === "delete") askDelete(id);
    });

    document.querySelectorAll("[data-close]").forEach(button => {
        button.addEventListener("click", () => closeModal(button.dataset.close));
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape") closeAllModals();
    });
}

/* ------------------------------ start ------------------------------ */

async function init() {
    bindEvents();
    populateSemesters(1);

    try {
        await loadLookups();
    } catch (error) {
        const message = error.status === 401 || error.status === 403
            ? "Staff sign-in is required to manage students."
            : error.message || "Could not load form options.";
        showMessage(message, "error");
        state.students = [];
        renderTable();
        renderPagination();
        return;
    }

    await loadStudents();
}

init();
