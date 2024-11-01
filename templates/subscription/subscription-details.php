<?php
/**
 * Subscription details
 *
 * This template can be overridden by copying it to yourtheme/subre-product-subscription-for-woo/subscription/subscription-details.php.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$subscription = wc_get_order( $subscription_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if ( ! $subscription ) {
	return;
}

$order_items           = $subscription->get_items( apply_filters( 'subre_purchase_order_item_types', 'line_item' ) );
$show_purchase_note    = $subscription->has_status( apply_filters( 'subre_purchase_note_subscription_statuses', array(
	'subre_trial',
	'subre_active',
	'subre_expired'
) ) );
$show_customer_details = is_user_logged_in() && $subscription->get_user_id() === get_current_user_id();

?>
    <section class="woocommerce-order-details">
		<?php do_action( 'subre_subscription_details_before_order_table', $subscription ); ?>

        <h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Subscription details', 'subre-product-subscription-for-woo' ); ?></h2>

        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
            <tbody>
            <tr>
                <th><?php esc_html_e( 'Start date', 'subre-product-subscription-for-woo' ); ?>:</th>
                <td><?php SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $subscription->get_date_created() ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Status', 'subre-product-subscription-for-woo' ); ?>:</th>
                <td>
					<?php
					$status = $subscription->get_status();
					?>
                    <span class="subre-subscription-status <?php echo esc_attr( "subre-subscription-status-{$status}" ); ?>"><span><?php echo esc_html( SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_name( $status ) ) ?></span></span>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Product', 'subre-product-subscription-for-woo' ); ?>:</th>
                <td>
					<?php
					$product_subtotal = '';
					foreach ( $order_items as $item_id => $item ) {
						$product_subtotal  = $subscription->get_formatted_line_subtotal( $item );
						$product           = $item->get_product();
						$product_permalink = apply_filters( 'woocommerce_order_item_permalink', $product->get_permalink( $item ), $item, $subscription );

						echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $product_permalink ? sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $item->get_name() ) : $item->get_name(), $item, true ) );

						$qty          = $item->get_quantity();
						$refunded_qty = $subscription->get_qty_refunded_for_item( $item_id );

						if ( $refunded_qty ) {
							$qty_display = '<del>' . esc_html( $qty ) . '</del> <ins>' . esc_html( $qty - ( $refunded_qty * - 1 ) ) . '</ins>';
						} else {
							$qty_display = esc_html( $qty );
						}

						echo SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::wp_kses_post( apply_filters( 'woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $qty_display ) . '</strong>', $item ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

						do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $subscription, false );

						wc_display_item_meta( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

						do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $subscription, false );
					}
					?>
                </td>
            </tr>
			<?php
			if ( $product_subtotal ) {
				?>
                <tr>
                    <td><?php esc_html_e( 'Product subtotal', 'subre-product-subscription-for-woo' ); ?>:</td>
                    <td><?php echo wp_kses_post( $product_subtotal ) ?></td>
                </tr>
				<?php
			}
			if ( $subscription->get_shipping_method() ) {
				?>
                <tr>
                    <th><?php esc_html_e( 'Shipping', 'subre-product-subscription-for-woo' ); ?>:</th>
                    <td><?php echo wp_kses_post( $subscription->get_shipping_to_display( get_option( 'woocommerce_tax_display_cart' ) ) ) ?></td>
                </tr>
				<?php
			}
			?>
            <tr>
                <th><?php esc_html_e( 'Recurring Amount', 'subre-product-subscription-for-woo' ); ?>:</th>
                <td><?php echo wp_kses_post( SUBRE_SUBSCRIPTION_ORDER::get_formatted_subscription_recurring_amount( $subscription, get_option( 'woocommerce_tax_display_cart' ) ) ); ?></td>
            </tr>
            <tr>
                <th>
					<?php
					$payment_method_info  = SUBRE_SUBSCRIPTION_ORDER::get_subscription_payment_method( $subscription_id );
					$payment_method_title = $payment_method_info['title'];
					if ( SUBRE_SUBSCRIPTION_ORDER::is_automatic_payment_supported( $payment_method_info['id'] ) ) {
						esc_html_e( 'Renew automatically via', 'subre-product-subscription-for-woo' );
					} else {
						esc_html_e( 'Renew manually via', 'subre-product-subscription-for-woo' );
					}
					if ( $show_customer_details ) {
						/*Show card details*/
						$card_info = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_card_token_info( $subscription );
						if ( $card_info ) {
							$payment_method_title .= '<p><small>' . $card_info . '</small></p>';
						}
					}
					?>:
                </th>
                <td><?php echo wp_kses_post( $payment_method_title ) ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Payment due date', 'subre-product-subscription-for-woo' ); ?>:</th>
                <td>
					<?php
					$next_payment = get_post_meta( $subscription_id, '_subre_subscription_next_payment', true );
					if ( $next_payment ) {
						SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $next_payment );
					}
					?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Expiry Date', 'subre-product-subscription-for-woo' ); ?>:</th>
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
            </tr>

			<?php
			$can_renew_from_expired = SUBRE_SUBSCRIPTION_ORDER::can_renew_from_expired( $subscription );
			if ( ! empty( $can_renew_from_expired['can_renew'] ) && ! empty( $can_renew_from_expired['subscription_cycle'] ) ) {
				$subscription_cycle = $can_renew_from_expired['subscription_cycle'];
				$sub_expire         = $subscription->get_meta( '_subre_subscription_expire', true );
				$expired_interval   = time() - $sub_expire;
				$missed_pays        = $expired_interval / $subscription_cycle;

				if ( intval( $missed_pays ) ) {
					$missed_pays = intval( $missed_pays ) + 1;
					?>
                    <tr>
                        <th><?php esc_html_e( 'Number of times payment is required to renew', 'subre-product-subscription-for-woo' ); ?>:</th>
                        <td><?php echo esc_html( $missed_pays ) ?></td>
                    </tr>
					<?php
				}
			}
			?>
            </tbody>
        </table>

		<?php do_action( 'subre_subscription_details_after_order_table', $subscription ); ?>
    </section>

<?php
$downloads = $subscription->get_downloadable_items();

$show_downloads = $subscription->has_downloadable_item() && $subscription->is_download_permitted();
if ( $show_downloads ) {
	wc_get_template(
		'order/order-downloads.php',
		array(
			'downloads'  => $downloads,
			'show_title' => true,
		)
	);
}

/**
 * Action hook fired after the order details.
 *
 * @param WC_Order $subscription Order data.
 *
 * @since 1.0.0
 */
do_action( 'subre_after_subscription_details', $subscription );

if ( $show_customer_details ) {
	wc_get_template( 'order/order-details-customer.php', array( 'order' => $subscription ) );
}
