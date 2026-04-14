// frontend/assets/js/components/rating-widget.js
// Widget de notation par étoiles pour les films achetés.
// S'attache aux éléments .rating-widget
// Requiert : data-tmdb-id, data-csrf, data-purchased="1"

(function () {
    'use strict';

    const API = '/backend/api/ratings.php';

    function getCsrf(el) {
        return el?.dataset?.csrf
            || document.querySelector('meta[name="csrf-token"]')?.content
            || '';
    }

    // ── Rendu HTML du widget ─────────────────────────────────────────────────
    function renderWidget(container, tmdbId, csrf, currentRating = 0, currentComment = '') {
        container.innerHTML = `
        <div class="rw-stars" role="group" aria-label="Votre note">
            ${[1,2,3,4,5].map(i => `
            <button class="rw-star ${i <= currentRating ? 'rw-star--active' : ''}"
                    data-value="${i}"
                    aria-label="${i} étoile${i > 1 ? 's' : ''}"
                    title="${i} étoile${i > 1 ? 's' : ''}">
                <svg viewBox="0 0 24 24" fill="${i <= currentRating ? 'var(--gold)' : 'none'}"
                     stroke="${i <= currentRating ? 'var(--gold)' : 'var(--border-bright)'}"
                     stroke-width="1.5">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
            </button>`).join('')}
            <span class="rw-label">${currentRating > 0 ? ratingLabel(currentRating) : 'Notez ce film'}</span>
        </div>
        <div class="rw-comment-wrap" style="display:${currentRating > 0 ? 'flex' : 'none'};">
            <textarea class="rw-comment" placeholder="Votre avis (optionnel)" maxlength="1000"
                      rows="3">${currentComment}</textarea>
            <div class="rw-actions">
                <button class="rw-submit btn-primary">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    ${currentRating > 0 && currentComment ? 'Modifier' : 'Enregistrer'}
                </button>
                ${currentRating > 0 ? `
                <button class="rw-delete" title="Supprimer ma note">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    </svg>
                    Supprimer
                </button>` : ''}
            </div>
            <div class="rw-char-count"><span class="rw-chars">0</span>/1000 caractères</div>
        </div>
        <div class="rw-status" aria-live="polite"></div>`;

        attachEvents(container, tmdbId, csrf);
    }

    function ratingLabel(r) {
        return ['', '😞 Décevant', '😐 Passable', '😊 Bien', '😄 Très bien', '🤩 Excellent'][r] || '';
    }

    // ── Gestion des événements ────────────────────────────────────────────────
    function attachEvents(container, tmdbId, csrf) {
        let selectedRating = 0;
        const stars        = container.querySelectorAll('.rw-star');
        const label        = container.querySelector('.rw-label');
        const commentWrap  = container.querySelector('.rw-comment-wrap');
        const commentEl    = container.querySelector('.rw-comment');
        const submitBtn    = container.querySelector('.rw-submit');
        const deleteBtn    = container.querySelector('.rw-delete');
        const statusEl     = container.querySelector('.rw-status');
        const charCount    = container.querySelector('.rw-chars');

        // Récupérer la note courante depuis les étoiles actives
        stars.forEach(s => {
            if (s.classList.contains('rw-star--active')) {
                selectedRating = parseInt(s.dataset.value, 10);
            }
        });

        // Hover
        stars.forEach(star => {
            star.addEventListener('mouseenter', () => {
                const val = parseInt(star.dataset.value, 10);
                highlightStars(stars, val, false);
                if (label) label.textContent = ratingLabel(val);
            });
        });

        container.querySelector('.rw-stars')?.addEventListener('mouseleave', () => {
            highlightStars(stars, selectedRating, true);
            if (label) label.textContent = selectedRating > 0 ? ratingLabel(selectedRating) : 'Notez ce film';
        });

        // Clic sur étoile
        stars.forEach(star => {
            star.addEventListener('click', () => {
                const val = parseInt(star.dataset.value, 10);
                // Toggle : cliquer sur la même note la désélectionne
                selectedRating = selectedRating === val ? 0 : val;
                highlightStars(stars, selectedRating, true);
                if (label) label.textContent = selectedRating > 0 ? ratingLabel(selectedRating) : 'Notez ce film';
                if (commentWrap) commentWrap.style.display = selectedRating > 0 ? 'flex' : 'none';
            });
        });

        // Compteur de caractères
        commentEl?.addEventListener('input', () => {
            if (charCount) charCount.textContent = commentEl.value.length;
        });

        // Soumission
        submitBtn?.addEventListener('click', async () => {
            if (selectedRating === 0) {
                showStatus(statusEl, 'Sélectionnez d\'abord une note.', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoi…';

            try {
                const res = await fetch(API, {
                    method:  'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'X-CSRF-TOKEN':     csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        action:   'submit',
                        tmdb_id:  tmdbId,
                        rating:   selectedRating,
                        comment:  commentEl?.value?.trim() || '',
                    }),
                });
                const data = await res.json();

                if (data.ok) {
                    showStatus(statusEl, '✅ Note enregistrée !', 'success');
                    submitBtn.textContent = 'Modifier';
                    submitBtn.disabled = false;
                    // Rafraîchir les stats affichées
                    refreshMovieRatings(tmdbId);
                } else {
                    showStatus(statusEl, data.error || 'Erreur.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Enregistrer';
                }
            } catch {
                showStatus(statusEl, 'Erreur réseau.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enregistrer';
            }
        });

        // Suppression
        deleteBtn?.addEventListener('click', async () => {
            if (!confirm('Supprimer votre note pour ce film ?')) return;

            deleteBtn.disabled = true;
            try {
                const res = await fetch(API, {
                    method:  'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'X-CSRF-TOKEN':     csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ action: 'delete', tmdb_id: tmdbId }),
                });
                const data = await res.json();

                if (data.ok) {
                    selectedRating = 0;
                    renderWidget(container, tmdbId, csrf, 0, '');
                    refreshMovieRatings(tmdbId);
                } else {
                    deleteBtn.disabled = false;
                }
            } catch {
                deleteBtn.disabled = false;
            }
        });
    }

    function highlightStars(stars, value, persist) {
        stars.forEach(s => {
            const v   = parseInt(s.dataset.value, 10);
            const svg = s.querySelector('svg');
            const on  = v <= value;
            s.classList.toggle('rw-star--active', on && persist);
            if (svg) {
                svg.setAttribute('fill', on ? 'var(--gold)' : 'none');
                svg.setAttribute('stroke', on ? 'var(--gold)' : 'var(--border-bright)');
            }
        });
    }

    function showStatus(el, msg, type) {
        if (!el) return;
        el.textContent = msg;
        el.className   = 'rw-status rw-status--' + type;
        setTimeout(() => { el.textContent = ''; el.className = 'rw-status'; }, 4000);
    }

    // ── Rafraîchir les stats du film ─────────────────────────────────────────
    async function refreshMovieRatings(tmdbId) {
        const statsEl = document.querySelector('[data-rating-stats]');
        if (!statsEl) return;

        try {
            const res  = await fetch(`${API}?tmdb_id=${tmdbId}`);
            const data = await res.json();
            if (!data.ok) return;

            const avgEl   = statsEl.querySelector('.rw-avg');
            const countEl = statsEl.querySelector('.rw-count');
            if (avgEl)   avgEl.textContent   = data.avg > 0 ? data.avg.toFixed(1) + '/5' : '-';
            if (countEl) countEl.textContent  = data.count + ' avis';
        } catch {}
    }

    // ── Styles ────────────────────────────────────────────────────────────────
    const styles = `
    .rating-widget {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px;
        margin-top: 20px;
    }
    .rating-widget-title {
        font-size: 13px;
        font-weight: 500;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--text-faint);
        margin-bottom: 14px;
    }
    .rw-stars {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 12px;
    }
    .rw-star {
        background: none;
        border: none;
        padding: 2px;
        cursor: pointer;
        transition: transform var(--transition-snap);
        line-height: 0;
    }
    .rw-star:hover { transform: scale(1.2); }
    .rw-star svg { width: 28px; height: 28px; display: block; transition: fill 0.15s, stroke 0.15s; }
    .rw-label {
        font-size: 13px;
        color: var(--text-muted);
        margin-left: 6px;
        transition: color var(--transition);
        white-space: nowrap;
    }
    .rw-comment-wrap {
        flex-direction: column;
        gap: 10px;
    }
    .rw-comment {
        width: 100%;
        padding: 10px 12px;
        background: var(--surface-3);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        color: var(--text);
        font-family: var(--font);
        font-size: 13px;
        outline: none;
        resize: vertical;
        transition: border-color var(--transition);
        box-sizing: border-box;
    }
    .rw-comment:focus { border-color: var(--accent); }
    .rw-actions { display: flex; gap: 10px; align-items: center; }
    .rw-delete {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 12px; color: var(--text-faint);
        background: none; border: 1px solid var(--border);
        border-radius: var(--radius); padding: 8px 14px;
        cursor: pointer; font-family: var(--font);
        transition: all var(--transition);
    }
    .rw-delete:hover { color: var(--danger); border-color: var(--danger); }
    .rw-char-count { font-size: 11px; color: var(--text-faint); text-align: right; }
    .rw-status { font-size: 13px; margin-top: 6px; min-height: 20px; }
    .rw-status--success { color: var(--accent); }
    .rw-status--error   { color: var(--danger); }

    /* Stats globales */
    .rw-global-stats {
        display: flex; align-items: center; gap: 16px;
        padding: 14px 18px;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: var(--radius-lg); margin-bottom: 16px;
    }
    .rw-avg { font-size: 32px; font-weight: 500; color: var(--gold); letter-spacing: -0.02em; }
    .rw-avg-stars { display: flex; gap: 3px; }
    .rw-avg-stars svg { width: 16px; height: 16px; }
    .rw-count { font-size: 13px; color: var(--text-muted); }
    `;

    const styleEl = document.createElement('style');
    styleEl.textContent = styles;
    document.head.appendChild(styleEl);

    // ── Initialisation ────────────────────────────────────────────────────────
    async function initWidget(container) {
        if (container._rwInit) return;
        container._rwInit = true;

        const tmdbId   = parseInt(container.dataset.tmdbId, 10);
        const csrf     = getCsrf(container);
        const purchased = container.dataset.purchased === '1';

        if (!purchased) {
            container.innerHTML = `
            <p style="font-size:13px;color:var(--text-faint);text-align:center;padding:16px 0;">
                Achetez ce film pour pouvoir le noter.
            </p>`;
            return;
        }

        container.innerHTML = '<div style="font-size:13px;color:var(--text-faint);padding:8px 0;">Chargement…</div>';

        try {
            const res  = await fetch(`${API}?tmdb_id=${tmdbId}&mine=1`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            const r    = data.rating;

            renderWidget(container, tmdbId, csrf, r?.rating || 0, r?.comment || '');
        } catch {
            renderWidget(container, tmdbId, csrf, 0, '');
        }
    }

    requestAnimationFrame(() => {
        document.querySelectorAll('.rating-widget[data-tmdb-id]').forEach(initWidget);
    });

})();