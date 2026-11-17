/*
 * Small behaviour for every page. In a file (not onclick="…") because the
 * Content-Security-Policy blocks inline event handlers.
 */
document.addEventListener('click', (event) => {
    // × on the status toast
    const close = event.target.closest('[data-toast-close]');
    if (close) close.closest('.toast').remove();
});
