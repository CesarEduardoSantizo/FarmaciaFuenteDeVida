document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[data-presentations]');
    if (!form) return;

    const presentationsByProduct = JSON.parse(form.dataset.presentations || '{}');
    const productSelect = document.getElementById('purchase-product');
    const presentationSelect = document.getElementById('purchase-presentation');
    const quantityInput = document.getElementById('cantidad-compra');
    const costInput = document.getElementById('costo-compra');
    const costLabel = document.getElementById('purchase-cost-label');
    const totalOutput = document.getElementById('total-compra');
    const previouslySelectedProduct = Number(form.dataset.selectedProduct);
    const previouslySelected = Number(form.dataset.selectedPresentation);
    if (!productSelect.value && presentationsByProduct[previouslySelectedProduct]) {
        productSelect.value = String(previouslySelectedProduct);
    }

    const updateTotal = () => {
        const total = (Number(quantityInput.value) || 0) * (Number(costInput.value) || 0);
        totalOutput.textContent = total.toFixed(2);
    };
    const updatePresentations = () => {
        const items = presentationsByProduct[productSelect.value] || [];
        const currentId = presentationSelect.value;
        presentationSelect.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = items.length ? 'Seleccione presentación' : 'Sin presentaciones configuradas';
        presentationSelect.append(placeholder);
        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id_producto_presentacion;
            option.textContent = item.nombre;
            presentationSelect.append(option);
        });
        if (items.some((item) => String(item.id_producto_presentacion) === String(currentId))) {
            presentationSelect.value = currentId;
        } else if (items.some((item) => Number(item.id_producto_presentacion) === previouslySelected)) {
            presentationSelect.value = String(previouslySelected);
        }
        const item = items.find((presentation) => String(presentation.id_producto_presentacion) === presentationSelect.value);
        costLabel.firstChild.textContent = item
            ? `Costo por ${item.nombre} (${costLabel.dataset.currency})`
            : `Costo por presentación (${costLabel.dataset.currency})`;
    };

    productSelect.addEventListener('change', updatePresentations);
    presentationSelect.addEventListener('change', updatePresentations);
    quantityInput.addEventListener('input', updateTotal);
    costInput.addEventListener('input', updateTotal);
    updatePresentations();
    updateTotal();
});
