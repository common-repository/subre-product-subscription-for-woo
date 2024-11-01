<?php
/**
 * Related orders
 *
 * This template can be overridden by copying it to yourtheme/subre-product-subscription-for-woo/subscription/related-orders.php.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Related orders', 'subre-product-subscription-for-woo' ) ?></h2>
<table class="subre-related-orders-table">
    <thead>
    <tr>
        <th><?php esc_html_e( 'Order', 'subre-product-subscription-for-woo' ) ?></th>
        <th><?php esc_html_e( 'Type', 'subre-product-subscription-for-woo' ) ?></th>
        <th><?php esc_html_e( 'Date', 'subre-product-subscription-for-woo' ) ?></th>
        <th><?php esc_html_e( 'Total', 'subre-product-subscription-for-woo' ) ?></th>
        <th><?php esc_html_e( 'Status', 'subre-product-subscription-for-woo' ) ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody>
	<?php
	$renewal_orders = get_post_meta( $subscription_id, '_subre_subscription_renewal_ids', true );
	if ( $renewal_orders ) {
		rsort( $renewal_orders );
		foreach ( $renewal_orders as $renewal_order ) {
			$renewal_order_obj = wc_get_order( $renewal_order );
			if ( $renewal_order_obj ) {
				$status = $renewal_order_obj->get_status();
				if ( ! in_array( $status, array( 'trash', 'draft' ) ) ) {
					?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( $renewal_order_obj->get_view_order_url() ) ?>">#<?php echo esc_html( $renewal_order ) ?></a>
                        </td>
                        <td><?php esc_html_e( 'Renewal', 'subre-product-subscription-for-woo' ) ?></td>
                        <td><?php SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $renewal_order_obj->get_date_created() ); ?></td>
                        <td><?php echo wp_kses_post( $renewal_order_obj->get_formatted_order_total() ) ?></td>
                        <td>
                            <span class="subre-order-status subre-subscription-status <?php echo esc_attr( "subre-subscription-status-{$status}" ); ?>"><span><?php echo esc_html( wc_get_order_status_name( $status ) ) ?></span></span>
                        </td>
                        <td>
                            <a class="woocommerce-button button"
                               href="<?php echo esc_url( $renewal_order_obj->get_view_order_url() ) ?>"><?php esc_html_e( 'View', 'subre-product-subscription-for-woo' ) ?></a>
                        </td>
                    </tr>
					<?php
				}
			}
		}
	}
	$parent = get_post_parent( $subscription_id );
	if ( $parent ) {
		$parent_obj = wc_get_order( $parent->ID );
		if ( $parent_obj ) {
			$status = $parent_obj->get_status();
			if ( ! in_array( $status, array( 'trash', 'draft' ) ) ) {
				?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( $parent_obj->get_view_order_url() ) ?>">#<?php echo esc_html( $parent->ID ) ?></a>
                    </td>
                    <td><?php esc_html_e( 'Parent order', 'subre-product-subscription-for-woo' ) ?></td>
                    <td><?php SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $parent_obj->get_date_created() ); ?></td>
                    <td><?php echo wp_kses_post( $parent_obj->get_formatted_order_total() ) ?></td>
                    <td>
                        <span class="subre-order-status subre-subscription-status <?php echo esc_attr( "subre-subscription-status-{$status}" ); ?>"><span><?php echo esc_html( wc_get_order_status_name( $status ) ) ?></span></span>
                    </td>
                    <td>
                        <a class="woocommerce-button button"
                           href="<?php echo esc_url( $parent_obj->get_view_order_url() ) ?>"><?php esc_html_e( 'View', 'subre-product-subscription-for-woo' ) ?></a>
                    </td>
                </tr>
				<?php
			}
		}
	}
	?>
    </tbody>
</table>
