(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const fadeIn = () => {
        if (!reducedMotion.matches) {
            document.querySelector('main')?.animate([{opacity: 0.8}, {opacity: 1}], {
                duration: 160,
                easing: 'ease-out'
            });
        }
    };

    if ('onpagereveal' in window) {
        window.addEventListener('pagereveal', (event) => {
            if (!event.viewTransition) {
                fadeIn();
            }
        });
    } else {
        window.addEventListener('pageshow', fadeIn);
    }
})();
