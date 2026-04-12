// frontend/assets/js/app.js
// Point d'entrée global — chargé sur toutes les pages via footer.php
// Les composants individuels sont chargés par footer.php directement.
// Ce fichier expose des utilitaires globaux réutilisables.

(function () {
    'use strict';

    // ── Utilitaire : debounce ─────────────────────────────────────────────
    window.debounce = function (fn, delay) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), delay);
        };
    };

    // ── Utilitaire : formater un prix ─────────────────────────────────────
    window.formatPrice = function (price) {
        return parseFloat(price).toFixed(2).replace('.', ',') + '\u00a0€';
    };

    // ── Toast global ──────────────────────────────────────────────────────
    window.showToast = function (message, type) {
        type = type || 'info';
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText =
                'position:fixed;bottom:32px;left:50%;transform:translateX(-50%);' +
                'z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
            document.body.appendChild(container);
        }

        const colorMap = {
            success: { border: 'var(--accent)', color: 'var(--accent)' },
            error: { border: 'var(--danger)', color: 'var(--danger)' },
            info: { border: 'var(--border-bright)', color: 'var(--text)' },
        };
        const c = colorMap[type] || colorMap.info;

        const toast = document.createElement('div');
        toast.style.cssText =
            'background:var(--surface-2);border:1px solid ' + c.border + ';' +
            'color:' + c.color + ';font-family:var(--font);font-size:13px;' +
            'padding:11px 20px;border-radius:var(--radius);' +
            'box-shadow:0 4px 24px rgba(0,0,0,0.4);' +
            'opacity:0;transform:translateY(10px);' +
            'transition:opacity 0.25s ease,transform 0.25s ease;white-space:nowrap;';
        toast.textContent = message;
        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        });

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 3200);
    };

    // ── Mise à jour badge panier sans rechargement ────────────────────────
    window.updateCartBadge = function (count) {
        let badge = document.querySelector('.navbar-cart .cart-badge');
        const cart = document.querySelector('.navbar-cart');
        if (!cart) return;
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'cart-badge';
                cart.appendChild(badge);
            }
            badge.textContent = count;
        } else if (badge) {
            badge.remove();
        }
    };

})();