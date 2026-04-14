// frontend/assets/js/components/blur-up.js
// Effet blur-up pour les images : charge une version basse résolution (w92)
// puis remplace par la haute résolution (w500) avec une transition douce.
// Compatible avec les images ajoutées dynamiquement (carousel, pagination AJAX).

(function () {
    'use strict';

    const BLUR_CLASS = 'blur-up';
    const LOADED_CLASS = 'blur-up--loaded';

    // Styles CSS injectés
    const styles = `
    .blur-up {
        filter: blur(8px);
        transform: scale(1.03);
        transition:
            filter 0.35s ease,
            transform 0.35s ease;
        will-change: filter, transform;
    }
    .blur-up--loaded {
        filter: blur(0);
        transform: scale(1);
    }
    `;

    const styleEl = document.createElement('style');
    styleEl.textContent = styles;
    document.head.appendChild(styleEl);

    // ── Chargement d'une image blur-up ────────────────────────────────────────
    function loadBlurUp(img) {
        if (!img.dataset.src || img._blurInit) return;
        img._blurInit = true;

        // Si déjà visible dans le viewport, charger immédiatement
        // Sinon, on laisse l'IntersectionObserver s'en charger

        const highRes = new Image();
        highRes.src = img.dataset.src;

        highRes.onload = () => {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
            img.classList.add(LOADED_CLASS);
        };

        highRes.onerror = () => {
            // En cas d'erreur, on retire le blur sans remplacer
            img.classList.add(LOADED_CLASS);
        };
    }

    // ── IntersectionObserver pour le chargement différé ──────────────────────
    if (!('IntersectionObserver' in window)) {
        // Fallback : charger toutes les images blur-up immédiatement
        document.querySelectorAll('img.' + BLUR_CLASS).forEach(loadBlurUp);
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                loadBlurUp(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, {
        rootMargin: '200px 0px', // Pré-charger 200px avant l'entrée dans le viewport
        threshold: 0,
    });

    function observeBlurUps(root = document) {
        root.querySelectorAll('img.' + BLUR_CLASS + ':not([data-src=""])').forEach(img => {
            if (!img._blurObserved) {
                img._blurObserved = true;
                observer.observe(img);
            }
        });
    }

    // Init initiale
    observeBlurUps();

    // Observer dynamique pour le contenu ajouté (carousel, chargement AJAX)
    if ('MutationObserver' in window) {
        new MutationObserver(mutations => {
            mutations.forEach(m => {
                if (m.addedNodes.length) observeBlurUps();
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

})();