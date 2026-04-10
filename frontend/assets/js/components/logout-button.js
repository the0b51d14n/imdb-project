(function () {
    'use strict';

    const STATES = {
        default: {
            '--figure-duration': '100ms',
            '--transform-figure': 'none',
            '--walking-duration': '100ms',
            '--transform-arm1': 'none',
            '--transform-wrist1': 'none',
            '--transform-arm2': 'none',
            '--transform-wrist2': 'none',
            '--transform-leg1': 'none',
            '--transform-calf1': 'none',
            '--transform-leg2': 'none',
            '--transform-calf2': 'none',
        },
        hover: {
            '--figure-duration': '100ms',
            '--transform-figure': 'translateX(1.5px)',
            '--walking-duration': '100ms',
            '--transform-arm1': 'rotate(-5deg)',
            '--transform-wrist1': 'rotate(-15deg)',
            '--transform-arm2': 'rotate(5deg)',
            '--transform-wrist2': 'rotate(6deg)',
            '--transform-leg1': 'rotate(-10deg)',
            '--transform-calf1': 'rotate(5deg)',
            '--transform-leg2': 'rotate(20deg)',
            '--transform-calf2': 'rotate(-20deg)',
        },
        walking1: {
            '--figure-duration': '300ms',
            '--transform-figure': 'translateX(11px)',
            '--walking-duration': '300ms',
            '--transform-arm1': 'translateX(-4px) translateY(-2px) rotate(120deg)',
            '--transform-wrist1': 'rotate(-5deg)',
            '--transform-arm2': 'translateX(4px) rotate(-110deg)',
            '--transform-wrist2': 'rotate(-5deg)',
            '--transform-leg1': 'translateX(-3px) rotate(80deg)',
            '--transform-calf1': 'rotate(-30deg)',
            '--transform-leg2': 'translateX(4px) rotate(-60deg)',
            '--transform-calf2': 'rotate(20deg)',
        },
        walking2: {
            '--figure-duration': '400ms',
            '--transform-figure': 'translateX(17px)',
            '--walking-duration': '300ms',
            '--transform-arm1': 'rotate(60deg)',
            '--transform-wrist1': 'rotate(-15deg)',
            '--transform-arm2': 'rotate(-45deg)',
            '--transform-wrist2': 'rotate(6deg)',
            '--transform-leg1': 'rotate(-5deg)',
            '--transform-calf1': 'rotate(10deg)',
            '--transform-leg2': 'rotate(10deg)',
            '--transform-calf2': 'rotate(-20deg)',
        },
        falling1: {
            '--figure-duration': '1600ms',
            '--walking-duration': '400ms',
            '--transform-arm1': 'rotate(-60deg)',
            '--transform-wrist1': 'none',
            '--transform-arm2': 'rotate(30deg)',
            '--transform-wrist2': 'rotate(120deg)',
            '--transform-leg1': 'rotate(-30deg)',
            '--transform-calf1': 'rotate(-20deg)',
            '--transform-leg2': 'rotate(20deg)',
        },
        falling2: {
            '--walking-duration': '300ms',
            '--transform-arm1': 'rotate(-100deg)',
            '--transform-arm2': 'rotate(-60deg)',
            '--transform-wrist2': 'rotate(60deg)',
            '--transform-leg1': 'rotate(80deg)',
            '--transform-calf1': 'rotate(20deg)',
            '--transform-leg2': 'rotate(-60deg)',
        },
        falling3: {
            '--walking-duration': '500ms',
            '--transform-arm1': 'rotate(-30deg)',
            '--transform-wrist1': 'rotate(40deg)',
            '--transform-arm2': 'rotate(50deg)',
            '--transform-wrist2': 'none',
            '--transform-leg1': 'rotate(-30deg)',
            '--transform-leg2': 'rotate(20deg)',
            '--transform-calf2': 'none',
        },
    };

    const applyState = (btn, name) => {
        if (!STATES[name]) return;
        btn._logoutState = name;
        for (const [prop, val] of Object.entries(STATES[name])) {
            btn.style.setProperty(prop, val);
        }
    };

    const ms = (name, key) => parseInt(STATES[name]?.[key] ?? '100ms', 10);

    document.querySelectorAll('.logout-btn-animated').forEach((btn) => {
        btn._logoutState = 'default';

        btn.addEventListener('mouseenter', () => {
            if (btn._logoutState === 'default') applyState(btn, 'hover');
        });

        btn.addEventListener('mouseleave', () => {
            if (btn._logoutState === 'hover') applyState(btn, 'default');
        });

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const href = btn.dataset.href || null;
            if (btn._logoutState !== 'default' && btn._logoutState !== 'hover') return;

            btn.classList.add('clicked');
            applyState(btn, 'walking1');

            setTimeout(() => {
                btn.classList.add('door-slammed');
                applyState(btn, 'walking2');

                setTimeout(() => {
                    btn.classList.add('falling');
                    applyState(btn, 'falling1');

                    setTimeout(() => {
                        applyState(btn, 'falling2');

                        setTimeout(() => {
                            applyState(btn, 'falling3');

                            setTimeout(() => {
                                btn.classList.remove('clicked', 'door-slammed', 'falling');
                                applyState(btn, 'default');
                                if (href) window.location.href = href;
                            }, ms('falling3', '--walking-duration'));
                        }, ms('falling2', '--walking-duration'));
                    }, ms('falling1', '--walking-duration'));
                }, ms('walking2', '--figure-duration'));
            }, ms('walking1', '--figure-duration'));
        });
    });

})();