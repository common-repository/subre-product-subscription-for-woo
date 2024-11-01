<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Settings {
	private static $settings;

	public function __construct() {
		self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
		add_action( 'admin_init', array( $this, 'save_settings' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Save plugin settings
	 */
	public static function save_settings() {
		global $subre_settings;
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( $page === 'subre-product-subscription-for-woo' ) {
			if ( ! current_user_can( apply_filters( 'subre_admin_menu_capability', 'manage_options', 'subre-product-subscription-for-woo' ) ) ) {
				return;
			}

			if ( isset( $_POST['_subre_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['_subre_nonce'] ), 'subre_save_settings' ) ) {
				$current_args               = self::$settings->get_params();
				$args                       = [];
				$subscriptions_endpoint     = $current_args['subscriptions_endpoint'];
				$view_subscription_endpoint = $current_args['view_subscription_endpoint'];

				foreach ( $current_args as $key => $value ) {
					$full_key = 'subre_' . $key;
					if ( isset( $_POST[ $full_key ] ) ) {
						if ( in_array( $key, [ 'price_html_with_trial_and_sign_up_fee', 'price_html_with_trial', 'price_html_with_sign_up_fee' ] ) ) {
							$args[ $key ] = sanitize_textarea_field( stripslashes( $_POST[ $full_key ] ) );

							continue;
						}
						if ( is_array( $_POST[ $full_key ] ) ) {
							$args[ $key ] = wc_clean( $_POST[ $full_key ] );
						} else {
							$args[ $key ] = sanitize_text_field( stripslashes( $_POST[ $full_key ] ) );
						}
					}
				}

				$args = apply_filters( 'subre_save_settings_params', $args );
				if ( empty( $args['subscriptions_endpoint'] ) ) {
					$args['subscriptions_endpoint'] = 'subscriptions';
				}

				if ( empty( $args['view_subscription_endpoint'] ) ) {
					$args['view_subscription_endpoint'] = 'view-subscription';
				}

				if ( empty( $args['past_due_by'] ) ) {
					$args['past_due_by'] = 1;
				}

				$subre_settings = $args;
				self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance( true );

				update_option( 'subre_settings', $args );
				if ( $subscriptions_endpoint !== $args['subscriptions_endpoint'] || $view_subscription_endpoint !== $args['view_subscription_endpoint'] ) {
					/*Flush rewrite rules if endpoints change*/
					update_option( 'woocommerce_queue_flush_rewrite_rules', 'yes' );
				}
			}
		}
	}

	/**
	 * Enqueue admin scripts
	 */
	public function admin_enqueue_scripts() {
		global $pagenow;
//		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( $_REQUEST['page'] ) : '';
		$page_id = get_current_screen()->id;
		if ( $pagenow === 'admin.php' && ( $page_id === 'subre-product-subscription-for-woo' || $page_id === 'toplevel_page_subre-product-subscription-for-woo' ) ) {
			wp_enqueue_style( 'subre-admin-settings', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS . 'admin-settings.css', '', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
			wp_enqueue_script( 'subre-admin-settings', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_JS . 'admin-settings.js', array(
				'jquery',
				'jquery-ui-sortable'
			), SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION, false );
			self::enqueue_3rd_library( array(
				'button',
				'checkbox',
				'dropdown',
				'form',
				'icon',
				'input',
				'label',
				'menu',
				'message',
				'segment',
				'tab',
				'table',
				'select2',
			) );
		}
	}

	/**
	 * @param array $elements
	 * @param bool $exclude
	 */
	public static function enqueue_3rd_library( $elements = array(), $exclude = false ) {
		global $wp_scripts;
		$scripts         = $wp_scripts->registered;
		$exclude_dequeue = apply_filters( 'subre_exclude_dequeue_scripts', array( 'dokan-vue-bootstrap' ) );
		foreach ( $scripts as $k => $script ) {
			if ( in_array( $script->handle, $exclude_dequeue ) ) {
				continue;
			}
			preg_match( '/bootstrap/i', $k, $result );
			if ( count( array_filter( $result ) ) ) {
				unset( $wp_scripts->registered[ $k ] );
				wp_dequeue_script( $script->handle );
			}
		}
		wp_dequeue_script( 'select-js' );//Causes select2 error, from ThemeHunk MegaMenu Plus plugin
		wp_dequeue_style( 'eopa-admin-css' );
		$all_elements = array(
			'accordion',
			'button',
			'checkbox',
			'dimmer',
			'divider',
			'dropdown',
			'form',
			'grid',
			'icon',
			'image',
			'input',
			'label',
			'loader',
			'menu',
			'message',
			'progress',
			'segment',
			'tab',
			'table',
			'select2',
			'step',
			'sortable',
		);
		if ( ! count( $elements ) ) {
			$elements = $all_elements;
		} elseif ( $exclude ) {
			$elements = array_diff( $all_elements, $elements );
		}
		$libs_css_dir = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS_DIR . 'libs' . DIRECTORY_SEPARATOR;
		$libs_js_dir  = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_JS_DIR . 'libs' . DIRECTORY_SEPARATOR;
		$libs_css     = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS . 'libs/';
		$libs_js      = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_JS . 'libs/';
		foreach ( $elements as $element ) {
			if ( is_file( $libs_css_dir . "{$element}.min.css" ) ) {
				wp_enqueue_style( "subre-{$element}", $libs_css . "{$element}.min.css", array(),SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
			} elseif ( is_file( $libs_css_dir . "{$element}.css" ) ) {
				wp_enqueue_style( "subre-{$element}", $libs_css . "{$element}.css", array(),SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
			}
			if ( is_file( $libs_js_dir . "{$element}.min.js" ) ) {
				wp_enqueue_script( "subre-{$element}", $libs_js . "{$element}.min.js", array( 'jquery' ), SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION, false );
			} elseif ( is_file( $libs_js_dir . "{$element}.js" ) ) {
				wp_enqueue_script( "subre-{$element}", $libs_js . "{$element}.js", array( 'jquery' ), SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION, false );
			}
		}
		if ( in_array( 'sortable', $elements ) ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}
		if ( in_array( 'select2', $elements ) ) {
			wp_enqueue_style( 'select2', $libs_css . 'select2.min.css', array(), SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
			if ( woocommerce_version_check( '3.0.0' ) ) {
				wp_enqueue_script( 'select2' );
			} else {
				wp_enqueue_script( 'select2-v4', $libs_js . 'select2.js', array( 'jquery' ), '4.0.3', false );
			}
		}
		if ( in_array( 'dropdown', $elements ) ) {
			wp_enqueue_style( 'subre-transition', $libs_css . 'transition.min.css', array(), '2.1.7' );
			wp_enqueue_script( 'subre-transition', $libs_js . 'transition.min.js', array( 'jquery' ), '2.1.7', false );
			wp_enqueue_script( 'subre-address', $libs_js . 'jquery.address-1.6.min.js', array( 'jquery' ), '1.6', false );
		}
	}

	/**
	 * Settings page callback
	 */
	public function page_callback() {
		?>
        <div class="wrap subre-product-subscription-for-woo">
            <h2><?php esc_html_e( 'SUBRE – Product Subscription for Woo Settings', 'subre-product-subscription-for-woo' ) ?></h2>
            <div class="vi-ui positive message">
                <div class="header"><?php esc_html_e( 'How it works:', 'subre-product-subscription-for-woo' ) ?></div>
                <ul class="list">
                    <li><?php echo SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::wp_kses_post( __( 'If an order that contains subscription products is created via a payment method that supports automatic renewal, subscriptions generated from that order will be renewed automatically. Otherwise, subscriptions will be renewed manually. In both cases, a reminder email will be sent <strong>1 day</strong> before renewal payment due.', 'subre-product-subscription-for-woo' ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
                    <li><?php echo SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::wp_kses_post( __( 'Payment methods that support automatic renewal at the moment: Stripe and Sepa(<strong>WooCommerce Stripe Gateway</strong> by WooCommerce); Stripe Credit Cards, SEPA and Stripe Google Pay(<strong>Payment Plugins for Stripe WooCommerce</strong> by Payment Plugins)', 'subre-product-subscription-for-woo' ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
                </ul>
            </div>
            <form method="post" action="" class="vi-ui form">
				<?php wp_nonce_field( 'subre_save_settings', '_subre_nonce' ); ?>
                <div class="vi-ui attached tabular menu">
                    <div class="item active <?php self::set_params( 'tab-item' ) ?>" data-tab="general">
						<?php esc_html_e( 'General', 'subre-product-subscription-for-woo' ) ?>
                    </div>
                    <div class="item <?php self::set_params( 'tab-item' ) ?>" data-tab="custom">
						<?php esc_html_e( 'Custom', 'subre-product-subscription-for-woo' ) ?>
                    </div>
                </div>
                <div class="vi-ui bottom attached tab segment active <?php self::set_params( 'tab-content' ) ?>"
                     data-tab="general">
                    <table class="form-table">
                        <tbody>

                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'reduce_stock_if_renewal' ) ?>">
									<?php esc_html_e( 'Reduce stock if renewal', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'reduce_stock_if_renewal' ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'reduce_stock_if_renewal' ), 1 ) ?>
                                           tabindex="0" class="<?php self::set_params( 'reduce_stock_if_renewal' ) ?>"
                                           value="1"
                                           name="<?php self::set_params( 'reduce_stock_if_renewal', true ) ?>"/>
                                    <label><?php esc_html_e( 'Yes', 'subre-product-subscription-for-woo' ) ?></label>
                                </div>
                                <p class="description"><?php esc_html_e( 'Will stock of subscription products be reduced normally when a subscription renewal order is paid?', 'subre-product-subscription-for-woo' ) ?></p>
                                <p class="description"><?php esc_html_e( 'Only apply to products whose "Manage stock" is on.', 'subre-product-subscription-for-woo' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'change_payment_if_manual_renewal' ) ?>">
									<?php esc_html_e( 'Change payment', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'change_payment_if_manual_renewal' ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'change_payment_if_manual_renewal' ), 1 ) ?>
                                           tabindex="0"
                                           class="<?php self::set_params( 'change_payment_if_manual_renewal' ) ?>"
                                           value="1"
                                           name="<?php self::set_params( 'change_payment_if_manual_renewal', true ) ?>"/>
                                    <label><?php esc_html_e( 'Yes', 'subre-product-subscription-for-woo' ) ?></label>
                                </div>
                                <p class="description"><?php esc_html_e( 'Allow changing payment method when customers manually pay a subscription renewal order.', 'subre-product-subscription-for-woo' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'past_due_status' ) ?>">
									<?php esc_html_e( 'Subscription status', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <span><?php esc_html_e( 'If a recurring payment is incomplete for', 'subre-product-subscription-for-woo' ); ?></span>
								<?php
								$past_due_status = self::$settings->get_params( 'past_due_status' );
								?>
                                <div class="vi-ui right labeled input <?php self::set_params( 'past-due-by-container' ) ?>">
                                    <input id="<?php self::set_params( 'past_due_by' ) ?>"
                                           type="number"
                                           min="1"
                                           class="<?php self::set_params( 'past_due_by' ) ?>"
                                           value="<?php echo esc_attr( self::$settings->get_params( 'past_due_by' ) ); ?>"
                                           name="<?php self::set_params( 'past_due_by', true ) ?>"/>
                                    <label for="<?php self::set_params( 'past_due_by', true ) ?>"
                                           class="vi-ui label"><?php esc_html_e( 'day(s)', 'subre-product-subscription-for-woo' ) ?></label>
                                </div>
                                ,
                                <select id="<?php self::set_params( 'past_due_status' ) ?>"
                                        class="vi-ui dropdown <?php self::set_params( 'past_due_status' ) ?>"
                                        name="<?php self::set_params( 'past_due_status', true ) ?>">
                                    <option value="" <?php selected( $past_due_status, '' ) ?>><?php esc_html_e( 'Leave the subscription as-is', 'subre-product-subscription-for-woo' ) ?></option>
                                    <option value="cancel" <?php selected( $past_due_status, 'cancel' ) ?>><?php esc_html_e( 'Cancel the subscription', 'subre-product-subscription-for-woo' ) ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'expired_subscription_renewable' ) ?>">
									<?php esc_html_e( 'Allow expired subscriptions to be renewable', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'expired_subscription_renewable' ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'expired_subscription_renewable' ), 1 ) ?>
                                           tabindex="0"
                                           class="<?php self::set_params( 'expired_subscription_renewable' ) ?>"
                                           value="1"
                                           name="<?php self::set_params( 'expired_subscription_renewable', true ) ?>"/>
                                    <label><?php esc_html_e( 'Yes', 'subre-product-subscription-for-woo' ) ?></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'expired_subscription_renew_date_from_expired_date' ) ?>">
									<?php esc_html_e( 'Expired subscriptions\' start from previous expiry date after being renewed', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <div class="vi-ui toggle checkbox">
                                    <input id="<?php self::set_params( 'expired_subscription_renew_date_from_expired_date' ) ?>"
                                           type="checkbox" <?php checked( self::$settings->get_params( 'expired_subscription_renew_date_from_expired_date' ), 1 ) ?>
                                           tabindex="0"
                                           class="<?php self::set_params( 'expired_subscription_renew_date_from_expired_date' ) ?>"
                                           value="1"
                                           name="<?php self::set_params( 'expired_subscription_renew_date_from_expired_date', true ) ?>"/>
                                    <label><?php esc_html_e( 'Yes', 'subre-product-subscription-for-woo' ) ?></label>
                                </div>
                                <p class="description">
									<?php esc_html_e( 'If enabled, the new cycle will commence from the previous expiry date and  the total value of missed payments will be calculated, rather than starting from the current date.', 'subre-product-subscription-for-woo' ) ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'subscriptions_endpoint' ) ?>">
									<?php esc_html_e( 'Subscriptions endpoint', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <input id="<?php self::set_params( 'subscriptions_endpoint' ) ?>"
                                       type="text"
                                       class="<?php self::set_params( 'subscriptions_endpoint' ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'subscriptions_endpoint' ) ); ?>"
                                       name="<?php self::set_params( 'subscriptions_endpoint', true ) ?>"/>
                                <p class="description"><?php esc_html_e( 'Endpoint for My account/Subscriptions', 'subre-product-subscription-for-woo' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'view_subscription_endpoint' ) ?>">
									<?php esc_html_e( 'View subscription endpoint', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <input id="<?php self::set_params( 'view_subscription_endpoint' ) ?>"
                                       type="text"
                                       class="<?php self::set_params( 'view_subscription_endpoint' ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'view_subscription_endpoint' ) ); ?>"
                                       name="<?php self::set_params( 'view_subscription_endpoint', true ) ?>"/>
                                <p class="description"><?php esc_html_e( 'Endpoint for My account/View subscription', 'subre-product-subscription-for-woo' ) ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment <?php self::set_params( 'tab-content' ) ?>"
                     data-tab="custom">
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'atc_button_title' ) ?>">
									<?php esc_html_e( 'Add to cart button title', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <input id="<?php self::set_params( 'atc_button_title' ) ?>"
                                       type="text"
                                       class="<?php self::set_params( 'atc_button_title' ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'atc_button_title' ) ); ?>"
                                       name="<?php self::set_params( 'atc_button_title', true ) ?>"/>
                                <p class="description"><?php esc_html_e( 'Change title of add to cart button if a product is subscription', 'subre-product-subscription-for-woo' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'place_order_button_title' ) ?>">
									<?php esc_html_e( 'Checkout button title', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <input id="<?php self::set_params( 'place_order_button_title' ) ?>"
                                       type="text"
                                       class="<?php self::set_params( 'place_order_button_title' ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'place_order_button_title' ) ); ?>"
                                       name="<?php self::set_params( 'place_order_button_title', true ) ?>"/>
                                <p class="description"><?php esc_html_e( 'Change title of Checkout button if cart contains at least 1 subscription product', 'subre-product-subscription-for-woo' ) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'price_html' ) ?>">
			                        <?php esc_html_e( 'Change price html basic', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <textarea name="<?php self::set_params( 'price_html', true ) ?>"
                                          id="<?php self::set_params( 'price_html' ) ?>"
                                          class="<?php self::set_params( 'price_html' ) ?>"
                                          rows="3"><?php echo esc_attr( self::$settings->get_params( 'price_html' ) ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Price will display like this if a subscription product has nothing.', 'subre-product-subscription-for-woo' ) ?></p>
		                        <?php
		                        self::table_of_placeholders( array(
			                        'subscription_price'  => esc_html__( 'Subscription price', 'subre-product-subscription-for-woo' ),
			                        'subscription_period' => esc_html__( 'Subscription interval', 'subre-product-subscription-for-woo' ),
		                        ) );
		                        ?>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'price_html_with_trial_and_sign_up_fee' ) ?>">
									<?php esc_html_e( 'Change price html if a product has both sign-up fee and trial available', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <textarea name="<?php self::set_params( 'price_html_with_trial_and_sign_up_fee', true ) ?>"
                                          id="<?php self::set_params( 'price_html_with_trial_and_sign_up_fee' ) ?>"
                                          class="<?php self::set_params( 'price_html_with_trial_and_sign_up_fee' ) ?>"
                                          rows="3"><?php echo esc_attr( self::$settings->get_params( 'price_html_with_trial_and_sign_up_fee' ) ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Price will display like this if a subscription product has both sign-up fee and free trial.', 'subre-product-subscription-for-woo' ) ?></p>
								<?php
								self::table_of_placeholders( array(
									'subscription_price'  => esc_html__( 'Subscription price', 'subre-product-subscription-for-woo' ),
									'subscription_period' => esc_html__( 'Subscription interval', 'subre-product-subscription-for-woo' ),
									'trial_period'        => esc_html__( 'Trial period', 'subre-product-subscription-for-woo' ),
									'sign_up_fee'         => esc_html__( 'Sign-up fee', 'subre-product-subscription-for-woo' ),
								) );
								?>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'price_html_with_trial' ) ?>">
									<?php esc_html_e( 'Change price html if trial is available', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <textarea name="<?php self::set_params( 'price_html_with_trial', true ) ?>"
                                          id="<?php self::set_params( 'price_html_with_trial' ) ?>"
                                          class="<?php self::set_params( 'price_html_with_trial' ) ?>"
                                          rows="3"><?php echo esc_attr( self::$settings->get_params( 'price_html_with_trial' ) ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Price will display like this if a subscription product has trial but no sign-up fee.', 'subre-product-subscription-for-woo' ) ?></p>
								<?php
								self::table_of_placeholders( array(
									'subscription_price'  => esc_html__( 'Subscription price', 'subre-product-subscription-for-woo' ),
									'subscription_period' => esc_html__( 'Subscription interval', 'subre-product-subscription-for-woo' ),
									'trial_period'        => esc_html__( 'Trial period', 'subre-product-subscription-for-woo' ),
								) );
								?>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'price_html_with_sign_up_fee' ) ?>">
									<?php esc_html_e( 'Change price html if a product has sign-up fee', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <textarea name="<?php self::set_params( 'price_html_with_sign_up_fee', true ) ?>"
                                          id="<?php self::set_params( 'price_html_with_sign_up_fee' ) ?>"
                                          class="<?php self::set_params( 'price_html_with_sign_up_fee' ) ?>"
                                          rows="3"><?php echo esc_attr( self::$settings->get_params( 'price_html_with_sign_up_fee' ) ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Price will display like this if a subscription product has sign-up fee but no trial.', 'subre-product-subscription-for-woo' ) ?></p>
								<?php
								self::table_of_placeholders( array(
									'subscription_price'  => esc_html__( 'Subscription price', 'subre-product-subscription-for-woo' ),
									'subscription_period' => esc_html__( 'Subscription interval', 'subre-product-subscription-for-woo' ),
									'sign_up_fee'         => esc_html__( 'Sign-up fee', 'subre-product-subscription-for-woo' ),
								) );
								?>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="<?php self::set_params( 'expiry_date_info' ) ?>">
									<?php esc_html_e( 'Expiry date info', 'subre-product-subscription-for-woo' ) ?>
                                </label>
                            </th>
                            <td>
                                <input id="<?php self::set_params( 'expiry_date_info' ) ?>"
                                       type="text"
                                       class="<?php self::set_params( 'expiry_date_info' ) ?>"
                                       value="<?php echo esc_attr( self::$settings->get_params( 'expiry_date_info' ) ); ?>"
                                       name="<?php self::set_params( 'expiry_date_info', true ) ?>"/>
                                <p class="description"><?php esc_html_e( 'Additional info for subscriptions that have expiry dates', 'subre-product-subscription-for-woo' ) ?></p>
								<?php
								self::table_of_placeholders( array(
									'expiry_period' => esc_html__( 'Expiry period', 'subre-product-subscription-for-woo' ),
									'expiry_date'   => esc_html__( 'Expiry date', 'subre-product-subscription-for-woo' ),
								) );
								?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <p class="<?php self::set_params( 'save-settings-container' ) ?>">
                    <button type="submit"
                            class="vi-ui button primary labeled icon <?php self::set_params( 'save-settings' ) ?>"
                            name="<?php self::set_params( 'save-settings', true ) ?>"><i
                                class="save icon"></i><?php esc_html_e( 'Save Settings', 'subre-product-subscription-for-woo' ) ?>
                    </button>
                </p>
            </form>
        </div>
		<?php
		do_action( 'villatheme_support_subre-product-subscription-for-woo' );
	}

	private static function table_of_placeholders( $args ) {
		if ( count( $args ) ) {
			?>
            <table class="vi-ui celled table <?php self::set_params( 'table-of-placeholders' ) ?>">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Placeholder', 'subre-product-subscription-for-woo' ) ?></th>
                    <th><?php esc_html_e( 'Explanation', 'subre-product-subscription-for-woo' ) ?></th>
                </tr>
                </thead>
                <tbody>
				<?php
				foreach ( $args as $key => $value ) {
					?>
                    <tr>
                        <td class="<?php self::set_params( 'placeholder-value-container' ) ?>"><input
                                    class="<?php self::set_params( 'placeholder-value' ) ?>" type="text"
                                    readonly value="<?php echo esc_attr( "{{$key}}" ); ?>"><i
                                    class="vi-ui icon copy <?php self::set_params( 'placeholder-value-copy' ) ?>"
                                    title="<?php esc_attr_e( 'Copy', 'subre-product-subscription-for-woo' ) ?>"></i>
                        </td>
                        <td><?php echo esc_html( "{$value}" ); ?></td>
                    </tr>
					<?php
				}
				?>
                </tbody>
            </table>
			<?php
		}
	}

	/**
	 * Add settings and subscriptions menu page
	 */
	public function admin_menu() {
		$menu_slug = 'subre-product-subscription-for-woo';
		add_menu_page(
			esc_html__( 'SUBRE – Product Subscription for Woo', 'subre-product-subscription-for-woo' ),
			esc_html__( 'SUBRE', 'subre-product-subscription-for-woo' ),
			apply_filters( 'subre_admin_menu_capability', 'manage_options', $menu_slug ),
			$menu_slug,
			'',
			'dashicons-controls-repeat',
			2
		);
		add_submenu_page(
			$menu_slug,
			esc_html__( 'SUBRE', 'subre-product-subscription-for-woo' ),
			esc_html__( 'SUBRE', 'subre-product-subscription-for-woo' ),
			apply_filters( 'subre_admin_menu_capability', 'manage_options', $menu_slug ),
			$menu_slug,
			array( $this, 'page_callback' ),
			0
		);
	}

	/**
	 * @param $name
	 * @param bool $set_name
	 */
	private static function set_params( $name, $set_name = false ) {
		echo esc_attr( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::set( $name, $set_name ) );
	}

	/**
	 * @return mixed|void
	 */
	public static function create_ajax_nonce() {
		return apply_filters( 'subre_admin_ajax_nonce', wp_create_nonce( 'subre_admin_ajax' ) );
	}
}