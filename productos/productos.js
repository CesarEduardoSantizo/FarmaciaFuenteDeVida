document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.querySelector('.edit-modal');
    const confirmModal = document.getElementById('confirm-edit-cancel');
    const addModal = document.getElementById('add-product-modal');
    const openAddButton = document.querySelector('[data-open-add]');
    const presentationBuilder = document.getElementById('presentation-builder');

    if (presentationBuilder) {
        const catalog = JSON.parse(presentationBuilder.dataset.catalog || '[]');
        const selected = JSON.parse(presentationBuilder.dataset.selected || '[]');
        const rowsContainer = document.getElementById('presentation-rows');
        const addLevelButton = document.getElementById('add-presentation-level');
        const topPrice = document.querySelector('input[name="precio"]');
        const topPriceHidden = document.getElementById('precio-presentacion-mayor');
        const form = presentationBuilder.closest('form');
        let levels = selected.length
            ? selected.map((item) => ({
                id: String(item.id_catalogo_presentacion),
                units: item.unidades_siguiente === undefined ? '' : String(Math.round(Number(item.unidades_siguiente))),
                price: String(item.precio_venta ?? ''),
                customPrice: true
            }))
            : [{ id: String(catalog[0]?.id_catalogo_presentacion ?? ''), units: '', price: '', customPrice: false }];

        const availableCatalog = () => catalog.filter((item) => Number(item.estado) === 1 || levels.some((level) => level.id === String(item.id_catalogo_presentacion)));
        const getEntry = (id) => catalog.find((item) => String(item.id_catalogo_presentacion) === String(id));
        const calculateFactors = () => {
            const factors = Array(levels.length).fill(1);
            for (let index = levels.length - 2; index >= 0; index--) {
                factors[index] = factors[index + 1] * (Number(levels[index].units) || 1);
            }
            return factors;
        };
        const suggestPrices = () => {
            const factors = calculateFactors();
            const basePrice = Number(topPrice?.value) || 0;
            topPriceHidden.value = topPrice?.value ?? '';
            levels.forEach((level, index) => {
                const suggestedPrice = basePrice * factors[index] / (factors[0] || 1);
                if (index > 0 && !level.customPrice && suggestedPrice > 0) {
                    level.price = suggestedPrice.toFixed(2);
                }
            });
        };
        const syncPriceInputs = () => {
            topPrice.value = levels[0]?.price ?? '';
            topPriceHidden.value = topPrice.value;
            rowsContainer.querySelectorAll('input[name="presentacion_precios[]"]').forEach((input, index) => {
                input.value = levels[index + 1]?.price ?? '';
            });
        };
        const renderLevels = () => {
            rowsContainer.replaceChildren();
            levels.forEach((level, index) => {
                const entry = getEntry(level.id);
                const nextEntry = getEntry(levels[index + 1]?.id);
                const row = document.createElement('div');
                row.className = 'presentation-level';
                const selectLabel = document.createElement('label');
                selectLabel.textContent = index === 0 ? 'Empaque de nivel más alto' : `Nivel ${entry?.nivel ?? ''}`;
                const select = document.createElement('select');
                select.name = 'presentacion_ids[]';
                select.required = true;
                const prompt = document.createElement('option');
                prompt.value = '';
                prompt.textContent = 'Seleccione una presentación';
                select.append(prompt);
                const higherLevel = index === 0 ? Infinity : Number(getEntry(levels[index - 1]?.id)?.nivel);
                const lowerLevel = index === levels.length - 1 ? 0 : Number(getEntry(levels[index + 1]?.id)?.nivel);
                availableCatalog()
                    .filter((item) => Number(item.nivel) < higherLevel && Number(item.nivel) > lowerLevel)
                    .filter((item) => !levels.some((otherLevel, otherIndex) => otherIndex !== index && otherLevel.id === String(item.id_catalogo_presentacion)))
                    .forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.id_catalogo_presentacion;
                        option.textContent = `${item.nombre} · Nivel ${item.nivel}`;
                        option.selected = String(item.id_catalogo_presentacion) === level.id;
                        select.append(option);
                    });
                selectLabel.append(select);
                row.append(selectLabel);

                if (index < levels.length - 1) {
                    const quantityLabel = document.createElement('label');
                    quantityLabel.textContent = `Cuántas ${nextEntry?.nombre ?? 'unidades'} contiene`;
                    const quantityInput = document.createElement('input');
                    quantityInput.type = 'number';
                    quantityInput.name = 'presentacion_cantidades[]';
                    quantityInput.min = '1';
                    quantityInput.step = '1';
                    quantityInput.max = '1000000';
                    quantityInput.required = true;
                    quantityInput.value = level.units;
                    quantityInput.addEventListener('input', () => {
                        level.units = quantityInput.value;
                        suggestPrices();
                        syncPriceInputs();
                    });
                    quantityLabel.append(quantityInput);
                    row.append(quantityLabel);
                }

                if (index > 0) {
                    const priceLabel = document.createElement('label');
                    priceLabel.textContent = `Precio por ${entry?.nombre ?? 'presentación'}`;
                    const priceInput = document.createElement('input');
                    priceInput.type = 'number';
                    priceInput.name = 'presentacion_precios[]';
                    priceInput.min = '0.01';
                    priceInput.step = '0.01';
                    priceInput.required = true;
                    priceInput.value = level.price;
                    priceInput.addEventListener('input', () => {
                        level.price = priceInput.value;
                        level.customPrice = true;
                    });
                    priceLabel.append(priceInput);
                    row.append(priceLabel);
                }
                if (index > 0 && Number(entry?.nivel) > 1) {
                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'remove-presentation-level';
                    removeButton.textContent = 'Quitar nivel';
                    removeButton.setAttribute('aria-label', `Quitar ${entry?.nombre ?? 'presentación'} de la jerarquía`);
                    removeButton.addEventListener('click', () => {
                        levels[index - 1].units = '';
                        levels.splice(index, 1);
                        suggestPrices();
                        renderLevels();
                    });
                    row.append(removeButton);
                }

                select.addEventListener('change', () => {
                    level.id = select.value;
                    level.units = '';
                    levels = levels.slice(0, index + 1);
                    suggestPrices();
                    renderLevels();
                });
                rowsContainer.append(row);
            });
            const selectedEntry = getEntry(levels[levels.length - 1]?.id);
            const canAdd = selectedEntry && Number(selectedEntry.nivel) > 1 &&
                availableCatalog().some((item) => Number(item.nivel) < Number(selectedEntry.nivel));
            addLevelButton.disabled = !canAdd;
            addLevelButton.setCustomValidity(selectedEntry && Number(selectedEntry.nivel) !== 1
                ? 'Agrega los niveles inferiores hasta llegar a Unidad.'
                : '');
            syncPriceInputs();
        };

        if (topPrice) {
            topPrice.addEventListener('input', () => {
                levels[0].price = topPrice.value;
                levels[0].customPrice = true;
                suggestPrices();
                syncPriceInputs();
            });
        }
        addLevelButton.addEventListener('click', () => {
            if (!levels.length) return;
            const last = getEntry(levels[levels.length - 1].id);
            if (!last || Number(last.nivel) <= 1) return;
            const next = availableCatalog()
                .filter((item) => Number(item.nivel) < Number(last.nivel))
                .sort((a, b) => Number(b.nivel) - Number(a.nivel))[0];
            if (!next) return;
            levels[levels.length - 1].units = '';
            levels.push({ id: String(next.id_catalogo_presentacion), units: '', price: '', customPrice: false });
            suggestPrices();
            renderLevels();
        });
        if (form) {
            form.addEventListener('submit', (event) => {
                const lastEntry = getEntry(levels[levels.length - 1]?.id);
                if (!lastEntry || Number(lastEntry.nivel) !== 1) {
                    event.preventDefault();
                    addLevelButton.reportValidity();
                    return;
                }
                topPriceHidden.value = topPrice?.value ?? '';
                levels.forEach((level, index) => {
                    const priceInput = rowsContainer.querySelectorAll('input[name="presentacion_precios[]"]')[index - 1];
                    if (index > 0 && priceInput) priceInput.value = level.price;
                });
            });
        }
        suggestPrices();
        renderLevels();
    }

    const wizard = document.querySelector('[data-product-wizard]');
    if (wizard) {
        const form = wizard.closest('form');
        const panels = [...wizard.querySelectorAll('[data-wizard-panel]')];
        const indicators = [...wizard.querySelectorAll('[data-step-indicator]')];
        const previousButton = wizard.querySelector('[data-wizard-previous]');
        const nextButton = wizard.querySelector('[data-wizard-next]');
        const submitButton = wizard.querySelector('[data-wizard-submit]');
        const status = wizard.querySelector('.wizard-status');
        const stepCount = Number(wizard.dataset.stepCount);
        let currentStep = 0;

        form.noValidate = true;

        const showStep = (step) => {
            currentStep = Math.max(0, Math.min(step, stepCount - 1));
            panels.forEach((panel, index) => {
                panel.hidden = index !== currentStep;
            });
            indicators.forEach((indicator, index) => {
                indicator.classList.toggle('is-current', index === currentStep);
                indicator.classList.toggle('is-complete', index < currentStep);
                if (index === currentStep) indicator.setAttribute('aria-current', 'step');
                else indicator.removeAttribute('aria-current');
            });
            previousButton.hidden = currentStep === 0;
            nextButton.hidden = currentStep === stepCount - 1;
            submitButton.hidden = currentStep !== stepCount - 1;
            status.textContent = `Paso ${currentStep + 1} de ${stepCount}`;
            wizard.closest('.form-box').scrollTop = 0;
        };

        const firstInvalidControl = (panel) => [...panel.querySelectorAll('input, select, textarea')]
            .find((control) => !control.disabled && !control.checkValidity());

        nextButton.addEventListener('click', () => {
            let invalidControl = firstInvalidControl(panels[currentStep]);
            if (!invalidControl && currentStep === 1) {
                const addLevelButton = document.getElementById('add-presentation-level');
                if (addLevelButton && !addLevelButton.checkValidity()) invalidControl = addLevelButton;
            }
            if (invalidControl) {
                invalidControl.reportValidity();
                return;
            }
            showStep(currentStep + 1);
            panels[currentStep].querySelector('input, select, textarea, button')?.focus();
        });

        previousButton.addEventListener('click', () => {
            showStep(currentStep - 1);
            panels[currentStep].querySelector('input, select, textarea, button')?.focus();
        });

        form.addEventListener('submit', (event) => {
            const invalidControl = [...form.querySelectorAll('input, select, textarea')]
                .find((control) => !control.disabled && !control.checkValidity());
            if (!invalidControl) return;

            event.preventDefault();
            const invalidPanel = invalidControl.closest('[data-wizard-panel]');
            const invalidStep = panels.indexOf(invalidPanel);
            if (invalidStep >= 0) showStep(invalidStep);
            invalidControl.reportValidity();
        });

        showStep(0);
    }

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
