<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Subscription_Edit {
	use SUBRE_TRAIT_ORDER_LIST_TABLE;

	public function __construct() {
		add_filter( 'wc_order_statuses', array( $this, 'wc_order_statuses' ), PHP_INT_MAX );
		add_filter( 'wp_insert_post_data', array( $this, 'wp_insert_post_data' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_filter( 'woocommerce_order_actions', array( $this, 'change_to_subscription_actions' ), 10, 2 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_subscription_info' ), 5, 2 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'handle_subscription_actions' ), 99, 2 );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
		add_action( 'wp_insert_post_data', array( $this, 'wp_insert_post_data' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), PHP_INT_MAX );
		/*Do not allow adding new subscription*/
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		/*Do not allow editing subscription items*/
		add_filter( 'wc_order_is_editable', array( $this, 'wc_order_is_editable' ), 10, 2 );
	}

	/**
	 * @param $editable
	 * @param $order WC_Order
	 *
	 * @return bool
	 */
	public function wc_order_is_editable( $editable, $order ) {
		if ( SUBRE_SUBSCRIPTION_ORDER::is_a_subscription( $order ) ) {
			$editable = false;
		}

		return $editable;
	}

	public function admin_init() {
		global $pagenow;
		if ( $pagenow === 'post-new.php' ) {
			if ( isset( $_REQUEST['subre_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['subre_nonce'] ) ), 'subre_nonce' ) ) {
			    return;
            }
			$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : '';
			if ( 'subre_subscription' === $post_type ) {
				wp_die( esc_html__( 'Not allowed.', 'subre-product-subscription-for-woo' ) );
			}
		}
	}

	public function post_updated_messages( $messages ) {
		global $post;
		$messages['subre_subscription'] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => esc_html__( 'Subscription updated.', 'subre-product-subscription-for-woo' ),
			2  => esc_html__( 'Custom field updated.', 'subre-product-subscription-for-woo' ),
			3  => esc_html__( 'Custom field deleted.', 'subre-product-subscription-for-woo' ),
			4  => esc_html__( 'Subscription updated.', 'subre-product-subscription-for-woo' ),
			5  => esc_html__( 'Revision restored.', 'subre-product-subscription-for-woo' ),
			6  => esc_html__( 'Subscription updated.', 'subre-product-subscription-for-woo' ),
			7  => esc_html__( 'Subscription saved.', 'subre-product-subscription-for-woo' ),
			8  => esc_html__( 'Subscription submitted.', 'subre-product-subscription-for-woo' ),
			9  => sprintf(
			/* translators: %s: date */
				esc_html__( 'Subscription scheduled for: %s.', 'subre-product-subscription-for-woo' ),
				'<strong>' . date_i18n( esc_html__( 'M j, Y @ G:i', 'subre-product-subscription-for-woo' ), strtotime( $post->post_date ) ) . '</strong>'
			),
			10 => esc_html__( 'Subscription draft updated.', 'subre-product-subscription-for-woo' ),
			11 => esc_html__( 'Subscription updated and sent.', 'subre-product-subscription-for-woo' ),
			12 => esc_html__( 'Subscription cancelled.', 'subre-product-subscription-for-woo' ),
			13 => esc_html__( 'Subscription active.', 'subre-product-subscription-for-woo' ),
		);

		return $messages;
	}

	/**
	 * Save subscription info and handle data changes
	 * Schedule immediately if expire or renew within 24 hours
	 *
	 * @param $subscription_id
	 * @param $post
	 *
	 * @throws Exception
	 */
	public function save_subscription_info( $subscription_id, $post ) {
		if ( 'subre_subscription' === get_post_type( $subscription_id ) ) {
			if ( isset( $_REQUEST['subre_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['subre_nonce'] ) ), 'subre_nonce' ) ) {
				return;
			}
			$now              = time();
			$subscription     = wc_get_order( $subscription_id );
			$next_payment_old = get_post_meta( $subscription_id, '_subre_subscription_next_payment', true );
			$sub_expire_old   = get_post_meta( $subscription_id, '_subre_subscription_expire', true );
			$next_payment     = isset( $_POST['_subre_subscription_next_payment_date'] ) ? sanitize_text_field( $_POST['_subre_subscription_next_payment_date'] ) : '';
			if ( $next_payment ) {
				$date         = gmdate( 'Y-m-d H:i:s', strtotime( $next_payment . ' ' . (int) sanitize_text_field( $_POST['_subre_subscription_next_payment_hour'] ) . ':' . (int) sanitize_text_field( $_POST['_subre_subscription_next_payment_minute'] ) . ':' . (int) sanitize_text_field( $_POST['_subre_subscription_next_payment_second'] ) ) );
				$next_payment = wc_string_to_timestamp( get_gmt_from_date( gmdate( 'Y-m-d H:i:s', wc_string_to_timestamp( $date ) ) ) );
			}
			$sub_expire = isset( $_POST['_subre_subscription_expire_date'] ) ? sanitize_text_field( $_POST['_subre_subscription_expire_date'] ) : '';
			if ( $sub_expire ) {
				$date       = gmdate( 'Y-m-d H:i:s', strtotime( $sub_expire . ' ' . (int) sanitize_text_field( $_POST['_subre_subscription_expire_hour'] ) . ':' . (int) sanitize_text_field( $_POST['_subre_subscription_expire_minute'] ) . ':' . (int) sanitize_text_field( $_POST['_subre_subscription_expire_second'] ) ) );
				$sub_expire = wc_string_to_timestamp( get_gmt_from_date( gmdate( 'Y-m-d H:i:s', wc_string_to_timestamp( $date ) ) ) );
			}
			/*payment due changes*/
			if ( $next_payment != $next_payment_old ) {
				if ( $next_payment_old ) {
					$subscription->add_order_note( sprintf( '%1s %2s %3s %4s',
						esc_html__( 'Subscription payment due changed from', 'subre-product-subscription-for-woo' ),
                        SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $next_payment_old )->date_i18n( 'Y-m-d H:i:s' ),
						esc_html__( 'to', 'subre-product-subscription-for-woo' ),
                        SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $next_payment )->date_i18n( 'Y-m-d H:i:s' ) ) );
				} else {
					$subscription->add_order_note( sprintf( '%1s %2s',
						esc_html__( 'Subscription payment due changed to', 'subre-product-subscription-for-woo' ),
                        SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $next_payment )->date_i18n( 'Y-m-d H:i:s' ) ) );
				}
				update_post_meta( $subscription_id, '_subre_subscription_next_payment', $next_payment );
				SUBRE_SUBSCRIPTION_SCHEDULE::unschedule( $subscription_id, 'renew' );
			}
			if ( $next_payment > $now && $next_payment - $now <= DAY_IN_SECONDS ) {
				SUBRE_SUBSCRIPTION_SCHEDULE::unschedule( $subscription_id, 'expire' );
				SUBRE_SUBSCRIPTION_SCHEDULE::unschedule( $subscription_id, 'cancel' );
				SUBRE_SUBSCRIPTION_SCHEDULE::schedule( $subscription_id, 'renew' );
			}
			/*expiry date changes*/
			if ( $sub_expire != $sub_expire_old ) {
				$expired = false;
				if ( $sub_expire ) {
					if ( $sub_expire_old ) {
						$subscription->add_order_note( sprintf('%1s %2s %3s %4s',
							esc_html__( 'Subscription expiry date changed from', 'subre-product-subscription-for-woo' ),
                            SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $sub_expire_old )->date_i18n( 'Y-m-d H:i:s' ),
							esc_html__( 'to', 'subre-product-subscription-for-woo' ),
                            SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $sub_expire )->date_i18n( 'Y-m-d H:i:s' ) ) );
					} else {
						$subscription->add_order_note( sprintf('%1s %2s',
							esc_html__( 'Subscription expiry date changed to', 'subre-product-subscription-for-woo' ),
                            SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $sub_expire )->date_i18n( 'Y-m-d H:i:s' ) ) );
					}
					if ( $sub_expire <= $now ) {
						$expired = true;
						SUBRE_SUBSCRIPTION_SCHEDULE::unschedule( $subscription_id, 'renew' );
						if ( SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'expired' ) ) {
							/*Change order_status in global $_POST so that it will not be changed back to original order_status by WooCommerce*/
							$_POST['order_status'] = SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'expired' );
						}
					} elseif ( $sub_expire - $now <= DAY_IN_SECONDS ) {
						$tomorrow = strtotime( '+1 day midnight' );
						if ( $sub_expire < $tomorrow || ( $next_payment >= $tomorrow && $next_payment - $now <= DAY_IN_SECONDS && $next_payment >= $sub_expire ) ) {
							/*Unschedule future renewal if the subscription expires today or expire and renewal both occur tomorrow*/
							SUBRE_SUBSCRIPTION_SCHEDULE::unschedule( $subscription_id, 'renew' );
						}
						SUBRE_SUBSCRIPTION_SCHEDULE::schedule( $subscription_id, 'expire' );
					}
				} else {
					$subscription->add_order_note( sprintf('%1s %2s',
						esc_html__( 'Subscription expiry date removed, previous value is', 'subre-product-subscription-for-woo' ),
                        SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $sub_expire_old )->date_i18n( 'Y-m-d H:i:s' ) ) );
				}
				if ( ! $expired && SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'expired' ) === get_post_status( $subscription_id ) ) {
					if ( SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'active' ) ) {
						/*Change order_status in global $_POST so that it will not be changed back to original order_status by WooCommerce*/
						$_POST['order_status'] = SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'active' );
					}
				}
				update_post_meta( $subscription_id, '_subre_subscription_expire', $sub_expire );
			}
		}
	}

	/**
	 * Change the post saved message if a subscription is successfully cancelled via bulk actions
	 *
	 * @param $location
	 *
	 * @return string
	 */
	public static function set_subscription_cancelled_message( $location ) {
		return add_query_arg( 'message', 12, $location );
	}

	/**
	 * Change the post saved message if a subscription is successfully set active via bulk actions
	 *
	 * @param $location
	 *
	 * @return string
	 */
	public static function set_subscription_active_message( $location ) {
		return add_query_arg( 'message', 13, $location );
	}

	/**
	 * Handle bulk actions
	 *
	 * @param $subscription_id
	 * @param $post
	 */
	public function handle_subscription_actions( $subscription_id, $post ) {
		if ( 'subre_subscription' === get_post_type( $subscription_id ) ) {
			if ( isset( $_REQUEST['subre_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['subre_nonce'] ) ), 'subre_nonce' ) ) {
				return;
			}

			$action = isset( $_POST['wc_order_action'] ) ? wc_clean( wp_unslash( $_POST['wc_order_action'] ) ) : '';
			switch ( $action ) {
				case 'subre_cancel_subscription':
					if ( in_array( get_post_status( $subscription_id ), array(
						SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'active' ),
						SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'trial' ),
						SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'on-hold' ),
					), true ) ) {
						if ( SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'cancelled' ) ) {
							add_filter( 'redirect_post_location', array(
								__CLASS__,
								'set_subscription_cancelled_message'
							) );
						}
					}
					break;
				case 'subre_set_active':
					if ( ! in_array( get_post_status( $subscription_id ), array(
						SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'expired' )
					), true ) ) {
						if ( SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'active' ) ) {
							add_filter( 'redirect_post_location', array(
								__CLASS__,
								'set_subscription_active_message'
							) );
						}
					}
					break;
				case 'subre_set_on_hold':
					if ( in_array( get_post_status( $subscription_id ), array(
						SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'active' ),
					), true ) ) {
						SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'on-hold' );
					}
					break;
				default:
			}
		}
	}

	/**
	 * @param $actions
	 * @param $subscription WC_Order
	 *
	 * @return array
	 */
	public function change_to_subscription_actions( $actions, $subscription ) {
		if ( $subscription ) {
			if ( 'subre_subscription' === get_post_type( $subscription->get_id() ) ) {
				$actions = array();
				if ( ! in_array( $subscription->get_status(), array( 'cancelled', 'subre_expired' ), true ) ) {
					$actions['subre_cancel_subscription'] = esc_html__( 'Cancel subscription', 'subre-product-subscription-for-woo' );
				}
				if ( ! in_array( $subscription->get_status(), array( 'subre_active', 'subre_expired' ), true ) ) {
					$actions['subre_set_active'] = esc_html__( 'Set subscription Active', 'subre-product-subscription-for-woo' );
				}
				if ( in_array( $subscription->get_status(), array( 'subre_active' ), true ) ) {
					$actions['subre_set_on_hold'] = esc_html__( 'Set subscription On-hold', 'subre-product-subscription-for-woo' );
				}
			}
		}

		return $actions;
	}

	public function admin_enqueue_scripts() {
		global $pagenow, $post;
		if ( $pagenow === 'post.php' ) {
			$screen = get_current_screen();
			if ( is_a( $screen, 'WP_Screen' ) && $screen->id === 'subre_subscription' ) {
				wp_enqueue_style( 'subre-admin-subscription-statuses', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS . 'admin-subscription-statuses.css', '', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
				wp_enqueue_style( 'subre-admin-subscription-edit', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS . 'admin-subscription-edit.css', '', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION );
				wp_enqueue_script( 'subre-admin-subscription-edit', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_JS . 'admin-subscription-edit.js', array(
					'jquery',
					'wc-admin-meta-boxes'
				), SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION, false );
				wp_localize_script( 'subre-admin-subscription-edit', 'subre_admin_subscription_edit', array(
					'subscription_heading' => $post ? '<h2 class="woocommerce-order-data__heading">' .
                                                      sprintf( '%1s%2s %3s',
	                                                      esc_html__( 'Subscription #', 'subre-product-subscription-for-woo' ), $post->ID,
	                                                      esc_html__( 'details', 'subre-product-subscription-for-woo' )) . '</h2>' : ''
				) );
			}
		}
	}

	/**
	 * Add related orders and Subscription info metaboxes
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'subre_related_orders',
			esc_html__( 'Related Orders', 'subre-product-subscription-for-woo' ),
			array( $this, 'related_orders_callback' ),
			'subre_subscription',
			'normal',
			'core'
		);
		add_meta_box(
			'subre_subscription_info',
			esc_html__( 'Subscription Info', 'subre-product-subscription-for-woo' ),
			array( $this, 'subscription_info_callback' ),
			'subre_subscription',
			'side',
			'core'
		);
	}

	/**
	 * Related orders
	 */
	public function related_orders_callback() {
		global $post;
		$subscription_id = $post->ID;
		$subscription    = wc_get_order( $subscription_id );
		if ( $subscription ) {
			?>
            <table class="wp-list-table widefat fixed striped subre-related-orders-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Order', 'subre-product-subscription-for-woo' ) ?></th>
                    <th><?php esc_html_e( 'Type', 'subre-product-subscription-for-woo' ) ?></th>
                    <th><?php esc_html_e( 'Date', 'subre-product-subscription-for-woo' ) ?></th>
                    <th><?php esc_html_e( 'Total', 'subre-product-subscription-for-woo' ) ?></th>
                    <th><?php esc_html_e( 'Status', 'subre-product-subscription-for-woo' ) ?></th>
                </tr>
                </thead>
                <tbody>
				<?php
				$renewal_orders = get_post_meta( $subscription_id, '_subre_subscription_renewal_ids', true );
				if ( $renewal_orders ) {
					rsort( $renewal_orders );
					foreach ( $renewal_orders as $renewal_order ) {
						$this->output_related_order_row( $renewal_order );
					}
				}
				$parent = get_post_parent( $subscription_id );
				if ( $parent ) {
					$this->output_related_order_row( $parent->ID, false );
				}
				?>
                </tbody>
            </table>
			<?php
		}
	}

	/**
	 * @param $order_id
	 * @param $is_renew
	 */
	private function output_related_order_row( $order_id, $is_renew = true ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			?>
            <tr>
                <td><?php echo wp_kses_post( subre_get_order_subscription_edit_link( $order_id ) ); ?></td>
                <td><?php $is_renew ? esc_html_e( 'Renewal', 'subre-product-subscription-for-woo' ) : esc_html_e( 'Parent order', 'subre-product-subscription-for-woo' ) ?></td>
                <td><?php $this->render_column( 'order_date', $order ); ?></td>
                <td><?php echo wp_kses_post( $order->get_formatted_order_total() ) ?></td>
                <td><?php $this->render_column( 'order_status', $order ); ?></td>
            </tr>
			<?php
		}
	}

	/**
	 * @throws Exception
	 */
	public function subscription_info_callback() {
		global $post;
		$subscription_id = $post->ID;
		$subscription    = wc_get_order( $subscription_id );
		if ( $subscription ) {
			$sub_expire               = $subscription->get_meta( '_subre_subscription_expire', true );
			$sub_expire_              = $sub_expire ? SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $sub_expire ) : '';
			$sub_expire_localised     = $sub_expire_ ? $sub_expire_->getOffsetTimestamp() : '';
			$next_payment             = $subscription->get_meta( '_subre_subscription_next_payment', true );
			$next_payment_            = $next_payment ? SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $next_payment ) : '';
			$next_payment_localised   = $next_payment_ ? $next_payment_->getOffsetTimestamp() : '';
			$subscription_period      = $subscription->get_meta( '_subre_subscription_period', true );
			$subscription_period_unit = $subscription->get_meta( '_subre_subscription_period_unit', true );
			$trial_end                = $subscription->get_meta( '_subre_subscription_trial_end', true );
			$_nonce = wp_create_nonce('subre_nonce');
			?>
            <p class="form-field form-field-wide subre-subscription-info-item-wrap">
                <input type="hidden" id="subre_nonce" name="subre_nonce" value="<?php echo esc_attr( $_nonce ); ?>">
                <label><?php esc_html_e( 'Status', 'subre-product-subscription-for-woo' ) ?>:</label>
                <span class="subre-subscription-info-item subre-subscription-status-container"><?php $this->render_column( 'order_status', $subscription ); ?></span>
            </p>
			<?php
			if ( $subscription_period ) {
				?>
                <p class="form-field form-field-wide subre-subscription-info-item-wrap">
                    <label><?php esc_html_e( 'Billing cycle', 'subre-product-subscription-for-woo' ) ?>:</label>
                    <span class="subre-subscription-info-item subre-billing-cycle-container"><?php echo esc_html( SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_formatted_period( $subscription_period, $subscription_period_unit, true ) ) ?></span>
                </p>
				<?php
			}
			if ( $trial_end ) {
				$trial_end_ = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_datetime( $trial_end );
				?>
                <p class="form-field form-field-wide subre-subscription-info-item-wrap">
                    <label><?php esc_html_e( 'Trial ends', 'subre-product-subscription-for-woo' ) ?>:</label>
                    <span class="subre-subscription-info-item subre-trial-end-container"><?php echo esc_html( $trial_end_->date_i18n( 'Y-m-d H:i:s' ) ) ?></span>
                </p>
				<?php
			}
			?>
            <p class="form-field form-field-wide subre-subscription-info-item-wrap">
                <label><?php esc_html_e( 'Payment due', 'subre-product-subscription-for-woo' ) ?>:</label>
                <span class="subre-subscription-info-item subre-next-payment-container">
	                <input type="text" class="date-picker" name="_subre_subscription_next_payment_date" maxlength="10"
                           value="<?php echo $next_payment_localised ? esc_attr( date_i18n( 'Y-m-d', $next_payment_localised ) ) : ''; ?>"
                           pattern="<?php echo esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment ?>"/>@
                &lrm;
	                <input type="number" class="subre-hour"
                           placeholder="<?php esc_attr_e( 'h', 'subre-product-subscription-for-woo' ); ?>"
                           name="_subre_subscription_next_payment_hour" min="0" max="23" step="1"
                           value="<?php echo $next_payment_localised ? esc_attr( date_i18n( 'H', $next_payment_localised ) ) : ''; ?>"
                           pattern="([01]?[0-9]{1}|2[0-3]{1})"/>:
	                <input type="number" class="subre-minute"
                           placeholder="<?php esc_attr_e( 'm', 'subre-product-subscription-for-woo' ); ?>"
                           name="_subre_subscription_next_payment_minute" min="0" max="59" step="1"
                           value="<?php echo $next_payment_localised ? esc_attr( date_i18n( 'i', $next_payment_localised ) ) : ''; ?>"
                           pattern="[0-5]{1}[0-9]{1}"/>
	                <input type="hidden" name="_subre_subscription_next_payment_second"
                           value="<?php echo $next_payment_localised ? esc_attr( date_i18n( 's', $next_payment_localised ) ) : ''; ?>"/>
                </span>
            </p>
            <p class="form-field form-field-wide subre-subscription-info-item-wrap">
                <label><?php esc_html_e( 'Expiry date', 'subre-product-subscription-for-woo' ) ?>:</label>
                <span class="subre-subscription-info-item subre-expire-container">
	                <input type="text" class="date-picker" name="_subre_subscription_expire_date" maxlength="10"
                           value="<?php echo $sub_expire_localised ? esc_attr( date_i18n( 'Y-m-d', $sub_expire_localised ) ) : ''; ?>"
                           pattern="<?php echo esc_attr( apply_filters( 'woocommerce_date_input_html_pattern', '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])' ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment ?>"/>@
                &lrm;
	                <input type="number" class="subre-hour"
                           placeholder="<?php esc_attr_e( 'h', 'subre-product-subscription-for-woo' ); ?>"
                           name="_subre_subscription_expire_hour" min="0" max="23" step="1"
                           value="<?php echo $sub_expire_localised ? esc_attr( date_i18n( 'H', $sub_expire_localised ) ) : ''; ?>"
                           pattern="([01]?[0-9]{1}|2[0-3]{1})"/>:
	                <input type="number" class="subre-minute"
                           placeholder="<?php esc_attr_e( 'm', 'subre-product-subscription-for-woo' ); ?>"
                           name="_subre_subscription_expire_minute" min="0" max="59" step="1"
                           value="<?php echo $sub_expire_localised ? esc_attr( date_i18n( 'i', $sub_expire_localised ) ) : ''; ?>"
                           pattern="[0-5]{1}[0-9]{1}"/>
	                <input type="hidden" name="_subre_subscription_expire_second"
                           value="<?php echo $sub_expire_localised ? esc_attr( date_i18n( 's', $sub_expire_localised ) ) : ''; ?>"/>
                </span>
            </p>
            <input type="hidden" name="order_status"
                   value="<?php echo esc_attr( get_post_status( $subscription_id ) ) ?>">
			<?php
		}
	}

	/**
	 * Prevent subscriptions from renaming when manually updating a subscription
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function wp_insert_post_data( $data ) {
		if ( 'subre_subscription' === $data['post_type'] && isset( $data['post_date'] ) ) {
			$order_title = 'Subscription';
			if ( $data['post_date'] ) {
				$order_title .= ' &ndash; ' . date_i18n( 'F j, Y @ h:i A', strtotime( $data['post_date'] ) );
			}
			$data['post_title'] = $order_title;
		}

		return $data;
	}

	/**
	 * Make sure subscription statuses only show when editing a subscription
	 *
	 * @param $statuses
	 *
	 * @return mixed
	 */
	public function wc_order_statuses( $statuses ) {
		global $theorder;
		if ( $theorder ) {
			$order = $theorder;
			if ( 'subre_subscription' === get_post_type( $order->get_id() ) ) {
				$keep_statuses = SUBRE_SUBSCRIPTION_ORDER::get_subscription_statuses();
				foreach ( $statuses as $key => $value ) {
					if ( ! in_array( $key, $keep_statuses, true ) ) {
						unset( $statuses[ $key ] );
					}
				}
			}
		}

		return $statuses;
	}
}
