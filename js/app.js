// Client booking page: appointment form + "My Appointments" list.
const form = document.getElementById('appointmentForm');
const submitBtn = document.getElementById('submitBtn');
const dateInput = document.getElementById('appointmentDate');
const mechanicSelect = document.getElementById('mechanicSelect');
const mechanicInfo = document.getElementById('mechanicInfo');
const mechanicInfoText = document.getElementById('mechanicInfoText');
const helpToggle = document.getElementById('helpToggle');
const helpContent = document.getElementById('helpContent');
const appointmentsList = document.getElementById('appointmentsList');

dateInput.min = todayStr();

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
        // Both are YYYY-MM-DD, so string comparison is a date comparison.
        if (value < todayStr()) return 'Appointment date cannot be in the past.';
        return null;
    },
    mechanicSelect: (value) => {
        if (!value) return 'Please select a mechanic.';
        return null;
    }
};

function validateField(fieldId) {
    const error = validators[fieldId](document.getElementById(fieldId).value);
    if (error) {
        setFieldError(fieldId, error);
        return false;
    }
    clearFieldError(fieldId);
    return true;
}

function validateAll() {
    // Validate every field (no short-circuit) so all errors are shown at once.
    return Object.keys(validators).map(validateField).every(Boolean);
}

Object.keys(validators).forEach(fieldId => {
    const field = document.getElementById(fieldId);
    field.addEventListener('blur', () => validateField(fieldId));
    field.addEventListener('input', () => {
        if (field.classList.contains('error')) validateField(fieldId);
    });
});

function resetMechanicSelect() {
    mechanicSelect.innerHTML = '<option value="">— Select a date first —</option>';
    mechanicInfo.style.display = 'none';
}

dateInput.addEventListener('change', () => {
    if (!dateInput.value) {
        resetMechanicSelect();
        return;
    }
    if (!validateField('appointmentDate')) return;
    loadMechanicOptions(mechanicSelect, mechanicInfo, mechanicInfoText, dateInput.value);
});

async function loadUserAppointments() {
    try {
        const { result } = await apiRequest('appointments.php');
        if (result.success) {
            renderUserAppointments(result.data || []);
        } else {
            appointmentsList.innerHTML = `<div class="list-message error-text">${escapeHtml(result.message || 'Failed to load appointments.')}</div>`;
        }
    } catch (error) {
        appointmentsList.innerHTML = '<div class="list-message error-text">Connection error.</div>';
    }
}

function renderUserAppointments(appointments) {
    if (appointments.length === 0) {
        appointmentsList.innerHTML = `
            <div class="list-message">
                <div class="list-message-icon">📅</div>
                <h4>No Scheduled Services</h4>
                <p>Use the booking form to schedule a mechanic.</p>
            </div>
        `;
        return;
    }
    const today = todayStr();
    appointmentsList.innerHTML = appointments.map(appt => {
        const rescheduledNotice = parseInt(appt.is_updated_by_admin) === 1 ? `
            <div class="appointment-alert">
                <strong>⚠️ Rescheduled by Admin</strong>
                <p>The workshop manager updated the date or assigned mechanic for this booking.</p>
                <button class="btn btn-secondary btn-sm" onclick="dismissAppointmentAlert(${appt.id})">Acknowledge</button>
            </div>
        ` : '';
        const isPast = appt.appointment_date < today;
        return `
            <div class="appointment-item${isPast ? ' past' : ''}">
                ${rescheduledNotice}
                <div class="appointment-item-header">
                    <div>
                        <h4>${escapeHtml(appt.client_name)}</h4>
                        <p>${escapeHtml(appt.car_license)}</p>
                    </div>
                    <span class="date-badge">📅 ${formatDate(appt.appointment_date)}${isPast ? ' · Past' : ''}</span>
                </div>
                <div class="appointment-item-mechanic">
                    <span class="mechanic-name">👨‍🔧 ${escapeHtml(appt.mechanic_name)}</span>
                    <span class="mechanic-specialization">(${escapeHtml(appt.mechanic_specialization)})</span>
                </div>
            </div>
        `;
    }).join('');
}

async function dismissAppointmentAlert(appointmentId) {
    try {
        const { result } = await apiRequest('appointments.php', 'POST', {
            action: 'dismiss_notification',
            appointment_id: appointmentId
        });
        if (result.success) {
            loadUserAppointments();
        } else {
            showToast('error', 'Error', result.message || 'Failed to dismiss alert.');
        }
    } catch (error) {
        showToast('error', 'Connection Error', 'Failed to connect to the server.');
    }
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
    try {
        const { status, result } = await apiRequest('appointments.php', 'POST', {
            client_name: document.getElementById('clientName').value.trim(),
            address: document.getElementById('address').value.trim(),
            phone: document.getElementById('phone').value.trim(),
            car_license: document.getElementById('carLicense').value.trim(),
            car_engine: document.getElementById('carEngine').value.trim(),
            appointment_date: dateInput.value,
            mechanic_id: parseInt(mechanicSelect.value)
        });
        if (result.success) {
            showToast('success', 'Appointment Booked!', result.message, 7000);
            form.reset();
            loadUserAppointments();
        } else {
            showToast(status === 409 ? 'warning' : 'error', 'Booking Failed', result.message, 7000);
        }
    } catch (error) {
        showToast('error', 'Connection Error', 'Unable to connect to the server. Please try again later.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

form.addEventListener('reset', () => {
    clearAllFieldErrors(form);
    resetMechanicSelect();
});

loadUserAppointments();
