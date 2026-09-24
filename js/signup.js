const signupForm = document.getElementById('signupForm');
const signupBtn = document.getElementById('signupBtn');
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const NAME_PATTERN = /^[a-zA-Z\s.'\-]+$/;

redirectIfLoggedIn();

['name', 'email', 'password', 'confirmPassword'].forEach(id => {
    const field = document.getElementById(id);
    field.addEventListener('input', () => {
        if (field.classList.contains('error')) clearFieldError(id);
    });
});

signupForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    let hasError = false;
    const check = (id, isValid, message) => {
        if (isValid) {
            clearFieldError(id);
        } else {
            setFieldError(id, message);
            hasError = true;
        }
    };
    check('name', NAME_PATTERN.test(name), 'Name should contain only letters, spaces, dots, and hyphens.');
    check('email', EMAIL_PATTERN.test(email), 'Please enter a valid email address.');
    check('password', password.length >= 6, 'Password must be at least 6 characters.');
    check('confirmPassword', confirmPassword !== '' && password === confirmPassword, 'Passwords do not match.');
    if (hasError) {
        showToast('warning', 'Validation Error', 'Please fix the highlighted fields.');
        return;
    }

    signupBtn.disabled = true;
    const originalText = signupBtn.innerHTML;
    signupBtn.innerHTML = '<span class="spinner"></span> Creating account...';
    try {
        const { status, result } = await apiRequest('auth.php', 'POST', {
            action: 'signup',
            name,
            email,
            password,
            confirm_password: confirmPassword
        });
        if (result.success) {
            showToast('success', 'Account Created!', result.message);
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1000);
            return;
        }
        showToast(status === 409 ? 'warning' : 'error', 'Signup Failed', result.message);
    } catch (error) {
        showToast('error', 'Connection Error', 'Unable to reach the server. Please try again later.');
    }
    signupBtn.disabled = false;
    signupBtn.innerHTML = originalText;
});
