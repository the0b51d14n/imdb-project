// frontend/assets/js/components/movie-card.js
// Animations et interactions des cartes film (tilt 3D, glow suivant la souris)

(function () {
    'use strict';

    const TILT_MAX = 8; // degrés max

    function initCard(card) {
        const poster = card.querySelector('.movie-card-poster');
        if (!poster) return;

        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width  - 0.5;
            const y = (e.clientY - rect.top)  / rect.height - 0.5;

            card.style.transform =
                'translateY(-8px) scale(1.02) ' +
                'rotateY(' + (x * TILT_MAX) + 'deg) ' +
                'rotateX(' + (-y * TILT_MAX * 0.6) + 'deg)';

            const glow = card.querySelector('.movie-card-glow-bubble');
            if (glow) {
                glow.style.setProperty('--glow-x', (x * 60 + 50) + '%');
                glow.style.setProperty('--glow-y', (y * 60 + 50) + '%');
            }
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = '';
        });

        // Empêche la navigation si l'utilisateur fait un clic-glissé
        let startX = 0;
        card.addEventListener('mousedown', (e) => { startX = e.clientX; });
        card.querySelector('a')?.addEventListener('click', (e) => {
            if (Math.abs(e.clientX - startX) > 6) e.preventDefault();
        });
    }

    // Init sur les cartes existantes
    document.querySelectorAll('.movie-card').forEach(initCard);

    // Observer pour les cartes ajoutées dynamiquement (pagination Ajax, etc.)
    if ('MutationObserver' in window) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((m) => {
                m.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;
                    if (node.classList.contains('movie-card')) {
                        initCard(node);
                    }
                    node.querySelectorAll?.('.movie-card').forEach(initCard);
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

})();