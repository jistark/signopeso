/* ==========================================================================
   SP2 — SignoPeso v2 Theme JavaScript
   1. Header scroll observer (compact bar toggle)
   2. "Sigue leyendo" inline post expand
   3. Infinite scroll with animated loader
   ========================================================================== */

/* ---------- 1. Header scroll observer ---------- */
document.addEventListener('DOMContentLoaded', function () {
    var headerTop = document.querySelector('.sp2-header__top');
    if (!headerTop) return;

    var observer = new IntersectionObserver(function (entries) {
        document.body.classList.toggle('sp2-header--scrolled', !entries[0].isIntersecting);
    }, { threshold: 0 });

    observer.observe(headerTop);
});

/* ---------- 2. "Sigue leyendo" inline post expand ---------- */
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.sp-post-card__read');
    if (!btn) return;

    var card = btn.closest('.sp-post-card');
    if (!card) return;

    var excerpt     = card.querySelector('.sp-post-card__excerpt');
    var fullContent = card.querySelector('.sp-post-card__full-content');

    // No full content in the DOM — let the link navigate normally.
    if (!fullContent) return;

    e.preventDefault();

    var isExpanded = !fullContent.hidden;

    if (isExpanded) {
        // Collapse
        fullContent.hidden = true;
        if (excerpt) excerpt.hidden = false;
        btn.innerHTML = 'sigue leyendo <span>→</span>';
        btn.classList.remove('sp-post-card__read--expanded');
        window.history.pushState(null, '', '/');
    } else {
        // Expand
        fullContent.hidden = false;
        if (excerpt) excerpt.hidden = true;
        btn.innerHTML = 'contraer <span>↑</span>';
        btn.classList.add('sp-post-card__read--expanded');

        // Push permalink for browser history / back-button hygiene.
        var permalink = card.dataset.permalink;
        if (permalink) {
            window.history.pushState({ spExpand: true }, '', permalink);
        }

        // Fire Jetpack Stats pageview.
        if (typeof _stq !== 'undefined') {
            var img   = new Image();
            var blogId = (window._stq_config && window._stq_config.blog_id) ? window._stq_config.blog_id : '';
            img.src = document.location.protocol +
                '//pixel.wp.com/g.gif?v=ext&blog=' + blogId +
                '&post=' + (card.dataset.postId || '0') +
                '&t=' + Date.now();
        }

        // Fire Google Analytics pageview.
        if (typeof gtag === 'function' && permalink) {
            try {
                gtag('event', 'page_view', {
                    page_path: new URL(permalink).pathname
                });
            } catch (_) {}
        }
    }
});

/* ---------- 3. Infinite scroll ---------- */
(function () {
    var SYMBOLS       = ['$', '€', '£', '¥', '₱', '₿', '₩', '₹'];
    var symbolIndex   = 0;
    var symbolInterval = null;
    var loading       = false;

    function startSymbolRotation(el) {
        symbolIndex    = 0;
        symbolInterval = setInterval(function () {
            symbolIndex   = (symbolIndex + 1) % SYMBOLS.length;
            el.textContent = SYMBOLS[symbolIndex];
        }, 400);
    }

    function stopSymbolRotation() {
        if (symbolInterval) {
            clearInterval(symbolInterval);
            symbolInterval = null;
        }
    }

    function initInfiniteScroll() {
        var loader  = document.querySelector('.sp2-loader');
        if (!loader) return;

        var stream   = document.querySelector('.sp-date-stream');
        if (!stream) return;

        var symbolEl = loader.querySelector('.sp2-loader__symbol');

        var observer = new IntersectionObserver(function (entries) {
            if (!entries[0].isIntersecting || loading) return;

            var nextPage = parseInt(loader.dataset.nextPage, 10);
            var maxPages = parseInt(loader.dataset.maxPages, 10);

            if (isNaN(nextPage) || isNaN(maxPages) || nextPage > maxPages) {
                loader.style.display = 'none';
                return;
            }

            var perPage = loader.dataset.perPage || 10;

            loading        = true;
            loader.hidden  = false;
            if (symbolEl) startSymbolRotation(symbolEl);

            fetch('/wp-json/signopeso/v1/stream?page=' + nextPage + '&per_page=' + perPage)
                .then(function (r) {
                    if (!r.ok) throw new Error('Network response was not ok');
                    return r.text();
                })
                .then(function (html) {
                    if (!html.trim()) {
                        loader.style.display = 'none';
                        return;
                    }

                    // Insert rendered HTML before the loader element.
                    var temp = document.createElement('div');
                    temp.innerHTML = html;
                    while (temp.firstChild) {
                        stream.insertBefore(temp.firstChild, loader);
                    }

                    loader.dataset.nextPage = nextPage + 1;
                    loading = false;
                    stopSymbolRotation();
                    if (symbolEl) symbolEl.textContent = SYMBOLS[0];
                })
                .catch(function () {
                    loading = false;
                    stopSymbolRotation();
                    loader.style.display = 'none';
                });
        }, { rootMargin: '200px' });

        observer.observe(loader);
    }

    document.addEventListener('DOMContentLoaded', initInfiniteScroll);
})();
