// frontend/assets/js/pages/movie-detail.js
// Interactions de la page fiche film : trailer, cast scroll, sticky panel, animations

(function () {
    'use strict';

    // ── Trailer modal ────────────────────────────────────────────────────────
    // (déjà défini inline dans backend/pages/movie-detail.php,
    //  ce fichier enrichit avec des fonctionnalités complémentaires)

    // Ouverture trailer via bouton clavier (Entrée / Espace)
    document.querySelectorAll('[onclick^="openTrailer"]').forEach((btn) => {
        btn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                btn.click();
            }
        });
    });

    // ── Animation d'entrée des éléments de la page ───────────────────────────
    if ('IntersectionObserver' in window) {

        // Cast avatars
        const castObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0) scale(1)';
                    }, i * 60);
                    castObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.movie-actor').forEach((actor) => {
            actor.style.opacity = '0';
            actor.style.transform = 'translateY(12px) scale(0.96)';
            actor.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
            castObserver.observe(actor);
        });

        // Grille recommandations
        const recObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, i * 50);
                    recObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05 });

        document.querySelectorAll('.movie-rec-grid .movie-card').forEach((card) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(16px)';
            card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            recObserver.observe(card);
        });

        // Section recommandations header
        const recHeader = document.querySelector('.movie-recommendations');
        if (recHeader) {
            const headerObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                        headerObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            recHeader.style.opacity = '0';
            recHeader.style.transform = 'translateY(20px)';
            recHeader.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            headerObserver.observe(recHeader);
        }
    }

    // ── Panneau achat sticky — effet parallaxe léger au scroll ───────────────
    const buyPanel = document.querySelector('.movie-buy-panel');
    const posterWrap = document.querySelector('.movie-poster-wrap');

    if (buyPanel && posterWrap) {
        let ticking = false;

        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    const scrollY = window.scrollY;
                    // Légère translation vers le bas sur scroll
                    const offset = Math.min(scrollY * 0.04, 24);
                    posterWrap.style.transform = `translateY(${offset}px)`;
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });
    }

    // ── Bouton "Ajouter au panier" — feedback visuel au clic ─────────────────
    const addToCartForm = document.querySelector('form [name="action"][value="add_to_cart"]');
    if (addToCartForm) {
        const form = addToCartForm.closest('form');
        const btn  = form?.querySelector('button[type="submit"]');

        if (btn) {
            form.addEventListener('submit', () => {
                btn.disabled = true;
                btn.style.opacity = '0.7';
                btn.style.cursor  = 'not-allowed';
            });
        }
    }

    // ── Tags / badges — animation d'entrée ───────────────────────────────────
    document.querySelectorAll('.movie-tag, .movie-rating').forEach((tag, i) => {
        tag.style.opacity = '0';
        tag.style.transform = 'translateY(-6px)';
        tag.style.transition = `opacity 0.3s ease ${i * 0.06}s, transform 0.3s ease ${i * 0.06}s`;

        // Déclencher après un court délai (laisser le DOM se stabiliser)
        setTimeout(() => {
            tag.style.opacity = '1';
            tag.style.transform = 'translateY(0)';
        }, 80 + i * 60);
    });

    // ── Titre du film — effet de reveal ──────────────────────────────────────
    const movieTitle = document.querySelector('.movie-title');
    if (movieTitle) {
        movieTitle.style.opacity = '0';
        movieTitle.style.transform = 'translateY(16px)';
        movieTitle.style.transition = 'opacity 0.5s ease 0.1s, transform 0.5s ease 0.1s';
        setTimeout(() => {
            movieTitle.style.opacity = '1';
            movieTitle.style.transform = 'translateY(0)';
        }, 100);
    }

    // ── Synopsis — reveal progressif ─────────────────────────────────────────
    const synopsis = document.querySelector('.movie-synopsis');
    if (synopsis) {
        synopsis.style.opacity = '0';
        synopsis.style.transition = 'opacity 0.6s ease 0.25s';
        setTimeout(() => { synopsis.style.opacity = '1'; }, 150);
    }

    // ── Collection badge — glow au hover ─────────────────────────────────────
    const collection = document.querySelector('.movie-collection');
    if (collection) {
        collection.style.opacity = '0';
        collection.style.transition = 'opacity 0.4s ease 0.3s, box-shadow 0.3s ease, border-color 0.3s ease';
        setTimeout(() => { collection.style.opacity = '1'; }, 200);

        collection.addEventListener('mouseenter', () => {
            collection.style.borderColor = 'var(--accent)';
            collection.style.boxShadow = '0 0 16px var(--accent-glow)';
        });
        collection.addEventListener('mouseleave', () => {
            collection.style.borderColor = '';
            collection.style.boxShadow = '';
        });
    }

    // ── Alert succès — auto-dismiss après 4s ─────────────────────────────────
    const successAlert = document.querySelector('.movie-alert-success');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 0.4s ease, max-height 0.4s ease, padding 0.4s ease, margin 0.4s ease';
            successAlert.style.opacity = '0';
            successAlert.style.maxHeight = '0';
            successAlert.style.padding = '0';
            successAlert.style.marginBottom = '0';
            successAlert.style.overflow = 'hidden';
        }, 4000);
    }

    // ── Poster — effet de brillance au hover ─────────────────────────────────
    const poster = document.querySelector('.movie-poster-wrap img');
    if (poster) {
        const wrap = poster.closest('.movie-poster-wrap');
        if (wrap) {
            // Shimmer overlay
            const shimmer = document.createElement('div');
            shimmer.style.cssText = `
                position: absolute;
                inset: 0;
                background: linear-gradient(
                    105deg,
                    transparent 40%,
                    rgba(255,255,255,0.08) 50%,
                    transparent 60%
                );
                background-size: 200% 100%;
                background-position: 200% 0;
                transition: background-position 0.6s ease;
                pointer-events: none;
                z-index: 2;
                border-radius: inherit;
            `;
            wrap.style.position = 'relative';
            wrap.appendChild(shimmer);

            wrap.addEventListener('mouseenter', () => {
                shimmer.style.backgroundPosition = '-200% 0';
            });
            wrap.addEventListener('mouseleave', () => {
                shimmer.style.backgroundPosition = '200% 0';
            });
        }
    }

})();