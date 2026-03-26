/* ==========================================================================
   SP2 — SignoPeso v2 Theme JavaScript
   0. Elevator marquee (date + FX rate)
   1. Header scroll observer (compact bar toggle)
   2. Custom menu open/close
   3. "Sigue leyendo" inline post expand
   4. Infinite scroll with animated loader
   ========================================================================== */

/* ---------- 0. Elevator ticker ---------- */
document.addEventListener('DOMContentLoaded', function () {
    var track = document.getElementById('sp2-ticker');
    if (!track) return;

    var days = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    var months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var now = new Date();

    function pad(n) { return n < 10 ? '0' + n : n; }

    // Read server-provided data (from wp_localize_script), fallback to placeholders
    var t = window.sp2Ticker || {};
    var fx = t.fx || {};
    var weather = t.weather || {};
    var lastUpdate = t.lastUpdate ? new Date(t.lastUpdate * 1000) : now;

    // Format last update as relative or absolute
    var diffMin = Math.floor((now.getTime() - lastUpdate.getTime()) / 60000);
    var lastUpdateStr;
    if (diffMin < 60) {
        lastUpdateStr = 'hace <strong>' + Math.max(1, diffMin) + 'min</strong>';
    } else if (diffMin < 1440) {
        lastUpdateStr = 'hace <strong>' + Math.floor(diffMin / 60) + 'h</strong>';
    } else {
        lastUpdateStr = '<strong>' + pad(lastUpdate.getHours()) + ':' + pad(lastUpdate.getMinutes()) + '</strong>, ' + pad(lastUpdate.getDate()) + '/' + pad(lastUpdate.getMonth() + 1);
    }

    var usd = fx.usd || '—';
    var eur = fx.eur || '—';
    var temp = weather.temp || '—';
    var condition = weather.condition || '';
    var emoji = weather.emoji || '🌡';

    var items = [
        'cdmx, <strong>' + days[now.getDay()] + ' ' + now.getDate() + ' de ' + months[now.getMonth()] + '</strong>',
        '💵 $1 usd = <strong>$' + usd + ' mxn</strong>',
        '💶 €1 eur = <strong>$' + eur + ' mxn</strong>',
        emoji + ' <strong>' + temp + '</strong>°c' + (condition ? ', ' + condition : ''),
        'actualizado: ' + lastUpdateStr,
    ];

    // Render items + clone first for seamless loop
    items.forEach(function (html) {
        var span = document.createElement('span');
        span.className = 'sp2-marquee__item';
        span.innerHTML = html;
        track.appendChild(span);
    });
    // Clone first item at end for seamless wrap
    var first = track.children[0].cloneNode(true);
    track.appendChild(first);

    var index = 0;
    var total = items.length;

    setInterval(function () {
        index++;
        track.style.transition = 'transform 0.4s ease';
        track.style.transform = 'translateY(-' + (index * 18) + 'px)';

        // When we reach the clone, snap back invisibly
        if (index >= total) {
            setTimeout(function () {
                track.style.transition = 'none';
                track.style.transform = 'translateY(0)';
                index = 0;
            }, 420);
        }
    }, 3000);

    // Update "actualizado" item every minute (relative time drifts as user stays on page)
    setInterval(function () {
        var n = new Date();
        var diff = Math.floor((n.getTime() - lastUpdate.getTime()) / 60000);
        var str;
        if (diff < 60) {
            str = 'hace <strong>' + Math.max(1, diff) + 'min</strong>';
        } else if (diff < 1440) {
            str = 'hace <strong>' + Math.floor(diff / 60) + 'h</strong>';
        } else {
            str = '<strong>' + pad(lastUpdate.getHours()) + ':' + pad(lastUpdate.getMinutes()) + '</strong>, ' + pad(lastUpdate.getDate()) + '/' + pad(lastUpdate.getMonth() + 1);
        }
        var lastUpdateItem = track.children[4]; // index 4 = actualizado
        if (lastUpdateItem) {
            lastUpdateItem.innerHTML = 'actualizado: ' + str;
        }
    }, 60000);
});

/* ---------- 1. Header scroll observer ---------- */
document.addEventListener('DOMContentLoaded', function () {
    var headerTop = document.querySelector('.sp2-header__top');
    if (!headerTop) return;

    var observer = new IntersectionObserver(function (entries) {
        document.body.classList.toggle('sp2-header--scrolled', !entries[0].isIntersecting);
    }, { threshold: 0 });

    observer.observe(headerTop);
});

/* ---------- 2. Custom menu open/close ---------- */
document.addEventListener('DOMContentLoaded', function () {
    var menu      = document.querySelector('.sp2-menu');
    var toggleBtn = document.querySelector('.sp2-bar__toggle');
    var closeBtn  = document.querySelector('.sp2-menu__close');

    if (!menu || !toggleBtn) return;

    function openMenu() {
        menu.classList.add('is-open');
        menu.setAttribute('aria-hidden', 'false');
        toggleBtn.setAttribute('aria-expanded', 'true');
        document.body.classList.add('sp2-menu-open');
    }

    function closeMenu() {
        menu.classList.remove('is-open');
        menu.setAttribute('aria-hidden', 'true');
        toggleBtn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('sp2-menu-open');
    }

    toggleBtn.addEventListener('click', function () {
        var isOpen = menu.classList.contains('is-open');
        isOpen ? closeMenu() : openMenu();
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeMenu);
    }

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && menu.classList.contains('is-open')) {
            closeMenu();
        }
    });

    // Close when clicking a menu link (navigate)
    menu.addEventListener('click', function (e) {
        if (e.target.closest('a[href]') && !e.target.closest('a[href="#"]')) {
            closeMenu();
        }
    });

    // Accordion toggle for menu sections
    menu.querySelectorAll('.sp2-menu__head[type="button"]').forEach(function (head) {
        head.addEventListener('click', function () {
            var section = head.closest('.sp2-menu__section');
            var isCollapsed = section.classList.contains('is-collapsed');

            if (isCollapsed) {
                section.classList.remove('is-collapsed');
                head.setAttribute('aria-expanded', 'true');
            } else {
                section.classList.add('is-collapsed');
                head.setAttribute('aria-expanded', 'false');
            }
        });
    });
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

/* ---------- 3. Infinite scroll + currency symbol loader ---------- */
(function () {
    var SYMBOLS = ['$', '¥', '€', '£', '₱', '₿', '₩', '₹'];
    var loading = false;

    function initInfiniteScroll() {
        var loader = document.querySelector('.sp2-loader');
        if (!loader) return;

        var stream   = document.querySelector('.sp-date-stream');
        if (!stream) return;

        var symbolEl = loader.querySelector('.sp2-loader__symbol');

        // Always rotate symbols as idle animation
        var symbolIndex = 0;
        if (symbolEl) {
            setInterval(function () {
                symbolIndex = (symbolIndex + 1) % SYMBOLS.length;
                symbolEl.textContent = SYMBOLS[symbolIndex];
            }, 500);
        }

        var observer = new IntersectionObserver(function (entries) {
            if (!entries[0].isIntersecting || loading) return;

            var nextPage = parseInt(loader.dataset.nextPage, 10);
            var maxPages = parseInt(loader.dataset.maxPages, 10);

            if (isNaN(nextPage) || isNaN(maxPages) || nextPage > maxPages) {
                loader.style.display = 'none';
                return;
            }

            loading = true;

            fetch('/wp-json/signopeso/v1/stream?page=' + nextPage + '&per_page=' + (loader.dataset.perPage || 10))
                .then(function (r) {
                    if (!r.ok) throw new Error('fetch failed');
                    return r.text();
                })
                .then(function (html) {
                    if (!html.trim()) {
                        loader.style.display = 'none';
                        return;
                    }
                    var temp = document.createElement('div');
                    temp.innerHTML = html;
                    while (temp.firstChild) {
                        stream.insertBefore(temp.firstChild, loader);
                    }
                    loader.dataset.nextPage = nextPage + 1;
                    loading = false;
                })
                .catch(function () {
                    loading = false;
                    loader.style.display = 'none';
                });
        }, { rootMargin: '200px' });

        observer.observe(loader);
    }

    document.addEventListener('DOMContentLoaded', initInfiniteScroll);
})();

/* ---------- 4. Recirculation polling (fresh cards every 5 min) ---------- */
document.addEventListener('DOMContentLoaded', function () {
    var inner = document.querySelector('.sp-recirculation__inner');
    if (!inner) return;

    var cardsContainer = inner.querySelector('.sp-recirculation__cards');
    if (!cardsContainer) return;

    setInterval(function () {
        var exclude = inner.dataset.exclude || '0';

        fetch('/wp-json/signopeso/v1/recirculation?exclude=' + exclude + '&count=4')
            .then(function (r) {
                if (!r.ok) throw new Error('fetch failed');
                return r.text();
            })
            .then(function (html) {
                if (!html.trim()) return;

                // Fade out
                cardsContainer.style.opacity = '0';

                setTimeout(function () {
                    cardsContainer.innerHTML = html;
                    cardsContainer.style.opacity = '1';
                }, 300);
            })
            .catch(function () {
                // silently fail — keep existing cards
            });
    }, 5 * 60 * 1000);
});
