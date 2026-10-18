/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    return function (config, element) {
        var $button = $(element),
            $result = $('#aipr-test-connection-result');

        $button.on('click', function () {
            $result.text($t('Testing...'));
            $.ajax({
                url: $button.data('url'),
                type: 'POST',
                dataType: 'json',
                data: {'form_key': window.FORM_KEY},
                showLoader: true
            }).done(function (response) {
                $result.text(response.message || '').css('color', response.success ? '#185b00' : '#e22626');
            }).fail(function () {
                $result.text($t('Request failed.')).css('color', '#e22626');
            });
        });
    };
});
