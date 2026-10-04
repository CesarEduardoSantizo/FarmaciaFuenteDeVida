document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.querySelector('.edit-modal');
    const confirmModal = document.getElementById('confirm-edit-cancel');
    const addModal = document.getElementById('add-product-modal');
    const openAddButton = document.querySelector('[data-open-add]');

    if (editModal && confirmModal) {
        const keepEditingButton = confirmModal.querySelector('[data-keep-edit]');
        const cancelButtons = editModal.querySelectorAll('[data-cancel-edit]');

        const askToDiscard = () => {
            confirmModal.hidden = false;
            keepEditingButton.focus();
        };

        const keepEditing = () => {
            confirmModal.hidden = true;
            editModal.querySelector('input, select, textarea')?.focus();
        };

        cancelButtons.forEach((button) => button.addEventListener('click', askToDiscard));
        keepEditingButton.addEventListener('click', keepEditing);

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;

            if (!confirmModal.hidden) {
                keepEditing();
            } else {
                askToDiscard();
            }
        });
    }

    if (!addModal || !openAddButton) return;

    const cancelAddButtons = addModal.querySelectorAll('[data-cancel-add]');
    if (!addModal.hidden) {
        document.body.style.overflow = 'hidden';
        addModal.querySelector('input, select, textarea')?.focus();
    }
    const closeAddModal = () => {
        addModal.hidden = true;
        document.body.style.overflow = '';
        openAddButton.focus();
    };

    openAddButton.addEventListener('click', () => {
        addModal.hidden = false;
        document.body.style.overflow = 'hidden';
        addModal.querySelector('input, select, textarea')?.focus();
    });

    cancelAddButtons.forEach((button) => button.addEventListener('click', closeAddModal));

    document.addEventListener('keydown', (event) => {
        if (addModal.hidden) return;

        if (event.key === 'Escape') {
            closeAddModal();
        } else if (event.key === 'Tab') {
            const focusable = [...addModal.querySelectorAll('button, input, select, textarea, a[href]')]
                .filter((element) => !element.disabled && element.getClientRects().length > 0);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
});
