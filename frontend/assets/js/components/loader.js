(function () {
    const loader = document.getElementById('page-loader');
    if (!loader) return;
    const hide = () => loader.classList.add('hidden');
    if (document.readyState === 'complete') {
        setTimeout(hide, 700);
    } else {
        window.addEventListener('load', () => setTimeout(hide, 700));
    }
})();