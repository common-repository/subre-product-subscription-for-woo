<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Checkout {
	private static $settings;

	/**
	 * SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Checkout constructor.
	 */
	public function __construct() {
		self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
		add_action( 'woocommerce_before_checkout_process', array( $this, 'validate_trial_before_order_placed' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_create_subscription_order' ), 10, 3 );
		add_action( 'woocommerce_payment_complete', array( $this, 'maybe_set_subscription_active' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_set_subscription_active' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_set_subscription_active' ) );
		add_filter( 'woocommerce_checkout_registration_enabled', array( $this, 'enable_and_require_registration' ) );
		add_filter( 'woocommerce_checkout_registration_required', array( $this, 'enable_and_require_registration' ) );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

	}

	public function enqueue_scripts() {
		if ( is_cart() || is_checkout() ) {
			wp_enqueue_script( 'subre-blocks', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_JS . 'wc-blocks/index.js', '', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION, false );
		}

	}

	public function subscription_info_for_cart_and_checkout() {
		if ( ! SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Cart::cart_contains_subscription() ) {
			return;
		}
		?>
		<tr>
			<th><?php esc_html_e( 'Subscription', 'subre-product-subscription-for-woo' ) ?></th>
			<td></td>
		</tr>
		<?php
		foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( SUBRE_SUBSCRIPTION_PRODUCT_HELPER::is_subscription( $cart_item['data'] ) ) {

			}
		}
	}

	/**
	 *
	 */
	public function validate_trial_before_order_placed() {
		foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( SUBRE_SUBSCRIPTION_PRODUCT_HELPER::is_subscription( $cart_item['data'] ) ) {
				WC()->cart->cart_contents[ $cart_item_key ]['subre_trial_period'] = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_subscription_trial_period( $cart_item['data'] );
			}
		}
	}

	/**
	 * If cart contains a subscription product, registration is enabled and required
	 *
	 * @param $enable
	 *
	 * @return bool
	 */
	public function enable_and_require_registration( $enable ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $enable;
		}
		if ( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_Cart::cart_contains_subscription() ) {
			return true;
		}

		return $enable;
	}

	/**
	 * @param $order_id
	 */
	public function maybe_set_subscription_active( $order_id ) {
		$parent = get_post_parent( $order_id );
		if ( $parent ) {
			/*renewal order paid*/
			if ( $parent->post_type === 'subre_subscription' && $order_id == get_post_meta( $parent->ID, '_subre_subscription_current_renewal_order', true ) ) {
				SUBRE_SUBSCRIPTION_ACTIONS::update_next_payment_after_successfully_renewing( $order_id, $parent->ID );
			}
		} else {
			/*main order paid, if subscriptions exist change their status to active*/
			$args      = array(
				'post_type'      => 'subre_subscription',
				'post_status'    => array( 'wc-pending', 'wc-on-hold' ),
				'posts_per_page' => - 1,
				'post_parent'    => $order_id,
				'fields'         => 'ids',
			);
			$the_query = new WP_Query( $args );
			if ( $the_query->found_posts > 0 ) {
				$active_status = SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'active' );
				foreach ( $the_query->posts as $subscription_id ) {
					if ( SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, $active_status ) ) {
						$subscription = wc_get_order( $subscription_id );
						if ( $subscription->has_downloadable_item() ) {
							wc_downloadable_product_permissions( $subscription_id );
						}
					}
					do_action( 'subre_subscription_parent_order_payment_complete', $order_id, wc_get_order( $subscription_id ) );
				}
			}
		}
	}

	/**
	 * @param $order_id
	 * @param $posted_data
	 * @param $order WC_Order
	 *
	 * @throws WC_Data_Exception
	 */
	public function maybe_create_subscription_order( $order_id, $posted_data, $order ) {
		foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
			SUBRE_SUBSCRIPTION_ORDER::create_subscription_from_order( $order, $cart_item['product_id'], isset( $cart_item['subre_trial_period'] ) ? $cart_item['subre_trial_period'] : false );
		}
	}

	/**
	 * @param $product WC_Product
	 * @param bool $is_recurring
	 *
	 * @return float|int|string
	 */
//	public static function get_subscription_price( $product, $is_recurring = false ) {
//		if ( $is_recurring ) {
//			$price = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_item_price( $product );
//		} else {
//			$instance    = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
//			$sign_up_fee = $product->get_meta( '_subre_product_sign_up_fee', true );
//			if ( SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_subscription_trial_period( $product ) ) {
//				if ( $instance->get_params( 'sign_up_fee_if_trial' ) ) {
//					$price = $sign_up_fee;
//				} else {
//					$price = 0;
//				}
//			} else {
//				$price = $sign_up_fee;
//			}
//		}
//
//		return $price;
//	}
}