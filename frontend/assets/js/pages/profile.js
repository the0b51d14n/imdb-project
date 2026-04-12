(function () {
    'use strict';

    // Auto-dismiss du banner succès commande après 5s
    const orderBanner = document.querySelector('.profile-order-success');
    if (orderBanner) {
        setTimeout(() => {
            orderBanner.style.transition = 'opacity 0.5s ease, max-height 0.5s ease';
            orderBanner.style.opacity = '0';
            setTimeout(() => {
                orderBanner.style.maxHeight = '0';
                orderBanner.style.marginBottom = '0';
                orderBanner.style.overflow = 'hidden';
            }, 500);
        }, 5000);
    }

    // Confirmation avant soumission du formulaire mot de passe
    const pwdForm = document.querySelector('form[data-confirm-pwd]');
    if (pwdForm) {
        pwdForm.addEventListener('submit', (e) => {
            const newPwd = pwdForm.querySelector('[name="new_password"]')?.value || '';
            const confirmPwd = pwdForm.querySelector('[name="confirm_password"]')?.value || '';
            if (newPwd !== confirmPwd) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
            }
        });
    }

})();