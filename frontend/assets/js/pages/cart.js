(function () {
    'use strict';

    document.querySelectorAll('.cart-item-remove').forEach((btn) => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const form = this.closest('form');
            const movieId = form.querySelector('[name="movie_id"]').value;
            const item = document.getElementById('cart-item-' + movieId);

            if (item) {
                item.classList.add('removing');
                item.addEventListener('animationend', () => form.submit(), { once: true });
                setTimeout(() => form.submit(), 350);
            } else {
                form.submit();
            }
        });
    });

    const btn = document.getElementById('btn-checkout');
    if (btn) {
        btn.addEventListener('click', () => {
            const check = setInterval(() => {
                if (btn.classList.contains('done')) {
                    clearInterval(check);
                    setTimeout(() => {
                        showToast('Commande simulée — intégration backend à venir !');
                    }, 500);
                }
            }, 100);
        });
    }

    function showToast(message) {
        let toast = document.getElementById('cart-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'cart-toast';
            toast.style.cssText = `
                position: fixed;
                bottom: 32px;
                left: 50%;
                transform: translateX(-50%) translateY(20px);
                background: var(--surface-2);
                border: 1px solid var(--border-bright);
                color: var(--text);
                font-family: var(--font);
                font-size: 13px;
                padding: 12px 24px;
                border-radius: var(--radius);
                box-shadow: var(--shadow-lg), 0 0 20px var(--accent-glow);
                z-index: 9999;
                opacity: 0;
                transition: opacity 0.3s ease, transform 0.3s var(--ease-out-expo);
                white-space: nowrap;
                pointer-events: none;
            `;
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(-50%) translateY(0)';
        });

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-50%) translateY(20px)';
        }, 3500);
    }

})();