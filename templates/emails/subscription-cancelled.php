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

<?php /* translators: %s: Order number */ ?>
    <p><?php printf( esc_html__( 'Notification to let you know — subscription #%1$s belonging to %2$s has been cancelled.', 'subre-product-subscription-for-woo' ), wp_kses_post( subre_get_order_subscription_edit_link( $subscription->get_id() ) ), esc_html( $subscription->get_formatted_billing_full_name() ) ); ?></p>
<?php

do_action( 'subre_woocommerce_email_subscription_details', $subscription, $sent_to_admin, $plain_text, $email );

do_action( 'subre_woocommerce_email_subscription_meta', $subscription, $sent_to_admin, $plain_text, $email );

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
