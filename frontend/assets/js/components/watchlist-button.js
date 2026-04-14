// frontend/assets/js/components/watchlist-button.js
// Gestion du bouton "Ajouter à ma liste" (watchlist) via AJAX.
// Le bouton doit avoir la class .btn-watchlist et les data-attributes suivants :
//   data-tmdb-id   : ID TMDB du film
//   data-title     : Titre du film
//   data-poster    : URL de l'affiche
//   data-year      : Année
//   data-note      : Note (float)
//   data-in-list   : "1" ou "0" (état initial)
//   data-csrf      : Token CSRF

(function () {
    'use strict';

    const ENDPOINT = '/backend/api/watchlist.php?action=toggle';

    function initWatchlistBtn(btn) {
        if (btn._wlInit) return;
        btn._wlInit = true;

        let inList = btn.dataset.inList === '1';
        updateUI(btn, inList, false);

        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            if (btn._loading) return;
            btn._loading = true;

            const tmdbId = parseInt(btn.dataset.tmdbId, 10);
            const csrf = btn.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';

            if (!tmdbId) {
                btn._loading = false;
                return;
            }

            // Feedback immédiat
            btn.classList.add('wl-loading');
            updateUI(btn, !inList, true); // Optimistic UI

            try {
                const res = await fetch(ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        tmdb_id: tmdbId,
                        title: btn.dataset.title || '',
                        poster: btn.dataset.poster || '',
                        year: btn.dataset.year || '',
                        note: parseFloat(btn.dataset.note || 0),
                    }),
                });

                const data = await res.json();

                if (data.ok) {
                    inList = data.in_watchlist;
                    btn.dataset.inList = inList ? '1' : '0';
                    updateUI(btn, inList, false);

                    // Toast de confirmation
                    if (typeof window.showToast === 'function') {
                        window.showToast(
                            inList ? '✨ Ajouté à votre liste' : '🗑 Retiré de votre liste',
                            inList ? 'success' : 'info'
                        );
                    }

                    // Mettre à jour le compteur watchlist dans la navbar si présent
                    const badge = document.querySelector('.watchlist-badge');
                    if (badge && data.count !== undefined) {
                        badge.textContent = data.count;
                        badge.style.display = data.count > 0 ? '' : 'none';
                    }

                } else if (res.status === 401) {
                    // Non connecté → redirection vers login
                    window.location.href = '/pages/login.php?redirect=' + encodeURIComponent(window.location.pathname);
                } else {
                    // Rollback
                    updateUI(btn, inList, false);
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.error || 'Erreur', 'error');
                    }
                }

            } catch {
                // Rollback réseau
                updateUI(btn, inList, false);
                if (typeof window.showToast === 'function') {
                    window.showToast('Erreur réseau. Réessayez.', 'error');
                }
            } finally {
                btn._loading = false;
                btn.classList.remove('wl-loading');
            }
        });
    }

    function updateUI(btn, inList, optimistic) {
        const iconAdd = `
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>`;

        const iconCheck = `
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"/>
        </svg>`;

        const iconLoading = `
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             style="animation: wl-spin 0.6s linear infinite;">
            <circle cx="12" cy="12" r="10" stroke-dasharray="31.4" stroke-dashoffset="10"/>
        </svg>`;

        const label = btn.querySelector('.wl-label');
        const icon = btn.querySelector('.wl-icon');

        if (optimistic || btn.classList.contains('wl-loading')) {
            if (icon) icon.innerHTML = iconLoading;
            btn.classList.add('wl-pending');
            return;
        }

        btn.classList.remove('wl-pending');
        btn.classList.toggle('wl-active', inList);

        if (icon) icon.innerHTML = inList ? iconCheck : iconAdd;
        if (label) label.textContent = inList ? 'Dans ma liste' : 'Ma liste';

        btn.title = inList ? 'Retirer de ma liste' : 'Ajouter à ma liste';
    }

    // Injection des styles du bouton
    const styles = `
    @keyframes wl-spin {
        to { transform: rotate(360deg); }
    }
    .btn-watchlist {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 16px;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        background: transparent;
        color: var(--text-muted);
        font-family: var(--font);
        font-size: 12px;
        font-weight: 500;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        cursor: pointer;
        transition:
            color var(--transition),
            border-color var(--transition),
            background var(--transition),
            transform var(--transition-snap);
        -webkit-tap-highlight-color: transparent;
        position: relative;
        overflow: hidden;
    }
    .btn-watchlist:hover {
        color: var(--text);
        border-color: var(--border-bright);
        transform: translateY(-1px);
    }
    .btn-watchlist.wl-active {
        color: var(--accent);
        border-color: rgba(87,204,153,0.4);
        background: var(--accent-subtle);
    }
    .btn-watchlist.wl-active:hover {
        color: var(--danger);
        border-color: rgba(224,90,106,0.4);
        background: rgba(224,90,106,0.06);
    }
    .btn-watchlist.wl-loading {
        opacity: 0.7;
        cursor: wait;
        transform: none;
    }
    .wl-icon {
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }
    .wl-icon svg {
        display: block;
    }
    `;

    const styleEl = document.createElement('style');
    styleEl.textContent = styles;
    document.head.appendChild(styleEl);

    // Initialisation
    requestAnimationFrame(() => {
        document.querySelectorAll('.btn-watchlist').forEach(initWatchlistBtn);
    });

    // Observer dynamique
    if ('MutationObserver' in window) {
        new MutationObserver(mutations => {
            mutations.forEach(m => m.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                node.querySelectorAll?.('.btn-watchlist').forEach(initWatchlistBtn);
                if (node.matches?.('.btn-watchlist')) initWatchlistBtn(node);
            }));
        }).observe(document.body, { childList: true, subtree: true });
    }

})();