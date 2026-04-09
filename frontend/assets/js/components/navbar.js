(function () {
    const navbar = document.getElementById('navbar');

    if (navbar) {
        const onScroll = () => navbar.classList.toggle('scrolled', window.scrollY > 10);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    const burger = document.getElementById('navbar-hamburger');
    if (burger) {
        burger.addEventListener('click', () => {
            const links = document.querySelector('.navbar-links');
            if (!links) return;
            const isOpen = links.style.display === 'flex';
            links.style.cssText = isOpen ? '' :
                'display:flex;flex-direction:column;position:absolute;top:64px;left:0;right:0;' +
                'background:rgba(13,13,13,0.98);padding:16px 24px;gap:16px;border-bottom:1px solid #2a2a2a;z-index:99;';
        });
    }
})();