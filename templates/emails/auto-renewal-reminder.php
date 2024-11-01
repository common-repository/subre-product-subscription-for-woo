<?php
/**
 * Customer processing order email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-processing-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
$datetime_obj = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $subscription->get_meta( '_subre_subscription_next_payment', true ) );
$card_info    = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_card_token_info( $subscription );
if ( ! $card_info ) {
	$card_info = SUBRE_SUBSCRIPTION_ORDER::get_subscription_payment_method( $subscription->get_id() )['title'];
}
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer first name */ ?>
    <p><?php printf( esc_html__( 'Hi %s,', 'subre-product-subscription-for-woo' ), esc_html( $subscription->get_billing_first_name() ) ); ?></p>
<?php /* translators: %1$s: Order number */ ?>
    <p><?php printf( esc_html__( 'Your subscription #%1$s is about to due and it will be automatically renewed on %2$s via %3$s for a total amount of %4$s.', 'subre-product-subscription-for-woo' ), esc_html( $subscription->get_order_number() ), esc_html( $datetime_obj->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ), '<strong>' . esc_html( $card_info ) . '</strong>', wp_kses_post( $subscription->get_formatted_order_total() ) ); ?></p>
<?php /* translators: %1$s: Order link */ ?>
    <p><?php printf( esc_html__( 'If you need to take any actions, please go to %s.', 'subre-product-subscription-for-woo' ), '<a target="_blank" href="' . esc_url( subre_get_subscription_view_link( $subscription->get_id(), true ) ) . '">' . esc_html__( 'View subscription', 'subre-product-subscription-for-woo' ) . '</a>' ); ?></p>

<?php

do_action( 'subre_woocommerce_email_subscription_details', $subscription, $sent_to_admin, $plain_text, $email );

do_action( 'subre_woocommerce_email_subscription_meta', $subscription, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $subscription, $sent_to_admin, $plain_text, $email );

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
