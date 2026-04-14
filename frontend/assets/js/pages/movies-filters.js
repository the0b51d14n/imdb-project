// frontend/assets/js/pages/movies-filters.js
// Filtres avancés côté client pour le catalogue : tri, note min, prix max.
// Fonctionne sur les éléments déjà rendus + resoumission du formulaire pour la pagination.

(function () {
    'use strict';

    // ── Éléments ──────────────────────────────────────────────────────────────
    const filtersBar = document.querySelector('.filters-bar');
    const moviesGrid = document.querySelector('.movies-grid');
    if (!moviesGrid) return;

    // ── Récupération des données depuis les cards ──────────────────────────────
    function getCards() {
        return [...moviesGrid.querySelectorAll('.movie-card[data-note][data-price]')];
    }

    // ── Filtrage + tri ────────────────────────────────────────────────────────
    function applyFilters() {
        const sortVal = document.getElementById('filter-sort')?.value || 'default';
        const noteMin = parseFloat(document.getElementById('filter-note')?.value ?? 0);
        const priceMax = parseFloat(document.getElementById('filter-price')?.value ?? 9999);
        const yearMin = parseInt(document.getElementById('filter-year')?.value ?? 0, 10);

        let cards = getCards();

        // Filtrage
        let visibleCount = 0;
        cards.forEach(card => {
            const note = parseFloat(card.dataset.note || 0);
            const price = parseFloat(card.dataset.price || 0);
            const year = parseInt(card.dataset.year || 0, 10);

            const show = note >= noteMin
                && price <= priceMax
                && (yearMin === 0 || year >= yearMin);

            card.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        // Tri (sur les cartes visibles)
        const visibleCards = cards.filter(c => c.style.display !== 'none');

        if (sortVal !== 'default' && visibleCards.length > 1) {
            const sorted = [...visibleCards].sort((a, b) => {
                switch (sortVal) {
                    case 'note-desc': return parseFloat(b.dataset.note || 0) - parseFloat(a.dataset.note || 0);
                    case 'note-asc': return parseFloat(a.dataset.note || 0) - parseFloat(b.dataset.note || 0);
                    case 'price-asc': return parseFloat(a.dataset.price || 0) - parseFloat(b.dataset.price || 0);
                    case 'price-desc': return parseFloat(b.dataset.price || 0) - parseFloat(a.dataset.price || 0);
                    case 'year-desc': return parseInt(b.dataset.year || 0) - parseInt(a.dataset.year || 0);
                    case 'year-asc': return parseInt(a.dataset.year || 0) - parseInt(b.dataset.year || 0);
                    case 'title-asc': return (a.dataset.title || '').localeCompare(b.dataset.title || '', 'fr');
                    default: return 0;
                }
            });

            sorted.forEach(card => moviesGrid.appendChild(card));
        }

        // Afficher le nombre de résultats
        const countEl = document.querySelector('.movies-page-count, [data-movies-count]');
        if (countEl) {
            countEl.textContent = `${visibleCount} film${visibleCount > 1 ? 's' : ''} trouvé${visibleCount > 1 ? 's' : ''}`;
        }

        // Message "aucun résultat"
        let noResults = moviesGrid.querySelector('.filters-no-results');
        if (visibleCount === 0) {
            if (!noResults) {
                noResults = document.createElement('div');
                noResults.className = 'filters-no-results';
                noResults.innerHTML = `
                <div class="filters-no-results-icon">🔍</div>
                <h3>Aucun film ne correspond</h3>
                <p>Essayez d'élargir vos critères de recherche.</p>
                <button class="btn-more" onclick="resetFilters()">Réinitialiser les filtres</button>`;
                moviesGrid.appendChild(noResults);
            }
        } else if (noResults) {
            noResults.remove();
        }

        // Indicateur de filtres actifs
        updateActiveCount(sortVal, noteMin, priceMax, yearMin);
    }

    function updateActiveCount(sort, note, price, year) {
        let count = 0;
        if (sort !== 'default') count++;
        if (note > 0) count++;
        if (price < 9999) count++;
        if (year > 0) count++;

        const badge = document.querySelector('.filter-active-count');
        const reset = document.querySelector('.filter-reset');

        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline-flex' : 'none';
        }
        if (reset) {
            reset.disabled = count === 0;
        }
    }

    // ── Réinitialisation ──────────────────────────────────────────────────────
    window.resetFilters = function () {
        const sort = document.getElementById('filter-sort');
        const note = document.getElementById('filter-note');
        const price = document.getElementById('filter-price');
        const year = document.getElementById('filter-year');

        if (sort) { sort.value = 'default'; }
        if (note) { note.value = note.min; updateRange(note); }
        if (price) { price.value = price.max; updateRange(price); }
        if (year) { year.value = year.min; }

        applyFilters();
    };

    // ── Mise à jour de l'affichage de la valeur du range ─────────────────────
    function updateRange(input) {
        const valueEl = document.getElementById(input.id + '-value');
        if (!valueEl) return;

        const id = input.id;
        if (id === 'filter-note') {
            valueEl.textContent = parseFloat(input.value) > 0 ? `≥ ${input.value}/10` : 'Toutes';
        } else if (id === 'filter-price') {
            const v = parseFloat(input.value);
            valueEl.textContent = v >= parseFloat(input.max) ? 'Tous' : `≤ ${input.value.replace('.', ',')}€`;
        }
    }

    // ── Écouteurs ─────────────────────────────────────────────────────────────
    if (filtersBar) {
        filtersBar.addEventListener('change', applyFilters);
        filtersBar.addEventListener('input', (e) => {
            if (e.target.type === 'range') {
                updateRange(e.target);
                applyFilters();
            }
        });
    }

    // ── Init des ranges ───────────────────────────────────────────────────────
    document.querySelectorAll('.filter-range').forEach(updateRange);
    applyFilters(); // Appel initial pour mettre à jour le compteur

})();