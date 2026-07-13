
const AUTH_API = 'api/auth.php';
const currentScript = document.currentScript;
const requiredRole = currentScript ? currentScript.getAttribute('data-role') : null;
let currentUser = null;
async function logoutUser() {
    try {
        await fetch(AUTH_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'logout' })
        });
    } catch (e) {
    }
    window.location.href = 'login.html';
}
(async function authGuard() {
    try {
        const response = await fetch(AUTH_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'check' })
        });
        const result = await response.json();
        if (!result.authenticated) {
            window.location.href = 'login.html';
            return;
        }
        currentUser = result.data;
        if (requiredRole && result.data.role !== requiredRole) {
            if (result.data.role === 'admin') {
                window.location.href = 'admin.html';
            } else {
                window.location.href = 'index.html';
            }
            return;
        }
        const navUser = document.getElementById('navUser');
        if (navUser) {
            navUser.textContent = `👤 ${result.data.name}`;
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = 'login.html';
    }
})();
