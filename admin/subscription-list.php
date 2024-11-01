<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Subscription_List {
	use SUBRE_TRAIT_ORDER_LIST_TABLE;

	public function __construct() {
		add_action( 'current_screen', array( $this, 'setup_screen' ) );
		add_action( 'check_ajax_referer', array( $this, 'setup_screen' ) );
		add_filter( 'manage_subre_subscription_posts_columns', array( $this, 'define_columns' ) );
		add_action( 'manage_subre_subscription_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 100, 2 );
		add_filter( 'bulk_actions-edit-subre_subscription', array( $this, 'bulk_actions' ), 50 );
	}

	/**
	 * Remove edit from bulk actions
	 *
	 * @param $bulk_actions
	 *
	 * @return mixed
	 */
	public function bulk_actions( $bulk_actions ) {
		unset( $bulk_actions['edit'] );

		return $bulk_actions;
	}

	/**
	 * Remove row actions
	 *
	 * @param $actions
	 * @param $post
	 *
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( 'subre_subscription' === $post->post_type ) {
			$actions = array();
		}

		return $actions;
	}

	public function admin_enqueue_scripts() {
		global $pagenow, $post_type;
		if ( $pagenow === 'edit.php' && $post_type === 'subre_subscription' ) {
			wp_enqueue_style( 'subre-admin-subscription-list', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS . 'admin-subscription-list.css', '', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
			wp_enqueue_style( 'subre-admin-subscription-statuses', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS . 'admin-subscription-statuses.css', '', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
		}
	}

	/**
	 * @param $column
	 * @param $post_id
	 *
	 * @throws Exception
	 */
	public function render_columns( $column, $post_id ) {
		$subscription = wc_get_order( $post_id );
		switch ( $column ) {
			case 'subscription':
				$this->render_column( 'order_number', $subscription );
				break;
			case 'subscription_date':
				$this->render_column( 'order_date', $subscription );
				break;
			case 'subscription_status':
				$this->render_column( 'order_status', $subscription );
				break;
			case 'parent_order':
				$parent_order = get_post_parent( $post_id );
				if ( $parent_order ) {
					echo wp_kses_post( subre_get_order_subscription_edit_link( $parent_order->ID ) );
				}
				break;
			case 'renewal_orders':
				$renewal_ids = get_post_meta( $post_id, '_subre_subscription_renewal_ids', true );
				if ( $renewal_ids ) {
					$renewal_ids_show = array();
					foreach ( $renewal_ids as $renewal_id ) {
						if ( wc_get_order( $renewal_id ) ) {
							$renewal_ids_show[] = subre_get_order_subscription_edit_link( $renewal_id );
						}
					}
					echo SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::wp_kses_post( implode( ', ', $renewal_ids_show ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				break;
			case 'recurring_amount':
				echo wp_kses_post( SUBRE_SUBSCRIPTION_ORDER::get_formatted_subscription_recurring_amount( $subscription ) );
				break;
			case 'next_payment':
				$next_payment = get_post_meta( $post_id, '_subre_subscription_next_payment', true );
				if ( $next_payment ) {
					SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $next_payment );
				}
				break;
			case 'expiry_date':
				$expiry_date = get_post_meta( $post_id, '_subre_subscription_expire', true );
				if ( $expiry_date ) {
					SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $expiry_date );
				}
				break;
			case 'payment_method':
				echo esc_html( SUBRE_SUBSCRIPTION_ORDER::get_subscription_payment_method( $post_id )['title'] );
				break;
			default:
		}
	}

	/**
	 * @param $columns
	 *
	 * @return array
	 */
	public function define_columns( $columns ) {
		$show_columns                        = array();
		$show_columns['cb']                  = $columns['cb'];
		$show_columns['subscription']        = esc_html__( 'Subscription', 'subre-product-subscription-for-woo' );
		$show_columns['parent_order']        = esc_html__( 'Parent Order', 'subre-product-subscription-for-woo' );
		$show_columns['renewal_orders']      = esc_html__( 'Renewal Orders', 'subre-product-subscription-for-woo' );
		$show_columns['recurring_amount']    = esc_html__( 'Recurring Amount', 'subre-product-subscription-for-woo' );
		$show_columns['subscription_date']   = esc_html__( 'Date', 'subre-product-subscription-for-woo' );
		$show_columns['subscription_status'] = esc_html__( 'Status', 'subre-product-subscription-for-woo' );
		$show_columns['next_payment']        = esc_html__( 'Payment due', 'subre-product-subscription-for-woo' );
		$show_columns['payment_method']      = esc_html__( 'Payment Method', 'subre-product-subscription-for-woo' );
		$show_columns['expiry_date']         = esc_html__( 'Expiry Date', 'subre-product-subscription-for-woo' );

		return $show_columns;
	}

	public function setup_screen() {
		global $wc_list_table;

		$screen_id = false;

		if ( function_exists( 'get_current_screen' ) ) {
			$screen    = get_current_screen();
			$screen_id = isset( $screen, $screen->id ) ? $screen->id : '';
		}

		if ( ! empty( $_REQUEST['screen'] ) ) {
			if ( ! isset( $_REQUEST['subre_nonce'] ) || wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['subre_nonce'] ) ), 'subre_nonce' ) ) {
				$screen_id = wc_clean( wp_unslash( $_REQUEST['screen'] ) );
			}
		}
		if ( $screen_id === 'edit-subre_subscription' ) {
			include_once WC_ADMIN_ABSPATH . 'includes/admin/list-tables/class-wc-admin-list-table-orders.php';
			$wc_list_table = new WC_Admin_List_Table_Orders();
		}

		// Ensure the table handler is only loaded once. Prevents multiple loads if a plugin calls check_ajax_referer many times.
		remove_action( 'current_screen', array( $this, 'setup_screen' ) );
		remove_action( 'check_ajax_referer', array( $this, 'setup_screen' ) );
	}
}
