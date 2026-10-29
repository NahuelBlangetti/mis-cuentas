(() => {
    const modal = document.getElementById('client-modal');
    const form = document.getElementById('client-form');
    const storeClientUrl = form?.action;
    const title = document.getElementById('client-modal-title');
    const method = document.getElementById('client-method');
    const fields = {
        name: document.getElementById('client-name'),
        systems: document.getElementById('client-systems'),
        fee: document.getElementById('client-fee'),
        due: document.getElementById('client-due'),
        notes: document.getElementById('client-notes'),
    };

    document.querySelectorAll('[data-open-client]').forEach((button) => button.addEventListener('click', () => {
        if (!modal) return;
        form.action = storeClientUrl;
        method.value = 'POST';
        title.textContent = 'Agregar cliente';
        fields.name.value = '';
        fields.systems.value = '1';
        fields.fee.value = '';
        fields.due.value = '10';
        fields.notes.value = '';
        modal.showModal();
        fields.name.focus();
    }));

    document.querySelectorAll('[data-edit-client]').forEach((button) => button.addEventListener('click', () => {
        if (!modal) return;
        form.action = button.dataset.url;
        method.value = 'PUT';
        title.textContent = 'Editar cliente';
        fields.name.value = button.dataset.name || '';
        fields.systems.value = button.dataset.systems || '1';
        fields.fee.value = button.dataset.fee || '';
        fields.due.value = button.dataset.due || '10';
        fields.notes.value = button.dataset.notes || '';
        modal.showModal();
        fields.name.focus();
    }));

    document.querySelectorAll('[data-close-client]').forEach((button) => button.addEventListener('click', () => modal?.close()));
    modal?.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });

    document.querySelectorAll('[data-payment-month]').forEach((select) => select.addEventListener('change', () => {
        const owed = select.selectedOptions[0].dataset.owed;
        select.form.elements.amount.value = Number(owed) > 0 ? owed : select.dataset.fee;
    }));

    const movementForm = document.getElementById('movement-form');
    const movementTitle = document.getElementById('movement-form-title');
    const movementCancel = document.querySelector('[data-cancel-movement]');
    const storeMovementUrl = movementForm?.action;

    document.querySelectorAll('[data-edit-movement]').forEach((button) => button.addEventListener('click', () => {
        if (!movementForm) return;
        const data = button.dataset;
        movementForm.action = data.url;
        movementForm.elements._method.value = 'PUT';
        ['type', 'area', 'description', 'category', 'amount', 'date'].forEach((name) => { movementForm.elements[name].value = data[name] || ''; });
        // Los cobros de clientes siempre son ingresos del negocio.
        movementForm.elements.type.disabled = movementForm.elements.area.disabled = data.payment === '1';
        movementTitle.textContent = 'Editar movimiento';
        movementCancel.hidden = false;
        movementForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        movementForm.elements.description.focus({ preventScroll: true });
    }));

    movementCancel?.addEventListener('click', () => {
        movementForm.reset();
        movementForm.action = storeMovementUrl;
        movementForm.elements._method.value = 'POST';
        movementForm.elements.type.disabled = movementForm.elements.area.disabled = false;
        movementTitle.textContent = 'Nuevo movimiento';
        movementCancel.hidden = true;
    });

    document.querySelectorAll('[data-autosubmit]').forEach((field) => field.addEventListener('change', () => field.form.submit()));
    document.querySelectorAll('form[data-confirm]').forEach((confirmForm) => confirmForm.addEventListener('submit', (event) => {
        if (!window.confirm(confirmForm.dataset.confirm)) event.preventDefault();
    }));

    if ('serviceWorker' in navigator && location.protocol === 'https:') {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
})();
