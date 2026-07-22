document.documentElement.classList.add('js');

document.addEventListener('DOMContentLoaded', function () {
    var header = document.querySelector('[data-site-header]');
    var toggle = document.querySelector('[data-nav-toggle]');
    var panel = document.querySelector('[data-nav-panel]');
    var backdrop = document.querySelector('[data-nav-backdrop]');
    var mobileQuery = window.matchMedia('(max-width: 79.99rem)');
    var previouslyFocused = null;

    var focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    var updateHeaderState = function () {
        if (!header) {
            return;
        }

        header.classList.toggle('is-scrolled', window.scrollY > 8);
    };

    var closeMenu = function (restoreFocus) {
        if (!toggle || !panel || !backdrop) {
            return;
        }

        toggle.setAttribute('aria-expanded', 'false');
        panel.hidden = true;
        backdrop.hidden = true;
        document.body.classList.remove('nav-open');

        if (restoreFocus && previouslyFocused instanceof HTMLElement) {
            previouslyFocused.focus();
        }
    };

    var openMenu = function () {
        if (!toggle || !panel || !backdrop) {
            return;
        }

        previouslyFocused = toggle;
        toggle.setAttribute('aria-expanded', 'true');
        panel.hidden = false;
        backdrop.hidden = false;
        document.body.classList.add('nav-open');

        window.setTimeout(function () {
            var firstFocusable = panel.querySelector(focusableSelector);
            if (firstFocusable instanceof HTMLElement) {
                firstFocusable.focus();
            } else {
                panel.focus();
            }
        }, 40);
    };

    var syncNavigation = function () {
        if (!toggle || !panel || !backdrop) {
            return;
        }

        toggle.hidden = !mobileQuery.matches;

        if (!mobileQuery.matches) {
            closeMenu(false);
        }
    };

    var setupNavigation = function () {
        if (!toggle || !panel || !backdrop) {
            return;
        }

        syncNavigation();

        toggle.addEventListener('click', function () {
            if (toggle.getAttribute('aria-expanded') === 'true') {
                closeMenu(true);
                return;
            }

            openMenu();
        });

        backdrop.addEventListener('click', function () {
            closeMenu(true);
        });

        panel.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            if (target.closest('a, button[type="submit"]')) {
                closeMenu(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && mobileQuery.matches && toggle.getAttribute('aria-expanded') === 'true') {
                closeMenu(true);
            }
        });

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', syncNavigation);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(syncNavigation);
        }
    };

    var setupRevealObserver = function () {
        if (!('IntersectionObserver' in window)) {
            return;
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        var revealNodes = document.querySelectorAll(
            '.card, .action-card, .summary-card, .trust-card, .feedback, .page-section, .auth-note'
        );

        revealNodes.forEach(function (node, index) {
            if (!(node instanceof HTMLElement)) {
                return;
            }

            if (node.closest('.nav-drawer')) {
                return;
            }

            if (!node.hasAttribute('data-reveal')) {
                node.setAttribute('data-reveal', '');
            }

            node.style.transitionDelay = Math.min(index % 4, 3) * 50 + 'ms';
        });

        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.classList.add('is-reveal-visible');
                    observer.unobserve(entry.target);
                });
            },
            {
                rootMargin: '0px 0px -10% 0px',
                threshold: 0.12,
            }
        );

        document.querySelectorAll('[data-reveal]').forEach(function (node) {
            node.classList.add('is-reveal-visible');
            node.classList.remove('is-reveal-visible');
            observer.observe(node);
        });
    };

    setupNavigation();
    setupRevealObserver();
    updateHeaderState();
    window.addEventListener('scroll', updateHeaderState, { passive: true });
    window.__GV_APP_READY__ = true;
});
