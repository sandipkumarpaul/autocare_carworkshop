
const API_BASE = 'api';
const appointmentTableBody = document.getElementById('appointmentTableBody');
const emptyState = document.getElementById('emptyState');
const tableWrapper = document.querySelector('.table-wrapper');
const searchInput = document.getElementById('searchInput');
const toastContainer = document.getElementById('toastContainer');
const editModal = document.getElementById('editModal');
const editForm = document.getElementById('editForm');
const editDateInput = document.getElementById('editDate');
const editMechanicSelect = document.getElementById('editMechanic');
const editMechanicInfo = document.getElementById('editMechanicInfo');
const editMechanicInfoText = document.getElementById('editMechanicInfoText');
const saveEditBtn = document.getElementById('saveEditBtn');
const statTotal = document.getElementById('statTotal');
const statToday = document.getElementById('statToday');
let allAppointments = [];
function showToast(type, title, message, duration = 5000) {
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">${icons[type] || 'ℹ'}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="removeToast(this.parentElement)">&times;</button>
    `;
    toastContainer.appendChild(toast);
    setTimeout(() => removeToast(toast), duration);
}
function removeToast(toast) {
    if (!toast || toast.classList.contains('removing')) return;
    toast.classList.add('removing');
    setTimeout(() => toast.remove(), 300);
}
function formatDate(dateStr) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', options);
}
function getTodayStr() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}
async function loadAppointments() {
    appointmentTableBody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                <span class="spinner" style="border-color: rgba(148,163,184,0.3); border-top-color: var(--accent-blue);"></span>
                <span style="margin-left: 12px;">Loading appointments...</span>
            </td>
        </tr>
    `;
    try {
        const response = await fetch(`${API_BASE}/admin.php`);
        const result = await response.json();
        if (result.success) {
            allAppointments = result.data || [];
            updateStats();
            renderAppointments(allAppointments);
        } else {
            showToast('error', 'Error', result.message || 'Failed to load appointments.');
            appointmentTableBody.innerHTML = '';
            showEmptyState(true);
        }
    } catch (error) {
        showToast('error', 'Connection Error', 'Unable to connect to the server.');
        console.error('Load appointments error:', error);
        appointmentTableBody.innerHTML = '';
        showEmptyState(true);
    }
}
function updateStats() {
    const todayStr = getTodayStr();
    const todayCount = allAppointments.filter(a => a.appointment_date === todayStr).length;
    statTotal.textContent = allAppointments.length;
    statToday.textContent = todayCount;
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
            <td style="color: var(--text-muted); font-weight: 500;">${index + 1}</td>
            <td>
                <div style="font-weight: 600;">${escapeHtml(appt.client_name)}</div>
            </td>
            <td style="font-family: monospace; font-size: 0.85rem;">${escapeHtml(appt.phone)}</td>
            <td style="font-family: monospace; font-size: 0.85rem;">${escapeHtml(appt.car_license)}</td>
            <td>
                <span class="date-badge">📅 ${formatDate(appt.appointment_date)}</span>
            </td>
            <td>
                <span class="mechanic-badge">👨‍🔧 ${escapeHtml(appt.mechanic_name)}</span>
            </td>
            <td>
                <button class="btn btn-primary btn-sm" onclick="openEditModal(${appt.id})">
                    ✏️ Edit
                </button>
            </td>
        </tr>
    `).join('');
}
function showEmptyState(show) {
    emptyState.style.display = show ? 'block' : 'none';
    tableWrapper.style.display = show ? 'none' : 'block';
}
searchInput.addEventListener('input', () => {
    const query = searchInput.value.toLowerCase().trim();
    if (!query) {
        renderAppointments(allAppointments);
        return;
    }
    const filtered = allAppointments.filter(appt =>
        appt.client_name.toLowerCase().includes(query) ||
        appt.phone.toLowerCase().includes(query) ||
        appt.car_license.toLowerCase().includes(query) ||
        appt.mechanic_name.toLowerCase().includes(query) ||
        appt.appointment_date.includes(query)
    );
    renderAppointments(filtered);
});
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
function openEditModal(appointmentId) {
    const appt = allAppointments.find(a => a.id == appointmentId);
    if (!appt) {
        showToast('error', 'Error', 'Appointment not found.');
        return;
    }
    document.getElementById('editAppointmentId').value = appt.id;
    document.getElementById('editClientName').value = appt.client_name;
    document.getElementById('editPhone').value = appt.phone;
    document.getElementById('editCarLicense').value = appt.car_license;
    editDateInput.value = appt.appointment_date;
    loadEditMechanics(appt.appointment_date, appt.mechanic_id);
    editModal.classList.add('active');
    document.querySelectorAll('#editForm .error').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('#editForm .error-msg').forEach(el => el.classList.remove('visible'));
}
function closeEditModal() {
    editModal.classList.remove('active');
}
editModal.addEventListener('click', (e) => {
    if (e.target === editModal) closeEditModal();
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && editModal.classList.contains('active')) {
        closeEditModal();
    }
});
editDateInput.addEventListener('change', () => {
    const currentMechanicId = editMechanicSelect.value;
    loadEditMechanics(editDateInput.value, currentMechanicId);
});
async function loadEditMechanics(date, selectedMechanicId) {
    if (!date) return;
    editMechanicSelect.innerHTML = '<option value="">Loading...</option>';
    editMechanicSelect.disabled = true;
    try {
        const response = await fetch(`${API_BASE}/mechanics.php?date=${encodeURIComponent(date)}`);
        const result = await response.json();
        if (result.success && result.data) {
            editMechanicSelect.innerHTML = '<option value="">— Choose a mechanic —</option>';
            result.data.forEach(mechanic => {
                const option = document.createElement('option');
                option.value = mechanic.id;
                const availableSlots = parseInt(mechanic.available_slots);
                const totalSlots = result.max_per_day;
                const currentLabel = (mechanic.id == selectedMechanicId) ? ' ★ Current' : '';
                if (availableSlots <= 0 && mechanic.id != selectedMechanicId) {
                    option.textContent = `${mechanic.name} — FULLY BOOKED${currentLabel}`;
                    option.disabled = true;
                } else {
                    option.textContent = `${mechanic.name} — ${availableSlots}/${totalSlots} slots free${currentLabel}`;
                }
                if (mechanic.id == selectedMechanicId) {
                    option.selected = true;
                }
                editMechanicSelect.appendChild(option);
            });
            editMechanicInfo.style.display = 'flex';
            editMechanicInfoText.textContent = `Availability shown for ${date}. Max ${result.max_per_day} appointments per mechanic.`;
        } else {
            editMechanicSelect.innerHTML = '<option value="">Failed to load</option>';
        }
    } catch (error) {
        editMechanicSelect.innerHTML = '<option value="">Server error</option>';
        console.error('Load edit mechanics error:', error);
    } finally {
        editMechanicSelect.disabled = false;
    }
}
editForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const appointmentId = document.getElementById('editAppointmentId').value;
    const newDate = editDateInput.value;
    const newMechanicId = editMechanicSelect.value;
    let hasError = false;
    if (!newDate) {
        editDateInput.classList.add('error');
        document.getElementById('editDateError').classList.add('visible');
        hasError = true;
    } else {
        editDateInput.classList.remove('error');
        document.getElementById('editDateError').classList.remove('visible');
    }
    if (!newMechanicId) {
        editMechanicSelect.classList.add('error');
        document.getElementById('editMechanicError').classList.add('visible');
        hasError = true;
    } else {
        editMechanicSelect.classList.remove('error');
        document.getElementById('editMechanicError').classList.remove('visible');
    }
    if (hasError) {
        showToast('warning', 'Validation Error', 'Please fill in all required fields.');
        return;
    }
    saveEditBtn.disabled = true;
    const originalText = saveEditBtn.innerHTML;
    saveEditBtn.innerHTML = '<span class="spinner"></span> Saving...';
    try {
        const response = await fetch(`${API_BASE}/admin.php`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                appointment_id: parseInt(appointmentId),
                appointment_date: newDate,
                mechanic_id: parseInt(newMechanicId)
            })
        });
        const result = await response.json();
        if (result.success) {
            showToast('success', 'Updated!', result.message, 6000);
            closeEditModal();
            loadAppointments();
        } else {
            const toastType = response.status === 409 ? 'warning' : 'error';
            showToast(toastType, 'Update Failed', result.message, 6000);
        }
    } catch (error) {
        showToast('error', 'Connection Error', 'Unable to connect to the server.');
        console.error('Save edit error:', error);
    } finally {
        saveEditBtn.disabled = false;
        saveEditBtn.innerHTML = originalText;
    }
});
loadAppointments();
