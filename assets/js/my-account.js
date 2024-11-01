jQuery(document).ready(function ($) {
    'use strict';
    $('.subre-my-account-subscriptions-list .cancel,.subre-button-cancel-subscription').on('click', function () {
        if (!confirm(subre_my_account_params.i18n_confirm_cancel)) {
            return false;
        }
    })
});