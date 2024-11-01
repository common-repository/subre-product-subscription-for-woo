<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Order_List {

	public function __construct() {
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_subscription_statuses' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	public function admin_enqueue_scripts() {
		global $pagenow, $post_type;
		if ( $pagenow === 'edit.php' && $post_type === 'shop_order' ) {
			wp_enqueue_style( 'subre-admin-order-list', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS . 'admin-order-list.css', '', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
		}
	}

	public function render_columns( $column, $post_id ) {
		if ( $column === 'subre_subscription_info' ) {
			$subscription_obj = get_post_parent( $post_id );
			if ( $subscription_obj && 'subre_subscription' === $subscription_obj->post_type ) {
				?>
                <div class="subre-subscription-renewal"
                     title="<?php esc_attr_e( 'The subscription of which this order is a renewal', 'subre-product-subscription-for-woo' ) ?>">
                    <span class="subre-subscription-renewal-icon dashicons dashicons-update-alt"></span><?php echo wp_kses_post( subre_get_order_subscription_edit_link( $subscription_obj->ID ) ); ?>
                </div>
				<?php
			} else {
				$subscription_ids = get_post_meta( $post_id, '_subre_subscription_ids', true );
				if ( $subscription_ids ) {
					?>
                    <div class="subre-subscription-list">
						<?php
						foreach ( $subscription_ids as $subscription_id ) {
							?>
                            <div class="subre-subscription-list-item"
                                 title="<?php esc_attr_e( 'The subscription created from this order', 'subre-product-subscription-for-woo' ) ?>">
                                <span class="subre-subscription-icon dashicons dashicons-controls-repeat"></span><?php echo wp_kses_post( subre_get_order_subscription_edit_link( $subscription_id ) ); ?>
                            </div>
							<?php
						}
						?>
                    </div>
					<?php
				}
			}
		}
	}

	public function add_columns( $columns ) {
		$columns['subre_subscription_info'] = esc_html__( 'Subscription Info', 'subre-product-subscription-for-woo' );

		return $columns;
	}

	public function register_subscription_statuses( $statuses ) {
		$subs_statuses = SUBRE_SUBSCRIPTION_ORDER::get_subscription_only_statuses();
		foreach ( $subs_statuses as $key => $value ) {
			$statuses[ $key ] = array(
				'label'                     => $value,
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
//				'label_count'               => _n_noop( $value . ' <span class="count">(%s)</span>', $value . ' <span class="count">(%s)</span>', 'subre-product-subscription-for-woo' ),
			);
			switch ( $key ) {
                case 'wc-subre_active':
	                /* translators: %s: number of orders */
	                $statuses[ $key ]['label_count'] = _n_noop('Active <span class="count">(%s)</span>',
		                'Active <span class="count">(%s)</span>', 'subre-product-subscription-for-woo' );
                    break;
                case 'wc-subre_expired':
	                /* translators: %s: number of orders */
	                $statuses[ $key ]['label_count'] = _n_noop('Expired <span class="count">(%s)</span>',
		                'Expired <span class="count">(%s)</span>', 'subre-product-subscription-for-woo' );
                    break;
                case 'wc-subre_a_cancel':
	                /* translators: %s: number of orders */
	                $statuses[ $key ]['label_count'] = _n_noop('Awaiting Cancel <span class="count">(%s)</span>',
		                'Awaiting Cancel <span class="count">(%s)</span>', 'subre-product-subscription-for-woo' );
                    break;
                default:
                    //wc-subre_trial
	                /* translators: %s: number of orders */
	                $statuses[ $key ]['label_count'] = _n_noop('Trial <span class="count">(%s)</span>',
                        'Trial <span class="count">(%s)</span>', 'subre-product-subscription-for-woo' );
            }
		}

		return $statuses;
	}
}
