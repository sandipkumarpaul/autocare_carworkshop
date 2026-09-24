// Admin dashboard: stats, searchable appointment table, edit modal.
const appointmentTableBody = document.getElementById('appointmentTableBody');
const emptyState = document.getElementById('emptyState');
const tableWrapper = document.querySelector('.table-wrapper');
const searchInput = document.getElementById('searchInput');
const editModal = document.getElementById('editModal');
const editForm = document.getElementById('editForm');
const editDateInput = document.getElementById('editDate');
const editMechanicSelect = document.getElementById('editMechanic');
const editMechanicInfo = document.getElementById('editMechanicInfo');
const editMechanicInfoText = document.getElementById('editMechanicInfoText');
const saveEditBtn = document.getElementById('saveEditBtn');

let allAppointments = [];
let editingAppointment = null;

async function loadAppointments() {
    appointmentTableBody.innerHTML = `
        <tr>
            <td colspan="7" class="table-loading">
                <span class="spinner"></span>
                <span>Loading appointments...</span>
            </td>
        </tr>
    `;
    try {
        const { result } = await apiRequest('admin.php');
        if (!result.success) {
            showToast('error', 'Error', result.message || 'Failed to load appointments.');
            appointmentTableBody.innerHTML = '';
            showEmptyState(true);
            return;
        }
        allAppointments = result.data || [];
        updateStats(result.mechanic_count);
        applySearch();
    } catch (error) {
        showToast('error', 'Connection Error', 'Unable to connect to the server.');
        appointmentTableBody.innerHTML = '';
        showEmptyState(true);
    }
}

function updateStats(mechanicCount) {
    const today = todayStr();
    document.getElementById('statTotal').textContent = allAppointments.length;
    document.getElementById('statToday').textContent = allAppointments.filter(a => a.appointment_date === today).length;
    document.getElementById('statUpcoming').textContent = allAppointments.filter(a => a.appointment_date > today).length;
    document.getElementById('statMechanics').textContent = mechanicCount ?? '—';
}

function renderAppointments(appointments) {
    if (appointments.length === 0) {
        appointmentTableBody.innerHTML = '';
        showEmptyState(true);
        return;
    }
    showEmptyState(false);
    appointmentTableBody.innerHTML = appointments.map((appt, index) => `
        <tr>
            <td class="cell-muted">${index + 1}</td>
            <td class="cell-strong">${escapeHtml(appt.client_name)}</td>
            <td class="cell-mono">${escapeHtml(appt.phone)}</td>
            <td class="cell-mono">${escapeHtml(appt.car_license)}</td>
            <td><span class="date-badge">📅 ${formatDate(appt.appointment_date)}</span></td>
            <td><span class="mechanic-badge">👨‍🔧 ${escapeHtml(appt.mechanic_name)}</span></td>
            <td>
                <button class="btn btn-primary btn-sm" onclick="openEditModal(${appt.id})">✏️ Edit</button>
            </td>
        </tr>
    `).join('');
}

function showEmptyState(show) {
    const isFiltered = allAppointments.length > 0;
    emptyState.querySelector('h3').textContent = isFiltered ? 'No Matching Appointments' : 'No Appointments Found';
    emptyState.querySelector('p').textContent = isFiltered
        ? 'Try a different search term.'
        : 'There are no appointments in the system yet. Clients can book them from the booking page.';
    emptyState.style.display = show ? 'block' : 'none';
    tableWrapper.style.display = show ? 'none' : 'block';
}

function applySearch() {
    const query = searchInput.value.toLowerCase().trim();
    if (!query) {
        renderAppointments(allAppointments);
        return;
    }
    renderAppointments(allAppointments.filter(appt =>
        [appt.client_name, appt.phone, appt.car_license, appt.mechanic_name, appt.appointment_date]
            .some(value => String(value).toLowerCase().includes(query))
    ));
}

searchInput.addEventListener('input', applySearch);
document.getElementById('refreshBtn').addEventListener('click', loadAppointments);

function openEditModal(appointmentId) {
    editingAppointment = allAppointments.find(a => a.id == appointmentId);
    if (!editingAppointment) {
        showToast('error', 'Error', 'Appointment not found.');
        return;
    }
    document.getElementById('editClientName').value = editingAppointment.client_name;
    document.getElementById('editPhone').value = editingAppointment.phone;
    document.getElementById('editCarLicense').value = editingAppointment.car_license;
    editDateInput.value = editingAppointment.appointment_date;
    editDateInput.min = todayStr();
    clearAllFieldErrors(editForm);
    editMechanicSelect.innerHTML = '';
    loadEditMechanics();
    editModal.classList.add('active');
}

function closeEditModal() {
    editModal.classList.remove('active');
    editingAppointment = null;
}

editModal.addEventListener('click', (e) => {
    if (e.target === editModal) closeEditModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && editModal.classList.contains('active')) {
        closeEditModal();
    }
});

function loadEditMechanics() {
    const date = editDateInput.value;
    if (!date) return;
    const onOriginalDate = date === editingAppointment.appointment_date;
    loadMechanicOptions(editMechanicSelect, editMechanicInfo, editMechanicInfoText, date, {
        // The assigned mechanic already holds a slot on the original date.
        currentMechanicId: onOriginalDate ? editingAppointment.mechanic_id : null,
        selectedId: editMechanicSelect.value || editingAppointment.mechanic_id
    });
}

editDateInput.addEventListener('change', loadEditMechanics);

editForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const newDate = editDateInput.value;
    const newMechanicId = editMechanicSelect.value;
    const dateChanged = newDate !== editingAppointment.appointment_date;

    let hasError = false;
    if (!newDate || (dateChanged && newDate < todayStr())) {
        setFieldError('editDate', newDate ? 'Appointments cannot be moved to a past date.' : 'Please select a valid date.');
        hasError = true;
    } else {
        clearFieldError('editDate');
    }
    if (!newMechanicId) {
        setFieldError('editMechanic');
        hasError = true;
    } else {
        clearFieldError('editMechanic');
    }
    if (hasError) {
        showToast('warning', 'Validation Error', 'Please fix the highlighted fields.');
        return;
    }

    saveEditBtn.disabled = true;
    const originalText = saveEditBtn.innerHTML;
    saveEditBtn.innerHTML = '<span class="spinner"></span> Saving...';
    try {
        const { status, result } = await apiRequest('admin.php', 'PUT', {
            appointment_id: editingAppointment.id,
            appointment_date: newDate,
            mechanic_id: parseInt(newMechanicId)
        });
        if (result.success) {
            showToast('success', 'Updated!', result.message, 6000);
            closeEditModal();
            loadAppointments();
        } else {
            showToast(status === 409 ? 'warning' : 'error', 'Update Failed', result.message, 6000);
        }
    } catch (error) {
        showToast('error', 'Connection Error', 'Unable to connect to the server.');
    } finally {
        saveEditBtn.disabled = false;
        saveEditBtn.innerHTML = originalText;
    }
});

loadAppointments();
