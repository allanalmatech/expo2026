(function () {
    'use strict';

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function post(url, data) {
        const body = new FormData();
        Object.entries(data).forEach(([key, value]) => body.append(key, value));
        body.append('csrf_token', token);
        return fetch(url, {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then((response) => response.json());
    }

    document.addEventListener('change', (event) => {
        const select = event.target;
        if (!(select instanceof HTMLSelectElement) || !select.matches('[data-status-select]')) return;

        select.disabled = true;
        post('../ajax/update-status.php', {
            application_id: select.dataset.id || '',
            field: select.dataset.field || '',
            value: select.value
        }).then((data) => {
            window.showToast?.(data.message || 'Status updated.', data.ok ? 'success' : 'error');
        }).catch(() => {
            window.showToast?.('Unable to update status. Please try again.', 'error');
        }).finally(() => {
            select.disabled = false;
        });
    });

    const filterForm = document.querySelector('[data-filter-applications]');
    const tableBody = document.querySelector('[data-applications-body]');
    const paginationBox = document.querySelector('[data-applications-pagination]');
    const bulkApplicationsForm = document.querySelector('[data-bulk-applications-form]');
    const selectAllApplications = document.querySelector('[data-select-all-applications]');
    const selectedCount = document.querySelector('[data-selected-count]');
    const bulkPaymentsForm = document.querySelector('[data-bulk-payments-form]');
    const selectAllPayments = document.querySelector('[data-select-all-payments]');
    const paymentSelectedCount = document.querySelector('[data-payment-selected-count]');

    function applicationCheckboxes() {
        return Array.from(document.querySelectorAll('[data-application-row-select]'));
    }

    function updateApplicationSelectionState() {
        const checkboxes = applicationCheckboxes();
        const checked = checkboxes.filter((checkbox) => checkbox.checked);
        if (selectedCount) selectedCount.textContent = String(checked.length);
        if (selectAllApplications) {
            selectAllApplications.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
            selectAllApplications.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
        }
    }

    function paymentCheckboxes() {
        return Array.from(document.querySelectorAll('[data-payment-row-select]'));
    }

    function updatePaymentSelectionState() {
        const checkboxes = paymentCheckboxes();
        const checked = checkboxes.filter((checkbox) => checkbox.checked);
        if (paymentSelectedCount) paymentSelectedCount.textContent = String(checked.length);
        if (selectAllPayments) {
            selectAllPayments.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
            selectAllPayments.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
        }
    }

    let filterTimer = null;
    if (filterForm && tableBody) {
        const pageInput = filterForm.querySelector('[data-application-page-input]');
        const listSizeInput = filterForm.querySelector('[data-application-list-size-input]');

        const runFilter = (resetPage = true) => {
            if (resetPage && pageInput instanceof HTMLInputElement) pageInput.value = '1';
            const params = new URLSearchParams(new FormData(filterForm));
            tableBody.classList.add('is-loading');
            fetch(`../ajax/filter-applications.php?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then((response) => response.json())
                .then((data) => {
                    tableBody.innerHTML = data.rows || '';
                    if (paginationBox) paginationBox.innerHTML = data.pagination || '';
                    updateApplicationSelectionState();
                })
                .catch(() => window.showToast?.('Unable to filter applications.', 'error'))
                .finally(() => tableBody.classList.remove('is-loading'));
        };

        filterForm.addEventListener('input', () => {
            window.clearTimeout(filterTimer);
            filterTimer = window.setTimeout(() => runFilter(true), 350);
        });
        filterForm.addEventListener('change', () => runFilter(true));
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            runFilter(true);
        });

        document.addEventListener('change', (event) => {
            const select = event.target;
            if (!(select instanceof HTMLSelectElement) || !select.matches('[data-application-list-size]')) return;
            if (listSizeInput instanceof HTMLInputElement) listSizeInput.value = select.value;
            if (pageInput instanceof HTMLInputElement) pageInput.value = '1';
            runFilter(false);
        });

        document.addEventListener('click', (event) => {
            const button = event.target instanceof Element ? event.target.closest('[data-application-page]') : null;
            if (!(button instanceof HTMLButtonElement) || button.disabled) return;
            if (!(pageInput instanceof HTMLInputElement)) return;

            const current = Math.max(1, Number.parseInt(pageInput.value || '1', 10));
            pageInput.value = button.dataset.applicationPage === 'next' ? String(current + 1) : String(Math.max(1, current - 1));
            runFilter(false);
        });
    }

    document.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) return;

        if (target.matches('[data-select-all-applications]')) {
            applicationCheckboxes().forEach((checkbox) => {
                checkbox.checked = target.checked;
            });
            updateApplicationSelectionState();
        }

        if (target.matches('[data-application-row-select]')) {
            updateApplicationSelectionState();
        }

        if (target.matches('[data-select-all-payments]')) {
            paymentCheckboxes().forEach((checkbox) => {
                checkbox.checked = target.checked;
            });
            updatePaymentSelectionState();
        }

        if (target.matches('[data-payment-row-select]')) {
            updatePaymentSelectionState();
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-bulk-applications-form]')) return;
        if (applicationCheckboxes().some((checkbox) => checkbox.checked)) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        window.appAlert?.({ title: 'No Selection', message: 'Select at least one application or synced sheet row first.' });
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-bulk-payments-form]')) return;
        if (paymentCheckboxes().some((checkbox) => checkbox.checked)) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        window.appAlert?.({ title: 'No Selection', message: 'Select at least one payment first.' });
    }, true);

    if (bulkApplicationsForm) updateApplicationSelectionState();
    if (bulkPaymentsForm) updatePaymentSelectionState();

    document.addEventListener('click', (event) => {
        const button = event.target;
        if (!(button instanceof HTMLButtonElement) || !button.matches('[data-mark-read]')) return;

        button.disabled = true;
        post('../ajax/mark-read.php', { message_id: button.dataset.messageId || '' })
            .then((data) => {
                window.showToast?.(data.message || 'Message updated.', data.ok ? 'success' : 'error');
                if (data.ok) button.closest('.message-card')?.classList.add('is-read');
            })
            .catch(() => window.showToast?.('Unable to mark message as read.', 'error'))
            .finally(() => { button.disabled = false; });
    });
})();
