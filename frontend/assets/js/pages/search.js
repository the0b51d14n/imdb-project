(function () {
    'use strict';

    // Auto-submit quand l'utilisateur change le type (titre / réalisateur)
    document.querySelectorAll('.search-type-label input[type="radio"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            const form = radio.closest('form');
            if (form) form.submit();
        });
    });

    // Highlight du label actif
    document.querySelectorAll('.search-type-label').forEach((label) => {
        const radio = label.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            label.classList.add('active');
        }
        radio?.addEventListener('change', () => {
            document.querySelectorAll('.search-type-label').forEach(l => l.classList.remove('active'));
            label.classList.add('active');
        });
    });

    // Focus automatique sur le champ de recherche si vide
    const input = document.querySelector('.search-input');
    if (input && !input.value) {
        input.focus();
    }

    // Animations d'entrée des résultats
    if ('IntersectionObserver' in window) {
        const cards = document.querySelectorAll('.movie-card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05 });

        cards.forEach((card, i) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(16px)';
            card.style.transition = `opacity 0.35s ease ${i * 0.03}s, transform 0.35s ease ${i * 0.03}s`;
            observer.observe(card);
        });
    }

})();