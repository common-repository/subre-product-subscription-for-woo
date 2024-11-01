jQuery(document).ready(function ($) {
    'use strict';

    $(document).on('change', '#_subre_product_period,._subre_product_period_unit,#_subre_product_expire_after,._subre_product_expire_after_unit', function () {
        let $expire = $('#_subre_product_expire_after'),
            $expire_unit = $('._subre_product_expire_after_unit'),
            $warning = $('.subre-invalid-expire-value-warning');

        if ($expire_unit.val() !== 'cycle' && $expire.val()) {
            let sub_interval = get_interval_in_day($('#_subre_product_period').val(), $('._subre_product_period_unit').val()),
                sub_expire = get_interval_in_day($expire.val(), $expire_unit.val());

            if (sub_expire < sub_interval) {
                $warning.fadeIn(200);
            } else {
                $warning.fadeOut(200);
            }
        } else {
            $warning.fadeOut(200);
        }
    });

    $('#_subre_product_period').trigger('change');

    $(document).on('change', '#_subre_product_is_subscription', function () {
        if ($(this).prop('checked')) {
            $('.show_if_subre_subscription').show();
            $('.hide_if_subre_subscription').hide();
        } else {
            $('.show_if_subre_subscription').hide();
            $('.hide_if_subre_subscription').show();
        }
    });

    $('#_subre_product_is_subscription').trigger('change');

    function get_interval_in_day(value, unit) {
        switch (unit) {
            case 'year':
                value *= 360;
                break;
            case 'month':
                value *= 30;
                break;
            case 'week':
                value *= 7;
                break;
            case 'day':
            default:
        }
        return +value;
    }

    $(document).on('submit', 'form#post', function (e) {
        if ($('#_subre_product_is_subscription:checked').length) {
            if ($('#_subre_product_period').val().trim() === '') {
                alert('Please input subscription interval.');
                $('#publishing-action .spinner').removeClass('is-active');
                $('#publishing-action #publish').removeClass('disabled');
                return false;
            }
        }
    })
});