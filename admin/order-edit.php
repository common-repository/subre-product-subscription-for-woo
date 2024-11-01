<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Order_Edit {
	use SUBRE_TRAIT_ORDER_LIST_TABLE;
	private static $settings;

	public function __construct() {
		self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
		add_filter( 'wc_order_statuses', array( __CLASS__, 'wc_order_statuses' ), PHP_INT_MAX );
		add_filter( 'woocommerce_prevent_adjust_line_item_product_stock', array(
			$this,
			'woocommerce_prevent_adjust_line_item_product_stock'
		), 10, 3 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	public function admin_enqueue_scripts() {
		global $pagenow, $post_type;
		if ( $pagenow === 'post.php' && $post_type === 'shop_order' ) {
			wp_enqueue_style( 'subre-admin-subscription-statuses', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS . 'admin-subscription-statuses.css', '', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
		}
	}

	public function add_meta_boxes() {
		add_meta_box(
			'subre_related_subscription',
			esc_html__( 'Related Subscriptions', 'subre-product-subscription-for-woo' ),
			array( $this, 'related_subscriptions_callback' ),
			'shop_order',
			'normal',
			'core'
		);
	}

	public function related_subscriptions_callback() {
		global $post;
		$order_id = $post->ID;
		$order    = wc_get_order( $order_id );
		if ( $order ) {
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
				?>
                <table class="wp-list-table widefat fixed striped subre-related-subscriptions-table">
                    <thead>
                    <tr>
                        <th><?php esc_html_e( 'Subscription', 'subre-product-subscription-for-woo' ) ?></th>
                        <th><?php esc_html_e( 'Payment due', 'subre-product-subscription-for-woo' ) ?></th>
                        <th><?php esc_html_e( 'Status', 'subre-product-subscription-for-woo' ) ?></th>
                        <th><?php esc_html_e( 'Expiry Date', 'subre-product-subscription-for-woo' ) ?></th>
                    </tr>
                    </thead>
                    <tbody>
					<?php
					foreach ( $subscription_ids as $subscription_id ) {
						$this->output_related_subscription_row( $subscription_id );
					}
					?>
                    </tbody>
                </table>
				<?php
			}
		}
	}

	/**
	 * @param $subscription_id
	 *
	 * @throws Exception
	 */
	private function output_related_subscription_row( $subscription_id ) {
		$subscription = wc_get_order( $subscription_id );
		if ( $subscription ) {
			?>
            <tr>
                <td><?php echo wp_kses_post( subre_get_order_subscription_edit_link( $subscription_id ) ); ?></td>
                <td>
					<?php
					$next_payment = get_post_meta( $subscription_id, '_subre_subscription_next_payment', true );
					if ( $next_payment  ) {
						SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $next_payment );
					}
					?>
                </td>
                <td>
					<?php
					remove_filter( 'wc_order_statuses', array( __CLASS__, 'wc_order_statuses' ), PHP_INT_MAX );
					$this->render_column( 'order_status', $subscription );
					add_filter( 'wc_order_statuses', array( __CLASS__, 'wc_order_statuses' ), PHP_INT_MAX );
					?>
                </td>
                <td>
					<?php
					$expiry_date = get_post_meta( $subscription_id, '_subre_subscription_expire', true );
					if ( $expiry_date ) {
						SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $expiry_date );
					} else {
						echo '-';
					}
					?>
                </td>
            </tr>
			<?php
		}
	}

	/**
	 * @param $prevent
	 * @param $item WC_Order_Item
	 * @param $item_quantity
	 *
	 * @return bool
	 */
	public function woocommerce_prevent_adjust_line_item_product_stock( $prevent, $item, $item_quantity ) {
		if ( ! self::$settings->get_params( 'reduce_stock_if_renewal' ) && SUBRE_SUBSCRIPTION_ORDER::is_a_renewal_order( $item->get_order_id() ) ) {
			$prevent = true;
		}

		return $prevent;
	}

	/**
	 * Make sure subscription statuses only show when editing a subscription
	 *
	 * @param $statuses
	 *
	 * @return mixed
	 */
	public static function wc_order_statuses( $statuses ) {
		global $theorder;
		if ( $theorder ) {
			$order = $theorder;
			if ( 'shop_order' === get_post_type( $order->get_id() ) ) {
				foreach ( array_keys( SUBRE_SUBSCRIPTION_ORDER::get_subscription_only_statuses() ) as $key ) {
					unset( $statuses[ $key ] );
				}
			}
		}

		return $statuses;
	}
}
