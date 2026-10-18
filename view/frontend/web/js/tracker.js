/**
 * Saurabhg_AIproductRecommend
 *
 * Consent-aware behavior tracker. No event and no visitor cookie before consent; identity is resolved
 * server-side from the first-party cookie and the session. Events are batched and sent with sendBeacon.
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
define(['jquery', 'mage/cookies'], function ($) {
    'use strict';

    var config = null,
        queue = [],
        timer = null;

    /**
     * @param {Number} bytes
     * @returns {String}
     */
    function randomHex(bytes) {
        var data = new Uint8Array(bytes),
            out = '',
            i;

        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(data);
        } else {
            for (i = 0; i < bytes; i++) {
                data[i] = Math.floor(Math.random() * 256);
            }
        }

        for (i = 0; i < data.length; i++) {
            out += ('0' + data[i].toString(16)).slice(-2);
        }

        return out;
    }

    /**
     * @returns {Boolean}
     */
    function hasConsent() {
        var consent = config && config.consent || {},
            value,
            allowed;

        if (consent.mode === 'none') {
            return true;
        }

        value = $.mage.cookies.get(consent.cookie) || '';

        if (consent.mode === 'custom_cookie') {
            return value !== '' && (!consent.value || decodeURIComponent(value).indexOf(consent.value) !== -1);
        }

        try {
            allowed = JSON.parse(value || '{}');
        } catch (e) {
            allowed = {};
        }

        return !!allowed[consent.websiteId];
    }

    /**
     * Pseudonymous visitor ID; created only with consent and when anonymous tracking is enabled
     *
     * @returns {String|null}
     */
    function visitorId() {
        var id = $.mage.cookies.get(config.visitorCookie);

        if (!id && config.trackAnonymous && hasConsent()) {
            id = 'v' + randomHex(16);
            $.mage.cookies.set(config.visitorCookie, id, {
                expires: new Date(Date.now() + 365 * 86400000),
                path: '/',
                samesite: 'lax'
            });
        }

        return id || null;
    }

    /**
     * @param {Boolean} beacon
     */
    function flush(beacon) {
        var batch,
            body,
            sent = false;

        if (timer) {
            clearTimeout(timer);
            timer = null;
        }

        if (!queue.length) {
            return;
        }

        visitorId();
        batch = queue.splice(0, config.maxBatch || 50);
        body = JSON.stringify({
            'form_key': $.mage.cookies.get('form_key') || window.FORM_KEY || '',
            events: batch
        });

        if (beacon && navigator.sendBeacon) {
            try {
                sent = navigator.sendBeacon(config.collectUrl, new Blob([body], {type: 'application/json'}));
            } catch (e) {
                sent = false;
            }
        }

        if (!sent && window.fetch) {
            window.fetch(config.collectUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                keepalive: true,
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            })['catch'](function () {
                return null;
            });
        }

        if (queue.length) {
            flush(beacon);
        }
    }

    return {
        /**
         * @param {Object} cfg
         */
        init: function (cfg) {
            if (config) {
                return;
            }
            config = cfg;
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden') {
                    flush(true);
                }
            });
            window.addEventListener('pagehide', function () {
                flush(true);
            });
        },

        /**
         * @returns {Boolean}
         */
        canTrack: function () {
            return !!(config && config.tracking && hasConsent());
        },

        /**
         * @param {Object} event
         * @param {Boolean} immediate
         */
        push: function (event, immediate) {
            if (!this.canTrack()) {
                return;
            }
            event['event_id'] = 'w' + randomHex(15);
            event.timestamp = Math.floor(Date.now() / 1000);
            queue.push(event);

            if (immediate) {
                flush(true);
            } else if (!timer) {
                timer = setTimeout(function () {
                    flush(false);
                }, 1500);
            }
        },

        /**
         * Page-level events of the current page
         */
        trackPage: function () {
            if (config.pageType === 'product' && config.productId) {
                this.push({'event_type': 'product_view', 'product_id': config.productId});
            } else if (config.pageType === 'category' && config.categoryId && config.trackCategory) {
                this.push({'event_type': 'category_view', 'category_id': config.categoryId});
            } else if (config.pageType === 'search' && config.searchQuery && config.trackSearch) {
                this.push({'event_type': 'search', query: config.searchQuery});
            }
        },

        flush: flush
    };
});
