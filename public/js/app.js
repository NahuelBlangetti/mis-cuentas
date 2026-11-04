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

    // El nombre se pide solo para un cliente ocasional. Un cobro de abono o un movimiento personal anterior no cambian
    // de tipo ni de cliente. Un campo oculto queda deshabilitado para que no se envíe.
    const setMovementLocked = (locked) => {
        movementForm.elements.type.disabled = locked;
        movementForm.dataset.locked = locked ? '1' : '';
    };
    const syncClientFields = () => {
        if (!movementForm) return;
        const { client, client_name: clientName } = movementForm.elements;
        clientField.hidden = movementForm.dataset.locked === '1';
        client.disabled = clientField.hidden;
        clientNameField.hidden = clientField.hidden || client.value !== 'occasional';
        clientName.disabled = clientNameField.hidden;
        clientName.required = !clientNameField.hidden;
    };
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
        ['type', 'description', 'category', 'amount', 'date', 'client', 'client_name'].forEach((name) => { movementForm.elements[name].value = data[name] || ''; });
        setMovementLocked(data.payment === '1' || data.area === 'personal');
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
        setMovementLocked(false);
        syncClientFields();
        movementTitle.textContent = 'Nuevo movimiento';
        movementCancel.hidden = true;
    });

    // Gasto personal: una sola vez lleva fecha; todos los meses lleva moneda y mes de inicio.
    // Los campos del otro tipo quedan ocultos y deshabilitados para que no se envíen ni se exijan.
    const expenseForm = document.querySelector('[data-expense-form]');
    const syncExpenseKind = () => {
        const kind = expenseForm?.querySelector('[data-expense-kind]:checked')?.value;
        if (!kind) return;
        expenseForm.querySelectorAll('[data-kind]').forEach((field) => {
            field.hidden = field.dataset.kind !== kind;
            field.querySelectorAll('input, select').forEach((input) => { input.disabled = field.hidden; });
        });
        expenseForm.querySelector('[data-expense-submit]').textContent = kind === 'monthly' ? 'Guardar gasto recurrente' : 'Guardar gasto';
    };
    expenseForm?.querySelectorAll('[data-expense-kind]').forEach((radio) => radio.addEventListener('change', syncExpenseKind));
    syncExpenseKind();

    document.querySelectorAll('[data-autosubmit]').forEach((field) => field.addEventListener('change', () => field.form.submit()));
    document.querySelectorAll('form[data-confirm]').forEach((confirmForm) => confirmForm.addEventListener('submit', (event) => {
        if (!window.confirm(confirmForm.dataset.confirm)) event.preventDefault();
    }));

    const moreToggle = document.querySelector('[data-more-toggle]');
    const moreSheet = document.getElementById('more-sheet');
    const setMoreOpen = (open) => {
        if (!moreToggle || !moreSheet) return;
        moreSheet.toggleAttribute('hidden', !open);
        moreToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        moreToggle.classList.toggle('is-open', open);
    };
    moreToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        setMoreOpen(moreSheet.hasAttribute('hidden'));
    });
    document.addEventListener('click', (event) => {
        if (!moreSheet || !moreToggle || moreSheet.hasAttribute('hidden')) return;
        if (moreSheet.contains(event.target) || moreToggle.contains(event.target)) return;
        setMoreOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setMoreOpen(false);
    });

    if ('serviceWorker' in navigator && location.protocol === 'https:') {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
})();
