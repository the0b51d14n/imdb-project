// frontend/assets/js/components/cart-ajax.js
// Gestion AJAX du panier — remplace les rechargements de page complets.
// Compatible avec l'API /backend/api/cart.php
//
// Les boutons d'ajout doivent avoir :
//   class="btn-add-to-cart"
//   data-tmdb-id, data-title, data-poster, data-csrf
//
// Les boutons de suppression :
//   class="btn-remove-from-cart"
//   data-tmdb-id, data-csrf

(function () {
    'use strict';

    const API = '/backend/api/cart.php';

    // ── Utilitaires ───────────────────────────────────────────────────────────
    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('[name="_csrf_token"]')?.value
            || '';
    }

    async function apiCall(action, body = {}) {
        const csrf = getCsrf();
        const res = await fetch(`${API}?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ ...body, action }),
        });
        return res.json();
    }

    // ── Mise à jour du badge navbar ───────────────────────────────────────────
    function updateCartBadge(count) {
        if (typeof window.updateCartBadge === 'function') {
            window.updateCartBadge(count);
        }
    }

    // ── Bouton "Ajouter au panier" ────────────────────────────────────────────
    function initAddToCartBtn(btn) {
        if (btn._cartInit) return;
        btn._cartInit = true;

        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (btn._loading) return;
            btn._loading = true;

            const tmdbId = parseInt(btn.dataset.tmdbId, 10);
            if (!tmdbId) { btn._loading = false; return; }

            // État de chargement
            const originalHTML = btn.innerHTML;
            btn.innerHTML = `
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 style="animation:cart-spin 0.6s linear infinite;">
                <circle cx="12" cy="12" r="10" stroke-dasharray="31.4" stroke-dashoffset="10"/>
            </svg>
            Ajout…`;
            btn.disabled = true;

            try {
                const data = await apiCall('add', {
                    tmdb_id: tmdbId,
                    title: btn.dataset.title || '',
                    poster: btn.dataset.poster || '',
                });

                if (data.ok) {
                    // État "dans le panier"
                    btn.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Dans le panier`;
                    btn.classList.add('btn-in-cart');
                    btn.disabled = true;

                    updateCartBadge(data.count);

                    if (typeof window.showToast === 'function') {
                        window.showToast('🛒 Ajouté au panier !', 'success');
                    }

                    // Lien vers le panier après 2s
                    setTimeout(() => {
                        btn.innerHTML = `
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                            <line x1="3" y1="6" x2="21" y2="6"/>
                            <path d="M16 10a4 4 0 0 1-8 0"/>
                        </svg>
                        Voir le panier`;
                        btn.disabled = false;
                        btn._cartInit = false;
                        btn.onclick = () => window.location.href = '/backend/pages/cart.php';
                    }, 2000);

                } else if (data.redirect) {
                    window.location.href = data.redirect + '?redirect=' + encodeURIComponent(window.location.pathname);
                } else {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.error || 'Erreur lors de l\'ajout', 'error');
                    }
                }
            } catch {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                if (typeof window.showToast === 'function') {
                    window.showToast('Erreur réseau. Réessayez.', 'error');
                }
            } finally {
                btn._loading = false;
            }
        });
    }

    // ── Bouton "Supprimer du panier" ──────────────────────────────────────────
    function initRemoveFromCartBtn(btn) {
        if (btn._cartRemoveInit) return;
        btn._cartRemoveInit = true;

        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (btn._loading) return;
            btn._loading = true;

            const tmdbId = parseInt(btn.dataset.tmdbId, 10);
            if (!tmdbId) { btn._loading = false; return; }

            // Animation de suppression sur l'article parent
            const cartItem = btn.closest('.cart-item') || btn.closest('[data-cart-item]');
            if (cartItem) {
                cartItem.style.transition = 'opacity 0.25s ease, transform 0.25s ease, max-height 0.4s ease';
                cartItem.style.opacity = '0.5';
            }

            try {
                const data = await apiCall('remove', { tmdb_id: tmdbId });

                if (data.ok) {
                    if (cartItem) {
                        cartItem.style.opacity = '0';
                        cartItem.style.transform = 'translateX(24px) scale(0.97)';
                        setTimeout(() => {
                            cartItem.style.maxHeight = '0';
                            cartItem.style.padding = '0';
                            cartItem.style.marginBottom = '0';
                            cartItem.style.overflow = 'hidden';
                            setTimeout(() => cartItem.remove(), 400);
                        }, 250);
                    }

                    updateCartBadge(data.count);

                    // Mettre à jour le total affiché
                    const totalEl = document.querySelector('.cart-total-amount, [data-cart-total]');
                    if (totalEl && data.total !== undefined) {
                        totalEl.textContent = data.total.toFixed(2).replace('.', ',') + '\u00a0€';
                    }

                    // Mettre à jour le compteur d'articles
                    const countEl = document.querySelector('.cart-count, [data-cart-count]');
                    if (countEl && data.count !== undefined) {
                        countEl.textContent = data.count + ' film' + (data.count > 1 ? 's' : '');
                    }

                    // Si le panier est maintenant vide, recharger pour afficher l'état vide
                    if (data.count === 0) {
                        setTimeout(() => window.location.reload(), 600);
                    }

                } else {
                    if (cartItem) cartItem.style.opacity = '1';
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.error || 'Erreur', 'error');
                    }
                }
            } catch {
                if (cartItem) cartItem.style.opacity = '1';
                if (typeof window.showToast === 'function') {
                    window.showToast('Erreur réseau. Réessayez.', 'error');
                }
            } finally {
                btn._loading = false;
            }
        });
    }

    // ── Styles ────────────────────────────────────────────────────────────────
    const styles = `
    @keyframes cart-spin {
        to { transform: rotate(360deg); }
    }
    .btn-in-cart {
        background: var(--accent-subtle) !important;
        border-color: rgba(87,204,153,0.4) !important;
        color: var(--accent) !important;
    }
    `;
    const styleEl = document.createElement('style');
    styleEl.textContent = styles;
    document.head.appendChild(styleEl);

    // ── Initialisation ────────────────────────────────────────────────────────
    requestAnimationFrame(() => {
        document.querySelectorAll('.btn-add-to-cart').forEach(initAddToCartBtn);
        document.querySelectorAll('.btn-remove-from-cart').forEach(initRemoveFromCartBtn);
    });

    // Observer dynamique
    if ('MutationObserver' in window) {
        new MutationObserver(mutations => {
            mutations.forEach(m => m.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                node.querySelectorAll?.('.btn-add-to-cart').forEach(initAddToCartBtn);
                node.querySelectorAll?.('.btn-remove-from-cart').forEach(initRemoveFromCartBtn);
                if (node.matches?.('.btn-add-to-cart')) initAddToCartBtn(node);
                if (node.matches?.('.btn-remove-from-cart')) initRemoveFromCartBtn(node);
            }));
        }).observe(document.body, { childList: true, subtree: true });
    }

})();