<?php
/**
 * Additional information about the subscription that this order is a renewal order of
 *
 * This template can be overridden by copying it to yourtheme/subre-product-subscription-for-woo/emails/subscription-renewal-info.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @version 1.0.0
 */

$subscription_link = $sent_to_admin ? subre_get_order_subscription_edit_link( $subscription_id ) : subre_get_subscription_view_link( $subscription_id );
/* translators: %s: subscription link */
$additional_text   = sprintf( esc_html__( 'This is a renewal order of subscription %s.', 'subre-product-subscription-for-woo' ), $subscription_link );
if ( $plain_text ) {
	echo esc_html( $additional_text ) . "\n";
} else {
	?>
    <p><?php echo wp_kses_post( $additional_text ); ?></p>
	<?php
}
