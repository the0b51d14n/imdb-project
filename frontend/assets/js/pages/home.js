(function () {
    'use strict';

    // ── Carousels ────────────────────────────────────────────────────────────
    document.querySelectorAll('.carousel-wrap').forEach((wrap) => {
        const track = wrap.querySelector('.carousel-track');
        const prev = wrap.querySelector('.carousel-btn.prev');
        const next = wrap.querySelector('.carousel-btn.next');
        if (!track) return;

        const cardWidth = () => {
            const card = track.querySelector('.movie-card');
            return card ? card.offsetWidth + 14 : 194;
        };

        const scroll = (dir) => track.scrollBy({ left: dir * cardWidth() * 3, behavior: 'smooth' });

        const updateBtns = () => {
            if (prev) prev.disabled = track.scrollLeft <= 0;
            if (next) next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
        };

        if (prev) prev.addEventListener('click', () => scroll(-1));
        if (next) next.addEventListener('click', () => scroll(1));
        track.addEventListener('scroll', updateBtns, { passive: true });
        updateBtns();
    });

    // ── Trailer modal ────────────────────────────────────────────────────────
    const modal = document.getElementById('trailer-modal');
    const iframe = document.getElementById('trailer-iframe');

    window.openTrailer = (key) => {
        if (!modal || !iframe || !key) return;
        iframe.src = `https://www.youtube.com/embed/${key}?autoplay=1&rel=0&modestbranding=1`;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeTrailer = () => {
        if (!modal || !iframe) return;
        iframe.src = '';
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    window.closeTrailerOnBg = (e) => {
        if (e.target.id === 'trailer-modal') window.closeTrailer();
    };

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') window.closeTrailer();
    });

})();