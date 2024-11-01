<?php
/**
 * Related subscriptions
 *
 * This template can be overridden by copying it to yourtheme/subre-product-subscription-for-woo/order/related-subscriptions.php.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Related subscriptions', 'subre-product-subscription-for-woo' ) ?></h2>
<table class="subre-related-subscriptions-table">
    <thead>
    <tr>
        <th><?php esc_html_e( 'Subscription', 'subre-product-subscription-for-woo' ) ?></th>
        <th><?php esc_html_e( 'Payment due', 'subre-product-subscription-for-woo' ) ?></th>
        <th><?php esc_html_e( 'Status', 'subre-product-subscription-for-woo' ) ?></th>
        <th><?php esc_html_e( 'Expiry Date', 'subre-product-subscription-for-woo' ) ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody>
	<?php
	foreach ( $subscription_ids as $subscription_id ) {
		$subscription = wc_get_order( $subscription_id );
		if ( $subscription ) {
			$status = $subscription->get_status();
			?>
            <tr>
                <td><?php echo wp_kses_post( subre_get_subscription_view_link( $subscription_id,false,false ) ); ?></td>
                <td>
					<?php
					$next_payment = get_post_meta( $subscription_id, '_subre_subscription_next_payment', true );
					if ( $next_payment ) {
						SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $next_payment );
					}
					?>
                </td>
                <td>
                    <span class="subre-subscription-status <?php echo esc_attr( "subre-subscription-status-{$status}" ); ?>"><span><?php echo esc_html( SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_name( $status ) ) ?></span></span>
                </td>
                <td>
					<?php
					$expiry_date = get_post_meta( $subscription_id, '_subre_subscription_expire', true );
					if ( $expiry_date ) {
						SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $expiry_date );
					} else {
						echo '-';
					}
					?>
                </td>
                <td>
                    <a class="woocommerce-button button" href="<?php echo esc_url( subre_get_subscription_view_link( $subscription_id,  true ) ) ?>"><?php esc_html_e( 'View', 'subre-product-subscription-for-woo' ) ?></a>
                </td>
            </tr>
			<?php
		}
	}
	?>
    </tbody>
</table>
