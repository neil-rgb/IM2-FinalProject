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
        const profileToggle = sidebar?.querySelector('[data-profile-toggle]');
        const profileEditor = sidebar?.querySelector('#sidebar-profile-editor');
        const profileBack = sidebar?.querySelector('[data-profile-back]');

        if (!sidebar || !toggle || !backdrop) {
            return;
        }

        const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

        const openSidebar = () => {
            document.body.classList.add('sidebar-open');
            sidebar.setAttribute('aria-hidden', 'false');
            toggle.setAttribute('aria-expanded', 'true');
            backdrop.hidden = false;

            const profileField = sidebar.querySelector('#sidebar-profile-editor:not([hidden]) input:not([type="hidden"])');
            const firstNavigationLink = sidebar.querySelector('.primary-nav .nav-link');

            if (profileField) {
                profileField.focus();
            } else if (firstNavigationLink) {
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

        const showProfileEditor = () => {
            if (!profileToggle || !profileEditor) {
                return;
            }

            profileEditor.hidden = false;
            sidebar.classList.add('is-editing-profile');
            profileToggle.setAttribute('aria-expanded', 'true');
            profileToggle.classList.add('is-active');
            profileEditor.querySelector('input:not([type="hidden"])')?.focus();
        };

        const hideProfileEditor = () => {
            if (!profileToggle || !profileEditor) {
                return;
            }

            profileEditor.hidden = true;
            sidebar.classList.remove('is-editing-profile');
            profileToggle.setAttribute('aria-expanded', 'false');
            profileToggle.classList.remove('is-active');
            profileToggle.focus();
        };

        toggle.addEventListener('click', openSidebar);
        backdrop.addEventListener('click', () => closeSidebar());
        sidebar.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse') {
                closeSidebar();
            }
        });

        sidebar.querySelectorAll('.nav-link[href]').forEach((link) => {
            link.addEventListener('click', () => closeSidebar(false));
        });

        profileToggle?.addEventListener('click', () => {
            if (profileEditor?.hidden) {
                showProfileEditor();
            } else {
                hideProfileEditor();
            }
        });
        profileBack?.addEventListener('click', hideProfileEditor);

        if (profileEditor && !profileEditor.hidden) {
            const errorSummary = profileEditor.querySelector('.sidebar-profile-error');
            (errorSummary || profileEditor.querySelector('input:not([type="hidden"])'))?.focus();
        }

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

    const initStorageLocationSelect = (root, typeSelector, locationSelector) => {
        const typeSelect = root.querySelector(typeSelector);
        const locationControl = root.querySelector(locationSelector);

        if (!typeSelect || !locationControl) {
            return;
        }

        if (locationControl instanceof HTMLInputElement && locationControl.type === 'radio') {
            const locationChoices = Array.from(root.querySelectorAll(`${locationSelector}[data-location-choice]`));
            const locationGroup = root.querySelector('[data-location-group]');
            const emptyState = root.querySelector('[data-location-empty]');

            const updateLocationChoices = () => {
                const selectedTypeId = typeSelect.value;
                let matchingCount = 0;

                locationChoices.forEach((choice) => {
                    const matchesType = selectedTypeId !== '' && choice.dataset.typeId === selectedTypeId;
                    const label = choice.closest('[data-storage-choice]');

                    if (choice.checked && !matchesType) {
                        choice.checked = false;
                    }

                    choice.disabled = !matchesType;
                    if (label) {
                        label.hidden = !matchesType;
                    }
                    if (matchesType) {
                        matchingCount += 1;
                    }
                });

                if (emptyState) {
                    emptyState.hidden = selectedTypeId !== '' && matchingCount > 0;
                    emptyState.textContent = selectedTypeId === ''
                        ? 'Choose a document category to display matching folders.'
                        : 'No storage folders are configured for this category.';
                }
            };

            locationChoices.forEach((choice) => {
                choice.addEventListener('change', () => {
                    if (!choice.checked) {
                        return;
                    }

                    locationGroup?.setAttribute('aria-invalid', 'false');
                    locationChoices.forEach((locationChoice) => locationChoice.setAttribute('aria-invalid', 'false'));
                });
            });

            typeSelect.addEventListener('change', updateLocationChoices);
            updateLocationChoices();
            return;
        }

        const locationSelect = locationControl;
        if (!(locationSelect instanceof HTMLSelectElement)) {
            return;
        }

        const placeholder = locationSelect.options[0];
        const updateLocations = () => {
            const selectedTypeId = typeSelect.value;

            if (locationSelect.value && locationSelect.selectedOptions[0]?.dataset.typeId !== selectedTypeId) {
                locationSelect.value = '';
            }

            locationSelect.disabled = selectedTypeId === '';
            placeholder.textContent = selectedTypeId === ''
                ? 'Select document type first'
                : 'Select cabinet / drawer / folder';

            Array.from(locationSelect.options).slice(1).forEach((option) => {
                const matchesType = selectedTypeId !== '' && option.dataset.typeId === selectedTypeId;
                option.hidden = !matchesType;
                option.disabled = !matchesType;
            });
        };

        typeSelect.addEventListener('change', updateLocations);
        updateLocations();
    };

    const initDocumentForm = () => {
        const form = document.querySelector('[data-document-form]');

        if (!form) {
            return;
        }

        initStorageLocationSelect(form, '[name="category"]', '[name="location_id"]');

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

    const initAddDocumentModal = () => {
        const modal = document.querySelector('[data-add-document-modal]');
        const dialog = modal?.querySelector('[data-add-document-dialog]');
        const openButton = document.querySelector('[data-add-document-open]');

        if (!modal || !dialog || !openButton) {
            return;
        }

        initStorageLocationSelect(modal, '[name="type_id"]', '[name="location_id"]');

        const closeButtons = modal.querySelectorAll('[data-add-document-close]');
        const focusableSelector = 'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])';
        let previousFocus = null;

        const closeModal = (restoreFocus = true) => {
            modal.hidden = true;
            modal.classList.remove('is-open');
            document.body.classList.remove('modal-open');

            if (restoreFocus && previousFocus instanceof HTMLElement) {
                previousFocus.focus();
            }
        };

        const showModal = (focusFirst = true) => {
            previousFocus = document.activeElement;
            modal.hidden = false;
            modal.classList.add('is-open');
            document.body.classList.add('modal-open');

            if (focusFirst) {
                const firstField = dialog.querySelector('input:not([type="hidden"]), select');
                (firstField || dialog).focus();
            }
        };

        openButton.addEventListener('click', () => showModal());
        closeButtons.forEach((button) => button.addEventListener('click', () => closeModal()));

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        const direction = modal.querySelector('[data-modal-direction]');
        const partyLabel = modal.querySelector('[data-modal-party-label]');
        const dateLabel = modal.querySelector('[data-modal-date-label]');
        direction?.addEventListener('change', () => {
            if (direction.value === 'Incoming') {
                partyLabel.textContent = 'Received from / Sender *';
                dateLabel.textContent = 'Date received *';
            } else if (direction.value === 'Outgoing') {
                partyLabel.textContent = 'Sent to / Recipient *';
                dateLabel.textContent = 'Date released *';
            } else {
                partyLabel.textContent = 'Sender / Recipient *';
                dateLabel.textContent = 'Date received / released *';
            }
        });

        document.addEventListener('keydown', (event) => {
            if (modal.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusable = Array.from(dialog.querySelectorAll(focusableSelector));
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (!first || !last) {
                return;
            }
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        if (!modal.hidden) {
            showModal(false);
            (modal.querySelector('[role="alert"]') || dialog.querySelector('input:not([type="hidden"]), select'))?.focus();
        }
    };

    initAuthForm();
    initSidebar();
    initSearchFilters();
    initRecordListLoading();
    initDocumentForm();
    initAddDocumentModal();
})();
