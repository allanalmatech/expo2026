(function () {
    'use strict';

    const toastStack = document.getElementById('toast-stack');

    window.showToast = function (message, type = 'info') {
        if (!toastStack) return;
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toastStack.appendChild(toast);
        window.setTimeout(() => toast.remove(), 5000);
    };

    function openDialog(options) {
        const settings = Object.assign({
            title: 'Please confirm',
            message: 'Continue with this action?',
            confirmText: 'Continue',
            cancelText: 'Cancel',
            danger: false,
            alertOnly: false
        }, typeof options === 'string' ? { message: options } : options);

        return new Promise((resolve) => {
            const backdrop = document.createElement('div');
            backdrop.className = 'app-dialog-backdrop';
            backdrop.innerHTML = `
                <div class="app-dialog" role="dialog" aria-modal="true" aria-labelledby="app-dialog-title">
                    <div class="app-dialog-icon ${settings.danger ? 'danger' : ''}">${settings.danger ? '!' : 'i'}</div>
                    <div class="app-dialog-content">
                        <h2 id="app-dialog-title">${escapeHtml(settings.title)}</h2>
                        <p>${escapeHtml(settings.message)}</p>
                        <div class="app-dialog-actions">
                            ${settings.alertOnly ? '' : `<button class="button button-ghost" type="button" data-dialog-cancel>${escapeHtml(settings.cancelText)}</button>`}
                            <button class="button ${settings.danger ? 'button-danger' : 'button-primary'}" type="button" data-dialog-confirm>${escapeHtml(settings.confirmText)}</button>
                        </div>
                    </div>
                </div>
            `;

            function close(value) {
                document.removeEventListener('keydown', onKeydown);
                backdrop.remove();
                resolve(value);
            }

            function onKeydown(event) {
                if (event.key === 'Escape') close(false);
            }

            backdrop.addEventListener('click', (event) => {
                if (event.target === backdrop) close(false);
                if (event.target instanceof Element && event.target.matches('[data-dialog-cancel]')) close(false);
                if (event.target instanceof Element && event.target.matches('[data-dialog-confirm]')) close(true);
            });
            document.addEventListener('keydown', onKeydown);
            document.body.appendChild(backdrop);
            backdrop.querySelector('[data-dialog-confirm]')?.focus();
        });
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    window.appConfirm = function (options) {
        return openDialog(options);
    };

    window.appAlert = function (options) {
        return openDialog(Object.assign({ alertOnly: true, confirmText: 'OK' }, typeof options === 'string' ? { message: options } : options));
    };

    document.querySelectorAll('[data-toast]').forEach((toast) => {
        window.setTimeout(() => toast.remove(), 6000);
    });

    const sidebar = document.getElementById('sidebar');
    const publicLinks = document.getElementById('public-links');

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        if (target.matches('[data-sidebar-toggle]')) {
            document.body.classList.toggle('sidebar-open');
        }

        if (target.matches('[data-sidebar-close]')) {
            document.body.classList.remove('sidebar-open');
        }

        if (target.matches('[data-public-nav-toggle]') && publicLinks) {
            publicLinks.classList.toggle('open');
        }
    });

    if (sidebar) {
        sidebar.addEventListener('click', (event) => {
            if (event.target instanceof HTMLAnchorElement) {
                document.body.classList.remove('sidebar-open');
            }
        });
    }

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (form.dataset.confirmed === '1') {
                delete form.dataset.confirmed;
                return;
            }
            const message = form.getAttribute('data-confirm') || 'Continue with this action?';
            event.preventDefault();
            const confirmed = await window.appConfirm({
                title: 'Confirm Action',
                message,
                confirmText: 'Yes, continue',
                danger: true
            });
            if (!confirmed) return;

            if (event.submitter instanceof HTMLButtonElement && event.submitter.name) {
                let hidden = Array.from(form.querySelectorAll('input[type="hidden"][data-submit-proxy]')).find((input) => input.name === event.submitter.name);
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = event.submitter.name;
                    hidden.dataset.submitProxy = '1';
                    form.appendChild(hidden);
                }
                hidden.value = event.submitter.value;
            }
            form.dataset.confirmed = '1';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        });
    });

    document.querySelectorAll('[data-contact-card]').forEach((card) => {
        const checkbox = card.querySelector('[data-contact-select]');
        const actions = Array.from(card.querySelectorAll('[data-contact-action]'));

        function refreshContactActions() {
            const selected = checkbox instanceof HTMLInputElement && checkbox.checked;
            actions.forEach((action) => action.classList.toggle('is-disabled', !selected));
        }

        checkbox?.addEventListener('change', refreshContactActions);
        actions.forEach((action) => {
            action.addEventListener('click', (event) => {
                if (checkbox instanceof HTMLInputElement && checkbox.checked) return;
                event.preventDefault();
                window.appAlert?.({ title: 'Select Number', message: 'Tick the phone number before calling or opening WhatsApp.' });
            });
        });
        refreshContactActions();
    });

    document.querySelectorAll('[data-applicant-search]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) return;
        const form = input.closest('form');
        const hidden = form?.querySelector('[data-applicant-id]');
        const listId = input.getAttribute('list') || '';
        const list = listId ? document.getElementById(listId) : null;
        const options = list ? Array.from(list.querySelectorAll('option')) : [];

        function syncApplicant(validate) {
            const match = options.find((option) => option.value === input.value);
            if (hidden instanceof HTMLInputElement) {
                hidden.value = match instanceof HTMLOptionElement ? (match.dataset.applicationId || '') : '';
            }
            input.setCustomValidity(validate && input.value.trim() !== '' && hidden instanceof HTMLInputElement && hidden.value === '' ? 'Select an applicant from the search results.' : '');
        }

        input.addEventListener('input', () => syncApplicant(false));
        input.addEventListener('change', () => syncApplicant(false));
        form?.addEventListener('submit', (event) => {
            syncApplicant(true);
            if (hidden instanceof HTMLInputElement && hidden.value === '') {
                event.preventDefault();
                input.reportValidity();
            }
        });
        syncApplicant(false);
    });

    document.querySelectorAll('[data-applicant-tent-map]').forEach((map) => {
        const panelsWrap = document.querySelector('[data-applicant-tent-panels]');
        const buttons = Array.from(map.querySelectorAll('[data-applicant-tent-open]'));
        const panels = panelsWrap ? Array.from(panelsWrap.querySelectorAll('[data-applicant-tent-panel]')) : [];

        function showTent(tentGroup) {
            panels.forEach((panel) => {
                panel.hidden = panel.getAttribute('data-applicant-tent-panel') !== tentGroup;
            });
            buttons.forEach((button) => {
                button.classList.toggle('is-selected', button.getAttribute('data-applicant-tent-open') === tentGroup);
            });
        }

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const tentGroup = button.getAttribute('data-applicant-tent-open') || '';
                showTent(tentGroup);
            });
        });

        const firstTent = buttons[0]?.getAttribute('data-applicant-tent-open');
        if (firstTent) showTent(firstTent);
    });

    function closeProofModals() {
        document.querySelectorAll('[data-proof-modal]').forEach((modal) => {
            modal.hidden = true;
        });
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const openButton = target.closest('[data-proof-modal-open]');
        if (openButton instanceof HTMLElement) {
            const modalId = openButton.getAttribute('data-proof-modal-open') || '';
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.hidden = false;
                modal.querySelector('[data-proof-modal-close]')?.focus?.();
            }
            return;
        }

        if (target.closest('[data-proof-modal-close]')) {
            closeProofModals();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeProofModals();
    });
})();
