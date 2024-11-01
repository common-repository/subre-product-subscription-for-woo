<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Cart {
	private static $settings;

	/**
	 * SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Cart constructor.
	 */
	public function __construct() {
		self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
		add_filter( 'woocommerce_cart_item_price', array( $this, 'change_subscription_price_html_in_cart' ), 10, 3 );
		add_filter( 'woocommerce_order_button_text', array( $this, 'change_order_button_text' ) );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'change_subscription_subtotal_html_in_cart' ), 10, 3 );
		/*Change subscription price while calculating total*/
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'add_calculation_price_filter' ) );
		add_action( 'woocommerce_calculate_totals', array( $this, 'remove_calculation_price_filter' ) );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'remove_calculation_price_filter' ) );
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'add_signup_fee' ] );
		add_action( 'init', [ $this, 'extend_store_endpoint' ] );
	}

	/**
	 * Change place order button based on settings
	 *
	 * @param $text
	 *
	 * @return bool|mixed|void
	 */
	public function change_order_button_text( $text ) {
		if ( self::cart_contains_subscription() ) {
			$place_order_button_title = self::$settings->get_params( 'place_order_button_title' );
			if ( $place_order_button_title ) {
				$text = $place_order_button_title;
			}
		}

		return $text;
	}

	public function add_calculation_price_filter() {
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'change_subscription_price_in_cart' ), 10, 2 );
	}

	public function remove_calculation_price_filter() {
		remove_filter( 'woocommerce_product_get_price', array( __CLASS__, 'change_subscription_price_in_cart' ) );
	}

	/**
	 * Adjust subscription price based on settings
	 *
	 * @param $price
	 * @param $product
	 *
	 * @return float|int|string
	 */
	public static function change_subscription_price_in_cart( $price, $product ) {
		if ( SUBRE_SUBSCRIPTION_PRODUCT_HELPER::is_subscription( $product ) ) {
			remove_filter( 'woocommerce_product_get_price', array( __CLASS__, 'change_subscription_price_in_cart' ) );
			$price = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_subscription_price( $product );
		}

		return $price;
	}

	/**
	 * @param $price
	 * @param $cart_item
	 * @param $cart_item_key
	 *
	 * @return array|mixed|string
	 * @throws Exception
	 */
	public function change_subscription_price_html_in_cart( $price, $cart_item, $cart_item_key ) {
		$subscription_price = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_subscription_price_html( $cart_item['data'], false );
		if ( $subscription_price ) {
			$price = $subscription_price;
		}

		return $price;
	}

	/**
	 * Additional subscription information
	 *
	 * @param $price
	 * @param $cart_item
	 * @param $cart_item_key
	 *
	 * @return mixed|string
	 * @throws Exception
	 */
	public function change_subscription_subtotal_html_in_cart( $price, $cart_item, $cart_item_key ) {
		$subscription_price = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_subscription_price_html( $cart_item['data'], false, $cart_item['quantity'] );
		if ( $subscription_price ) {
			$price                    = $subscription_price;
			$subscription_period      = $cart_item['data']->get_meta( '_subre_product_period', true );
			$subscription_period_unit = $cart_item['data']->get_meta( '_subre_product_period_unit', true );
			$subscription_cycle       = $subscription_period * SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_unit_in_seconds( $subscription_period_unit );
			$price                    .= '<p>' . sprintf( '%1s %2s',
					esc_html__( 'First renewal:', 'subre-product-subscription-for-woo' ),
					wc_format_datetime( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( time() + $subscription_cycle ) ) ) . '</p>';
		}

		return $price;
	}

	/**
	 * Check if current cart contains subscription product
	 *
	 * @return bool
	 */
	public static function cart_contains_subscription() {
		$contains_subscription = false;
		foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( SUBRE_SUBSCRIPTION_PRODUCT_HELPER::is_subscription( $cart_item['data'] ) ) {
				$contains_subscription = true;
				break;
			}
		}

		return $contains_subscription;
	}

	/**
	 * @param $cart \WC_Cart
	 */
	public function add_signup_fee( $cart ) {
		$cart_contents = $cart->get_cart_contents();
		$signup_fees   = 0;

		if ( ! empty( $cart_contents ) ) {
			$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
			foreach ( $cart_contents as $item ) {

				if ( SUBRE_SUBSCRIPTION_PRODUCT_HELPER::is_subscription( $item['product_id'] ) ) {
					$product = wc_get_product( $item['product_id'] );
					if ( $product ) {
						$signup_fee = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_product_sign_up_fee( $product );
						$trial      = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_subscription_trial_period( $product );

						if ( ! $trial || ( $trial && $settings->get_params( 'sign_up_fee_if_trial' ) ) ) {
							$signup_fees += $signup_fee * $item['quantity'];
						}
					}
				}
			}
		}

		if ( $signup_fees > 0 ) {
			$cart->add_fee( esc_html__( 'Sign up fee', 'subre-product-subscription-for-woo' ), $signup_fees, true, '' );
		}
	}

	protected function register_endpoint_data( $args ) {
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data( $args );
		} else {
			Package::container()->get( ExtendSchema::class )->register_endpoint_data( $args );
		}
	}

	public function extend_store_endpoint() {
		$namespace = 'subreSubscription';
		$this->register_endpoint_data(
			array(
				'endpoint'      => CartItemSchema::IDENTIFIER,
				'namespace'     => $namespace,
				'data_callback' => array( $this, 'extend_cart_item_data' ),
//				'schema_callback' => array( $this, 'extend_cart_item_schema' ),
				'schema_type'   => ARRAY_A,
			)
		);
	}

	public function extend_cart_item_data( $cart_item ) {
		$product = $cart_item['data'];

//		$item_data['origin_price']    = $origin_price;
		$item_data = [];

		if ( SUBRE_SUBSCRIPTION_PRODUCT_HELPER::is_subscription( $product ) ) {
			$money_formatter = function_exists( 'woocommerce_store_api_get_formatter' ) ? woocommerce_store_api_get_formatter( 'money' ) : Package::container()->get( ExtendSchema::class )->get_formatter( 'money' );

			$origin_price = $cart_item['subre_origin_price'] ?? '';
			$quantity     = $cart_item['quantity'];

			$price = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_item_price( $product, array( 'qty' => $quantity, 'price' => $origin_price, ) );

			$item_data['quantity']           = $quantity;
			$item_data['cart_item_price']    = $product->get_price();
			$item_data['subscription_price'] = $money_formatter->format( $price );
			$instance                        = \SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();

			$sign_up_fee = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_product_sign_up_fee( $product );
			if ( $sign_up_fee ) {
				$item_data['sign_up_fee'] = $money_formatter->format( $quantity * $sign_up_fee );
			}

			$trial_period             = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_subscription_trial_period( $product );
			$trial_period_unit        = $product->get_meta( '_subre_product_trial_period_unit', true );
			$subscription_period      = $product->get_meta( '_subre_product_period', true );
			$subscription_period_unit = $product->get_meta( '_subre_product_period_unit', true );
			$expire_after             = $product->get_meta( '_subre_product_expire_after', true );
//			$expire_after_unit        = $product->get_meta( '_subre_product_expire_after_unit', true );
			$expire_after_unit = 'cycle';

			if ( $trial_period && $sign_up_fee ) {
				$price_html_with_trial_and_sign_up_fee = $instance->get_params( 'price_html_with_trial_and_sign_up_fee' );
				$item_data['subscription_period']      = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_formatted_period( $subscription_period, $subscription_period_unit );
				$item_data['trial_period']             = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_formatted_period( $trial_period, $trial_period_unit, true );
//				$item_data['sign_up_fee']              = $quantity * $sign_up_fee;

				if ( $price_html_with_trial_and_sign_up_fee ) {
//					$display = str_replace( array(
//						'{subscription_price}',
//						'{subscription_period}',
//						'{sign_up_fee}',
//						'{trial_period}'
//					), array(
//						$price,
//						self::get_formatted_period( $subscription_period, $subscription_period_unit ),
//						wc_price( $quantity * $sign_up_fee ),
//						self::get_formatted_period( $trial_period, $trial_period_unit, true ),
//					), $price_html_with_trial_and_sign_up_fee );
					$item_data['display'] = $price_html_with_trial_and_sign_up_fee;

				}
			} else {
				if ( $trial_period ) {
					$price_html_with_trial = $instance->get_params( 'price_html_with_trial' );
					if ( $price_html_with_trial ) {
//						$display = str_replace( array(
//							'{subscription_price}',
//							'{subscription_period}',
//							'{trial_period}'
//						), array(
//							$price,
//							self::get_formatted_period( $subscription_period, $subscription_period_unit ),
//							self::get_formatted_period( $trial_period, $trial_period_unit, true ),
//						), $price_html_with_trial );

						$item_data['display']             = $price_html_with_trial;
						$item_data['subscription_period'] = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_formatted_period( $subscription_period, $subscription_period_unit );
						$item_data['trial_period']        = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_formatted_period( $trial_period, $trial_period_unit, true );

					}
				} elseif ( $sign_up_fee ) {
					$price_html_with_sign_up_fee = $instance->get_params( 'price_html_with_sign_up_fee' );
					if ( $price_html_with_sign_up_fee ) {
//						$display = str_replace( array(
//							'{subscription_price}',
//							'{subscription_period}',
//							'{sign_up_fee}'
//						), array(
//							$price,
//							self::get_formatted_period( $subscription_period, $subscription_period_unit ),
//							wc_price( $quantity * $sign_up_fee ),
//						), $price_html_with_sign_up_fee );

						$item_data['display'] = $price_html_with_sign_up_fee;
//						$item_data['sign_up_fee']         = $quantity * $sign_up_fee;
						$item_data['subscription_period'] = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_formatted_period( $subscription_period, $subscription_period_unit );

					}
				} else {
					$price_html = $instance->get_params( 'price_html' );
					if ( $price_html ) {
//						$display = str_replace( array(
//							'{subscription_price}',
//							'{subscription_period}',
//						), array(
//							$price,
//							self::get_formatted_period( $subscription_period, $subscription_period_unit ),
//						), $price_html );

						$item_data['display']             = $price_html;
						$item_data['subscription_period'] = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_formatted_period( $subscription_period, $subscription_period_unit );

					}
				}
			}

			if ( $expire_after ) {
				$expiry_date_info = $instance->get_params( 'expiry_date_info' );
				if ( $expiry_date_info ) {
					if ( 'cycle' === $expire_after_unit ) {
						$expire_after      = $expire_after * $subscription_period;
						$expire_after_unit = $subscription_period_unit;
					}
					switch ( $expire_after_unit ) {
						case 'year':
							/* translators: %s: number of year to expire */
							$expire_period = sprintf( _n( '%s year', '%s years', $expire_after, 'subre-product-subscription-for-woo' ), $expire_after );
							break;
						case 'month':
							/* translators: %s: number of month to expire */
							$expire_period = sprintf( _n( '%s month', '%s months', $expire_after, 'subre-product-subscription-for-woo' ), $expire_after );
							break;
						case 'week':
							/* translators: %s: number of week to expire */
							$expire_period = sprintf( _n( '%s week', '%s weeks', $expire_after, 'subre-product-subscription-for-woo' ), $expire_after );
							break;
						case 'day':
						default:
							/* translators: %s: number of day to expire */
							$expire_period = sprintf( _n( '%s day', '%s days', $expire_after, 'subre-product-subscription-for-woo' ), $expire_after );
							break;
					}
					$expiry_date = time() + $expire_after * SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_unit_in_seconds( $expire_after_unit );
					if ( $trial_period ) {
						$expiry_date += $trial_period * SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_unit_in_seconds( $trial_period_unit );
					}

					$item_data['expiry'] = str_replace(
						array( '{expiry_period}', '{expiry_date}', ),
						array( $expire_period, wc_format_datetime( \SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $expiry_date ) ), ),
						$expiry_date_info );
				}
			}

			$subscription_period      = $product->get_meta( '_subre_product_period', true );
			$subscription_period_unit = $product->get_meta( '_subre_product_period_unit', true );
			$subscription_cycle       = $subscription_period * SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_unit_in_seconds( $subscription_period_unit );
			$item_data['first_renew'] = sprintf('%1s %2s',
				esc_html__( 'First renewal:', 'subre-product-subscription-for-woo' ),
				wc_format_datetime( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( time() + $subscription_cycle ) ) );
		}

		return $item_data;
	}
}