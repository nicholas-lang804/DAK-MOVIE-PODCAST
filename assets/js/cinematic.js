/* Cinematic theme helpers: mobile menu, auto-scrolling stars rail */
(function () {
    'use strict';

    var toggle = document.querySelector('.nav-toggle');
    var menu = document.getElementById('primary-menu');
    if (toggle && menu) {
        var setOpen = function (open) {
            menu.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
        document.addEventListener('click', function (e) {
            if (!menu.contains(e.target) && !toggle.contains(e.target)) setOpen(false);
        });
        window.addEventListener('resize', function () { if (window.innerWidth > 1240) setOpen(false); });
    }

    // Gentle drift on the stars rail; pauses on touch/hover/focus and for reduced-motion users.
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) return;
    document.querySelectorAll('.stars-section .stars-rail').forEach(function (rail) {
        if (rail.scrollWidth <= rail.clientWidth + 8) return;
        var paused = false, dir = 1, last = 0;
        ['mouseenter', 'touchstart', 'focusin', 'pointerdown'].forEach(function (ev) { rail.addEventListener(ev, function () { paused = true; }, { passive: true }); });
        ['mouseleave', 'focusout'].forEach(function (ev) { rail.addEventListener(ev, function () { paused = false; }); });
        function tick(ts) {
            if (!paused && ts - last > 30) {
                last = ts;
                rail.scrollLeft += dir * 0.6;
                if (rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 1) dir = -1;
                else if (rail.scrollLeft <= 0) dir = 1;
            }
            window.requestAnimationFrame(tick);
        }
        window.requestAnimationFrame(tick);
    });
})();
