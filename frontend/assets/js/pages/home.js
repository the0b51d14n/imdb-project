document.querySelectorAll('.carousel-wrap').forEach(function (wrap) {
    var track = wrap.querySelector('.carousel-track');
    var prev = wrap.querySelector('.carousel-btn.prev');
    var next = wrap.querySelector('.carousel-btn.next');
    if (!track) return;

    function cardWidth() {
        var card = track.querySelector('.movie-card');
        return card ? card.offsetWidth + 14 : 194;
    }

    function scroll(dir) {
        track.scrollBy({ left: dir * cardWidth() * 3, behavior: 'smooth' });
    }

    function updateBtns() {
        if (prev) prev.disabled = track.scrollLeft <= 0;
        if (next) next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
    }

    if (prev) prev.addEventListener('click', function () { scroll(-1); });
    if (next) next.addEventListener('click', function () { scroll(1); });
    track.addEventListener('scroll', updateBtns, { passive: true });
    updateBtns();
});

function openTrailer(key) {
    var modal = document.getElementById('trailer-modal');
    var iframe = document.getElementById('trailer-iframe');
    if (!modal || !iframe || !key) return;
    iframe.src = 'https://www.youtube.com/embed/' + key + '?autoplay=1&rel=0&modestbranding=1';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeTrailerOnBg(e) {
    if (e.target.id === 'trailer-modal') closeTrailer();
}

function closeTrailer() {
    var modal = document.getElementById('trailer-modal');
    var iframe = document.getElementById('trailer-iframe');
    if (!modal || !iframe) return;
    iframe.src = '';
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeTrailer();
});

(function applyHeroStripes() {
    var selectors = ['.btn-primary', '.btn-ghost', '.btn-more'];
    selectors.forEach(function (sel) {
        document.querySelectorAll(sel).forEach(function (el) {
            if (el.querySelector('.nfx-stripe')) return;
            for (var i = 0; i < 120; i++) {
                var s = document.createElement('span');
                s.classList.add('nfx-stripe');
                s.style.left = (i * 2) + 'px';
                s.style.transitionDelay = Math.random() + 's';
                el.appendChild(s);
            }
        });
    });
})();