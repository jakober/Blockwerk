/**
 * Frontend-Interaktion der CMS-Blöcke: Slider/Hero und Galerie-Lightbox.
 * Wird vom Renderer automatisch in jede Seite injiziert.
 */
(function () {
    'use strict';

    /* ---------- Slider & Hero ---------- */

    document.querySelectorAll('[data-slider]').forEach(function (slider) {
        const slides = Array.from(slider.querySelectorAll('.cms-slide'));
        if (slides.length < 2) return;

        let index = slides.findIndex((s) => s.classList.contains('is-active'));
        if (index < 0) index = 0;
        let timer = null;

        const show = (i) => {
            index = (i + slides.length) % slides.length;
            slides.forEach((s, n) => s.classList.toggle('is-active', n === index));
            dots.forEach((d, n) => d.classList.toggle('is-active', n === index));
        };

        let dots = [];
        if (slider.hasAttribute('data-dots')) {
            const wrap = document.createElement('div');
            wrap.className = 'cms-slider-dots';
            slides.forEach(function (_, i) {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.setAttribute('aria-label', 'Slide ' + (i + 1));
                if (i === index) dot.classList.add('is-active');
                dot.addEventListener('click', function () { show(i); restart(); });
                wrap.appendChild(dot);
                dots.push(dot);
            });
            slider.appendChild(wrap);
        }

        if (slider.hasAttribute('data-arrows')) {
            [['is-prev', '‹', -1], ['is-next', '›', 1]].forEach(function (def) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'cms-slider-arrow ' + def[0];
                btn.innerHTML = def[1];
                btn.setAttribute('aria-label', def[2] < 0 ? 'Vorheriger Slide' : 'Nächster Slide');
                btn.addEventListener('click', function () { show(index + def[2]); restart(); });
                slider.appendChild(btn);
            });
        }

        const restart = () => {
            if (!slider.hasAttribute('data-autoplay')) return;
            clearInterval(timer);
            const seconds = parseInt(slider.getAttribute('data-interval'), 10) || 5;
            timer = setInterval(function () { show(index + 1); }, seconds * 1000);
        };
        slider.addEventListener('mouseenter', function () { clearInterval(timer); });
        slider.addEventListener('mouseleave', restart);
        restart();
    });

    /* ---------- Galerie-Lightbox ---------- */

    document.querySelectorAll('.cms-gallery[data-lightbox]').forEach(function (gallery) {
        const links = Array.from(gallery.querySelectorAll('.cms-gl-link'));
        links.forEach(function (link, i) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                openLightbox(links, i);
            });
        });
    });

    function openLightbox(links, startIndex) {
        let index = startIndex;

        const overlay = document.createElement('div');
        overlay.className = 'cms-lightbox';
        overlay.innerHTML =
            '<img src="" alt=""><div class="cms-lightbox-caption"></div>' +
            '<button type="button" class="cms-lightbox-close" aria-label="Schließen">✕</button>' +
            (links.length > 1
                ? '<button type="button" class="cms-lightbox-prev" aria-label="Zurück">‹</button>' +
                  '<button type="button" class="cms-lightbox-next" aria-label="Weiter">›</button>'
                : '');
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';

        const img = overlay.querySelector('img');
        const caption = overlay.querySelector('.cms-lightbox-caption');

        const show = (i) => {
            index = (i + links.length) % links.length;
            img.src = links[index].getAttribute('href');
            const text = links[index].getAttribute('data-caption') || '';
            caption.textContent = text;
            caption.style.display = text ? '' : 'none';
        };
        show(index);

        const close = () => {
            overlay.remove();
            document.body.style.overflow = '';
            document.removeEventListener('keydown', onKey);
        };
        const onKey = (e) => {
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') show(index - 1);
            if (e.key === 'ArrowRight') show(index + 1);
        };

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.closest('.cms-lightbox-close')) close();
            if (e.target.closest('.cms-lightbox-prev')) show(index - 1);
            if (e.target.closest('.cms-lightbox-next')) show(index + 1);
        });
        document.addEventListener('keydown', onKey);
    }
})();

/* ---------- Countdown ---------- */
(function () {
    'use strict';
    document.querySelectorAll('[data-countdown]').forEach(function (el) {
        const target = new Date(el.getAttribute('data-countdown')).getTime();
        if (isNaN(target)) return;
        const cells = {};
        el.querySelectorAll('[data-cd]').forEach(function (cell) { cells[cell.getAttribute('data-cd')] = cell; });
        const tick = function () {
            let diff = Math.floor((target - Date.now()) / 1000);
            if (diff <= 0) {
                const grid = el.querySelector('.cms-cd-grid');
                if (grid) grid.outerHTML = '<div class="cms-cd-done">' + (el.getAttribute('data-expired') || 'Es ist so weit!') + '</div>';
                clearInterval(timer);
                return;
            }
            const d = Math.floor(diff / 86400); diff %= 86400;
            const h = Math.floor(diff / 3600); diff %= 3600;
            const m = Math.floor(diff / 60);
            const s = diff % 60;
            if (cells.d) cells.d.textContent = d;
            if (cells.h) cells.h.textContent = h;
            if (cells.m) cells.m.textContent = m;
            if (cells.s) cells.s.textContent = s;
        };
        tick();
        const timer = setInterval(tick, 1000);
    });
})();

/* ---------- Navigation: Mobil-Menü, Touch-Bedienung, Mega-Vollbreite ---------- */
(function () {
    'use strict';

    document.querySelectorAll('[data-nav]').forEach(function (nav) {
        const breakpoint = parseInt(nav.getAttribute('data-breakpoint'), 10) || 0;
        const toggle = nav.querySelector('.cms-nav-toggle');

        const applyMode = function () {
            const mobile = breakpoint > 0 && window.innerWidth <= breakpoint;
            nav.classList.toggle('is-mobile', mobile);
            if (!mobile) nav.classList.remove('is-open');
        };
        applyMode();
        window.addEventListener('resize', applyMode);

        if (toggle) {
            toggle.addEventListener('click', function () {
                const rect = nav.getBoundingClientRect();
                nav.style.setProperty('--nav-mob-top', Math.max(0, rect.bottom) + 'px');
                const open = nav.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        // Mega-Menü über die volle Seitenbreite ausrichten
        if (nav.classList.contains('is-mega-full')) {
            const position = function () {
                nav.querySelectorAll('.cms-mega-panel').forEach(function (panel) {
                    if (nav.classList.contains('is-mobile')) {
                        panel.style.left = '';
                        panel.style.width = '';
                        return;
                    }
                    const ul = panel.closest('ul');
                    if (!ul) return;
                    const rect = ul.getBoundingClientRect();
                    panel.style.left = (-rect.left) + 'px';
                    panel.style.width = document.documentElement.clientWidth + 'px';
                });
            };
            position();
            window.addEventListener('resize', position);
        }
    });

    // Touch-Geräte (Tablets/Handys ohne Maus): erster Tipp öffnet das
    // Untermenü/Mega-Panel, zweiter Tipp folgt dem Link.
    if (window.matchMedia('(hover: none)').matches) {
        document.addEventListener('click', function (e) {
            const link = e.target.closest('.cms-menu li.has-children > a, .menu li.has-children > a');
            if (!link) {
                if (!e.target.closest('.cms-mega-panel, ul.submenu')) {
                    document.querySelectorAll('.cms-menu li.is-open, .menu li.is-open').forEach(function (li) {
                        li.classList.remove('is-open');
                    });
                }
                return;
            }
            const li = link.parentElement;
            const nav = link.closest('.cms-nav');
            if (nav && nav.classList.contains('is-mobile')) return; // Mobil: Listen sind offen
            if (!li.classList.contains('is-open')) {
                e.preventDefault();
                if (li.parentElement) {
                    li.parentElement.querySelectorAll(':scope > li.is-open').forEach(function (other) {
                        if (other !== li) other.classList.remove('is-open');
                    });
                }
                li.classList.add('is-open');
            }
        });
    }
})();

/* Scroll-Animationen: Blöcke mit .cms-anim beim Sichtbarwerden einblenden. */
(function () {
    document.documentElement.classList.add('cms-js');
    const els = document.querySelectorAll('.cms-anim');
    if (!els.length) return;
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        els.forEach((el) => el.classList.add('in-view'));
        return;
    }
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('in-view');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    els.forEach((el) => observer.observe(el));
})();
