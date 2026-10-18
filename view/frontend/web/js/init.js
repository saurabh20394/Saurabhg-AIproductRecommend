/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
define([
    'Saurabhg_AIproductRecommend/js/tracker',
    'Saurabhg_AIproductRecommend/js/recommendations'
], function (tracker, recommendations) {
    'use strict';

    return function (config) {
        tracker.init(config);
        tracker.trackPage();
        recommendations(config);
    };
});
