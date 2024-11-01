<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment Plugins for Stripe WooCommerce by Payment Plugins, support@paymentplugins.com
 *
 * Class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Payment_Stripe_Cc
 */
class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Payment_Stripe_Cc {

	public function __construct() {
		add_filter( 'wc_stripe_should_save_payment_method', array( $this, 'should_save_payment_method' ), 10, 3 );
		add_action( 'subre_renewal_order_payment_due', array( $this, 'handle_payment' ), 10, 2 );
		add_action( 'subre_renewal_order_payment_stripe_cc_failed', array( $this, 'process_failed_payment' ), 10, 2 );
		add_action( 'subre_subscription_parent_order_payment_complete', array( $this, 'copy_payment_data_to_subscription' ), 10, 2 );
		add_action( 'subre_new_renewal_order_created', array( $this, 'copy_payment_data' ), 10, 3 );
	}

	/**
	 * Save payment method for renewal
	 *
	 * @param $save
	 * @param $order WC_Order
	 * @param $gateway WC_Payment_Gateway_Stripe
	 *
	 * @return mixed
	 */
	public function should_save_payment_method( $save, $order, $gateway ) {
		if ( SUBRE_SUBSCRIPTION_ORDER::is_a_renewal_order( $order->get_id() ) ) {
			return true;
		}
		$order_items = $order->get_items();
		foreach ( $order_items as $order_item ) {
			if ( SUBRE_SUBSCRIPTION_PRODUCT_HELPER::is_subscription( $order_item->get_product() ) ) {
				$save = true;
				break;
			}
		}

		return $save;
	}

	/**
	 * @param $order_id
	 * @param $subscription WC_Order
	 */
	public function copy_payment_data_to_subscription( $order_id, $subscription ) {
		$order = wc_get_order( $order_id );
		if ( self::is_payment_method_supported( $order->get_payment_method( 'edit' ), false ) && $order->get_meta( '_payment_method_token' ) ) {
			update_post_meta( $subscription->get_id(), '_payment_intent_id', $order->get_meta( '_payment_intent_id', true ) );
			update_post_meta( $subscription->get_id(), '_payment_method_token', $order->get_meta( '_payment_method_token' ) );
			update_post_meta( $subscription->get_id(), '_wc_stripe_mode', $order->get_meta( '_wc_stripe_mode', true ) );
		}
	}

	/**
	 * @param $subscription_or_renewal_order WC_Order
	 * @param $subscription WC_Order
	 * @param $payment_order WC_Order
	 */
	public function copy_payment_data( $subscription_or_renewal_order, $subscription, $payment_order ) {
		if ( self::is_payment_method_supported( $subscription_or_renewal_order->get_payment_method( 'edit' ), false ) && $payment_order->get_meta( '_payment_method_token', true ) ) {
			update_post_meta( $subscription_or_renewal_order->get_id(), '_payment_intent_id', $payment_order->get_meta( '_payment_intent_id', true ) );
			update_post_meta( $subscription_or_renewal_order->get_id(), '_payment_method_token', $payment_order->get_meta( '_payment_method_token', true ) );
			update_post_meta( $subscription_or_renewal_order->get_id(), '_wc_stripe_mode', $payment_order->get_meta( '_wc_stripe_mode', true ) );
		}
	}

	/**
	 * @param $order WC_Order
	 * @param $exception WC_Stripe_Exception
	 */
	public function process_failed_payment( $order, $exception ) {
		$order_id = $order->get_id();
		SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Log::log( sprintf('%1s%2s %3s%4s',
			esc_html__( 'Payment failed for renewal order #', 'subre-product-subscription-for-woo' ),
			$order_id,
			esc_html__( 'of subscription #', 'subre-product-subscription-for-woo' ),
			get_post_parent( $order_id )->ID ) );
	}

	/**
	 * Handle scheduled subscription renewal payment
	 *
	 * @param $order_id
	 * @param $subscription WC_Order
	 */
	public function handle_payment( $order_id, $subscription ) {
		if ( ! is_plugin_active( 'woo-stripe-payment/stripe-payments.php' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( ! $order->needs_payment() ) {
			return;
		}
		$payment_method = $order->get_payment_method( 'edit' );
		if ( ! self::is_payment_method_supported( $payment_method ) ) {
			return;
		}
		if ( 'stripe_sepa' === $payment_method && ! $order->get_meta( '_payment_method_token' ) ) {
			return;
		}
		$parent_order_obj = get_post_parent( $subscription->get_id() );
		if ( ! $parent_order_obj ) {
			return;
		}
		$this->process_subscription_payment( $order );
	}

	private static function is_payment_method_supported( $payment_method, $check_active = true ) {
		$support = false;
		if ( in_array( $payment_method, self::get_supported_payment_methods(), true ) ) {
			$support = true;
			if ( $check_active ) {
				if ( ! isset( WC()->payment_gateways()->get_available_payment_gateways()[ $payment_method ] ) ) {
					$support = false;
				}
			}
		}

		return $support;
	}

	public static function get_supported_payment_methods() {
		return array(
			'stripe_cc',
			'stripe_googlepay',
			'stripe_sepa',
		);
	}

	/**
	 * @param WC_Order $renewal_order
	 *
	 * @throws \Stripe\Exception\ApiErrorException
	 */
	public function process_subscription_payment( $renewal_order ) {
		try {
			$payment_method  = $renewal_order->get_payment_method( 'edit' );
			$payment_gateway = WC()->payment_gateways()->payment_gateways()[ $payment_method ];
			$gateway         = WC_Stripe_Gateway::load();
			$payment_gateway->set_payment_method_token( $renewal_order->get_meta( WC_Stripe_Constants::PAYMENT_METHOD_TOKEN ) );

//			if ( 'stripe_googlepay' === $payment_method ) {
			$payment_client         = WC_Stripe_Payment_Factory::load( 'payment_intent', $payment_gateway, $gateway );
			$args                   = $payment_client->get_payment_intent_args( $renewal_order );
			$args['confirm']        = true;
			$args['payment_method'] = trim( $payment_gateway->get_order_meta_data( WC_Stripe_Constants::PAYMENT_METHOD_TOKEN, $renewal_order ) );

			if ( ( $customer = $payment_gateway->get_order_meta_data( WC_Stripe_Constants::CUSTOMER_ID, $renewal_order ) ) ) {
				$args['customer'] = $customer;
			}
			$result = $gateway->mode( $renewal_order )->paymentIntents->create( $args );
			if ( is_wp_error( $result ) ) {
				$renewal_order->update_status( 'failed' );
				$renewal_order->add_order_note( sprintf('%1s %2s',
					esc_html__( 'Recurring payment for order failed. Reason:', 'subre-product-subscription-for-woo' ),
					$result->get_error_message() ) );

				return;
			}
			$this->process_charge( $result->charges->data[0], $renewal_order );
//			} else {
//				$payment_charge = WC_Stripe_Payment_Factory::load( 'charge', $payment_gateway, $gateway );
//				$result         = $payment_charge->process_payment( $renewal_order, $payment_gateway );
//				if ( isset( $result->complete_payment ) ) {
//					$this->process_charge( $result->charge, $renewal_order );
//				}
//			}
		} catch ( WC_Stripe_Exception $e ) {
			do_action( 'subre_renewal_order_payment_stripe_cc_failed', $renewal_order, $e );
			wc_stripe_log_error( $e->getMessage() );
			$renewal_order->update_status( 'failed' );
		}
	}

	/**
	 * @param $charge
	 * @param $renewal_order WC_Order
	 */
	private function process_charge( $charge, $renewal_order ) {
		if ( $charge->captured ) {
			if ( $charge->status === 'pending' ) {
				// pending status means this is an asynchronous payment method.
				$renewal_order->update_status( apply_filters( 'wc_stripe_renewal_pending_order_status', 'on-hold', $renewal_order, $this, $charge ), esc_html__( 'Renewal payment initiated in Stripe. Waiting for the payment to clear.', 'subre-product-subscription-for-woo' ) );
			} else {
				WC_Stripe_Utils::add_balance_transaction_to_order( $charge, $renewal_order );
				$renewal_order->payment_complete( $charge->id );
				$renewal_order->add_order_note( sprintf( '%1s %2s',
					esc_html__( 'Recurring payment captured in Stripe. Payment method:', 'subre-product-subscription-for-woo' ),
					$renewal_order->get_payment_method_title() ) );
			}
			do_action( 'subre_renewal_order_payment_stripe_cc_processed', $charge, $renewal_order );
		} else {
			$renewal_order->update_status( apply_filters( 'wc_stripe_authorized_renewal_order_status', 'on-hold', $renewal_order, $this ),
				sprintf( '%1s %2s',
					esc_html__( 'Recurring payment authorized in Stripe. Payment method:', 'subre-product-subscription-for-woo' ),
					$renewal_order->get_payment_method_title() ) );
		}
	}
}