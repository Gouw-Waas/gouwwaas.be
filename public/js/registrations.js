/*
 * Registrations overview: filter, sort and select rows, and apply the selection bar's
 * actions (mail, attendance, certificates) to the selected registrations.
 *
 * Works on the rendered table (resources/views/activiteiten/inschrijvingen.blade.php):
 *   tr[data-row]          data-id, data-search, data-name, data-email, data-created,
 *                         data-present, data-payment, data-col-{fieldId}
 *   [data-filter="…"]     with data-match="contains|equals"
 *   th[data-sort="…"]     with data-type="text|number"
 *   [data-select-row], [data-select-all], [data-selection-bar], [data-selection-form]
 * Without JavaScript all of this stays hidden and the page works as before.
 */
(() => {
    const table = document.querySelector('table[data-registrations]');
    if (! table) return;

    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.querySelectorAll('tr[data-row]'));
    const original = rows.slice();
    const filters = Array.from(document.querySelectorAll('[data-filter]'));
    const count = document.querySelector('[data-filter-count]');
    const bar = document.querySelector('[data-selection-bar]');
    const selectionCount = document.querySelector('[data-selection-count]');
    const selectAll = table.querySelector('[data-select-all]');

    // Reveal the enhancements.
    document.querySelectorAll('[data-filters], [data-select-cell]').forEach((el) => { el.hidden = false; });

    const normalize = (value) => (value || '')
        .toLocaleLowerCase('nl')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .trim();

    // Attributes read directly: dataset would camelCase "data-col-12" awkwardly.
    const value = (row, key) => row.getAttribute(key === 'search' ? 'data-search' : `data-${key}`) || '';

    // ---- Filtering ---------------------------------------------------------------

    const matches = (row) => filters.every((filter) => {
        const wanted = filter.value;
        if (wanted === '') return true;

        const actual = value(row, filter.dataset.filter);
        return filter.dataset.match === 'equals'
            ? actual === wanted
            : normalize(actual).includes(normalize(wanted));
    });

    const applyFilters = () => {
        let shown = 0;
        rows.forEach((row) => {
            const visible = matches(row);
            row.hidden = ! visible;
            if (visible) {
                shown++;
            } else {
                // Never act on someone you can't see.
                const box = row.querySelector('[data-select-row]');
                if (box) box.checked = false;
            }
        });
        if (count) count.textContent = shown === rows.length ? `${rows.length} getoond` : `${shown} van ${rows.length} getoond`;
        updateSelection();
    };

    filters.forEach((filter) => {
        filter.addEventListener(filter.tagName === 'SELECT' ? 'change' : 'input', applyFilters);
    });

    const reset = document.querySelector('[data-filter-reset]');
    if (reset) {
        reset.addEventListener('click', () => {
            filters.forEach((filter) => { filter.value = ''; });
            applyFilters();
        });
    }

    const moreToggle = document.querySelector('[data-more-filters-toggle]');
    const more = document.querySelector('[data-more-filters]');
    if (moreToggle && more) {
        moreToggle.addEventListener('click', () => {
            more.hidden = ! more.hidden;
            moreToggle.setAttribute('aria-expanded', String(! more.hidden));
            moreToggle.textContent = more.hidden ? 'Meer filters' : 'Minder filters';
        });
    }

    // ---- Sorting -----------------------------------------------------------------

    const headers = Array.from(table.querySelectorAll('th[data-sort]'));
    let sortKey = null;
    let sortDir = 0; // 1 ascending, -1 descending, 0 original order

    const compare = (key, type) => (a, b) => {
        const x = value(a, key);
        const y = value(b, key);
        // Empty values always last, whatever the direction.
        if (x === '' && y === '') return 0;
        if (x === '') return 1;
        if (y === '') return -1;

        if (type === 'number') {
            const nx = parseFloat(x.replace(',', '.'));
            const ny = parseFloat(y.replace(',', '.'));
            if (! Number.isNaN(nx) && ! Number.isNaN(ny)) return (nx - ny) * sortDir;
        }
        return x.localeCompare(y, 'nl', { sensitivity: 'base', numeric: true }) * sortDir;
    };

    headers.forEach((th) => {
        const label = th.textContent.trim();
        th.textContent = '';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'sort-button';
        button.textContent = label;
        th.appendChild(button);

        button.addEventListener('click', () => {
            const key = th.dataset.sort;
            if (sortKey !== key) {
                sortKey = key;
                sortDir = 1;
            } else {
                sortDir = sortDir === 1 ? -1 : (sortDir === -1 ? 0 : 1);
            }

            headers.forEach((other) => other.removeAttribute('aria-sort'));
            if (sortDir !== 0) th.setAttribute('aria-sort', sortDir === 1 ? 'ascending' : 'descending');

            const ordered = sortDir === 0 ? original : rows.slice().sort(compare(key, th.dataset.type));
            ordered.forEach((row) => tbody.appendChild(row));
        });
    });

    // ---- Selection ---------------------------------------------------------------

    const selectedIds = () => rows
        .filter((row) => ! row.hidden)
        .map((row) => row.querySelector('[data-select-row]'))
        .filter((box) => box && box.checked)
        .map((box) => box.value);

    function updateSelection() {
        const ids = selectedIds();
        if (bar) bar.hidden = ids.length === 0;
        if (selectionCount) selectionCount.textContent = `${ids.length} geselecteerd`;

        if (selectAll) {
            const visible = rows.filter((row) => ! row.hidden).length;
            selectAll.checked = visible > 0 && ids.length === visible;
            selectAll.indeterminate = ids.length > 0 && ids.length < visible;
        }
    }

    tbody.addEventListener('change', (event) => {
        if (event.target.matches('[data-select-row]')) updateSelection();
    });

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            rows.forEach((row) => {
                const box = row.querySelector('[data-select-row]');
                if (box && ! row.hidden) box.checked = selectAll.checked;
            });
            updateSelection();
        });
    }

    const clear = document.querySelector('[data-selection-clear]');
    if (clear) {
        clear.addEventListener('click', () => {
            rows.forEach((row) => {
                const box = row.querySelector('[data-select-row]');
                if (box) box.checked = false;
            });
            updateSelection();
        });
    }

    // Each action sends the selected ids along (fresh hidden inputs on every submit).
    document.querySelectorAll('[data-selection-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('input[data-selection-id]').forEach((input) => input.remove());
            selectedIds().forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                input.dataset.selectionId = '';
                form.appendChild(input);
            });
        });
    });

    applyFilters();
})();
