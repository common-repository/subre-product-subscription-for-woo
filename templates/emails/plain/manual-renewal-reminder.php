<?php
/**
 * Customer processing order email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-processing-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails\Plain
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
$datetime_obj           = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $subscription->get_meta( '_subre_subscription_next_payment', true ) );
$subscription_view_link = subre_get_subscription_view_link( $subscription->get_id(), true );
/* translators: %s: Customer first name */
echo sprintf( esc_html__( 'Hi %s,', 'subre-product-subscription-for-woo' ), esc_html( $subscription->get_billing_first_name() ) ) . "\n\n";
/* translators: %s: Order number */
echo sprintf( esc_html__( 'Your subscription #%1$s is about to due. For not to be interrupted, please %2$s it before %3$s.', 'subre-product-subscription-for-woo' ), esc_html( $subscription->get_order_number() ), '<a target="_blank" href="' . esc_url( $subscription_view_link ) . '">' . esc_html__( 'renew', 'subre-product-subscription-for-woo' ) . '</a>', esc_html( $datetime_obj->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ) . "\n\n";
/* translators: %s: Order link */
echo sprintf( esc_html__( 'If you need to take any actions, please go to %s.', 'subre-product-subscription-for-woo' ), esc_url( $subscription_view_link ) ) . "\n\n";

do_action( 'subre_woocommerce_email_subscription_details', $subscription, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n\n";

do_action( 'subre_woocommerce_email_subscription_meta', $subscription, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $subscription, $sent_to_admin, $plain_text, $email );

echo "\n\n----------------------------------------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
