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
