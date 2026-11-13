document.querySelectorAll('[data-genre-dropdown]').forEach((control) => {
    const details = control.querySelector('details');
    const summaryLabel = control.querySelector('[data-genre-summary]');
    const selectedList = control.querySelector('[data-genre-selected]');
    const checkboxes = [...control.querySelectorAll('input[name="genre_ids[]"]')];

    if (!details || !summaryLabel || !selectedList) {
        return;
    }

    const renderSelectedGenres = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked);
        summaryLabel.textContent = selected.length === 0
            ? 'Chọn thể loại'
            : `Đã chọn ${selected.length} thể loại`;
        selectedList.replaceChildren();

        selected.forEach((checkbox) => {
            const chip = document.createElement('span');
            chip.className = 'genre-selected-chip';

            const name = document.createElement('span');
            name.textContent = checkbox.dataset.genreName || checkbox.value;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'genre-selected-remove';
            removeButton.setAttribute('aria-label', `Bỏ thể loại ${name.textContent}`);
            removeButton.textContent = '×';
            removeButton.addEventListener('click', () => {
                checkbox.checked = false;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });

            chip.append(name, removeButton);
            selectedList.append(chip);
        });
    };

    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', renderSelectedGenres));
    details.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            details.open = false;
            details.querySelector('summary')?.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!control.contains(event.target)) {
            details.open = false;
        }
    });

    renderSelectedGenres();
});
