<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\Jetpack\Constants;

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_My_Account {
	private static $settings;
	private static $current_page;
	private static $endpoint;

	/**
	 * SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_My_Account constructor.
	 */
	public function __construct() {
		self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
		self::$endpoint = array(
			'subscriptions'     => self::$settings->get_params( 'subscriptions_endpoint' ),
			'view-subscription' => self::$settings->get_params( 'view_subscription_endpoint' ),
		);
		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_subscription_menu' ) );
		add_filter( 'woocommerce_account_menu_item_classes', array(
			$this,
			'active_on_view_subscription'
		), 10, 2 );
		add_action( 'woocommerce_account_' . self::$endpoint['subscriptions'] . '_endpoint', array(
			$this,
			'subscriptions_page'
		), 1 );
		add_action( 'woocommerce_account_' . self::$endpoint['view-subscription'] . '_endpoint', array(
			$this,
			'view_subscription'
		) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_related_subscriptions' ) );
	}

	/**
	 * Related subscription on order detail screen
	 *
	 * @param $order WC_Order
	 */
	public function add_related_subscriptions( $order ) {
		$order_id = $order->get_id();
		if ( SUBRE_SUBSCRIPTION_ORDER::is_a_renewal_order( $order_id ) ) {
			$subscription_ids = array( get_post_parent( $order_id )->ID );
		} else {
			$args             = array(
				'post_type'      => 'subre_subscription',
				'post_status'    => 'any',
				'posts_per_page' => count( $order->get_items() ),
				'post_parent'    => $order_id,
				'fields'         => 'ids',
			);
			$the_query        = new WP_Query( $args );
			$subscription_ids = $the_query->posts;
		}

		if ( $subscription_ids ) {
			wc_get_template(
				'order/related-subscriptions.php',
				array(
					'order'            => $order,
					'subscription_ids' => $subscription_ids,
				),
				'subre-product-subscription-for-woo' . DIRECTORY_SEPARATOR,
				SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_TEMPLATES
			);
		}
	}

	/**
	 * Enqueue myaccount scripts
	 */
	public function wp_enqueue_scripts() {
		global $wp_query;
		if ( is_account_page() ) {
			$js_suffix  = '.min.js';
			$css_suffix = '.min.css';
			if ( WP_DEBUG || Constants::is_true( 'SCRIPT_DEBUG' ) ) {
				$js_suffix  = '.js';
				$css_suffix = '.css';
			}
			if ( isset( $wp_query->query_vars[ self::$endpoint['subscriptions'] ] ) || isset( $wp_query->query_vars[ self::$endpoint['view-subscription'] ] ) || isset( $wp_query->query_vars['view-order'] ) ) {
				wp_enqueue_style( 'subre-frontend-subscription-statuses', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS . 'frontend-subscription-statuses' . $css_suffix, '', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
			}
			if ( isset( $wp_query->query_vars[ self::$endpoint['subscriptions'] ] ) || isset( $wp_query->query_vars[ self::$endpoint['view-subscription'] ] ) ) {
				wp_enqueue_script( 'subre-my-account', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_JS . 'my-account' . $js_suffix, array( 'jquery' ), SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION, false );
				wp_localize_script( 'subre-my-account', 'subre_my_account_params', array(
					'i18n_confirm_cancel' => esc_html__( 'Are you sure you want to cancel this subscription?', 'subre-product-subscription-for-woo' ),
				) );
			}
		}
	}

	/**
	 * View a subscription screen
	 *
	 * @param $subscription_id
	 */
	public function view_subscription( $subscription_id ) {
		add_action( 'subre_subscription_details_after_order_table', array( $this, 'add_subscription_buttons' ) );
		add_action( 'subre_after_subscription_details', array( $this, 'add_related_orders' ) );
		$subscription = wc_get_order( $subscription_id );

		if ( ! $subscription || ! current_user_can( 'view_order', $subscription_id ) ) {
			echo '<div class="woocommerce-error">' . esc_html__( 'Invalid subscription.', 'subre-product-subscription-for-woo' ) . ' <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" class="wc-forward">' . esc_html__( 'My account', 'subre-product-subscription-for-woo' ) . '</a></div>';

			return;
		}

		wc_get_template(
			'myaccount/view-subscription.php',
			array(
				'subscription' => $subscription,
			),
			'subre-product-subscription-for-woo' . DIRECTORY_SEPARATOR,
			SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_TEMPLATES
		);
	}

	/**
	 * @param $subscription WC_Order
	 *
	 * @throws Exception
	 */
	public function add_related_orders( $subscription ) {
		wc_get_template(
			'subscription/related-orders.php',
			array(
				'subscription_id' => $subscription->get_id(),
			),
			'subre-product-subscription-for-woo' . DIRECTORY_SEPARATOR,
			SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_TEMPLATES
		);
	}

	/**
	 * @param $subscription WC_Order
	 */
	public function add_subscription_buttons( $subscription ) {
		if ( ! $subscription || ! is_a( $subscription, 'WC_Order' ) ) {
			return;
		}
		if ( ! SUBRE_SUBSCRIPTION_ORDER::is_a_subscription( $subscription ) ) {
			return;
		}
		$subscription_id     = $subscription->get_id();
		$subscription_status = $subscription->get_status();

		$next_payment    = $subscription->get_meta( '_subre_subscription_next_payment', true );
		$current_renewal = $subscription->get_meta( '_subre_subscription_current_renewal_order', true );
		$sub_expire      = $subscription->get_meta( '_subre_subscription_expire', true );
		$pending_renewal = false;
		if ( $current_renewal ) {
			/*If there is an unpaid renewal order, renew button will link to payment page*/
			if ( in_array( $subscription_status, SUBRE_SUBSCRIPTION_ORDER::get_subscription_statuses_for_renew( $subscription ), true ) ) {
				$current_renewal_order = wc_get_order( $current_renewal );
				if ( $current_renewal_order ) {
					if ( ! $current_renewal_order->is_paid() && $current_renewal_order->needs_payment() ) {
						$pending_renewal = true;
						?>
                        <a class="button renew"
                           href="<?php echo esc_url( $current_renewal_order->get_checkout_payment_url() ) ?>"><?php esc_html_e( 'Renew', 'subre-product-subscription-for-woo' ) ?></a>
						<?php
					}
				}
			}
		}

		$renew_button = false;
		if ( SUBRE_SUBSCRIPTION_ORDER::can_renew_from_expired( $subscription )['can_renew'] ) {
			$renew_button = true;
		} else {
			if ( $next_payment ) {
				/*Show renew button if this subscription must be renewed manually*/
				if ( ! $pending_renewal && in_array( $subscription_status, SUBRE_SUBSCRIPTION_ORDER::get_subscription_statuses_for_renew( $subscription ), true ) && ! SUBRE_SUBSCRIPTION_ORDER::is_automatic_payment_supported( SUBRE_SUBSCRIPTION_ORDER::get_subscription_payment_method( $subscription_id )['id'] ) ) {
					if ( ! $sub_expire || $next_payment < $sub_expire ) {
						$renew_button = true;
					}
				}
			}
		}

		if ( $renew_button ) {
			?>
            <a class="button renew"
               href="<?php echo esc_url( SUBRE_SUBSCRIPTION_ORDER::get_renew_button_url( $subscription ) ) ?>"><?php esc_html_e( 'Renew', 'subre-product-subscription-for-woo' ) ?></a>
			<?php
		}

		if ( in_array( $subscription_status, SUBRE_SUBSCRIPTION_ORDER::get_subscription_statuses_for_cancel( $subscription ), true ) ) {
			?>
            <a class="button cancel subre-button-cancel-subscription"
               href="<?php echo esc_url( SUBRE_SUBSCRIPTION_ORDER::get_cancel_button_url( $subscription ) ) ?>"><?php esc_html_e( 'Cancel', 'subre-product-subscription-for-woo' ) ?></a>
			<?php
		}
	}

	/**
	 * Make Subscriptions menu active when viewing a subscription
	 *
	 * @param $classes
	 * @param $endpoint
	 *
	 * @return mixed
	 */
	public function active_on_view_subscription( $classes, $endpoint ) {
		global $wp;

		if ( self::$endpoint['subscriptions'] === $endpoint && isset( $wp->query_vars[ self::$endpoint['view-subscription'] ] ) ) {
			array_push( $classes, 'is-active' );
		}

		return $classes;
	}

	/**
     * Subscription table on My account
     * Use woocommerce_account_orders() function to inherit orders templates
     *
	 * @param $current_page
	 */
	public function subscriptions_page( $current_page ) {
		self::$current_page = $current_page;
		/*Custom columns for subscriptions*/
		add_action( 'woocommerce_my_account_my_orders_column_subre-payment-due', array(
			$this,
			'payment_due'
		) );
		add_action( 'woocommerce_my_account_my_orders_column_subre-expire', array(
			$this,
			'expiry_date'
		) );
		add_action( 'woocommerce_my_account_my_orders_column_subre-total', array(
			$this,
			'subscription_recurring_total'
		) );
		add_action( 'woocommerce_my_account_my_orders_column_subre-status', array(
			$this,
			'subscription_status'
		) );
		/*Reuse orders table to inherit theme styles*/
		add_filter( 'woocommerce_my_account_my_orders_query', array( __CLASS__, 'change_my_orders_query' ), 99 );
		add_filter( 'woocommerce_account_orders_columns', array( __CLASS__, 'change_columns' ) );
		add_filter( 'woocommerce_get_view_order_url', array( __CLASS__, 'change_view_url' ), 10, 2 );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'change_actions' ), 10, 2 );
		add_filter( 'woocommerce_get_endpoint_url', array( __CLASS__, 'change_order_endpoint' ), 10, 4 );
		?>
        <div class="subre-my-account-subscriptions-list">
			<?php woocommerce_account_orders( $current_page ); ?>
        </div>
		<?php
		remove_filter( 'woocommerce_my_account_my_orders_query', array( __CLASS__, 'change_my_orders_query' ), 99 );
		remove_filter( 'woocommerce_account_orders_columns', array( __CLASS__, 'change_columns' ) );
		remove_filter( 'woocommerce_get_view_order_url', array( __CLASS__, 'change_view_url' ), 10 );
		remove_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'change_actions' ) );
		remove_filter( 'woocommerce_get_endpoint_url', array( __CLASS__, 'change_order_endpoint' ), 10 );
	}

	/**
	 * Query subscriptions instead of orders via woocommerce_account_orders function
	 *
	 * @param $query
	 *
	 * @return mixed|void
	 */
	public static function change_my_orders_query( $query ) {
		$current_page = self::$current_page;

		return apply_filters(
			'subre_my_account_my_subscriptions_query',
			array(
				'customer'  => get_current_user_id(),
				'page'      => $current_page,
				'paginate'  => true,
				'post_type' => 'subre_subscription'
			)
		);
	}

	/**
	 * @param $subscription WC_Order
	 *
	 * @throws Exception
	 */
	public function payment_due( $subscription ) {
		$next_payment = $subscription->get_meta( '_subre_subscription_next_payment', true );
		if ( $next_payment ) {
			SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $next_payment );
		}
	}

	/**
	 * @param $subscription WC_Order
	 *
	 * @throws Exception
	 */
	public function expiry_date( $subscription ) {
		$sub_expire = $subscription->get_meta( '_subre_subscription_expire', true );
		if ( $sub_expire ) {
			SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $sub_expire );
		}
	}

	/**
	 * @param $subscription WC_Order
	 */
	public function subscription_recurring_total( $subscription ) {
		echo wp_kses_post( SUBRE_SUBSCRIPTION_ORDER::get_formatted_subscription_recurring_amount( $subscription ) );
	}

	/**
	 * @param $subscription WC_Order
	 */
	public function subscription_status( $subscription ) {
		$status = $subscription->get_status();
		?>
        <span class="subre-subscription-status <?php echo esc_attr( "subre-subscription-status-{$status}" ); ?>"><span><?php echo esc_html( SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_name( $status ) ) ?></span></span>
		<?php
	}

	/**
	 * @param $order WC_Order
	 */
	public function order_status( $order ) {
		$status = $order->get_status();
		?>
        <span class="subre-order-status subre-subscription-status <?php echo esc_attr( "subre-subscription-status-{$status}" ); ?>"><span><?php echo esc_html( wc_get_order_status_name( $status ) ) ?></span></span>
		<?php
	}

	/**
	 * Change view order url to view subscription url for Subscription and Actions columns
	 *
	 * @param $view_url
	 * @param $subscription WC_Order
	 *
	 * @return string
	 */
	public static function change_view_url( $view_url, $subscription ) {
		return subre_get_subscription_view_link( $subscription->get_id(), true );
	}

	/**
	 * Change url of pagination buttons
	 *
	 * @param $url
	 * @param $endpoint
	 * @param $value
	 * @param $permalink
	 *
	 * @return string
	 */
	public static function change_order_endpoint( $url, $endpoint, $value, $permalink ) {
		if ( 'orders' === $endpoint ) {
			remove_filter( 'woocommerce_get_endpoint_url', array( __CLASS__, 'change_order_endpoint' ), 10 );
			$url = wc_get_endpoint_url( self::$endpoint['subscriptions'], $value );
			add_filter( 'woocommerce_get_endpoint_url', array( __CLASS__, 'change_order_endpoint' ), 10, 4 );
		}

		return $url;
	}

	/**
	 * Actions buttons on Subscription table
	 *
	 * @param $actions
	 * @param $subscription WC_Order
	 *
	 * @return array
	 */
	public static function change_actions( $actions, $subscription ) {

		$actions             = array(
			'view' => array(
				'url'  => subre_get_subscription_view_link( $subscription->get_id(), true ),
				'name' => esc_html__( 'View', 'subre-product-subscription-for-woo' ),
			),
		);
		$subscription_status = $subscription->get_status();
		$next_payment        = $subscription->get_meta( '_subre_subscription_next_payment', true );
		$current_renewal     = $subscription->get_meta( '_subre_subscription_current_renewal_order', true );
		$sub_expire          = $subscription->get_meta( '_subre_subscription_expire', true );
		$pending_renewal     = false;

		if ( $current_renewal ) {
			/*If there is an unpaid renewal order, renew button will link to payment page*/
			if ( in_array( $subscription_status, SUBRE_SUBSCRIPTION_ORDER::get_subscription_statuses_for_renew( $subscription ), true ) ) {
				$current_renewal_order = wc_get_order( $current_renewal );
				if ( $current_renewal_order ) {
					if ( ! $current_renewal_order->is_paid() && $current_renewal_order->needs_payment() ) {
						$pending_renewal = true;
						$actions         = array_merge( array(
							'renew' => array(
								'url'  => $current_renewal_order->get_checkout_payment_url(),
								'name' => esc_html__( 'Renew', 'subre-product-subscription-for-woo' ),
							)
						), $actions );
					}
				}
			}
		}

		$renew_button = false;
		if ( SUBRE_SUBSCRIPTION_ORDER::can_renew_from_expired( $subscription )['can_renew'] ) {
			$renew_button = true;
		} else {
			if ( $next_payment ) {
				/*Show renew button if this subscription must be renewed manually*/
				if ( ! $pending_renewal && in_array( $subscription_status, SUBRE_SUBSCRIPTION_ORDER::get_subscription_statuses_for_renew( $subscription ), true )
                     && ! SUBRE_SUBSCRIPTION_ORDER::is_automatic_payment_supported( SUBRE_SUBSCRIPTION_ORDER::get_subscription_payment_method( $subscription->get_id() )['id'] ) ) {
					if ( ! $sub_expire || $next_payment < $sub_expire ) {
						$renew_button = true;
					}
				}
			}
		}

		if ( $renew_button ) {
			$actions = array_merge( array(
				'renew' => array(
					'url'  => esc_url( SUBRE_SUBSCRIPTION_ORDER::get_renew_button_url( $subscription ) ),
					'name' => esc_html__( 'Renew', 'subre-product-subscription-for-woo' ),
				)
			), $actions );
		}

		if ( in_array( $subscription_status, SUBRE_SUBSCRIPTION_ORDER::get_subscription_statuses_for_cancel( $subscription ), true ) ) {
			$actions['cancel'] = array(
				'url'  => SUBRE_SUBSCRIPTION_ORDER::get_cancel_button_url( $subscription ),
				'name' => esc_html__( 'Cancel', 'subre-product-subscription-for-woo' ),
			);
		}

		return $actions;
	}

	/**
	 * @param $columns
	 *
	 * @return array
	 */
	public static function change_columns( $columns ) {
		$subscriptions_columns = array(
			'order-number'  => esc_html__( 'Subscription', 'subre-product-subscription-for-woo' ),
			'order-date'    => esc_html__( 'Date', 'subre-product-subscription-for-woo' ),
			'subre-status'  => esc_html__( 'Status', 'subre-product-subscription-for-woo' ),
			'order-actions' => esc_html__( 'Actions', 'subre-product-subscription-for-woo' ),
		);
		$columns               = array_merge( $columns, $subscriptions_columns );
		foreach ( $columns as $key => $value ) {
			if ( ! isset( $subscriptions_columns[ $key ] ) ) {
				unset( $columns[ $key ] );
			}
		}
		if ( isset( $columns['order-actions'] ) ) {
			$order_action = $columns['order-actions'];
			unset( $columns['order-actions'] );
			$columns['subre-total']       = esc_html__( 'Recurring', 'subre-product-subscription-for-woo' );
			$columns['subre-payment-due'] = esc_html__( 'Payment due', 'subre-product-subscription-for-woo' );
			$columns['subre-expire']      = esc_html__( 'Expiry date', 'subre-product-subscription-for-woo' );
			$columns['order-actions']     = $order_action;
		} else {
			$columns['subre-total'] = esc_html__( 'Recurring', 'subre-product-subscription-for-woo' );
		}

		return apply_filters( 'subre_my_account_subscriptions_columns', $columns );
	}

	/**
	 * Subscriptions menu on My account
	 *
	 * @param $wc_menu
	 *
	 * @return mixed
	 */
	public function add_subscription_menu( $wc_menu ) {
		if ( isset( $wc_menu['customer-logout'] ) ) {
			$logout = $wc_menu['customer-logout'];
			unset( $wc_menu['customer-logout'] );
		}

		$wc_menu[ self::$endpoint['subscriptions'] ] = esc_html__( 'Subscriptions', 'subre-product-subscription-for-woo' );

		if ( isset( $logout ) ) {
			$wc_menu['customer-logout'] = $logout;
		}

		return $wc_menu;
	}

	/**
	 * Endpoint for subscriptions list and view subscription
	 */
	public function add_endpoint() {
		foreach ( self::$endpoint as $key => $endpoint ) {
			WC()->query->query_vars[ $key ] = $endpoint;
			add_rewrite_endpoint( $endpoint, WC()->query->get_endpoints_mask() );
		}
	}

	public static function get_myaccount_subscriptions_url() {
		return wc_get_endpoint_url( self::$settings->get_params( 'subscriptions_endpoint' ), '', wc_get_page_permalink( 'myaccount' ) );
	}
}