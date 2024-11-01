<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_SUBSCRIPTION_PRODUCT_HELPER {
	/**
	 * @param $product_id int|WC_Product
	 *
	 * @return bool
	 */
	public static function is_subscription( $product_id ) {
		$is_subscription = false;
		if ( ! is_a( $product_id, 'WC_Product' ) ) {
			$product = wc_get_product( $product_id );
		} else {
			$product = $product_id;
		}
		if ( $product ) {
			if ( $product->is_type( 'simple' ) ) {
				if ( 'yes' === $product->get_meta( '_subre_product_is_subscription', true ) ) {
					$is_subscription = true;
				}
			}
		}

		return $is_subscription;
	}

	public static function get_supported_intervals( $key = '' ) {
		$intervals = array(
			'day'   => esc_html__( 'Day', 'subre-product-subscription-for-woo' ),
			'week'  => esc_html__( 'Week', 'subre-product-subscription-for-woo' ),
			'month' => esc_html__( 'Month', 'subre-product-subscription-for-woo' ),
			'year'  => esc_html__( 'Year', 'subre-product-subscription-for-woo' ),
		);
		if ( $key ) {
			return isset( $intervals[ $key ] ) ? $intervals[ $key ] : '';
		} else {
			return $intervals;
		}
	}

	/**
	 * @param $product WC_Product
	 * @param int $quantity
	 * @param bool $for_shop
	 *
	 * @return mixed|string
	 * @throws Exception
	 */
	public static function get_subscription_price_html( $product, $for_shop = true, $quantity = 1 ) {
		$display = '';
		if ( self::is_subscription( $product ) ) {
			$instance = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();

			if ( $for_shop ) {
				remove_filter( 'woocommerce_get_price_html', array( 'SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Product', 'maybe_change_price_html' ) );
				$price = $product->get_price_html();
				add_filter( 'woocommerce_get_price_html', array( 'SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Product', 'maybe_change_price_html' ), 10, 2 );
			} else {
				if ( wc()->cart->display_prices_including_tax() ) {
					$price = wc_get_price_including_tax( $product );
				} else {
					$price = wc_get_price_excluding_tax( $product );
				}
//				$price = self::get_item_price( $product, $quantity );
				$price = wc_price( $price );
			}

			$sign_up_fee              = self::get_product_sign_up_fee( $product );
			$subscription_period      = $product->get_meta( '_subre_product_period', true );
			$subscription_period_unit = $product->get_meta( '_subre_product_period_unit', true );
			$expire_after             = $product->get_meta( '_subre_product_expire_after', true );
			$expire_after_unit        = $product->get_meta( '_subre_product_expire_after_unit', true );
			$trial_period             = self::get_subscription_trial_period( $product );
			$trial_period_unit        = $product->get_meta( '_subre_product_trial_period_unit', true );

			if ( $trial_period && $sign_up_fee ) {
				$price_html_with_trial_and_sign_up_fee = $instance->get_params( 'price_html_with_trial_and_sign_up_fee' );
				if ( $price_html_with_trial_and_sign_up_fee ) {
					$display = str_replace(
						array(
							'{subscription_price}',
							'{subscription_period}',
							'{sign_up_fee}',
							'{trial_period}'
						),
						array(
							$price,
							self::get_formatted_period( $subscription_period, $subscription_period_unit ),
							wc_price( $quantity * $sign_up_fee ),
							self::get_formatted_period( $trial_period, $trial_period_unit, true ),
						),
						$price_html_with_trial_and_sign_up_fee
					);
				}
			} else {
				if ( $trial_period ) {
					$price_html_with_trial = $instance->get_params( 'price_html_with_trial' );
					if ( $price_html_with_trial ) {
						$display = str_replace(
							array(
								'{subscription_price}',
								'{subscription_period}',
								'{trial_period}'
							),
							array(
								$price,
								self::get_formatted_period( $subscription_period, $subscription_period_unit ),
								self::get_formatted_period( $trial_period, $trial_period_unit, true ),
							),
							$price_html_with_trial
						);
					}
				} elseif ( $sign_up_fee ) {
					$price_html_with_sign_up_fee = $instance->get_params( 'price_html_with_sign_up_fee' );
					if ( $price_html_with_sign_up_fee ) {
						$display = str_replace(
							array(
								'{subscription_price}',
								'{subscription_period}',
								'{sign_up_fee}'
							),
							array(
								$price,
								self::get_formatted_period( $subscription_period, $subscription_period_unit ),
								wc_price( $quantity * $sign_up_fee ),
							),
							$price_html_with_sign_up_fee
						);
					}
				} else {
					$price_html = $instance->get_params( 'price_html' );
					if ( $price_html ) {
						$display = str_replace(
							array(
								'{subscription_price}',
								'{subscription_period}',
							),
							array(
								$price,
								self::get_formatted_period( $subscription_period, $subscription_period_unit ),
							),
							$price_html
						);
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
							/* translators: %s: year to expire */
							$expire_period = sprintf( _n( '%s year', '%s years', $expire_after, 'subre-product-subscription-for-woo' ), $expire_after );
							break;
						case 'month':
							/* translators: %s: month to expire */
							$expire_period = sprintf( _n( '%s month', '%s months', $expire_after, 'subre-product-subscription-for-woo' ), $expire_after );
							break;
						case 'week':
							/* translators: %s: week to expire */
							$expire_period = sprintf( _n( '%s week', '%s weeks', $expire_after, 'subre-product-subscription-for-woo' ), $expire_after );
							break;
						case 'day':
						default:
							/* translators: %s: day to expire */
							$expire_period = sprintf( _n( '%s day', '%s days', $expire_after, 'subre-product-subscription-for-woo' ), $expire_after );
							break;
					}

					$expiry_date = time() + $expire_after * self::get_unit_in_seconds( $expire_after_unit );

					if ( $trial_period ) {
						$expiry_date += $trial_period * self::get_unit_in_seconds( $trial_period_unit );
					}

					$display .= '<p>' . str_replace(
							array(
								'{expiry_period}',
								'{expiry_date}',
							),
							array(
								$expire_period,
								wc_format_datetime( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $expiry_date ) ),
							),
							$expiry_date_info ) . '</p>';
				}
			}
		}

		return nl2br( $display );
	}

	/**
	 * @param $value
	 * @param $unit
	 * @param bool $is_trial
	 *
	 * @return array|mixed|string
	 */
	public static function get_formatted_period( $value, $unit, $is_trial = false ) {
		if ( $is_trial ) {
			$period = self::get_supported_intervals( $unit );
			$period = "{$value}-{$period}";
		} else {
			$period = self::get_supported_intervals( $unit );
			if ( $value && intval( $value ) > 1 ) {
				$period = "{$value}-{$period}";
			}
		}

		return $period;
	}

//	/**
//	 * @param $item
//	 * @param int $quantity
//	 *
//	 * @return float|string
//	 */
//	public static function get_item_price( $item, $quantity = 1 ) {
//		return wc_get_price_to_display( $item, array( 'qty' => $quantity ) );
//	}


	/**
	 * @param $item
	 * @param array $args
	 *
	 * @return float|string
	 */
	public static function get_item_price( $item, $args = [] ) {
		$args = wp_parse_args(
			$args,
			array(
				'qty'   => 1,
				'price' => '',
			)
		);

		return wc_prices_include_tax()
			? wc_get_price_including_tax( $item, $args )
			: wc_get_price_excluding_tax( $item, $args );
	}

	/**
	 * @param $product WC_Product
	 *
	 * @return float|int|string
	 */
	public static function get_subscription_price( $product ) {
		return self::get_subscription_trial_period( $product ) ? 0 : self::get_item_price( $product );
	}

	/**
	 * @param $product WC_Product
	 *
	 * @return mixed|void
	 */
	public static function get_subscription_trial_period( $product ) {
		return apply_filters( 'subre_frontend_product_trial_period', $product->get_meta( '_subre_product_trial_period', true ), $product );
	}

	/**
	 * Convert unit to seconds
	 *
	 * @param $unit
	 *
	 * @return float|int
	 */
	public static function get_unit_in_seconds( $unit ) {
		switch ( $unit ) {
			case 'week':
				$return = 7 * DAY_IN_SECONDS;
				break;
			case 'month':
				$return = 30 * DAY_IN_SECONDS;
				break;
			case 'year':
				$return = 360 * DAY_IN_SECONDS;
				break;
			case 'day':
			default:
				$return = DAY_IN_SECONDS;
		}

		return $return;
	}

	/**
	 * @param $product WC_Product
	 *
	 * @return mixed|void
	 */
	public static function get_product_sign_up_fee( $product ) {
		$sign_up_fee = $product->get_meta( '_subre_product_sign_up_fee', true );
		if ( $sign_up_fee ) {
			$sign_up_fee = wc_get_price_to_display( $product, [ 'price' => $sign_up_fee ] );
			$sign_up_fee = apply_filters( 'wmc_change_3rd_plugin_price', $sign_up_fee );
		}

		return floatval( $sign_up_fee );
	}
}