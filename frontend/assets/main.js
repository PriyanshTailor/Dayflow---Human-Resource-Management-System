document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('loginForm');
    const loginInput = document.getElementById('loginId');
    const passwordInput = document.getElementById('password');
    const alertBox = document.getElementById('alertBox');

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const idRegex = /^[A-Za-z0-9._-]{3,}$/;

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const errors = [];
        const loginValue = loginInput.value.trim();
        const passwordValue = passwordInput.value.trim();

        if (!loginValue) {
            errors.push('Login ID or email is required.');
        } else if (loginValue.includes('@')) {
            if (!emailRegex.test(loginValue)) {
                errors.push('Enter a valid email address.');
            }
        } else if (!idRegex.test(loginValue)) {
            errors.push('Login ID must be at least 3 characters and can include letters, numbers, dots, underscores, or hyphens.');
        }

        if (!passwordValue) {
            errors.push('Password is required.');
        } else if (passwordValue.length < 8) {
            errors.push('Password must be at least 8 characters long.');
        }

        if (errors.length) {
            alertBox.classList.remove('d-none', 'alert-success');
            alertBox.classList.add('alert-danger');
            alertBox.innerHTML = '<ul class="mb-0">' + errors.map(function (err) {
                return '<li>' + err + '</li>';
            }).join('') + '</ul>';
            return;
        }

        alertBox.classList.remove('d-none', 'alert-danger');
        alertBox.classList.add('alert-success');
        alertBox.textContent = 'Looks good. Submitting...';

        form.submit();
    });
});
