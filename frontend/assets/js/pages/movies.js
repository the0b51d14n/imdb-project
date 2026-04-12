(function () {
    'use strict';

    // Auto-submit du formulaire de filtre genre au clic sur les radios
    document.querySelectorAll('.search-type-label input[type="radio"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            radio.closest('form')?.submit();
        });
    });

    // Animations d'entrée des cards au scroll
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
            card.style.transition = `opacity 0.4s ease ${i * 0.03}s, transform 0.4s ease ${i * 0.03}s`;
            observer.observe(card);
        });
    }

})();