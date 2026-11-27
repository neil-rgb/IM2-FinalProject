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

        const desktopQuery = window.matchMedia('(min-width: 64.01rem)');
        let isOpen = document.body.classList.contains('sidebar-open');
        const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

        const syncSidebar = () => {
            const useBackdrop = isOpen && !desktopQuery.matches;
            document.body.classList.toggle('sidebar-open', isOpen);
            document.body.classList.toggle('sidebar-modal-open', useBackdrop);
            sidebar.inert = !isOpen;
            sidebar.setAttribute('aria-hidden', String(!isOpen));
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
            backdrop.hidden = !useBackdrop;
        };

        const openSidebar = () => {
            isOpen = true;
            syncSidebar();

            const profileField = sidebar.querySelector('#sidebar-profile-editor:not([hidden]) input:not([type="hidden"])');
            const firstNavigationLink = sidebar.querySelector('.primary-nav .nav-link');
            (profileField || firstNavigationLink)?.focus({ preventScroll: true });
        };

        const closeSidebar = (restoreFocus = true) => {
            isOpen = false;
            syncSidebar();

            if (restoreFocus) {
                toggle.focus({ preventScroll: true });
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

        toggle.addEventListener('click', () => {
            if (isOpen) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
        backdrop.addEventListener('click', () => closeSidebar());

        sidebar.addEventListener('pointerleave', (event) => {
            if (isOpen && desktopQuery.matches && event.pointerType === 'mouse') {
                closeSidebar(sidebar.contains(document.activeElement));
            }
        });

        document.addEventListener('pointerdown', (event) => {
            if (isOpen && desktopQuery.matches && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
                closeSidebar(false);
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

        syncSidebar();
        desktopQuery.addEventListener('change', syncSidebar);

        if (isOpen && profileEditor && !profileEditor.hidden) {
            const errorSummary = profileEditor.querySelector('.sidebar-profile-error');
            (errorSummary || profileEditor.querySelector('input:not([type="hidden"])'))?.focus();
        }

        document.addEventListener('keydown', (event) => {
            if (!isOpen || event.defaultPrevented) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeSidebar();
                return;
            }

            if (event.key !== 'Tab' || desktopQuery.matches) {
                return;
            }

            const focusableElements = Array.from(sidebar.querySelectorAll(focusableSelector))
                .filter((element) => element.getClientRects().length > 0);
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

    const initWorkspaceScale = () => {
        const workspace = document.querySelector('.records-workspace');
        const main = workspace?.querySelector('.main-content');

        if (!main || !CSS.supports('zoom', '1')) {
            return;
        }

        const observer = new ResizeObserver(([entry]) => {
            const workspaceWidth = workspace.getBoundingClientRect().width;
            if (workspaceWidth > 0 && entry.contentRect.width > 0) {
                // Keep the content's layout width steady as the sidebar takes up space.
                const scale = Math.min(1, entry.contentRect.width / workspaceWidth);
                workspace.style.setProperty('--workspace-content-scale', String(scale));
            }
        });

        observer.observe(main);
    };

    const initSearchFilters = () => {
        const form = document.querySelector('[data-search-filters]');
        const toggle = form?.querySelector('[data-filter-toggle]');
        const panel = form?.querySelector('[data-filter-panel]');

        if (!toggle || !panel) {
            return;
        }

        const closeFilters = (restoreFocus = false) => {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');

            if (restoreFocus) {
                toggle.focus();
            }
        };

        const openFilters = () => {
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');

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
            if (!panel.hidden && !toggle.contains(event.target) && !panel.contains(event.target)) {
                closeFilters();
            }
        });

        form.addEventListener('keydown', (event) => {
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

        window.addEventListener('pageshow', () => {
            loadingState.hidden = true;
            listRegion.removeAttribute('aria-busy');
        });
    };

    const initStorageLocationSelect = (form) => {
        const typeSelect = form.querySelector('[name="category"], [name="type_id"]');
        const locationSelect = form.querySelector('select[name="location_id"]');

        if (!typeSelect || !locationSelect) {
            return;
        }

        const placeholder = locationSelect.options[0];
        const locationOptions = Array.from(locationSelect.options).slice(1);
        const locationHint = form.querySelector('[data-location-hint]');
        const updateLocations = () => {
            const selectedTypeId = typeSelect.value;
            const hasCategory = selectedTypeId !== '';
            let hasMatchingFolders = false;

            if (locationSelect.value && locationSelect.selectedOptions[0]?.dataset.typeId !== selectedTypeId) {
                locationSelect.value = '';
            }

            for (const option of locationOptions) {
                const matchesType = hasCategory && option.dataset.typeId === selectedTypeId;
                option.hidden = !matchesType;
                option.disabled = !matchesType;
                hasMatchingFolders ||= matchesType;
            }

            locationSelect.disabled = !hasCategory;
            placeholder.textContent = hasCategory && !hasMatchingFolders ? 'No matching folders' : 'Choose a folder';

            if (locationHint) {
                if (!hasCategory) {
                    locationHint.textContent = 'Choose a document category first.';
                } else {
                    locationHint.textContent = hasMatchingFolders
                        ? 'Folders are filtered by document category.'
                        : 'No folders are configured for this category.';
                }
            }
        };

        typeSelect.addEventListener('change', updateLocations);
        updateLocations();
    };

    const initFolderInventory = () => {
        const form = document.querySelector('[data-inventory-selection]');
        if (!form) {
            return;
        }

        const cabinet = form.querySelector('[data-inventory-cabinet]');
        const drawer = form.querySelector('[data-inventory-drawer]');
        const folder = form.querySelector('[data-inventory-folder]');
        const viewButton = form.querySelector('button[type="submit"]');
        const printButton = document.querySelector('[data-print-inventory]');
        const locations = Array.from(folder.options).slice(1);
        const loadedFolder = folder.selectedOptions[0];
        const loadedFolderId = folder.value;

        const setChoices = (select, placeholder, values, selected = '') => {
            select.replaceChildren(new Option(placeholder, ''));
            for (const value of new Set(values)) {
                select.add(new Option(value, value));
            }
            select.value = selected;
        };

        const updateActions = () => {
            viewButton.disabled = folder.value === '';
            if (printButton) {
                printButton.disabled = folder.value !== loadedFolderId;
            }
        };

        const updateFolders = (selected = '') => {
            for (const option of locations) {
                const matches = option.dataset.cabinet === cabinet.value && option.dataset.drawer === drawer.value;
                option.hidden = !matches;
                option.disabled = !matches;
            }
            folder.value = selected;
            folder.disabled = !drawer.value;
            updateActions();
        };

        const updateDrawers = (selected = '') => {
            const drawers = locations
                .filter((option) => option.dataset.cabinet === cabinet.value)
                .map((option) => option.dataset.drawer);
            setChoices(drawer, 'Choose a drawer', drawers, selected);
            drawer.disabled = !cabinet.value;
        };

        for (const location of locations) {
            location.textContent = location.dataset.folder;
        }
        setChoices(cabinet, 'Choose a cabinet', locations.map((option) => option.dataset.cabinet), loadedFolder.dataset.cabinet);
        cabinet.disabled = false;
        cabinet.closest('label').hidden = false;
        drawer.closest('label').hidden = false;
        updateDrawers(loadedFolder.dataset.drawer);
        updateFolders(loadedFolderId);

        cabinet.addEventListener('change', () => {
            updateDrawers();
            updateFolders();
        });
        drawer.addEventListener('change', () => updateFolders());
        folder.addEventListener('change', updateActions);

        if (printButton) {
            printButton.hidden = false;
            printButton.addEventListener('click', () => window.print());
        }
    };

    const initDocumentForm = (form) => {
        initStorageLocationSelect(form);

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
                transactionLabel.firstChild.textContent = 'Document date ';
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

    const initRecordDialog = (trigger) => {
        const dialog = document.getElementById(trigger.getAttribute('aria-controls'));

        if (typeof dialog?.showModal !== 'function') {
            return;
        }

        const form = dialog.querySelector('form');
        const cancel = dialog.querySelector('[data-record-dialog-cancel]');
        const submitButton = form.querySelector('button[type="submit"]');
        const submitLabel = submitButton.textContent;
        let saving = false;

        const setSaving = (state) => {
            saving = state;
            submitButton.disabled = state;
            submitButton.textContent = state ? 'Saving…' : submitLabel;
            cancel.setAttribute('aria-disabled', String(state));
        };

        const openDialog = () => {
            dialog.showModal();
            document.body.classList.add('modal-open');
        };

        trigger.addEventListener('click', (event) => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            openDialog();
        });

        cancel.addEventListener('click', (event) => {
            event.preventDefault();
            if (!saving) {
                dialog.close();
            }
        });

        dialog.addEventListener('cancel', (event) => {
            if (saving) {
                event.preventDefault();
            }
        });

        dialog.addEventListener('close', () => {
            document.body.classList.remove('modal-open');
            trigger.focus({ preventScroll: true });
        });

        form.addEventListener('submit', (event) => {
            if (saving) {
                event.preventDefault();
                return;
            }

            setSaving(true);
        });

        window.addEventListener('pageshow', (event) => {
            if (event.persisted && saving) {
                window.location.reload();
                return;
            }

            setSaving(false);
        });

        if (dialog.open) {
            dialog.removeAttribute('open');
            openDialog();
        }
    };

    initAuthForm();
    initSidebar();
    initWorkspaceScale();
    initSearchFilters();
    initRecordListLoading();
    initFolderInventory();
    document.querySelectorAll('[data-document-form]').forEach(initDocumentForm);
    initAddDocumentModal();
    document.querySelectorAll('[data-record-dialog-trigger]').forEach(initRecordDialog);
})();
