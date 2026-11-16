/*
 * A proper confirmation dialog instead of the browser's confirm().
 *
 * <form method="POST" data-confirm="Inschrijving verwijderen?"
 *       data-confirm-text="Dit kan niet ongedaan gemaakt worden."   (optional, plain text)
 *       data-confirm-details="some-template-id"                      (optional, <template> with HTML)
 *       data-confirm-button="Verwijderen">                           (optional, default "Verwijderen")
 *
 * Without JavaScript the form simply submits.
 */
(() => {
    const dialog = document.querySelector('[data-confirm-dialog]');
    if (! dialog) return;

    const title = dialog.querySelector('[data-confirm-title]');
    const body = dialog.querySelector('[data-confirm-body]');
    const okButton = dialog.querySelector('[data-confirm-ok]');
    let pending = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (! form.matches('form[data-confirm]') || form.dataset.confirmed === '1') return;

        event.preventDefault();
        pending = form;

        title.textContent = form.dataset.confirm;
        body.replaceChildren();

        if (form.dataset.confirmText) {
            const p = document.createElement('p');
            p.textContent = form.dataset.confirmText;
            body.append(p);
        }

        const template = form.dataset.confirmDetails && document.getElementById(form.dataset.confirmDetails);
        if (template) body.append(template.content.cloneNode(true));

        okButton.textContent = form.dataset.confirmButton || 'Verwijderen';
        dialog.returnValue = '';
        dialog.showModal();
    }, true);

    dialog.addEventListener('close', () => {
        if (dialog.returnValue === 'ok' && pending) {
            pending.dataset.confirmed = '1';
            pending.requestSubmit ? pending.requestSubmit() : pending.submit();
        }
        pending = null;
    });

    // Clicking the dimmed backdrop cancels.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close('cancel');
    });
})();
