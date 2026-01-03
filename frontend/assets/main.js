document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    if (!form) return;

    const loginInput = document.getElementById('loginId');
    const passwordInput = document.getElementById('password');
    const alertBox = document.getElementById('alertBox');

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const idRegex = /^[A-Za-z0-9._-]{3,}$/;

    const showAlert = (msg, type = 'danger') => {
        alertBox.classList.remove('d-none', 'alert-danger', 'alert-success');
        alertBox.classList.add(`alert-${type}`);
        alertBox.innerHTML = msg;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const loginValue = loginInput.value.trim();
        const passwordValue = passwordInput.value.trim();
        const errors = [];

        if (!loginValue) {
            errors.push('Login ID or email is required.');
        } else if (loginValue.includes('@')) {
            if (!emailRegex.test(loginValue)) errors.push('Enter a valid email address.');
        } else if (!idRegex.test(loginValue)) {
            errors.push('Login ID must be at least 3 characters and can include letters, numbers, dots, underscores, or hyphens.');
        }

        if (!passwordValue) {
            errors.push('Password is required.');
        }

        if (errors.length) {
            showAlert('<ul class="mb-0">' + errors.map(e => `<li>${e}</li>`).join('') + '</ul>', 'danger');
            return;
        }

        showAlert('Signing in...', 'success');

        try {
            const res = await fetch('/hrms/backend/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ login: loginValue, password: passwordValue })
            });

            const data = await res.json();
            if (!data.success) {
                showAlert(data.message || 'Login failed', 'danger');
                return;
            }

            const role = data.user?.role;
            if (role === 'admin') {
                window.location.href = '/hrms/frontend/admin/dashboard.html';
            } else {
                window.location.href = '/hrms/frontend/employee/dashboard.html';
            }
        } catch (err) {
            console.error(err);
            showAlert('Server error. Please try again.', 'danger');
        }
    });
});
