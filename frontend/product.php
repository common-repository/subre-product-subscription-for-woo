<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Product {
	private static $settings;

	/**
	 * SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Product constructor.
	 */
	public function __construct() {
		self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'maybe_change_price_html' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'maybe_change_atc_text' ), 10, 2 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'maybe_change_atc_text' ), 10, 2 );
		add_filter( 'subre_frontend_product_trial_period', array( $this, 'disable_trial_if_bought' ), 10, 2 );
	}

	/**
	 * If a customer already bought a subscription product before, disable trial if any
	 *
	 * @param $trial_period
	 * @param $product WC_Product
	 *
	 * @return int
	 */
	public function disable_trial_if_bought( $trial_period, $product ) {
		if ( $trial_period ) {
			if ( is_user_logged_in() ) {
				if ( wc_customer_bought_product( '', get_current_user_id(), $product->get_id() ) ) {
					$trial_period = 0;
				}
			} else {
				if ( WC()->customer ) {
					$customer_email = WC()->customer->get_billing_email();
					if ( $customer_email && wc_customer_bought_product( $customer_email, '', $product->get_id() ) ) {
						$trial_period = 0;
					}
				}
			}
		}

		return $trial_period;
	}

	/**
	 * @param $text
	 * @param $product WC_Product
	 *
	 * @return mixed
	 */
	public function maybe_change_atc_text( $text, $product ) {
		if ( SUBRE_SUBSCRIPTION_PRODUCT_HELPER::is_subscription( $product ) ) {
			$atc_button_title = self::$settings->get_params( 'atc_button_title' );
			if ( $atc_button_title ) {
				$text = self::$settings->get_params( 'atc_button_title' );
			}
		}

		return $text;
	}

	/**
	 * @param $price
	 * @param $product WC_Product
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public static function maybe_change_price_html( $price, $product ) {
		$subscription_price = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_subscription_price_html( $product );
		if ( $subscription_price ) {
			$price = $subscription_price;
		}

		return $price;
	}
}