<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Subscription_Product {
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_filter( 'product_type_options', array( $this, 'add_subscription_checkbox' ), 0 );
		add_action( 'woocommerce_product_options_pricing', array( $this, 'add_subscription_options' ), 5 );
		add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_subscription_options' ) );
	}

	public function save_subscription_options( $product_id ) {
	    if ( isset( $_REQUEST['subre_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['subre_nonce'] ) ), 'subre_nonce' ) ) {
	        return;
        }
		$_subre_subscription = isset( $_POST['_subre_product_is_subscription'] ) ? 'yes' : 'no';
		update_post_meta( $product_id, '_subre_product_is_subscription', $_subre_subscription );
		if ( 'yes' === $_subre_subscription ) {
			update_post_meta( $product_id, '_subre_product_sign_up_fee', isset( $_POST['_subre_product_sign_up_fee'] ) ? sanitize_text_field( $_POST['_subre_product_sign_up_fee'] ) : '' );
			update_post_meta( $product_id, '_subre_product_period_unit', isset( $_POST['_subre_product_period_unit'] ) ? sanitize_text_field( $_POST['_subre_product_period_unit'] ) : '' );
			update_post_meta( $product_id, '_subre_product_period', isset( $_POST['_subre_product_period'] ) ? sanitize_text_field( $_POST['_subre_product_period'] ) : '' );
			update_post_meta( $product_id, '_subre_product_trial_period', isset( $_POST['_subre_product_trial_period'] ) ? sanitize_text_field( $_POST['_subre_product_trial_period'] ) : '' );
			update_post_meta( $product_id, '_subre_product_trial_period_unit', isset( $_POST['_subre_product_trial_period_unit'] ) ? sanitize_text_field( $_POST['_subre_product_trial_period_unit'] ) : '' );
			update_post_meta( $product_id, '_subre_product_expire_after', isset( $_POST['_subre_product_expire_after'] ) ? sanitize_text_field( $_POST['_subre_product_expire_after'] ) : '' );
			update_post_meta( $product_id, '_subre_product_expire_after_unit', isset( $_POST['_subre_product_expire_after_unit'] ) ? sanitize_text_field( $_POST['_subre_product_expire_after_unit'] ) : '' );
		}
	}

	/**
	 * @param $page
	 */
	public function admin_enqueue_scripts( $page ) {
		global $post_type;
		if ( ( $page === 'post.php' || $page === 'post-new.php' ) && $post_type === 'product' ) {
			wp_enqueue_script( 'subre-admin-edit-product', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_JS . 'admin-product.js', array( 'jquery' ), SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION, false );
			wp_enqueue_style( 'subre-admin-edit-product', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS . 'admin-product.css', array(), SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
		}
	}

	public function add_subscription_options() {
		global $post;
		$product_id               = $post->ID;
		$intervals                = SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_supported_intervals();
		$sign_up_fee              = get_post_meta( $product_id, '_subre_product_sign_up_fee', true );
		$subscription_period      = get_post_meta( $product_id, '_subre_product_period', true );
		$subscription_period_unit = get_post_meta( $product_id, '_subre_product_period_unit', true );
		$trial_period             = get_post_meta( $product_id, '_subre_product_trial_period', true );
		$trial_period_unit        = get_post_meta( $product_id, '_subre_product_trial_period_unit', true );
		$expire_after             = get_post_meta( $product_id, '_subre_product_expire_after', true );
		$expire_after_unit        = get_post_meta( $product_id, '_subre_product_expire_after_unit', true );
		woocommerce_wp_text_input(
			array(
				'id'            => '_subre_product_sign_up_fee',
				'wrapper_class' => 'show_if_subre_subscription',
				'value'         => $sign_up_fee,
				'placeholder'   => esc_html__( 'Sign-up fee', 'subre-product-subscription-for-woo' ),
				'label'         => esc_html__( 'Sign-up fee', 'subre-product-subscription-for-woo' ),
				'desc_tip'      => true,
				'data_type'     => 'price',
			)
		);
		$_nonce = wp_create_nonce('subre_nonce');
		?>
        <p class="show_if_subre_subscription form-field">
            <input type="hidden" id="subre_nonce" name="subre_nonce" value="<?php echo esc_attr( $_nonce ); ?>">
            <label for="_subre_product_period">
				<?php esc_html_e( 'Subscription interval', 'subre-product-subscription-for-woo' ); ?>
            </label><?php echo SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::wp_kses_post( wc_help_tip( esc_html__( 'This is subscription billing cycle', 'subre-product-subscription-for-woo' ) ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <input type="number" class="short" min="1" name="_subre_product_period"
                   id="_subre_product_period" value="<?php echo esc_attr( $subscription_period ) ?>"/>
            <select name="_subre_product_period_unit" class="_subre_product_period_unit">
				<?php
				foreach ( $intervals as $key => $value ) {
					?>
                    <option value="<?php echo esc_attr( $key ) ?>" <?php selected( $key, $subscription_period_unit ) ?>><?php echo esc_html( $value ) ?></option>
					<?php
				}
				?>
            </select>
        </p>
        <p class="show_if_subre_subscription form-field"><label
                    for="_subre_product_trial_period"><?php esc_html_e( 'Subscription trial', 'subre-product-subscription-for-woo' ); ?></label><?php echo SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::wp_kses_post( wc_help_tip( esc_html__( 'Leave empty to not allow trial', 'subre-product-subscription-for-woo' ) ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <input type="number" class="short" min="0" name="_subre_product_trial_period"
                   placeholder="<?php esc_attr_e( 'Empty = no trial', 'subre-product-subscription-for-woo' ) ?>"
                   id="_subre_product_trial_period" value="<?php echo esc_attr( $trial_period ) ?>"/>
            <select name="_subre_product_trial_period_unit" class="_subre_product_trial_period_unit">
				<?php
				foreach ( $intervals as $key => $value ) {
					?>
                    <option value="<?php echo esc_attr( $key ) ?>" <?php selected( $key, $trial_period_unit ) ?>><?php echo esc_html( $value ) ?></option>
					<?php
				}
				?>
            </select>
        </p>
        <p class="show_if_subre_subscription form-field"><label
                    for="_subre_product_expire_after"><?php esc_html_e( 'Expire after', 'subre-product-subscription-for-woo' ); ?></label><?php echo SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::wp_kses_post( wc_help_tip( esc_html__( 'If empty, subscription will never expire', 'subre-product-subscription-for-woo' ) ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <input type="number" class="short" min="0" name="_subre_product_expire_after"
                   placeholder="<?php esc_attr_e( 'Empty = never expire', 'subre-product-subscription-for-woo' ) ?>"
                   id="_subre_product_expire_after" value="<?php echo esc_attr( $expire_after ) ?>"/>
            <select name="_subre_product_expire_after_unit" class="_subre_product_expire_after_unit">
				<?php
				$expire_intervals = array_merge( array( 'cycle' => esc_html__( 'Cycle', 'subre-product-subscription-for-woo' ) ), $intervals );
				foreach ( $expire_intervals as $key => $value ) {
					?>
                    <option value="<?php echo esc_attr( $key ) ?>" <?php selected( $key, $expire_after_unit ) ?>><?php echo esc_html( $value ) ?></option>
					<?php
				}
				?>
            </select>
            <span class="subre-invalid-expire-value-warning"><?php esc_html_e( 'Subscription expiration should be greater than subscription interval', 'subre-product-subscription-for-woo' ); ?></span>
        </p>
		<?php
	}

	public function add_subscription_checkbox( $options ) {
		$options['subre_product_is_subscription'] = array(
			'id'            => '_subre_product_is_subscription',
			'wrapper_class' => 'show_if_simple',
			'label'         => esc_html__( 'Subscription', 'subre-product-subscription-for-woo' ),
			'description'   => esc_html__( 'Check if this is a subscription product', 'subre-product-subscription-for-woo' ),
			'default'       => 'no',
		);

		return $options;
	}
}
