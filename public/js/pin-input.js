(function (window) {
    function renderDots(dots, length) {
        dots.forEach((dot, i) => {
            const filled = i < length;
            dot.classList.toggle('bg-primary-600', filled);
            dot.classList.toggle('border-primary-600', filled);
            dot.classList.toggle('border-neutral-300', !filled);
            dot.classList.toggle('dark:border-neutral-600', !filled);
        });
    }

    window.PinInput = { renderDots };
})(window);
