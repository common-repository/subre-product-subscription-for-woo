jQuery(document).ready(function ($) {
    'use strict';
    $('.vi-ui.tabular.menu .item').vi_tab({history: true, historyType: 'hash'});

    $('.vi-ui.checkbox').checkbox();

    $('select.vi-ui.dropdown').dropdown({placeholder: ''});

    $('.wrap.subre-product-subscription-for-woo form').on('submit', function () {
        $('.subre-save-settings').addClass('loading');
    });

    $('.subre-placeholder-value-copy').on('click', function () {
        let $container = $(this).closest('.subre-placeholder-value-container');
        $container.find('.subre-placeholder-value').select();
        document.execCommand('copy');
    });

    $('.subre-expired_subscription_renewable').on('change', function () {
        let $renew_date_from_expired_date = $('.subre-expired_subscription_renew_date_from_expired_date').closest('tr');
        let $renew_date_from_expired_date_fee = $('.subre-expired_subscription_renew_fee').closest('tr');
        if ($(this).prop('checked')) {
            $renew_date_from_expired_date.fadeIn(200);
            $renew_date_from_expired_date_fee.fadeIn(200);
        } else {
            $renew_date_from_expired_date.fadeOut(200);
            $renew_date_from_expired_date_fee.fadeOut(200);
        }
    }).trigger('change');
});