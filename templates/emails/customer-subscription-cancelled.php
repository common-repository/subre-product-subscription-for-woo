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
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer first name */ ?>
    <p><?php printf( esc_html__( 'Hi %s,', 'subre-product-subscription-for-woo' ), esc_html( $subscription->get_billing_first_name() ) ); ?></p>
<?php /* translators: %s: Order number */ ?>
    <p><?php printf( esc_html__( 'We would like to inform you that your subscription #%s is now cancelled.', 'subre-product-subscription-for-woo' ), esc_html( $subscription->get_order_number() ) ); ?></p>
<?php /* translators: %s: Order link */ ?>
    <p><?php printf( esc_html__( 'If you need to take any actions, please go to %s.', 'subre-product-subscription-for-woo' ), '<a target="_blank" href="' . esc_url( subre_get_subscription_view_link( $subscription->get_id() ,true) ) . '">' . esc_html__( 'View subscription', 'subre-product-subscription-for-woo' ) . '</a>' ); ?></p>
<?php

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $subscription, $sent_to_admin, $plain_text, $email );

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
