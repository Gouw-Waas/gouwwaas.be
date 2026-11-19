/*
 * Burger menu: when the tabs in the header no longer fit next to each other, the header
 * shows only the logo and a "Menu" button, which opens the side menu (a modal <dialog>)
 * with the tabs and the login state.
 *
 * Measured rather than a fixed breakpoint, so adding a tab just works. Without JavaScript
 * the tabs stay in the header and scroll sideways.
 */
(() => {
    const header = document.querySelector('[data-site-header]');
    const tabs = header && header.querySelector('.tabs');
    const burger = header && header.querySelector('[data-sidenav-open]');
    const sidenav = document.querySelector('[data-sidenav]');

    if (! tabs || ! burger || ! sidenav || typeof sidenav.showModal !== 'function') {
        return;
    }

    // Show the full header, check whether the tabs overflow, and switch back if so. This
    // happens within one frame, so the full header never flashes on screen.
    const fit = () => {
        header.classList.remove('is-compact');
        const compact = tabs.scrollWidth > tabs.clientWidth + 1;
        header.classList.toggle('is-compact', compact);

        if (! compact && sidenav.open) {
            sidenav.close();
        }
    };

    fit();

    let frame = null;
    window.addEventListener('resize', () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(fit);
    });

    // The web font is wider than the fallback font.
    if (document.fonts) {
        document.fonts.ready.then(fit);
    }

    burger.addEventListener('click', () => {
        sidenav.showModal();
        burger.setAttribute('aria-expanded', 'true');
    });

    sidenav.addEventListener('close', () => burger.setAttribute('aria-expanded', 'false'));

    sidenav.addEventListener('click', (event) => {
        // The close button, or the dimmed page next to the menu (the dialog's backdrop).
        if (event.target === sidenav || event.target.closest('[data-sidenav-close]')) {
            sidenav.close();
        }
    });
})();
