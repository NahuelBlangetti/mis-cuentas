(() => {
    const modal = document.getElementById('client-modal');
    const form = document.getElementById('client-form');
    const storeClientUrl = form?.action;
    const title = document.getElementById('client-modal-title');
    const method = document.getElementById('client-method');
    const fields = {
        name: document.getElementById('client-name'),
        alias: document.getElementById('client-alias'),
        phone: document.getElementById('client-phone'),
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
        fields.alias.value = '';
        fields.phone.value = '';
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
        fields.alias.value = button.dataset.alias || '';
        fields.phone.value = button.dataset.phone || '';
        fields.systems.value = button.dataset.systems || '1';
        fields.fee.value = button.dataset.fee || '';
        fields.due.value = button.dataset.due || '10';
        fields.notes.value = button.dataset.notes || '';
        modal.showModal();
        fields.name.focus();
    }));

    document.querySelectorAll('[data-close-client]').forEach((button) => button.addEventListener('click', () => modal?.close()));
    modal?.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });

    // Buscador de la cartera: filtra por nombre o alias sin importar mayúsculas ni tildes, y por los dígitos del celular.
    const clientSearch = document.querySelector('[data-client-search]');
    const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
    const clientCards = [...document.querySelectorAll('[data-client-card]')].map((card) => ({
        card, text: fold(card.dataset.search || ''), phone: card.dataset.searchPhone || '',
    }));
    clientSearch?.addEventListener('input', () => {
        const query = fold(clientSearch.value);
        const digits = query.replace(/\D/g, '');
        let visible = 0;
        clientCards.forEach(({ card, text, phone }) => {
            card.hidden = query !== '' && !text.includes(query) && !(digits.length >= 3 && phone.includes(digits));
            if (!card.hidden) visible += 1;
        });
        document.querySelector('[data-client-search-empty]').hidden = visible > 0;
    });

    document.querySelectorAll('[data-payment-month]').forEach((select) => select.addEventListener('change', () => {
        const owed = select.selectedOptions[0].dataset.owed;
        select.form.elements.amount.value = Number(owed) > 0 ? owed : select.dataset.fee;
    }));

    const movementForm = document.getElementById('movement-form');
    const movementTitle = document.getElementById('movement-form-title');
    const movementCancel = document.querySelector('[data-cancel-movement]');
    const storeMovementUrl = movementForm?.action;
    const clientField = movementForm?.querySelector('[data-client-field]');
    const clientNameField = movementForm?.querySelector('[data-client-name-field]');

    // El cliente solo aplica al negocio; el nombre se pide solo para un cliente ocasional.
    // Un campo oculto queda deshabilitado para que no se envíe.
    const syncClientFields = () => {
        if (!movementForm) return;
        const { area, client, client_name: clientName } = movementForm.elements;
        const locked = area.disabled;
        clientField.hidden = locked || area.value !== 'business';
        client.disabled = clientField.hidden;
        clientNameField.hidden = clientField.hidden || client.value !== 'occasional';
        clientName.disabled = clientNameField.hidden;
        clientName.required = !clientNameField.hidden;
    };
    movementForm?.elements.area.addEventListener('change', syncClientFields);
    movementForm?.elements.client.addEventListener('change', () => {
        syncClientFields();
        if (movementForm.elements.client.value === 'occasional') movementForm.elements.client_name.focus();
    });
    syncClientFields();

    document.querySelectorAll('[data-edit-movement]').forEach((button) => button.addEventListener('click', () => {
        if (!movementForm) return;
        const data = button.dataset;
        movementForm.action = data.url;
        movementForm.elements._method.value = 'PUT';
        ['type', 'area', 'description', 'category', 'amount', 'date', 'client', 'client_name'].forEach((name) => { movementForm.elements[name].value = data[name] || ''; });
        // Los cobros de abonos siempre son ingresos del negocio del mismo cliente.
        movementForm.elements.type.disabled = movementForm.elements.area.disabled = data.payment === '1';
        syncClientFields();
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
        syncClientFields();
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
