<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Renewal {
	private static $settings;
	private static $renewal_order_id;

	/**
	 * SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Renewal constructor.
	 */
	public function __construct() {
		self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
		add_action( 'before_woocommerce_pay', array( $this, 'before_woocommerce_pay' ) );
		add_action( 'after_woocommerce_pay', array( $this, 'after_woocommerce_pay' ) );
	}

	public function maybe_update_renew_type( $result, $order_id ) {
		if ( ! SUBRE_SUBSCRIPTION_ORDER::is_a_renewal_order( $order_id ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$payment      = $order->get_payment_method( 'edit' );
			$auto_payment = in_array( $payment, SUBRE_SUBSCRIPTION_ORDER::get_supported_automatic_payments(), true ) ? 'yes' : 'no';
		}
	}

	/**
	 * Start checking
	 */
	public function before_woocommerce_pay() {
		add_filter( 'woocommerce_order_needs_payment', array( __CLASS__, 'woocommerce_order_needs_payment' ), 10, 2 );
	}

	/**
	 * Remove filters
	 */
	public function after_woocommerce_pay() {
		if ( self::$renewal_order_id ) {
			do_action( 'subre_manually_pay_renewal_order_after', self::$renewal_order_id );
			self::$renewal_order_id = null;
		}
		remove_filter( 'woocommerce_order_needs_payment', array( __CLASS__, 'woocommerce_order_needs_payment' ), 10 );
		remove_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'woocommerce_available_payment_gateways' ), 10 );
		remove_filter( 'woocommerce_add_error', array( __CLASS__, 'woocommerce_add_error' ) );
		remove_filter( 'woocommerce_pay_order_button_text', array( __CLASS__, 'change_pay_order_button_text' ) );
	}

	/**
	 * @param $needs_payment
	 * @param $order WC_Order
	 *
	 * @return bool
	 */
	public static function woocommerce_order_needs_payment( $needs_payment, $order ) {
		if ( $needs_payment && $order ) {
			$order_id = $order->get_id();
			$parent   = get_post_parent( $order_id );
			if ( $parent && $parent->post_type === 'subre_subscription' ) {
				/*No need payment if subscription expired/cancelled*/
				$exclude_statuses = [ SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'cancelled' ) ];

				if ( ! self::$settings->get_params( 'expired_subscription_renewable' ) ) {
					$exclude_statuses[] = SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'expired' );
				}

				if ( in_array( $parent->post_status, $exclude_statuses, true ) ) {
					add_filter( 'woocommerce_add_error', array( __CLASS__, 'woocommerce_add_error' ) );

					return false;
				}
				/*Need payment no more if this is not the one set for the subscription at the moment*/
				$renewal_order = get_post_meta( $parent->ID, '_subre_subscription_current_renewal_order', true );

				if ( ! $renewal_order || $renewal_order != $order_id ) {
					add_filter( 'woocommerce_add_error', array( __CLASS__, 'woocommerce_add_error' ) );

					return false;
				}

				self::$renewal_order_id = $order_id;
				add_filter( 'woocommerce_pay_order_button_text', array( __CLASS__, 'change_pay_order_button_text' ) );
				add_action( 'before_woocommerce_pay_form', array( __CLASS__, 'manually_pay_renewal_order_message' ) );
				add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'woocommerce_available_payment_gateways' ) );
				do_action( 'subre_manually_pay_renewal_order_before', self::$renewal_order_id );
			}
		}

		return $needs_payment;
	}

	/**
	 * Make the payment of current renewal order selected by default by moving it to the first position
	 *
	 * @param $payment_gateways
	 *
	 * @return mixed
	 */
	public static function woocommerce_available_payment_gateways( $payment_gateways ) {
		if ( $payment_gateways && self::$renewal_order_id ) {
			$order = wc_get_order( self::$renewal_order_id );
			if ( $order ) {
				$payment_method = $order->get_payment_method( 'edit' );
				foreach ( $payment_gateways as $payment_gateway_id => $payment_gateway ) {
					if ( $payment_method === $payment_gateway_id ) {
						$insert = array( $payment_gateway_id => $payment_gateway );
						if ( self::$settings->get_params( 'change_payment_if_manual_renewal' ) ) {
							$payment_gateways = array_merge( $insert, $payment_gateways );
						} else {
							$payment_gateways = $insert;
						}
						do_action( 'subre_manually_pay_renewal_order_payment_found', self::$renewal_order_id );
						break;
					}
				}
			}
		}

		return $payment_gateways;
	}

	/**
	 * Change pay order button if paying a renewal order
	 *
	 * @param $button_text
	 *
	 * @return string
	 */
	public static function change_pay_order_button_text( $button_text ) {
		return esc_html__( 'Renew Now', 'subre-product-subscription-for-woo' );
	}

	/**
	 * Let customers know which subscription renew this payment is for
	 */
	public static function manually_pay_renewal_order_message() {
		$subscription_id = get_post_parent( self::$renewal_order_id )->ID;
		wc_print_notice( apply_filters( 'subre_manually_pay_renewal_order_notice',
			sprintf( '%1s%2s', esc_html__( 'Please complete the payment to renew your subscription #', 'subre-product-subscription-for-woo' ), $subscription_id ),
			self::$renewal_order_id, $subscription_id ), apply_filters( 'subre_manually_pay_renewal_order_notice', 'notice' ) );
	}

	/**
	 * Change error message when changing the result of woocommerce_order_needs_payment filter hook
	 *
	 * @param $error
	 *
	 * @return string
	 */
	public static function woocommerce_add_error( $error ) {
		return esc_html__( 'This order belongs to a subscription which is expired/cancelled or payment is not needed anymore.', 'subre-product-subscription-for-woo' );
	}
}