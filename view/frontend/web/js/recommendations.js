/**
 * Saurabhg_AIproductRecommend
 *
 * Lazy loads recommendation units near the viewport in one batched request, tracks impressions when an
 * item is at least half visible, tracks clicks with unit / position, and drives the accessible carousel.
 * Rendering never blocks the initial page: failures simply leave the placeholder empty.
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
define(['jquery', 'Saurabhg_AIproductRecommend/js/tracker'], function ($, tracker) {
    'use strict';

    var pending = [],
        loading = false,
        flushTimer = null,
        config = null,
        seen = {};

    /**
     * @param {HTMLElement} unit
     */
    function bindImpressions(unit) {
        var items = unit.querySelectorAll('[data-aipr-sku]'),
            observer;

        if (!('IntersectionObserver' in window)) {
            return;
        }

        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var el = entry.target,
                    key;

                if (!entry.isIntersecting) {
                    return;
                }
                observer.unobserve(el);
                key = unit.getAttribute('data-aipr-token') + '|' + el.getAttribute('data-aipr-sku');

                if (seen[key]) {
                    return;
                }
                seen[key] = true;
                tracker.push({
                    'event_type': 'recommendation_impression',
                    sku: el.getAttribute('data-aipr-sku'),
                    position: parseInt(el.getAttribute('data-aipr-pos'), 10),
                    'tracking_token': unit.getAttribute('data-aipr-token'),
                    'unit_code': unit.getAttribute('data-aipr-code')
                });
            });
        }, {threshold: 0.5});

        Array.prototype.forEach.call(items, function (item) {
            observer.observe(item);
        });
    }

    /**
     * @param {HTMLElement} unit
     */
    function bindInteractions(unit) {
        var $unit = $(unit),
            list = unit.querySelector('.aipr-items');

        $unit.on('click', '[data-aipr-sku] a, [data-aipr-sku] button.tocart', function () {
            var item = this.closest('[data-aipr-sku]');

            tracker.push({
                'event_type': 'recommendation_click',
                sku: item.getAttribute('data-aipr-sku'),
                position: parseInt(item.getAttribute('data-aipr-pos'), 10),
                'tracking_token': unit.getAttribute('data-aipr-token'),
                'unit_code': unit.getAttribute('data-aipr-code')
            }, true);
        });

        if (!list) {
            return;
        }

        $unit.on('click', '[data-aipr-nav]', function () {
            list.scrollBy({left: parseInt(this.getAttribute('data-aipr-nav'), 10) * list.clientWidth, behavior: 'smooth'});
        });

        $(list).on('keydown', function (event) {
            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                event.preventDefault();
                list.scrollBy({left: (event.key === 'ArrowRight' ? 1 : -1) * list.clientWidth / 2, behavior: 'smooth'});
            }
        });
    }

    /**
     * @param {Object} units
     */
    function render(units) {
        $('.aipr-unit--loading').each(function () {
            var code = this.getAttribute('data-aipr-code'),
                data = units[code];

            if (!data || pending.indexOf(this) !== -1 || this.getAttribute('data-aipr-requested') !== '1') {
                return;
            }

            this.classList.remove('aipr-unit--loading');
            this.removeAttribute('aria-busy');

            if (data.empty || !data.html) {
                this.classList.add('aipr-unit--empty');

                return;
            }
            this.setAttribute('data-aipr-token', data.token || '');
            this.innerHTML = data.html;
            $(this).trigger('contentUpdated');
            bindInteractions(this);
            bindImpressions(this);
        });
    }

    /**
     * Send one request for every placeholder that became visible
     */
    function load() {
        var batch, codes, params;

        if (loading || !pending.length) {
            return;
        }
        loading = true;
        batch = pending.splice(0, pending.length);
        codes = batch.map(function (el) {
            el.setAttribute('data-aipr-requested', '1');

            return el.getAttribute('data-aipr-code');
        });
        params = {
            codes: codes,
            'page_type': config.pageType
        };

        if (config.productId) {
            params['product_id'] = config.productId;
        }

        if (config.categoryId) {
            params['category_id'] = config.categoryId;
        }

        $.ajax({
            url: config.renderUrl,
            data: params,
            type: 'GET',
            dataType: 'json',
            cache: false,
            global: false
        }).done(function (response) {
            render(response && response.units ? response.units : {});
        }).always(function () {
            batch.forEach(function (el) {
                if (el.classList.contains('aipr-unit--loading')) {
                    el.classList.remove('aipr-unit--loading');
                    el.classList.add('aipr-unit--empty');
                    el.removeAttribute('aria-busy');
                }
            });
            loading = false;
            load();
        });
    }

    /**
     * @param {HTMLElement} el
     */
    function enqueue(el) {
        if (el.getAttribute('data-aipr-queued')) {
            return;
        }
        el.setAttribute('data-aipr-queued', '1');
        pending.push(el);
        clearTimeout(flushTimer);
        flushTimer = setTimeout(load, 60);
    }

    return function (cfg) {
        var placeholders = document.querySelectorAll('.aipr-unit[data-aipr-code]'),
            observer;

        config = cfg;

        if (!placeholders.length) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            Array.prototype.forEach.call(placeholders, enqueue);

            return;
        }

        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    enqueue(entry.target);
                }
            });
        }, {rootMargin: '400px 0px'});

        Array.prototype.forEach.call(placeholders, function (el) {
            observer.observe(el);
        });
    };
});
