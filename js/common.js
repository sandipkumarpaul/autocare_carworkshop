// Helpers shared by every page.
const API_BASE = 'api';

// Calls an API endpoint and returns { status, result }. `result` is the parsed JSON body.
async function apiRequest(path, method = 'GET', body = null) {
    const options = { method, headers: {} };
    if (body !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }
    const response = await fetch(`${API_BASE}/${path}`, options);
    const text = await response.text();
    try {
        return { status: response.status, result: JSON.parse(text) };
    } catch (e) {
        console.error(`Unexpected response from ${path}:`, text);
        throw new Error('Invalid server response');
    }
}

function homePageFor(role) {
    return role === 'admin' ? 'admin.html' : 'index.html';
}

// Used on the login/signup pages: skip the form if a session already exists.
async function redirectIfLoggedIn() {
    try {
        const { result } = await apiRequest('auth.php', 'POST', { action: 'check' });
        if (result.authenticated) {
            window.location.href = homePageFor(result.data.role);
        }
    } catch (e) {
        // Server unavailable — stay on the current page.
    }
}

function showToast(type, title, message, duration = 5000) {
    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">${icons[type] || icons.info}</div>
        <div class="toast-content">
            <div class="toast-title"></div>
            <div class="toast-message"></div>
        </div>
        <button class="toast-close" type="button" aria-label="Close">&times;</button>
    `;
    toast.querySelector('.toast-title').textContent = title;
    toast.querySelector('.toast-message').textContent = message;
    toast.querySelector('.toast-close').addEventListener('click', () => removeToast(toast));
    document.getElementById('toastContainer').appendChild(toast);
    setTimeout(() => removeToast(toast), duration);
}

function removeToast(toast) {
    if (!toast || toast.classList.contains('removing')) return;
    toast.classList.add('removing');
    setTimeout(() => toast.remove(), 300);
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

// Today's date as YYYY-MM-DD in the user's local timezone.
function todayStr() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function formatDate(dateStr) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', options);
}

function setFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorEl = document.getElementById(fieldId + 'Error');
    field.classList.add('error');
    if (errorEl) {
        if (message) errorEl.textContent = message;
        errorEl.classList.add('visible');
    }
}

function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorEl = document.getElementById(fieldId + 'Error');
    field.classList.remove('error');
    if (errorEl) errorEl.classList.remove('visible');
}

function clearAllFieldErrors(root = document) {
    root.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
    root.querySelectorAll('.error-msg').forEach(el => el.classList.remove('visible'));
}

// Fills a <select> with mechanics and their free slots for a date.
// Admin edit options: `currentMechanicId` stays selectable even when its day is full
// (it already holds this appointment's slot); `selectedId` is preselected if it is free.
async function loadMechanicOptions(select, infoBox, infoText, date, { currentMechanicId = null, selectedId = null } = {}) {
    select.innerHTML = '<option value="">Loading mechanics...</option>';
    select.disabled = true;
    try {
        const { result } = await apiRequest(`mechanics.php?date=${encodeURIComponent(date)}`);
        if (!result.success) {
            select.innerHTML = '<option value="">Failed to load mechanics</option>';
            showToast('error', 'Error', result.message || 'Could not load mechanics list.');
            return;
        }
        select.innerHTML = '<option value="">— Choose a mechanic —</option>';
        result.data.forEach(mechanic => {
            const option = document.createElement('option');
            const isCurrent = currentMechanicId !== null && mechanic.id == currentMechanicId;
            const currentLabel = isCurrent ? ' ★ Current' : '';
            option.value = mechanic.id;
            if (mechanic.available_slots <= 0 && !isCurrent) {
                option.textContent = `${mechanic.name} — FULLY BOOKED (${mechanic.specialization})`;
                option.disabled = true;
            } else {
                option.textContent = `${mechanic.name} — ${mechanic.available_slots}/${result.max_per_day} slots free (${mechanic.specialization})${currentLabel}`;
            }
            option.selected = !option.disabled && mechanic.id == selectedId;
            select.appendChild(option);
        });
        infoBox.style.display = 'flex';
        infoText.textContent = `Showing availability for ${formatDate(date)}. Each mechanic can take up to ${result.max_per_day} appointments per day.`;
    } catch (error) {
        select.innerHTML = '<option value="">Server unavailable</option>';
        showToast('error', 'Connection Error', 'Unable to connect to the server. Please try again.');
    } finally {
        select.disabled = false;
    }
}
