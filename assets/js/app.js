(() => {
    const initAuthForm = () => {
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

            if (field.error) {
                field.error.textContent = message;
            }

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

                if (summary) {
                    summary.hidden = false;
                    summary.focus();
                }

                return;
            }

            if (summary) {
                summary.hidden = true;
            }
        });
    };

    const initSidebar = () => {
        const sidebar = document.getElementById('app-sidebar');
        const toggle = document.querySelector('[data-sidebar-toggle]');
        const backdrop = document.querySelector('[data-sidebar-backdrop]');

        if (!sidebar || !toggle || !backdrop) {
            return;
        }

        const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

        const openSidebar = () => {
            document.body.classList.add('sidebar-open');
            sidebar.setAttribute('aria-hidden', 'false');
            toggle.setAttribute('aria-expanded', 'true');
            backdrop.hidden = false;

            const firstNavigationLink = sidebar.querySelector('.nav-link');

            if (firstNavigationLink) {
                firstNavigationLink.focus();
            }
        };

        const closeSidebar = (restoreFocus = true) => {
            document.body.classList.remove('sidebar-open');
            sidebar.setAttribute('aria-hidden', 'true');
            toggle.setAttribute('aria-expanded', 'false');
            backdrop.hidden = true;

            if (restoreFocus) {
                toggle.focus();
            }
        };

        toggle.addEventListener('click', openSidebar);
        backdrop.addEventListener('click', () => closeSidebar());
        sidebar.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse') {
                closeSidebar();
            }
        });

        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => closeSidebar(false));
        });

        document.addEventListener('keydown', (event) => {
            if (!document.body.classList.contains('sidebar-open')) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeSidebar();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusableElements = Array.from(sidebar.querySelectorAll(focusableSelector));
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (!firstElement || !lastElement) {
                return;
            }

            if (event.shiftKey && document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            } else if (!event.shiftKey && document.activeElement === lastElement) {
                event.preventDefault();
                firstElement.focus();
            }
        });
    };

    const initSearchFilters = () => {
        const form = document.querySelector('[data-search-filters]');

        if (!form) {
            return;
        }

        const toggle = form.querySelector('[data-filter-toggle]');
        const panel = form.querySelector('[data-filter-panel]');

        if (!toggle || !panel) {
            return;
        }

        const closeFilters = (restoreFocus = false) => {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open search filters');

            if (restoreFocus) {
                toggle.focus();
            }
        };

        const openFilters = () => {
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', 'Close search filters');

            const firstSelect = panel.querySelector('select');

            if (firstSelect) {
                firstSelect.focus();
            }
        };

        toggle.addEventListener('click', () => {
            if (panel.hidden) {
                openFilters();
            } else {
                closeFilters(true);
            }
        });

        document.addEventListener('pointerdown', (event) => {
            if (!panel.hidden && event.target instanceof Node && !form.contains(event.target)) {
                closeFilters();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !panel.hidden) {
                event.preventDefault();
                closeFilters(true);
            }
        });
    };

    const initRecordListLoading = () => {
        const form = document.querySelector('[data-search-filters]');
        const listRegion = document.querySelector('[data-list-region]');
        const loadingState = document.querySelector('[data-list-loading]');

        if (!form || !listRegion || !loadingState) {
            return;
        }

        form.addEventListener('submit', () => {
            loadingState.hidden = false;
            listRegion.setAttribute('aria-busy', 'true');
        });
    };

    const initDocumentForm = () => {
        const form = document.querySelector('[data-document-form]');

        if (!form) {
            return;
        }

        const direction = form.querySelector('[data-direction-select]');
        const partyLabel = form.querySelector('[data-party-label]');
        const transactionLabel = form.querySelector('[data-transaction-label]');
        const submitButton = form.querySelector('[data-document-submit]');
        const submitLabel = form.querySelector('[data-submit-label]');

        const updateDirectionLabels = () => {
            if (!direction || !partyLabel || !transactionLabel) {
                return;
            }

            if (direction.value === 'Incoming') {
                partyLabel.firstChild.textContent = 'Received from / Sender ';
                transactionLabel.firstChild.textContent = 'Date received ';
            } else if (direction.value === 'Outgoing') {
                partyLabel.firstChild.textContent = 'Sent to / Recipient ';
                transactionLabel.firstChild.textContent = 'Date released ';
            } else {
                partyLabel.firstChild.textContent = 'Sender / Recipient ';
                transactionLabel.firstChild.textContent = 'Date received / released ';
            }
        };

        if (direction) {
            direction.addEventListener('change', updateDirectionLabels);
            updateDirectionLabels();
        }

        form.addEventListener('submit', (event) => {
            queueMicrotask(() => {
                if (event.defaultPrevented || !submitButton || !submitLabel) {
                    return;
                }

                submitButton.disabled = true;
                submitLabel.textContent = 'Saving document';
                form.setAttribute('aria-busy', 'true');
            });
        });
    };

    initAuthForm();
    initSidebar();
    initSearchFilters();
    initRecordListLoading();
    initDocumentForm();
})();
