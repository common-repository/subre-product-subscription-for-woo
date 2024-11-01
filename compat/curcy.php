<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Curcy
 *
 * Class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Compat_Curcy
 */
class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Compat_Curcy {

	public function __construct() {
		add_action( 'subre_subscription_parent_order_payment_complete', array(
			$this,
			'copy_currency_info_to_subscription'
		), 10, 2 );
		add_action( 'subre_new_renewal_order_created', array(
			$this,
			'copy_currency_info'
		), 10, 3 );
	}

	/**
	 * @param $order_id
	 * @param $subscription WC_Order
	 */
	public function copy_currency_info_to_subscription( $order_id, $subscription ) {
		if ( $wmc_order_info = get_post_meta( $order_id, 'wmc_order_info', true ) ) {
			update_post_meta( $subscription->get_id(), 'wmc_order_info', $wmc_order_info );
		}
	}

	/**
	 * @param $subscription_or_renewal_order WC_Order
	 * @param $subscription WC_Order
	 * @param $payment_order WC_Order
	 */
	public function copy_currency_info( $subscription_or_renewal_order, $subscription, $payment_order ) {
		if ( $wmc_order_info = $payment_order->get_meta( 'wmc_order_info', true ) ) {
			update_post_meta( $subscription_or_renewal_order->get_id(), 'wmc_order_info', $wmc_order_info );
		}
	}
}