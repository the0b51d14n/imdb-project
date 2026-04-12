(function () {
    'use strict';

    // Suppression d'un article du panier avec animation
    // Compatible avec les deux versions (frontend session: movie_id, backend DB: tmdb_id)
    document.querySelectorAll('.cart-item-remove').forEach((btn) => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const form = this.closest('form');

            // Cherche movie_id (frontend/session) ou tmdb_id (backend/DB)
            const idInput = form.querySelector('[name="movie_id"], [name="tmdb_id"]');
            const movieId = idInput ? idInput.value : null;
            const item = movieId ? document.getElementById('cart-item-' + movieId) : null;

            if (item) {
                item.classList.add('removing');
                item.addEventListener('animationend', () => form.submit(), { once: true });
                setTimeout(() => form.submit(), 350);
            } else {
                form.submit();
            }
        });
    });

    // Bouton commander
    const btn = document.getElementById('btn-checkout');
    if (btn) {
        btn.addEventListener('click', () => {
            const check = setInterval(() => {
                if (btn.classList.contains('done')) {
                    clearInterval(check);
                    // Le formulaire est soumis automatiquement par order-button.js
                    // via form.submit() quand done = true
                }
            }, 100);
        });
    }

    // Toast notification
    window.showCartToast = function (message, type) {
        let toast = document.getElementById('cart-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'cart-toast';
            document.body.appendChild(toast);
        }

        const isError = type === 'error';
        toast.style.cssText = `
            position: fixed;
            bottom: 32px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: var(--surface-2);
            border: 1px solid ${isError ? 'var(--danger)' : 'var(--border-bright)'};
            color: ${isError ? 'var(--danger)' : 'var(--text)'};
            font-family: var(--font);
            font-size: 13px;
            padding: 12px 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg), 0 0 20px ${isError ? 'rgba(224,90,106,0.2)' : 'var(--accent-glow)'};
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease, transform 0.3s var(--ease-out-expo);
            white-space: nowrap;
            pointer-events: none;
        `;

        toast.textContent = message;
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(-50%) translateY(0)';
        });

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-50%) translateY(20px)';
        }, 3500);
    };

})();