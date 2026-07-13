
const API_BASE = 'api';
const form = document.getElementById('appointmentForm');
const submitBtn = document.getElementById('submitBtn');
const resetBtn = document.getElementById('resetBtn');
const dateInput = document.getElementById('appointmentDate');
const mechanicSelect = document.getElementById('mechanicSelect');
const mechanicInfo = document.getElementById('mechanicInfo');
const mechanicInfoText = document.getElementById('mechanicInfoText');
const helpToggle = document.getElementById('helpToggle');
const helpContent = document.getElementById('helpContent');
const toastContainer = document.getElementById('toastContainer');
(function setMinDate() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    dateInput.setAttribute('min', `${yyyy}-${mm}-${dd}`);
})();
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
    setTimeout(() => {
        removeToast(toast);
    }, duration);
}
function removeToast(toast) {
    if (!toast || toast.classList.contains('removing')) return;
    toast.classList.add('removing');
    setTimeout(() => {
        toast.remove();
    }, 300);
}
helpToggle.addEventListener('click', () => {
    helpToggle.classList.toggle('active');
    helpContent.classList.toggle('active');
});
const validators = {
    clientName: (value) => {
        if (!value.trim()) return 'Full name is required.';
        if (!/^[a-zA-Z\s.'\-]+$/.test(value.trim())) return 'Name should contain only letters, spaces, dots, and hyphens.';
        return null;
    },
    phone: (value) => {
        if (!value.trim()) return 'Phone number is required.';
        if (!/^[0-9]{7,15}$/.test(value.trim())) return 'Phone must contain only digits (7-15 digits).';
        return null;
    },
    address: (value) => {
        if (!value.trim()) return 'Address is required.';
        return null;
    },
    carLicense: (value) => {
        if (!value.trim()) return 'Car license number is required.';
        return null;
    },
    carEngine: (value) => {
        if (!value.trim()) return 'Car engine number is required.';
        if (!/^[a-zA-Z0-9\-]+$/.test(value.trim())) return 'Engine number must be alphanumeric (letters, digits, hyphens only).';
        return null;
    },
    appointmentDate: (value) => {
        if (!value) return 'Appointment date is required.';
        const selectedDate = new Date(value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) return 'Appointment date cannot be in the past.';
        return null;
    },
    mechanicSelect: (value) => {
        if (!value || value === '') return 'Please select a mechanic.';
        return null;
    }
};
function validateField(fieldId) {
    const field = document.getElementById(fieldId);
    const errorEl = document.getElementById(fieldId + 'Error');
    const validator = validators[fieldId];
    if (!validator || !field) return true;
    const error = validator(field.value);
    if (error) {
        field.classList.add('error');
        if (errorEl) {
            errorEl.textContent = error;
            errorEl.classList.add('visible');
        }
        return false;
    } else {
        field.classList.remove('error');
        if (errorEl) {
            errorEl.classList.remove('visible');
        }
        return true;
    }
}
function validateAll() {
    let isValid = true;
    for (const fieldId of Object.keys(validators)) {
        if (!validateField(fieldId)) {
            isValid = false;
        }
    }
    return isValid;
}
Object.keys(validators).forEach(fieldId => {
    const field = document.getElementById(fieldId);
    if (field) {
        field.addEventListener('blur', () => validateField(fieldId));
        field.addEventListener('input', () => {
            if (field.classList.contains('error')) {
                validateField(fieldId);
            }
        });
    }
});
dateInput.addEventListener('change', loadMechanics);
async function loadMechanics() {
    const date = dateInput.value;
    if (!date) {
        mechanicSelect.innerHTML = '<option value="">— Select a date first —</option>';
        mechanicInfo.style.display = 'none';
        return;
    }
    if (!validateField('appointmentDate')) return;
    mechanicSelect.innerHTML = '<option value="">Loading mechanics...</option>';
    mechanicSelect.disabled = true;
    try {
        const response = await fetch(`${API_BASE}/mechanics.php?date=${encodeURIComponent(date)}`);
        const result = await response.json();
        if (result.success && result.data) {
            mechanicSelect.innerHTML = '<option value="">— Choose a mechanic —</option>';
            result.data.forEach(mechanic => {
                const option = document.createElement('option');
                option.value = mechanic.id;
                const availableSlots = parseInt(mechanic.available_slots);
                const totalSlots = result.max_per_day;
                if (availableSlots <= 0) {
                    option.textContent = `${mechanic.name} — FULLY BOOKED (${mechanic.specialization})`;
                    option.disabled = true;
                    option.style.color = '#64748b';
                } else {
                    option.textContent = `${mechanic.name} — ${availableSlots}/${totalSlots} slots free (${mechanic.specialization})`;
                }
                mechanicSelect.appendChild(option);
            });
            mechanicInfo.style.display = 'flex';
            mechanicInfoText.textContent = `Showing availability for ${date}. Each mechanic can accept up to ${result.max_per_day} appointments per day.`;
        } else {
            mechanicSelect.innerHTML = '<option value="">Failed to load mechanics</option>';
            showToast('error', 'Error', result.message || 'Could not load mechanics list.');
        }
    } catch (error) {
        mechanicSelect.innerHTML = '<option value="">Server unavailable</option>';
        showToast('error', 'Connection Error', 'Unable to connect to the server. Please check your connection and try again.');
        console.error('Fetch mechanics error:', error);
    } finally {
        mechanicSelect.disabled = false;
    }
}
const appointmentsList = document.getElementById('appointmentsList');
console.log('[AutoCare Debug] app.js loaded. appointmentsList element is:', appointmentsList);
async function loadUserAppointments() {
    console.log('[AutoCare Debug] loadUserAppointments() invoked.');
    if (!appointmentsList) {
        console.warn('[AutoCare Debug] appointmentsList is NULL. Returning early.');
        return;
    }
    try {
        const response = await fetch(`${API_BASE}/appointments.php`);
        const result = await response.json();
        if (result.success) {
            renderUserAppointments(result.data || []);
        } else {
            appointmentsList.innerHTML = `<div style="text-align: center; color: var(--accent-red); padding: 1.5rem;">${result.message || 'Failed to load appointments.'}</div>`;
        }
    } catch (error) {
        console.error('Fetch user appointments error:', error);
        appointmentsList.innerHTML = '<div style="text-align: center; color: var(--accent-red); padding: 1.5rem;">Connection error.</div>';
    }
}
function renderUserAppointments(appointments) {
    if (appointments.length === 0) {
        appointmentsList.innerHTML = `
            <div style="text-align: center; color: var(--text-muted); padding: 3rem 1.5rem;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem; opacity: 0.5;">📅</div>
                <h4>No Scheduled Services</h4>
                <p style="font-size: 0.8rem; margin-top: 4px;">Use the booking form to schedule a mechanic.</p>
            </div>
        `;
        return;
    }
    appointmentsList.innerHTML = appointments.map(appt => {
        const dateOptions = { year: 'numeric', month: 'short', day: 'numeric' };
        const displayDate = new Date(appt.appointment_date + 'T00:00:00').toLocaleDateString('en-US', dateOptions);
        let warningHtml = '';
        if (parseInt(appt.is_updated_by_admin) === 1) {
            warningHtml = `
                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-sm); padding: 10px 12px; margin-bottom: 12px; display: flex; flex-direction: column; gap: 8px; animation: fadeIn 0.4s ease-out;">
                    <div style="display: flex; align-items: center; gap: 6px; color: var(--accent-orange); font-size: 0.8rem; font-weight: 600;">
                        <span>⚠️ Rescheduled by Admin</span>
                    </div>
                    <p style="color: var(--text-secondary); font-size: 0.75rem; line-height: 1.3;">
                        The workshop manager updated the date or assigned mechanic for this booking.
                    </p>
                    <button class="btn btn-secondary btn-sm" style="font-size: 0.7rem; padding: 4px 8px; align-self: flex-start;" onclick="dismissAppointmentAlert(${appt.id})">
                        Acknowledge
                    </button>
                </div>
            `;
        }
        return `
            <div class="card" style="padding: 1.25rem; background: rgba(255,255,255,0.015); border-color: rgba(255,255,255,0.04); margin-bottom: 0.5rem; animation: fadeInUp 0.4s ease-out;">
                ${warningHtml}
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                    <div>
                        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary);">${escapeHtml(appt.client_name)}</h4>
                        <p style="font-size: 0.75rem; color: var(--text-muted);">${escapeHtml(appt.car_license)}</p>
                    </div>
                    <span class="date-badge" style="font-size: 0.75rem;">📅 ${displayDate}</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: var(--text-secondary); border-top: 1px solid rgba(255,255,255,0.04); padding-top: 8px; margin-top: 8px;">
                    <span style="color: var(--accent-purple);">👨‍🔧 ${escapeHtml(appt.mechanic_name)}</span>
                    <span style="color: var(--text-muted); font-size: 0.75rem;">(${escapeHtml(appt.mechanic_specialization)})</span>
                </div>
            </div>
        `;
    }).join('');
}
async function dismissAppointmentAlert(apptId) {
    try {
        const response = await fetch(`${API_BASE}/appointments.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'dismiss_notification',
                appointment_id: apptId
            })
        });
        const result = await response.json();
        if (result.success) {
            loadUserAppointments();
        } else {
            showToast('error', 'Error', result.message || 'Failed to dismiss alert.');
        }
    } catch (error) {
        showToast('error', 'Connection Error', 'Failed to connect to the server.');
        console.error('Dismiss alert error:', error);
    }
}
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!validateAll()) {
        showToast('warning', 'Validation Error', 'Please fix the highlighted fields before submitting.');
        return;
    }
    submitBtn.disabled = true;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner"></span> Booking...';
    const data = {
        client_name: document.getElementById('clientName').value.trim(),
        address: document.getElementById('address').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        car_license: document.getElementById('carLicense').value.trim(),
        car_engine: document.getElementById('carEngine').value.trim(),
        appointment_date: dateInput.value,
        mechanic_id: parseInt(mechanicSelect.value)
    };
    try {
        const response = await fetch(`${API_BASE}/appointments.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
            showToast('success', 'Appointment Booked!', result.message, 7000);
            form.reset();
            mechanicSelect.innerHTML = '<option value="">— Select a date first —</option>';
            mechanicInfo.style.display = 'none';
            document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.error-msg').forEach(el => el.classList.remove('visible'));
            loadUserAppointments();
        } else {
            const toastType = response.status === 409 ? 'warning' : 'error';
            showToast(toastType, 'Booking Failed', result.message, 7000);
        }
    } catch (error) {
        showToast('error', 'Connection Error', 'Unable to connect to the server. Please try again later.');
        console.error('Submit error:', error);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});
form.addEventListener('reset', () => {
    setTimeout(() => {
        document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
        document.querySelectorAll('.error-msg').forEach(el => el.classList.remove('visible'));
        mechanicSelect.innerHTML = '<option value="">— Select a date first —</option>';
        mechanicInfo.style.display = 'none';
    }, 10);
});
loadUserAppointments();
