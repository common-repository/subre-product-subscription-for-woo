<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_SUBSCRIPTION_ORDER {
	private static $settings;

	public function __construct() {
		self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
		add_action( 'woocommerce_register_post_type', [ $this, 'register_post_type' ] );
		add_filter( 'wc_order_statuses', [ $this, 'wc_order_statuses' ] );
		add_filter( 'woocommerce_can_reduce_order_stock', [ $this, 'do_not_reduce_stock_for_renewal_orders' ], 10, 2 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'cancel_subscription' ], 10, 4 );
		add_action( 'subre_view_subscription', [ $this, 'subscription_details' ] );
		add_filter( 'woocommerce_order_is_download_permitted', [ $this, 'woocommerce_order_is_download_permitted' ], 10, 2 );
	}

	/**
	 * @param $is_download_permitted
	 * @param $order WC_Order
	 *
	 * @return mixed
	 */
	public function woocommerce_order_is_download_permitted( $is_download_permitted, $order ) {
		if ( self::is_a_renewal_order( $order->get_id() ) ) {
			return false;
		}
		if ( self::is_a_subscription( $order ) && $order->has_status( array(
				'subre_trial',
				'subre_active',
				'subre_a_cancel',
			) ) ) {
			return true;
		}

		return $is_download_permitted;
	}

	/**
	 * If an order is cancelled/refunded, cancel all related subscriptions
	 *
	 * @param $order_id
	 * @param $from
	 * @param $to
	 * @param $order WC_Order
	 */
	public function cancel_subscription( $order_id, $from, $to, $order ) {
		if ( ! in_array( $to, array( 'cancelled', 'refunded' ), true ) ) {
			return;
		}
		if ( self::is_a_renewal_order( $order_id ) ) {
			return;
		}
		if ( 'subre_subscription' === get_post_type( $order_id ) ) {
			return;
		}
		$args      = array(
			'post_type'      => 'subre_subscription',
			'post_status'    => array_diff( self::get_subscription_statuses(), array(
				'wc-cancelled',
				'wc-subre_expired'
			) ),
			'posts_per_page' => - 1,
			'post_parent'    => $order_id,
			'fields'         => 'ids',
		);
		$the_query = new WP_Query( $args );
		if ( $the_query->found_posts > 0 ) {
			$cancelled_status      = self::get_subscription_status_to_save( 'cancelled' );
			$cancelled_status_name = self::get_subscription_status_name( 'cancelled' );
			$to_status_name        = wc_get_order_status_name( $to );
			foreach ( $the_query->posts as $subscription_id ) {
				/* translators: %1s: link to order, %2s: status name */
				if ( self::update_subscription_status( $subscription_id, $cancelled_status, sprintf( esc_html__( 'Subscription\'s parent order %1$s is %2$s', 'subre-product-subscription-for-woo' ), subre_get_order_subscription_edit_link( $order_id ), $to_status_name ) ) ) {
					/* translators: %1s: link to order, %2s: cancelled status name */
					$order->add_order_note( sprintf( esc_html__( 'Subscription %1$s was %2$s', 'subre-product-subscription-for-woo' ), subre_get_order_subscription_edit_link( $subscription_id ), $cancelled_status_name ) );
				}
			}
		}
	}

	/**
	 * @param $reduce_stock
	 * @param $order WC_Order
	 *
	 * @return bool
	 */
	public function do_not_reduce_stock_for_renewal_orders( $reduce_stock, $order ) {
		if ( ! self::$settings->get_params( 'reduce_stock_if_renewal' ) && self::is_a_renewal_order( $order->get_id() ) ) {
			$reduce_stock = false;
		}

		return $reduce_stock;
	}

	/**
	 * Register subre_subscription as order type
	 */
	public function register_post_type() {
		wc_register_order_type(
			'subre_subscription',
			apply_filters(
				'subre_register_post_type_subre_subscription',
				array(
					'labels'                           => array(
						'name'                  => esc_html__( 'Subscriptions', 'subre-product-subscription-for-woo' ),
						'singular_name'         => esc_html_x( 'Subscription', 'subre_subscription post type singular name', 'subre-product-subscription-for-woo' ),
						'add_new'               => esc_html__( 'Add subscription', 'subre-product-subscription-for-woo' ),
						'add_new_item'          => esc_html__( 'Add new subscription', 'subre-product-subscription-for-woo' ),
						'edit'                  => esc_html__( 'Edit', 'subre-product-subscription-for-woo' ),
						'edit_item'             => esc_html__( 'Edit subscription', 'subre-product-subscription-for-woo' ),
						'new_item'              => esc_html__( 'New subscription', 'subre-product-subscription-for-woo' ),
						'view_item'             => esc_html__( 'View subscription', 'subre-product-subscription-for-woo' ),
						'search_items'          => esc_html__( 'Search subscriptions', 'subre-product-subscription-for-woo' ),
						'not_found'             => esc_html__( 'No subscriptions found', 'subre-product-subscription-for-woo' ),
						'not_found_in_trash'    => esc_html__( 'No subscriptions found in trash', 'subre-product-subscription-for-woo' ),
						'parent'                => esc_html__( 'Parent subscriptions', 'subre-product-subscription-for-woo' ),
						'menu_name'             => esc_html_x( 'Subscriptions', 'Admin menu name', 'subre-product-subscription-for-woo' ),
						'filter_items_list'     => esc_html__( 'Filter subscriptions', 'subre-product-subscription-for-woo' ),
						'items_list_navigation' => esc_html__( 'Subscriptions navigation', 'subre-product-subscription-for-woo' ),
						'items_list'            => esc_html__( 'Subscriptions list', 'subre-product-subscription-for-woo' ),
					),
					'description'                      => esc_html__( 'This is where store subscriptions are stored.', 'subre-product-subscription-for-woo' ),
					'public'                           => false,
					'show_ui'                          => true,
					'capability_type'                  => 'shop_order',
					'capabilities'                     => array( 'create_posts' => 'do_not_allow' ),
					'map_meta_cap'                     => true,
					'publicly_queryable'               => false,
					'exclude_from_search'              => true,
					'show_in_menu'                     => current_user_can( 'manage_woocommerce' ) ? 'subre-product-subscription-for-woo' : false,
					'hierarchical'                     => false,
					'show_in_nav_menus'                => false,
					'rewrite'                          => false,
					'query_var'                        => false,
					'supports'                         => array( 'title', 'comments', 'custom-fields' ),
					'has_archive'                      => false,
					'exclude_from_orders_screen'       => true,
					'add_order_meta_boxes'             => true,
					'exclude_from_order_count'         => true,
					'exclude_from_order_views'         => true,
					'exclude_from_order_webhooks'      => true,
					'exclude_from_order_reports'       => true,
					'exclude_from_order_sales_reports' => true,
				)
			)
		);
	}

	/**
	 * @param $order WC_Order
	 * @param $product_id
	 * @param bool $override_trial
	 *
	 * @return int|WP_Error
	 * @throws WC_Data_Exception
	 */
	public static function create_subscription_from_order( $order, $product_id, $override_trial = false ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) || 'shop_order' !== get_post_type( $order->get_id() ) ) {
			return new WP_Error( 'subre_error_invalid_order', esc_html__( 'Not a valid WooCommerce order object', 'subre-product-subscription-for-woo' ) );
		}

		if ( ! SUBRE_SUBSCRIPTION_PRODUCT_HELPER::is_subscription( $product_id ) ) {
			return new WP_Error( 'subre_error_not_a_subcription_product', esc_html__( 'Not a subscription product', 'subre-product-subscription-for-woo' ) );
		}

		$order_id = $order->get_id();
		if ( self::subscription_exists_in_order( $order_id, $product_id ) ) {
			return new WP_Error( 'subre_error_subscription_exists_in_order', esc_html__( 'A subscription from this product already exists in this order', 'subre-product-subscription-for-woo' ) );
		}

		$order_items  = $order->get_items();
		$line_item_id = '';

		foreach ( $order_items as $item_id => $order_item ) {
			if ( $order_item->get_product_id() == $product_id ) {
				$line_item_id = $item_id;
				$product      = $order_item->get_product();
				break;
			}
		}

		if ( ! isset( $product ) ) {
			return new WP_Error( 'subre_error_product_not_existing_in_order', esc_html__( 'Product not existing in order', 'subre-product-subscription-for-woo' ) );
		}

		$date_created = $order->get_date_created();
		$subscription = new WC_Order();
		$status       = 'pending';

		$subscription_ids = $order->get_meta( '_subre_subscription_ids', true );
		if ( ! is_array( $subscription_ids ) ) {
			$subscription_ids = array();
		}

		$sign_up_fee              = get_post_meta( $product_id, '_subre_product_sign_up_fee', true );
		$subscription_period      = get_post_meta( $product_id, '_subre_product_period', true );
		$subscription_period_unit = get_post_meta( $product_id, '_subre_product_period_unit', true );

		if ( $override_trial === false ) {
			$trial_period = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_subscription_trial_period( $product );
		} else {
			$trial_period = $override_trial;
		}

		$trial_period_unit  = get_post_meta( $product_id, '_subre_product_trial_period_unit', true );
		$expire_after       = get_post_meta( $product_id, '_subre_product_expire_after', true );
		$expire_after_unit  = get_post_meta( $product_id, '_subre_product_expire_after_unit', true );
		$trial_end          = '';
		$date_created_      = $date_created->getTimestamp();
		$subscription_cycle = $subscription_period * SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_unit_in_seconds( $subscription_period_unit );
		$sub_expire         = '';
		$cycle_interval     = '';

		if ( $expire_after ) {
			if ( 'cycle' === $expire_after_unit ) {
				$cycle_interval = $expire_after * $subscription_cycle;
				$sub_expire     = $date_created_ + $cycle_interval;
			} else {
				$cycle_interval = $expire_after * SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_unit_in_seconds( $expire_after_unit );
				$sub_expire     = $date_created_ + $cycle_interval;
			}
		}

		if ( $trial_period ) {
			$trial_period_ = $trial_period * SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_unit_in_seconds( $trial_period_unit );
			$trial_end     = $date_created_ + $trial_period_;
			$status        = 'trial';
			$next_payment  = $trial_end;
			if ( $sub_expire ) {
				$sub_expire += $trial_period_;
			}
		} else {
			$next_payment = $date_created_ + $subscription_cycle;
		}

		$subscription->update_meta_data( '_subre_subscription_sign_up_fee', $sign_up_fee );
		$subscription->update_meta_data( '_subre_subscription_trial_end', $trial_end );
		$subscription->update_meta_data( '_subre_subscription_period', $subscription_period );
		$subscription->update_meta_data( '_subre_subscription_period_unit', $subscription_period_unit );

		if ( $cycle_interval ) {
			$subscription->update_meta_data( '_subre_subscription_cycle_interval', $cycle_interval );
		}

		if ( $sub_expire ) {
			$subscription->update_meta_data( '_subre_subscription_expire', $sub_expire );
		}

		$subscription->update_meta_data( '_subre_subscription_next_payment', $next_payment );
		/*add product line item*/
		$product_line = $order->get_item( $line_item_id );
		$clone        = clone $product_line;
		$clone->set_id( 0 );

		if ( $trial_period ) {
			/*If trial, do not copy line product item total from order*/
			$total = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_item_price( $product,['qty'=> $product_line->get_quantity()] );

			$clone->set_total( $total );
		} else {
			$total = $product_line->get_total();
		}

		$subscription->add_item( $clone );
		$subscription->set_total( $total + $order->get_shipping_total() );

		if ( $product->needs_shipping() ) {
			/*Add shipping line*/
			$shipping_order_item = $order->get_items( 'shipping' );
			if ( $shipping_order_item ) {
				foreach ( $shipping_order_item as $shipping_item ) {
					$clone = clone $shipping_item;
					$clone->set_id( 0 );
					$clone->delete_meta_data( esc_html__( 'Items', 'subre-product-subscription-for-woo' ) );//Must keep woocommerce text domain
					$clone->add_meta_data( esc_html__( 'Items', 'subre-product-subscription-for-woo' ), $product->get_name() . ' &times; ' . $product_line->get_quantity() );//Must keep woocommerce text domain
					$subscription->add_item( $clone );
					break;
				}
			}
		}

		self::copy_main_order_data( $subscription, $order );
		self::copy_payment_method( $subscription, $order );
		$subscription->set_created_via( 'subre_subscription' );
		$subscription_id = $subscription->save();
		$subscription->calculate_taxes( array(
			'country'  => $order->get_shipping_country(),
			'state'    => $order->get_shipping_state(),
			'postcode' => $order->get_shipping_postcode(),
			'city'     => $order->get_shipping_city(),
		) );
		$subscription->calculate_totals( true );
		$update_args = array(
			'ID'          => $subscription_id,
			'post_parent' => $order_id,
			/* translators: %s: Subscription date use for title */
			'post_title'  => sprintf( esc_html__( 'Subscription &ndash; %s', 'subre-product-subscription-for-woo' ), $date_created->format( _x( 'M d, Y @ h:i A', 'Subscription date parsed by DateTime::format', 'subre-product-subscription-for-woo' ) ) ),
		);

		$update_args['post_status'] = self::get_subscription_status_to_save( $status );
		$update_args['post_type']   = 'subre_subscription';
		/* translators: %s: link to order */
		$subscription->add_order_note( sprintf( esc_html__( 'Subscription created from order %s', 'subre-product-subscription-for-woo' ), subre_get_order_subscription_edit_link( $order_id ) ) );
		$subscription_ids[] = $subscription_id;
		$order->update_meta_data( '_subre_subscription_ids', $subscription_ids );
		$order->save();
		wp_update_post( $update_args );
		do_action( 'subre_new_subscription_created', $subscription, $order );

		return $subscription_id;
	}

	/**
	 * @param $subscription WC_Order
	 *
	 * @return int|WP_Error
	 * @throws WC_Data_Exception
	 */
	public static function create_renewal_order( $subscription ) {
		if ( ! $subscription || ! is_a( $subscription, 'WC_Order' ) || 'subre_subscription' !== get_post_type( $subscription->get_id() ) ) {
			return new WP_Error( 'subre_error_invalid_subscription', esc_html__( 'Not a valid Subscription object', 'subre-product-subscription-for-woo' ) );
		}

		$subscription_id          = $subscription->get_id();
		$renewal_ids              = $subscription->get_meta( '_subre_subscription_renewal_ids', true );
		$sub_expire               = $subscription->get_meta( '_subre_subscription_expire', true );
		$next_payment             = $subscription->get_meta( '_subre_subscription_next_payment', true );
		$subscription_period      = $subscription->get_meta( '_subre_subscription_period', true );
		$subscription_period_unit = $subscription->get_meta( '_subre_subscription_period_unit', true );
		$current_renewal_order    = $subscription->get_meta( '_subre_subscription_current_renewal_order', true );

		$expired_interval = time() - intval( $sub_expire );

		if ( $current_renewal_order && ! wc_get_order( $current_renewal_order ) ) {
			$current_renewal_order = '';
		}

		$subscription_cycle = $subscription_period * SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_unit_in_seconds( $subscription_period_unit );
		$renew_from_expired = false;
		$new_next_payment   = '';
		$missed_pays        = '';

		if ( ! $next_payment ) {
			$can_renew_from_expired = self::can_renew_from_expired( $subscription );

			if ( $can_renew_from_expired['can_renew'] ) {
				$renew_from_expired = true;
				$new_next_payment   = $can_renew_from_expired['new_next_payment'];
				$next_payment       = $new_next_payment;
				$sub_expire         = $can_renew_from_expired['new_sub_expire'];
				$missed_pays        = $expired_interval / $subscription_cycle;
				$missed_pays        = intval( $missed_pays ) + 1;
			}
			if ( ! $renew_from_expired ) {
				return new WP_Error( 'subre_error_final_renewal_order_reached', esc_html__( 'Final subscription renewal order reached', 'subre-product-subscription-for-woo' ) );
			}
		}

		$current_due  = $next_payment;
		$next_payment += $subscription_cycle;
		$last_renewal = false;

		if ( $sub_expire && ! $renew_from_expired ) {
			if ( $next_payment >= $sub_expire ) {
				if ( $current_due <= $sub_expire ) {
					$last_renewal = true;
				} else {
					return new WP_Error( 'subre_error_final_renewal_order_reached', esc_html__( 'Final subscription renewal order reached', 'subre-product-subscription-for-woo' ) );
				}
			}
		}

		if ( ! is_array( $renewal_ids ) ) {
			$renewal_ids = array();
		}

		$renewal_no      = count( $renewal_ids );
		$last_renewal_id = '';

		if ( $renewal_no > 0 ) {
			if ( $current_renewal_order ) {
				return new WP_Error( 'subre_error_previous_renewal_unpaid', esc_html__( 'Previous subscription renewal order unpaid', 'subre-product-subscription-for-woo' ) );
			}
			$last_renewal_id = $renewal_ids[ $renewal_no - 1 ];
		}

		$renewal_no ++;
		/*Create a renewal order from a subscription*/
		$renewal_order = new WC_Order();
		$product_lines = $subscription->get_items();

		foreach ( $product_lines as $product_line ) {
			if ( ! SUBRE_SUBSCRIPTION_PRODUCT_HELPER::is_subscription( $product_line->get_product() ) ) {
				return new WP_Error( 'subre_error_not_a_subcription_product', esc_html__( 'Not a subscription product', 'subre-product-subscription-for-woo' ) );
			}

			/** @var WC_Order_Item_Product $clone */
			$clone = clone $product_line;


			$clone->set_id( 0 );
			if ( $missed_pays ) {
				$clone->set_quantity( $clone->get_quantity() * $missed_pays );
				$clone->set_total( $clone->get_total() * $missed_pays );
			}

			$renewal_order->add_item( $clone );
			break;
		}

		$shipping_order_item = $subscription->get_items( 'shipping' );

		if ( $shipping_order_item ) {
			foreach ( $shipping_order_item as $shipping_item ) {
				$clone = clone $shipping_item;
				$clone->set_id( 0 );

				if ( $missed_pays ) {
					$clone->set_total( $clone->get_total() * $missed_pays );
				}

				$renewal_order->add_item( $clone );
				break;
			}
		}

		self::copy_main_order_data( $renewal_order, $subscription );
		$payment_order = $subscription;

		if ( $last_renewal_id ) {
			$last_renewal_order = wc_get_order( $last_renewal_id );
			if ( $last_renewal_order && $last_renewal_order->is_paid() ) {
				$payment_order = $last_renewal_order;
				self::copy_payment_method( $renewal_order, $last_renewal_order );
			} else {
				self::copy_payment_method( $renewal_order, $subscription );
			}
		} else {
			self::copy_payment_method( $renewal_order, $subscription );
		}

		$renewal_order->set_created_via( 'subre_renewal_order' );
		$renewal_order->update_meta_data( '_subre_renewal_order_no', $renewal_no );
		$renewal_order_id = $renewal_order->save();
		$date_created     = $renewal_order->get_date_created();

		$renewal_order->calculate_taxes( array(
			'country'  => $subscription->get_shipping_country(),
			'state'    => $subscription->get_shipping_state(),
			'postcode' => $subscription->get_shipping_postcode(),
			'city'     => $subscription->get_shipping_city(),
		) );

		$renewal_order->calculate_totals( false );

		$update_args   = array(
			'ID'          => $renewal_order_id,
			'post_parent' => $subscription_id,
			/* translators: %s: Subscription date use for title */
			'post_title'  => sprintf( esc_html__( 'Subscription &ndash; %s', 'subre-product-subscription-for-woo' ), $date_created->format( _x( 'M d, Y @ h:i A', 'Subscription date parsed by DateTime::format', 'subre-product-subscription-for-woo' ) ) ),
		);
		$renewal_ids[] = $renewal_order_id;
		/*Add the newly created renewal order to subscription renewal ids*/
		update_post_meta( $subscription_id, '_subre_subscription_renewal_ids', $renewal_ids );
		/*Set the newly created renewal order as the current renewal order*/
		update_post_meta( $subscription_id, '_subre_subscription_current_renewal_order', $renewal_order_id );

		/* translators: %s: link to order */
		$renewal_order->add_order_note( sprintf( esc_html__( 'Renewal order created for subscription %s', 'subre-product-subscription-for-woo' ), subre_get_order_subscription_edit_link( $subscription_id ) ) );
		/* translators: %s: link to order */
		$subscription->add_order_note( sprintf( esc_html__( 'Pending renewal order created: %s', 'subre-product-subscription-for-woo' ), subre_get_order_subscription_edit_link( $renewal_order_id ) ) );

		if ( $renew_from_expired ) {
			/*If this is a renewal order from an expired subscription, update the new payment due and expiry date*/
			update_post_meta( $subscription_id, '_subre_subscription_next_payment', $new_next_payment );
			update_post_meta( $subscription_id, '_subre_subscription_expire', $sub_expire );
		}

		wp_update_post( $update_args );
		do_action( 'subre_new_renewal_order_created', $renewal_order, $subscription, $payment_order );

		if ( $renew_from_expired ) {
			/*If this is a renewal order from an expired subscription, change the subscription status from expired to on-hold*/
			self::update_subscription_status( $subscription_id, self::get_subscription_status_to_save( 'on-hold' ) );
			do_action( 'subre_new_renewal_order_created_from_expired_subscription', $renewal_order, $subscription, $payment_order );
		}

		return $renewal_order_id;
	}

	/**
	 * @param $subscription WC_Order
	 * @param $order WC_Order
	 *
	 * @throws Exception
	 */
	private static function copy_payment_method( &$subscription, $order ) {
		$subscription->set_payment_method( $order->get_payment_method( 'edit' ) );
		$subscription->set_payment_method_title( $order->get_payment_method_title( 'edit' ) );
	}

	/**
	 * @param $subscription WC_Order
	 * @param $order WC_Order
	 */
	private static function copy_main_order_data( &$subscription, $order ) {
		/*copy order data*/
		$data_keys       = array(
			'currency',
			/*Billing*/
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_country',
			'billing_address_1',
			'billing_address_2',
			'billing_postcode',
			'billing_city',
			'billing_state',
			'billing_phone',
			'billing_email',
			/*Shipping*/
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_country',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_postcode',
			'shipping_city',
			'shipping_state',
			'customer_id',
		);
		$fields_prefix   = array(
			'shipping' => true,
			'billing'  => true,
		);
		$shipping_fields = array(
			'shipping_method' => true,
			'shipping_total'  => true,
			'shipping_tax'    => true,
		);
		foreach ( $data_keys as $key ) {
			$value = is_callable( array(
				$order,
				"get_{$key}"
			) ) ? $order->{"get_{$key}"}() : $order->get_meta( '_' . $key, true );
			if ( is_callable( array( $subscription, "set_{$key}" ) ) ) {
				$subscription->{"set_{$key}"}( $value );
				// Store custom fields prefixed with wither shipping_ or billing_. This is for backwards compatibility with 2.6.x.
			} elseif ( isset( $fields_prefix[ current( explode( '_', $key ) ) ] ) ) {
				if ( ! isset( $shipping_fields[ $key ] ) ) {
					$subscription->update_meta_data( '_' . $key, $value );
				}
			}
		}
	}

	/**
	 * All valid statuses of a subscription
	 *
	 * @return array
	 */
	public static function get_subscription_statuses() {
		return array_merge( self::get_subscription_inherited_statuses(), array_keys( self::get_subscription_only_statuses() ) );
	}

	/**
	 * Subscription statuses which are inherited from order
	 *
	 * @return array
	 */
	public static function get_subscription_inherited_statuses() {
		return array(
			'wc-pending',
			'wc-on-hold',
			'wc-cancelled',
		);
	}

	/**
	 * Subscription statuses
	 * Cannot be more than 20 characters
	 * @return array
	 */
	public static function get_subscription_only_statuses() {
		//When changing subscription status please change the status title in file order-list too
		return array(
			'wc-subre_trial'    => esc_html_x( 'Trial', 'Subscription status', 'subre-product-subscription-for-woo' ),
			'wc-subre_active'   => esc_html_x( 'Active', 'Subscription status', 'subre-product-subscription-for-woo' ),
			'wc-subre_expired'  => esc_html_x( 'Expired', 'Subscription status', 'subre-product-subscription-for-woo' ),
			'wc-subre_a_cancel' => esc_html_x( 'Awaiting Cancel', 'Subscription status', 'subre-product-subscription-for-woo' ),
		);
	}

	/**
	 * @param $statuses
	 *
	 * @return array
	 */
	public function wc_order_statuses( $statuses ) {
		return array_merge( self::get_subscription_only_statuses(), $statuses );
	}

	/**
	 * @param $status
	 *
	 * @return string
	 */
	public static function get_subscription_status_to_save( $status ) {
		if ( in_array( "wc-{$status}", self::get_subscription_inherited_statuses(), true ) ) {
			$status = "wc-{$status}";
		} else {
			$status = "wc-subre_{$status}";
		}

		return $status;
	}

	/**
	 * @param $status
	 *
	 * @return false|string
	 */
	private static function get_status( $status ) {
		if ( 'wc-' === substr( $status, 0, 3 ) ) {
			$status = substr( $status, 3 );
		}
		if ( 'subre_' === substr( $status, 0, 6 ) ) {
			$status = substr( $status, 6 );
		}

		return $status;
	}

	/**
	 * @param $subscription_id
	 * @param $status
	 * @param string $note
	 * @param string $customer_note
	 *
	 * @return bool
	 */
	public static function update_subscription_status( $subscription_id, $status, $note = '', $customer_note = '' ) {
		if ( get_post_type( $subscription_id ) !== 'subre_subscription' ) {
			return false;
		}
		$status            = self::get_status( $status );
		$status_save       = self::get_subscription_status_to_save( $status );
		$valid_statuses    = self::get_subscription_statuses();
		$status_exceptions = array( 'auto-draft', 'trash' );
		if ( ! in_array( $status_save, $valid_statuses, true ) ) {
			return false;
		}
		$old_status = get_post_status( $subscription_id );

		if ( $status_save !== $old_status ) {
			if ( wp_update_post( array(
				'ID'          => $subscription_id,
				'post_status' => $status_save,
			) ) ) {
				$subscription = wc_get_order( $subscription_id );
				$old_status_  = self::get_status( $old_status );
				/* translators: %1s: old subscription status, %2s: new subscription status */
				$order_note   = sprintf( esc_html__( 'Subscription status changed from %1$s to %2$s', 'subre-product-subscription-for-woo' ), self::get_subscription_status_name( $old_status_ ), self::get_subscription_status_name( $status ) );
				if ( $note ) {
					$order_note = $note . '. ' . $order_note;
				}
				$subscription->add_order_note( $order_note );
				if ( $customer_note ) {
					$subscription->add_order_note( $customer_note, true );
				}
				WC()->mailer();//Must initialize WC email so that custom subscription emails can be triggered
				do_action( "subre_subscription_status_changed_to_{$status}", $subscription_id, $old_status_ );
				do_action( 'subre_subscription_status_changed', $subscription_id, $status, $old_status_ );

				return true;
			}
		}

		return false;
	}

	/**
	 * @param $payment_method
	 *
	 * @return bool
	 */
	public static function is_automatic_payment_supported( $payment_method ) {
		if ( in_array( $payment_method, self::get_supported_automatic_payments(), true ) && isset( WC()->payment_gateways()->get_available_payment_gateways()[ $payment_method ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @return mixed|void
	 */
	public static function get_supported_automatic_payments() {
		return apply_filters( 'subre_get_supported_automatic_payments', array_merge( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Payment_Stripe::get_supported_payment_methods(), SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Payment_Stripe_Cc::get_supported_payment_methods() ) );
	}

	/**
	 * @param $order_id
	 *
	 * @return bool
	 */
	public static function is_a_renewal_order( $order_id ) {
		$is_renewal = false;
		if ( get_post_meta( $order_id, '_subre_renewal_order_no', true ) && $subscription_obj = get_post_parent( $order_id ) ) {
			if ( $subscription_obj->post_type === 'subre_subscription' ) {
				$is_renewal = true;
			}
		}

		return $is_renewal;
	}

	/**
	 * @param $order_id
	 *
	 * @return bool
	 */
	public static function is_a_subscription( $order_id ) {
		$is_a_subscription = false;
		if ( ! is_a( $order_id, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		} else {
			$order = $order_id;
		}
		if ( $order && get_post_type( $order->get_id() ) === 'subre_subscription' ) {
			$is_a_subscription = true;
		}

		return $is_a_subscription;
	}

	/**
	 * @param $status
	 *
	 * @return string
	 */
	public static function get_subscription_status_name( $status ) {
		$status = self::get_status( $status );
		$status = self::get_subscription_status_to_save( $status );

		return wc_get_order_status_name( $status );
	}

	/**
	 * @param $subscription
	 *
	 * @return mixed|void
	 */
	public static function get_subscription_statuses_for_cancel( $subscription ) {
		return apply_filters( 'subre_valid_subscription_statuses_for_cancel', array(
			'subre_active',
			'subre_trial',
			'on-hold'
		), $subscription );
	}

	/**
	 * @param $subscription
	 *
	 * @return mixed|void
	 */
	public static function get_subscription_statuses_for_renew( $subscription ) {
		return apply_filters( 'subre_valid_subscription_statuses_for_renew', array(
			'subre_expired',
			'subre_active',
			'subre_trial',
			'on-hold'
		), $subscription );
	}

	/**
	 * @param $order_id
	 */
	public function subscription_details( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		wc_get_template( 'subscription/subscription-details.php', [ 'subscription_id' => $order_id ],
			'subre-product-subscription-for-woo' . DIRECTORY_SEPARATOR,
			SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_TEMPLATES
		);
	}

	/**
	 * Grant access to newly added downloadable files
	 *
	 * @param $subscription_id
	 *
	 * @throws Exception
	 */
	public static function regenerate_download_permissions( $subscription_id ) {
		if ( ! self::is_a_subscription( $subscription_id ) ) {
			return;
		}

		$subscription = wc_get_order( $subscription_id );

		$items = $subscription->get_items();
		foreach ( $items as $item ) {
			/**
			 * @var $product WC_Product
			 */
			$product = $item->get_product();
			if ( $product && $product->exists() && $product->is_downloadable() ) {
				$data_store           = WC_Data_Store::load( 'customer-download' );
				$downloads            = $product->get_downloads();
				$granted_downloads    = $data_store->get_downloads(
					array(
						'user_email' => $subscription->get_billing_email(),
						'order_key'  => $subscription->get_order_key(),
						'product_id' => $product->get_id(),
						'order_id'   => $subscription_id,
						'return'     => 'download_id',
					)
				);
				$granted_download_ids = array_column( $granted_downloads, 'download_id' );
				foreach ( array_keys( $downloads ) as $download_id ) {
					if ( ! in_array( $download_id, $granted_download_ids, true ) ) {
						wc_downloadable_file_permission( $download_id, $product, $subscription, $item->get_quantity(), $item );
					}
				}
			}
		}
		$subscription->get_data_store()->set_download_permissions_granted( $subscription, true );
		do_action( 'woocommerce_grant_product_download_permissions', $subscription_id );
	}

	/**
	 * @param $subscription_id
	 *
	 * @return array
	 */
	public static function get_subscription_payment_method( $subscription_id ) {
		$renewal_ids    = get_post_meta( $subscription_id, '_subre_subscription_renewal_ids', true );
		$payment_method = array(
			'id'         => '',
			'title'      => '',
			'from_order' => $subscription_id,
		);
		if ( $renewal_ids ) {
			end( $renewal_ids );
			$renewal_id = current( $renewal_ids );
			$renewal    = wc_get_order( $renewal_id );
			if ( $renewal ) {
				$payment_method['id']         = $renewal->get_payment_method( 'edit' );
				$payment_method['title']      = $renewal->get_payment_method_title( 'edit' );
				$payment_method['from_order'] = $renewal_id;
			}
		}
		if ( ! $payment_method['id'] ) {
			$subscription            = wc_get_order( $subscription_id );
			$payment_method['id']    = $subscription->get_payment_method( 'edit' );
			$payment_method['title'] = $subscription->get_payment_method_title( 'edit' );
		}

		return $payment_method;
	}

	/**
	 * @param $subscription WC_Order
	 *
	 * @return string
	 */
	public static function get_renew_button_url( $subscription ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'renew_subscription' => 'true',
					'order'              => $subscription->get_order_key(),
					'subscription_id'    => $subscription->get_id(),
				),
				wc_get_endpoint_url( self::$settings->get_params( 'subscriptions_endpoint' ), '', wc_get_page_permalink( 'myaccount' ) )
			),
			'subre_renew_subscription'
		);
	}

	/**
	 * @param $subscription WC_Order
	 *
	 * @return string
	 */
	public static function get_cancel_button_url( $subscription ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'cancel_subscription' => 'true',
					'order'               => $subscription->get_order_key(),
					'subscription_id'     => $subscription->get_id(),
				),
			),
			'subre_cancel_subscription'
		);
	}

	/**
	 * @param $subscription WC_Order
	 * @param string $tax_display
	 *
	 * @return string
	 */
	public static function get_formatted_subscription_recurring_amount( $subscription, $tax_display = '' ) {
		/* translators: %1s: subscription total, %2s: subscription period */
		return sprintf( esc_html__( '%1$s/%2$s', 'subre-product-subscription-for-woo' ), $subscription->get_formatted_order_total( $tax_display ), SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_formatted_period( $subscription->get_meta( '_subre_subscription_period', true ), $subscription->get_meta( '_subre_subscription_period_unit', true ) ) );
	}

	/**
	 * @param $order_id
	 * @param $product_id
	 *
	 * @return bool
	 */
	public static function subscription_exists_in_order( $order_id, $product_id ) {
		global $wpdb;
		$query = "select count(*) from {$wpdb->posts} as subre_posts left join {$wpdb->prefix}woocommerce_order_items as subre_order_items on subre_posts.ID=subre_order_items.order_id left join {$wpdb->prefix}woocommerce_order_itemmeta as subre_order_itemmeta on subre_order_items.order_item_id=subre_order_itemmeta.order_item_id where subre_posts.post_type='subre_subscription' and subre_posts.post_status not in ('wc-pending','auto-draft','trash') and post_parent=%s and meta_key='_product_id' and meta_value=%s limit 1";

		return $wpdb->get_var( $wpdb->prepare( $query, array( $order_id, $product_id ) ) ) ? true : false;// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * An expired subscription can only be renewed within the first billing cycle since it expired
	 *
	 * @param WC_Order $subscription
	 *
	 * @return array
	 */
	public static function can_renew_from_expired( $subscription ) {
		$return = array(
			'can_renew'        => false,
			'new_next_payment' => '',
			'new_sub_expire'   => '',
		);

		if ( self::$settings->get_params( 'expired_subscription_renewable' ) ) {
			$sub_expire = $subscription->get_meta( '_subre_subscription_expire', true );
			if ( 'subre_expired' === $subscription->get_status() && $sub_expire ) {
				$start_date         = $subscription->get_date_created()->getTimestamp();
				$subscription_trial = $subscription->get_meta( '_subre_subscription_trial_end', true );

				if ( $subscription_trial ) {
					$start_date = $subscription_trial;
				}

				$subscription_period      = $subscription->get_meta( '_subre_subscription_period', true );
				$subscription_period_unit = $subscription->get_meta( '_subre_subscription_period_unit', true );

				if ( self::$settings->get_params( 'expired_subscription_renew_date_from_expired_date' ) ) {
					$subscription_cycle = $subscription_period * SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_unit_in_seconds( $subscription_period_unit );

					$cycle_interval               = $subscription->get_meta( '_subre_subscription_cycle_interval', true );
					$cycle                        = ( time() - $sub_expire ) / ( $cycle_interval );
					$cycle                        = ceil( $cycle );
					$return['can_renew']          = true;
					$return['new_next_payment']   = time();
					$return['new_sub_expire']     = $sub_expire + $cycle * ( $cycle_interval );
					$return['subscription_cycle'] = $subscription_cycle;
				} else {
					$return['can_renew']        = true;
					$return['new_next_payment'] = time();
					$return['new_sub_expire']   = time() + ( $sub_expire - $start_date );
				}
			}
		}

		return $return;
	}
}

new SUBRE_SUBSCRIPTION_ORDER();