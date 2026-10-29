(() => {
    const modal = document.getElementById('client-modal');
    const form = document.getElementById('client-form');
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
        form.action = '/clientes';
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
        form.action = `/clientes/${button.dataset.id}`;
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

    if ('serviceWorker' in navigator && location.protocol === 'https:') {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
})();
