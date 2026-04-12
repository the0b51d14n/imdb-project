// frontend/assets/js/pages/cart.js
// Compatible exclusivement avec backend/pages/cart.php (DB, clé tmdb_id)

(function () {
    'use strict';

    // Animation de suppression d'un article
    document.querySelectorAll('.cart-item-remove').forEach((btn) => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const form   = this.closest('form');
            const idInput = form.querySelector('[name="tmdb_id"]');
            const tmdbId  = idInput ? idInput.value : null;
            const item    = tmdbId ? document.getElementById('cart-item-' + tmdbId) : null;

            if (item) {
                item.classList.add('removing');
                item.addEventListener('animationend', () => form.submit(), { once: true });
                // Fallback si animationend ne se déclenche pas
                setTimeout(() => form.submit(), 400);
            } else {
                form.submit();
            }
        });
    });

})();