// frontend/assets/js/components/search-autocomplete.js
// Recherche instantanée avec suggestions en temps réel (debounce 250ms).
// S'attache au champ .navbar-search-input ou au champ .search-input sur la page recherche.

(function () {
    'use strict';

    const DEBOUNCE_MS = 250;
    const MIN_CHARS = 2;
    const MAX_RESULTS = 6;

    // ── Utilitaires ───────────────────────────────────────────────────────────
    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // ── Rendu d'une suggestion ────────────────────────────────────────────────
    function renderSuggestion(item) {
        const typeLabel = item.type === 'director' ? 'Réalisateur' : 'Film';
        const typeIcon = item.type === 'director'
            ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>'
            : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>';

        const poster = item.poster
            ? `<img src="${esc(item.poster)}" alt="" class="ac-thumb" loading="lazy">`
            : `<div class="ac-thumb ac-thumb--placeholder">${typeIcon}</div>`;

        const note = item.note && item.note > 0
            ? `<span class="ac-note">⭐ ${item.note.toFixed(1)}</span>`
            : '';

        const meta = [item.year, note].filter(Boolean).join('&nbsp;·&nbsp;');

        return `
        <a href="${esc(item.url)}" class="ac-item" data-type="${esc(item.type)}">
            ${poster}
            <div class="ac-info">
                <div class="ac-title">${esc(item.label)}</div>
                ${meta ? `<div class="ac-meta">${meta}</div>` : ''}
            </div>
            <span class="ac-badge">${esc(typeLabel)}</span>
        </a>`;
    }

    // ── Création du dropdown ──────────────────────────────────────────────────
    function createDropdown(input) {
        const dropdown = document.createElement('div');
        dropdown.className = 'ac-dropdown';
        dropdown.setAttribute('role', 'listbox');
        dropdown.setAttribute('aria-label', 'Suggestions de recherche');

        // Insérer après le parent du champ
        const parent = input.closest('.search-input-wrap') || input.parentElement;
        parent.style.position = 'relative';
        parent.appendChild(dropdown);

        return dropdown;
    }

    // ── Initialisation sur un champ ───────────────────────────────────────────
    function initAutocomplete(input) {
        if (input._acInitialized) return;
        input._acInitialized = true;

        const dropdown = createDropdown(input);
        let abortCtrl = null;
        let isOpen = false;

        const show = () => { dropdown.style.display = 'block'; isOpen = true; };
        const hide = () => { dropdown.style.display = 'none'; isOpen = false; };

        const search = debounce(async (q) => {
            if (q.length < MIN_CHARS) { hide(); return; }

            // Annuler la requête précédente
            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();

            dropdown.innerHTML = '<div class="ac-loading">Recherche…</div>';
            show();

            try {
                const res = await fetch(
                    `/backend/api/search.php?q=${encodeURIComponent(q)}&limit=${MAX_RESULTS}`,
                    { signal: abortCtrl.signal }
                );
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();

                if (!data.results || data.results.length === 0) {
                    dropdown.innerHTML = `
                    <div class="ac-empty">
                        <span>Aucun résultat pour « ${esc(q)} »</span>
                    </div>`;
                    return;
                }

                dropdown.innerHTML = data.results.map(renderSuggestion).join('');

                // Ajouter un lien "Voir tous les résultats"
                dropdown.innerHTML += `
                <a href="/backend/pages/search.php?q=${encodeURIComponent(q)}" class="ac-see-all">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    Voir tous les résultats pour « ${esc(q)} »
                </a>`;

            } catch (err) {
                if (err.name === 'AbortError') return;
                hide();
            }
        }, DEBOUNCE_MS);

        // Événements
        input.addEventListener('input', (e) => search(e.target.value.trim()));
        input.addEventListener('focus', (e) => {
            if (e.target.value.trim().length >= MIN_CHARS) show();
        });

        // Navigation clavier
        input.addEventListener('keydown', (e) => {
            if (!isOpen) return;

            const items = dropdown.querySelectorAll('.ac-item, .ac-see-all');
            const active = dropdown.querySelector('.ac-item--active, .ac-see-all--active');
            let idx = [...items].indexOf(active);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                idx = (idx + 1) % items.length;
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                idx = (idx - 1 + items.length) % items.length;
            } else if (e.key === 'Enter' && active) {
                e.preventDefault();
                active.click();
                return;
            } else if (e.key === 'Escape') {
                hide();
                return;
            } else {
                return;
            }

            items.forEach(i => i.classList.remove('ac-item--active', 'ac-see-all--active'));
            items[idx]?.classList.add(
                items[idx].classList.contains('ac-see-all') ? 'ac-see-all--active' : 'ac-item--active'
            );
            items[idx]?.scrollIntoView({ block: 'nearest' });
        });

        // Fermeture au clic extérieur
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && e.target !== input) {
                hide();
            }
        });
    }

    // ── Styles ────────────────────────────────────────────────────────────────
    const styles = `
    .ac-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: var(--surface-2);
        border: 1px solid var(--border-bright);
        border-radius: var(--radius-lg);
        box-shadow: 0 16px 40px rgba(0,0,0,0.5), 0 0 0 1px rgba(87,204,153,0.1);
        z-index: 500;
        overflow: hidden;
        max-height: 420px;
        overflow-y: auto;
        scrollbar-width: thin;
    }
    .ac-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        text-decoration: none;
        color: var(--text);
        transition: background var(--transition);
        border-bottom: 1px solid var(--border-subtle);
    }
    .ac-item:last-of-type { border-bottom: none; }
    .ac-item:hover, .ac-item--active {
        background: var(--accent-subtle);
    }
    .ac-thumb {
        width: 34px;
        height: 51px;
        border-radius: var(--radius-sm);
        object-fit: cover;
        flex-shrink: 0;
        background: var(--surface-3);
    }
    .ac-thumb--placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-faint);
    }
    .ac-info { flex: 1; min-width: 0; }
    .ac-title {
        font-size: 13px;
        font-weight: 500;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ac-meta {
        font-size: 11px;
        color: var(--text-faint);
        margin-top: 2px;
    }
    .ac-note { color: var(--gold); }
    .ac-badge {
        font-size: 10px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--text-faint);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 2px 6px;
        flex-shrink: 0;
    }
    .ac-loading, .ac-empty {
        padding: 16px 14px;
        font-size: 13px;
        color: var(--text-muted);
        text-align: center;
    }
    .ac-see-all {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        font-size: 12px;
        color: var(--accent);
        text-decoration: none;
        background: var(--surface-3);
        border-top: 1px solid var(--border);
        transition: background var(--transition);
        font-weight: 500;
        letter-spacing: 0.02em;
    }
    .ac-see-all:hover, .ac-see-all--active {
        background: var(--accent-subtle);
    }
    `;

    const styleEl = document.createElement('style');
    styleEl.textContent = styles;
    document.head.appendChild(styleEl);

    // ── Montage sur les champs de recherche ───────────────────────────────────
    requestAnimationFrame(() => {
        document.querySelectorAll('.search-input, .navbar-search-field').forEach(initAutocomplete);
    });

    // Observer pour les champs ajoutés dynamiquement
    if ('MutationObserver' in window) {
        new MutationObserver((mutations) => {
            mutations.forEach(m => m.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                node.querySelectorAll?.('.search-input, .navbar-search-field').forEach(initAutocomplete);
                if (node.matches?.('.search-input, .navbar-search-field')) initAutocomplete(node);
            }));
        }).observe(document.body, { childList: true, subtree: true });
    }

})();