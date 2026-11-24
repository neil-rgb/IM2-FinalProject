(() => {
    const form = document.querySelector('[data-auth-form]');

    if (!form) {
        return;
    }

    const summary = document.getElementById('validation-summary');
    const fields = Array.from(form.querySelectorAll('[required]')).map((input) => ({
        input,
        error: document.getElementById(`${input.id}-error`),
        message: input.dataset.errorMessage || 'This field is required.'
    }));

    const validateField = (field) => {
        let message = '';

        if (field.input.value.trim() === '') {
            message = field.message;
        } else if (field.input.dataset.match) {
            const comparisonField = document.getElementById(field.input.dataset.match);

            if (comparisonField && field.input.value !== comparisonField.value) {
                message = field.input.dataset.matchMessage || 'The values do not match.';
            }
        }

        const isValid = message === '';
        field.input.setAttribute('aria-invalid', String(!isValid));
        field.error.textContent = message;
        return isValid;
    };

    fields.forEach((field) => {
        field.input.addEventListener('blur', (event) => {
            const nextControl = event.relatedTarget;
            const isItsPasswordToggle = nextControl instanceof HTMLElement
                && nextControl.hasAttribute('data-password-toggle')
                && nextControl.getAttribute('aria-controls') === field.input.id;

            if (!isItsPasswordToggle) {
                validateField(field);
            }
        });
        field.input.addEventListener('input', () => {
            if (field.input.getAttribute('aria-invalid') === 'true') {
                validateField(field);
            }
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((passwordToggle) => {
        passwordToggle.addEventListener('click', () => {
            const password = document.getElementById(passwordToggle.getAttribute('aria-controls'));

            if (!password) {
                return;
            }

            const willShow = password.type === 'password';
            password.type = willShow ? 'text' : 'password';
            passwordToggle.setAttribute('aria-pressed', String(willShow));
            passwordToggle.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
            passwordToggle.querySelector('.eye-icon--show').hidden = willShow;
            passwordToggle.querySelector('.eye-icon--hide').hidden = !willShow;
        });
    });

    form.addEventListener('submit', (event) => {
        const invalidFields = fields.filter((field) => !validateField(field));

        if (invalidFields.length > 0) {
            event.preventDefault();
            summary.hidden = false;
            summary.focus();
            return;
        }

        summary.hidden = true;
    });
})();
