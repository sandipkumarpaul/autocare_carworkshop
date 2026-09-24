// Protects a page: redirects to login when logged out, and to the right
// dashboard when the page's data-role doesn't match the user's role.
const requiredRole = document.currentScript ? document.currentScript.getAttribute('data-role') : null;

async function logoutUser() {
    try {
        await apiRequest('auth.php', 'POST', { action: 'logout' });
    } catch (e) {
        // Redirect to login regardless.
    }
    window.location.href = 'login.html';
}

(async function authGuard() {
    try {
        const { result } = await apiRequest('auth.php', 'POST', { action: 'check' });
        if (!result.authenticated) {
            window.location.href = 'login.html';
            return;
        }
        if (requiredRole && result.data.role !== requiredRole) {
            window.location.href = homePageFor(result.data.role);
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
