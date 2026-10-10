document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[data-presentations]');
    if (!form) return;

    const presentationsByProduct = JSON.parse(form.dataset.presentations || '{}');
    const productSelect = document.getElementById('sale-product');
    const presentationSelect = document.getElementById('sale-presentation');
    const quantityInput = form.querySelector('input[name="cantidad"]');
    const priceInput = document.getElementById('sale-unit-price');
    const totalOutput = document.getElementById('sale-total');
    const previouslySelected = Number(form.dataset.selectedPresentation);

    const chosenPresentation = () => {
        const productId = productSelect.value;
        return (presentationsByProduct[productId] || []).find((item) =>
            String(item.id_producto_presentacion) === presentationSelect.value
        );
    };
    const updateTotal = () => {
        const price = Number(chosenPresentation()?.precio_venta) || 0;
        const quantity = Number(quantityInput.value) || 0;
        priceInput.value = price.toFixed(2);
        totalOutput.textContent = (price * quantity).toFixed(2);
    };
    const updatePresentations = () => {
        const items = presentationsByProduct[productSelect.value] || [];
        const currentId = presentationSelect.value;
        presentationSelect.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = items.length ? 'Seleccione presentación' : 'No hay presentaciones';
        presentationSelect.append(placeholder);
        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id_producto_presentacion;
            option.textContent = `${item.nombre} · ${Math.floor(Number(item.stock_base) / Number(item.unidades_base))} disponibles`;
            presentationSelect.append(option);
        });
        if (items.some((item) => String(item.id_producto_presentacion) === String(currentId))) {
            presentationSelect.value = currentId;
        } else if (items.some((item) => Number(item.id_producto_presentacion) === previouslySelected)) {
            presentationSelect.value = String(previouslySelected);
        } else if (items.length) {
            presentationSelect.value = String(items[0].id_producto_presentacion);
        }
        updateTotal();
    };

    productSelect.addEventListener('change', updatePresentations);
    presentationSelect.addEventListener('change', updateTotal);
    quantityInput.addEventListener('input', updateTotal);
    updatePresentations();
});
