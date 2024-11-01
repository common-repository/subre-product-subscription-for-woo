<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Payment_Paypal_Payments {

	public function __construct() {
		add_action( 'subre_subscription_parent_order_payment_complete', array(
			$this,
			'copy_payment_data_to_subscription'
		), 10, 2 );
		add_action( 'subre_new_renewal_order_created', array(
			$this,
			'copy_payment_data'
		), 10, 3 );
	}

	/**
	 * @param $order_id
	 * @param $subscription WC_Order
	 */
	public function copy_payment_data_to_subscription( $order_id, $subscription ) {
		$order = wc_get_order( $order_id );
		if ( 'ppcp-gateway' === $order->get_payment_method('edit') ) {
			update_post_meta( $subscription->get_id(), '_ppcp_paypal_payment_mode', $order->get_meta( '_ppcp_paypal_payment_mode', true ) );
		}
	}

	/**
	 * @param $subscription_or_renewal_order WC_Order
	 * @param $subscription WC_Order
	 * @param $payment_order WC_Order
	 */
	public function copy_payment_data( $subscription_or_renewal_order, $subscription,$payment_order ) {
		if ( 'ppcp-gateway' === $subscription_or_renewal_order->get_payment_method('edit') ) {
			update_post_meta( $subscription_or_renewal_order->get_id(), '_ppcp_paypal_payment_mode', $payment_order->get_meta( '_ppcp_paypal_payment_mode', true ) );
		}
	}
}