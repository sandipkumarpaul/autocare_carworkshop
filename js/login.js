const loginForm = document.getElementById('loginForm');
const loginBtn = document.getElementById('loginBtn');
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

redirectIfLoggedIn();

document.getElementById('helpToggle').addEventListener('click', function () {
    this.classList.toggle('active');
    document.getElementById('helpContent').classList.toggle('active');
});

['email', 'password'].forEach(id => {
    const field = document.getElementById(id);
    field.addEventListener('input', () => {
        if (field.classList.contains('error')) clearFieldError(id);
    });
});

loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    let hasError = false;
    if (!EMAIL_PATTERN.test(email)) {
        setFieldError('email');
        hasError = true;
    } else {
        clearFieldError('email');
    }
    if (!password) {
        setFieldError('password');
        hasError = true;
    } else {
        clearFieldError('password');
    }
    if (hasError) return;

    loginBtn.disabled = true;
    const originalText = loginBtn.innerHTML;
    loginBtn.innerHTML = '<span class="spinner"></span> Logging in...';
    try {
        const { result } = await apiRequest('auth.php', 'POST', { action: 'login', email, password });
        if (result.success) {
            showToast('success', 'Welcome!', `Logged in as ${result.data.name}`);
            setTimeout(() => {
                window.location.href = homePageFor(result.data.role);
            }, 800);
            return;
        }
        showToast('error', 'Login Failed', result.message);
    } catch (error) {
        showToast('error', 'Connection Error', 'Unable to reach the server. Please try again later.');
    }
    loginBtn.disabled = false;
    loginBtn.innerHTML = originalText;
});
