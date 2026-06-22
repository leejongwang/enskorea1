(() => {
    const form = document.querySelector('[data-analyze-form]');
    if (form) {
        form.addEventListener('submit', () => {
            const button = form.querySelector('.primary-button');
            if (!button) return;
            button.classList.add('loading');
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    }

    const printButton = document.querySelector('[data-print]');
    if (printButton) {
        printButton.addEventListener('click', () => window.print());
    }
})();
