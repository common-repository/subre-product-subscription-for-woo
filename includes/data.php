<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA {
	private static $prefix;
	private $params;
	private $default;
	protected static $instance = null;
	protected static $allow_html = null;

	/**
	 * SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA constructor.
	 */
	public function __construct() {
		self::$prefix = 'subre-';
		global $subre_settings;
		if ( ! $subre_settings ) {
			$subre_settings = get_option( 'subre_settings', array() );
		}
		$this->default = array(
			'reduce_stock_if_renewal'                           => '',
			'change_payment_if_manual_renewal'                  => '',
			'allow_resubscribing'                               => '1',
			'sign_up_fee_if_trial'                              => 1,
			'atc_button_title'                                  => '',
			'price_html'                                        => '{subscription_price}/{subscription_period}',
			'price_html_with_trial'                             => "{subscription_price}/{subscription_period}\nwith a {trial_period} free trial",
			'price_html_with_sign_up_fee'                       => "{subscription_price}/{subscription_period}\nand {sign_up_fee} sign-up fee",
			'price_html_with_trial_and_sign_up_fee'             => "{subscription_price}/{subscription_period}\nwith a {trial_period} free trial\nand {sign_up_fee} sign-up fee",
			'expiry_date_info'                                  => 'Ends after {expiry_period}, on {expiry_date}',
			'place_order_button_title'                          => 'SUBSCRIBE',
			'next_payment_label'                                => '',
			'past_due_by'                                       => 1,
			'past_due_status'                                   => 'subre_cancelled',
			'subscriptions_endpoint'                            => 'subscriptions',
			'view_subscription_endpoint'                        => 'view-subscription',
			'expired_subscription_renewable'                    => '',
			'expired_subscription_renew_date_from_expired_date' => '',
			'expired_subscription_renew_fee' => '',
		);

		$this->params = apply_filters( 'subre_settings', wp_parse_args( $subre_settings, $this->default ) );
	}

	public function get_params( $name = '' ) {
		if ( ! $name ) {
			return $this->params;
		} elseif ( isset( $this->params[ $name ] ) ) {
			return apply_filters( 'subre_settings_' . $name, $this->params[ $name ] );
		} else {
			return false;
		}
	}

	public static function get_instance( $new = false ) {
		if ( $new || null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public static function set( $name, $set_name = false ) {
		if ( is_array( $name ) ) {
			return implode( ' ', array_map( array( 'SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA', 'set' ), $name ) );
		} else {
			if ( $set_name ) {
				return str_replace( '-', '_', self::$prefix . $name );
			} else {
				return self::$prefix . $name;
			}
		}
	}

	public function get_default( $name = "" ) {
		if ( ! $name ) {
			return $this->default;
		} elseif ( isset( $this->default[ $name ] ) ) {
			return apply_filters( 'subre_settings_default_' . $name, $this->default[ $name ] );
		} else {
			return false;
		}
	}

	public static function wp_kses_post( $content ) {
		if ( self::$allow_html === null ) {
			self::$allow_html = wp_kses_allowed_html( 'post' );
			self::$allow_html = array_merge_recursive( self::$allow_html, array(
					'input'  => array(
						'type'         => 1,
						'id'           => 1,
						'name'         => 1,
						'class'        => 1,
						'placeholder'  => 1,
						'autocomplete' => 1,
						'style'        => 1,
						'value'        => 1,
						'size'         => 1,
						'checked'      => 1,
						'disabled'     => 1,
						'readonly'     => 1,
						'data-*'       => 1,
					),
					'form'   => array(
						'method' => 1,
						'id'     => 1,
						'class'  => 1,
						'action' => 1,
						'data-*' => 1,
					),
					'select' => array(
						'id'       => 1,
						'name'     => 1,
						'class'    => 1,
						'multiple' => 1,
						'data-*'   => 1,
					),
					'option' => array(
						'value'    => 1,
						'selected' => 1,
						'data-*'   => 1,
					),
				)
			);
			foreach ( self::$allow_html as $key => $value ) {
				if ( $key === 'input' ) {
					self::$allow_html[ $key ]['data-*']   = 1;
					self::$allow_html[ $key ]['checked']  = 1;
					self::$allow_html[ $key ]['disabled'] = 1;
					self::$allow_html[ $key ]['readonly'] = 1;
				} elseif ( in_array( $key, array( 'div', 'span', 'a', 'form', 'select', 'option', 'tr', 'td' ) ) ) {
					self::$allow_html[ $key ]['data-*'] = 1;
				}
			}
		}

		return wp_kses( $content, self::$allow_html );
	}

	/**
	 * @param $timestamp
	 *
	 * @return WC_DateTime
	 * @throws Exception
	 */
	public static function get_datetime( $timestamp ) {
		$datetime = new WC_DateTime( "@{$timestamp}", new DateTimeZone( 'UTC' ) );
		if ( get_option( 'timezone_string' ) ) {
			$datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
		} else {
			$datetime->set_utc_offset( wc_timezone_offset() );
		}

		return $datetime;
	}

	/**
	 * @param $timestamp WC_DateTime|float
	 * @param $human_readable
	 *
	 * @throws Exception
	 */
	public static function render_date( $timestamp, $human_readable = true ) {
		if ( ! is_a( $timestamp, 'WC_DateTime' ) ) {
			$datetime_obj = self::get_datetime( $timestamp );
		} else {
			$datetime_obj = $timestamp;
			$timestamp    = $datetime_obj->getTimestamp();
		}
		$now  = time();
		$past = false;
		if ( $timestamp < $now ) {
			$past = true;
		}
		if ( $human_readable && $timestamp > $now && $timestamp <= strtotime( '+1 day', $now ) ) {
			$show_date = sprintf(
			/* translators: %s: human-readable time difference */
				_x( 'In %s', '%s = human-readable time difference', 'subre-product-subscription-for-woo' ),
				human_time_diff( $now, $timestamp )
			);
		} elseif ( $human_readable && $timestamp < $now && $timestamp >= strtotime( '-1 day', $now ) ) {
			$show_date = sprintf(
			/* translators: %s: human-readable time difference */
				_x( '%s ago', '%s = human-readable time difference', 'subre-product-subscription-for-woo' ),
				human_time_diff( $timestamp, $now )
			);
		} else {
			$show_date = $datetime_obj->date_i18n( apply_filters( 'woocommerce_admin_order_date_format', esc_html__( 'M j, Y', 'subre-product-subscription-for-woo' ) ) );//Should keep woocommerce text domain
		}
		printf(
			'<time class="%1$s" datetime="%2$s" title="%3$s">%4$s</time>',
			esc_attr( $past ? 'subre-date-in-the-past' : '' ),
			esc_attr( $datetime_obj->date( 'c' ) ),
			esc_html( $datetime_obj->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			esc_html( $show_date )
		);
	}

	/**
	 * @param $subscription WC_Order
	 *
	 * @return bool|mixed|void
	 */
	public static function get_card_token_info( $subscription ) {
		$payment_method_info = SUBRE_SUBSCRIPTION_ORDER::get_subscription_payment_method( $subscription->get_id() );
		$current_token       = '';
		if ( in_array( $payment_method_info['id'], SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Payment_Stripe::get_supported_payment_methods(), true ) ) {
			$current_token = get_post_meta( $payment_method_info['from_order'], '_stripe_source_id', true );
		} elseif ( in_array( $payment_method_info['id'], SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Payment_Stripe_Cc::get_supported_payment_methods(), true ) ) {
			$current_token = get_post_meta( $payment_method_info['from_order'], '_payment_method_token', true );
		}
		$card_info = '';
		if ( $current_token ) {
			$tokens = WC_Payment_Tokens::get_tokens( array(
				'user_id'    => $subscription->get_user_id(),
				'gateway_id' => $payment_method_info['id'],
			) );
			if ( $tokens ) {
				foreach ( $tokens as $token ) {
					if ( $token->get_token() === $current_token ) {
						$token_data = apply_filters( 'woocommerce_payment_methods_list_item', array(), $token );
						if ( ! empty( $token_data['method']['brand'] ) ) {
							if ( ! empty( $token_data['method']['last4'] ) ) {
								/* translators: 1: credit card type 2: last 4 digits */
								$card_info = sprintf( esc_html__( '%1$s ending in %2$s', 'subre-product-subscription-for-woo' ), esc_html( wc_get_credit_card_type_label( $token_data['method']['brand'] ) ), esc_html( $token_data['method']['last4'] ) );
							} else {
								$card_info = esc_html( wc_get_credit_card_type_label( $token_data['method']['brand'] ) );
							}
						} else {
							$token_data = $token->get_data();
							if ( $token_data ) {
								if ( ! empty( $token_data['brand'] ) ) {
									if ( ! empty( $token_data['last4'] ) ) {
										/* translators: 1: credit card type 2: last 4 digits */
										$card_info = sprintf( esc_html__( '%1$s ending in %2$s', 'subre-product-subscription-for-woo' ), esc_html( wc_get_credit_card_type_label( $token_data['brand'] ) ), esc_html( $token_data['last4'] ) );
									} else {
										$card_info = esc_html( wc_get_credit_card_type_label( $token_data['brand'] ) );
									}
								}
							}
						}
						break;
					}
				}
			}
		}

		return $card_info;
	}
}