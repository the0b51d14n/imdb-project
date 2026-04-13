// frontend/assets/js/pages/cart.js
// Compatible avec backend/pages/cart.php (DB, clé tmdb_id)

(function () {
    'use strict';

    // Animation de suppression d'un article
    document.querySelectorAll('.cart-item-remove').forEach((btn) => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const form    = this.closest('form');
            const idInput = form ? form.querySelector('[name="tmdb_id"]') : null;
            const tmdbId  = idInput ? idInput.value : null;
            const item    = tmdbId ? document.getElementById('cart-item-' + tmdbId) : null;

            if (item) {
                item.classList.add('removing');
                item.addEventListener('animationend', () => form.submit(), { once: true });
                setTimeout(() => form.submit(), 400);
            } else {
                form.submit();
            }
        });
    });

    // Bouton commander (order-button) → submit son form parent
    const checkoutBtn = document.getElementById('btn-checkout');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function () {
            // L'animation order-button s'en charge, le form se soumet après
        });
    }

})();