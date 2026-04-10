(function () {
    'use strict';

    window.addEventListener('load', () => {
        const loader = document.getElementById('page-loader');
        if (!loader) return;
        // Small delay so the bar animation completes gracefully
        setTimeout(() => loader.classList.add('hidden'), 400);
    });

})();