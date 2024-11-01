<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_SUBSCRIPTION_ACTIONS {
	public function __construct() {
		add_action( 'wp_loaded', array( __CLASS__, 'cancel_subscription_action' ), 20 );
		add_action( 'wp_loaded', array( __CLASS__, 'renew_subscription_action' ), 20 );
		add_action( 'subre_subscription_status_changed_to_cancelled', array( $this, 'handle_cancelled_subscription' ) );
		add_action( 'subre_cancel_a_subscription', array( $this, 'handle_scheduled_subscription_cancel' ) );
		add_action( 'subre_awaiting_cancel_subscription', array( $this, 'schedule_cancel' ) );
	}

	/**
	 * @param $subscription_id
	 */
	public function schedule_cancel( $subscription_id ) {
		SUBRE_SUBSCRIPTION_SCHEDULE::unschedule( $subscription_id, 'renew' );
		SUBRE_SUBSCRIPTION_SCHEDULE::schedule( $subscription_id, 'cancel' );
	}

	/**
	 * @param $subscription_id
	 */
	public function handle_scheduled_subscription_cancel( $subscription_id ) {
		if ( ! SUBRE_SUBSCRIPTION_ORDER::is_a_subscription( $subscription_id ) ) {
			return;
		}
		$subscription = wc_get_order( $subscription_id );
		if ( ! $subscription ) {
			return;
		}
		if ( ! $subscription->has_status( 'subre_a_cancel' ) ) {
			return;
		}
		self::cancel_a_subscription( $subscription_id );
	}

	/**
	 * After a subscription is cancelled, unschedule renewal if any
	 *
	 * @param $subscription_id
	 */
	public function handle_cancelled_subscription( $subscription_id ) {
		SUBRE_SUBSCRIPTION_SCHEDULE::unschedule( $subscription_id, 'renew' );
	}

	/**
	 * User cancels a subscription
	 */
	public static function cancel_subscription_action() {
		if (
			isset( $_GET['cancel_subscription'], $_GET['order'], $_GET['subscription_id'], $_GET['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'subre_cancel_subscription' )
		) {
			wc_nocache_headers();

			$order_key       = sanitize_text_field( wp_unslash( $_GET['order'] ) );
			$subscription_id = absint( $_GET['subscription_id'] );
			if ( ! SUBRE_SUBSCRIPTION_ORDER::is_a_subscription( $subscription_id ) ) {
				wc_add_notice( esc_html__( 'Invalid subscription.', 'subre-product-subscription-for-woo' ), 'error' );
			} else {
				$subscription     = wc_get_order( $subscription_id );
				$user_can_cancel  = current_user_can( 'cancel_order', $subscription_id );
				$order_can_cancel = $subscription->has_status( SUBRE_SUBSCRIPTION_ORDER::get_subscription_statuses_for_cancel( $subscription ) );

				if ( $user_can_cancel && $order_can_cancel && $subscription->get_id() === $subscription_id && hash_equals( $subscription->get_order_key(), $order_key ) ) {
					$next_payment = $subscription->get_meta( '_subre_subscription_next_payment', true );
					/*If active, schedule to cancel at the end of current billing cycle*/
					if ( $subscription->has_status( 'subre_active' ) && $next_payment > time() ) {
						SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'a_cancel', esc_html__( 'Subscription cancelled by customer', 'subre-product-subscription-for-woo' ) );
						wc_add_notice( apply_filters( 'subre_subscription_awaiting_cancel_notice', esc_html__( 'Your subscription is scheduled to cancel at the end of current billing cycle.', 'subre-product-subscription-for-woo' ) ), apply_filters( 'subre_subscription_awaiting_cancel_notice_type', 'notice' ) );
						do_action( 'subre_awaiting_cancel_subscription', $subscription_id );
					} else {
						self::cancel_a_subscription( $subscription_id );
						wc_add_notice( apply_filters( 'subre_subscription_cancelled_notice', esc_html__( 'Your subscription was cancelled.', 'subre-product-subscription-for-woo' ) ), apply_filters( 'subre_subscription_cancelled_notice_type', 'notice' ) );
					}
				} elseif ( $user_can_cancel && ! $order_can_cancel ) {
					wc_add_notice( esc_html__( 'Your subscription can no longer be cancelled. Please contact us if you need assistance.', 'subre-product-subscription-for-woo' ), 'error' );
				} else {
					wc_add_notice( esc_html__( 'Invalid subscription.', 'subre-product-subscription-for-woo' ), 'error' );
				}
			}
			wp_safe_redirect( esc_url_raw( remove_query_arg( array(
				'cancel_subscription',
				'order',
				'subscription_id',
				'_wpnonce'
			) ) ) );
			exit();
		}
	}

	/**
	 * User renews a subscription
	 */
	public static function renew_subscription_action() {
		if ( isset( $_GET['renew_subscription'], $_GET['order'], $_GET['subscription_id'], $_GET['_wpnonce'] ) &&
		     wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'subre_renew_subscription' ) ) {

			wc_nocache_headers();

			$order_key       = sanitize_text_field( wp_unslash( $_GET['order'] ) );
			$subscription_id = absint( $_GET['subscription_id'] );
			if ( ! SUBRE_SUBSCRIPTION_ORDER::is_a_subscription( $subscription_id ) ) {
				wc_add_notice( esc_html__( 'Invalid subscription.', 'subre-product-subscription-for-woo' ), 'error' );
			} else {
				$subscription           = wc_get_order( $subscription_id );
				$user_can_cancel        = current_user_can( 'cancel_order', $subscription_id );
				$subscription_can_renew = $subscription->has_status( SUBRE_SUBSCRIPTION_ORDER::get_subscription_statuses_for_renew( $subscription ) );

				if ( $user_can_cancel && $subscription_can_renew && $subscription->get_id() === $subscription_id && hash_equals( $subscription->get_order_key(), $order_key ) ) {
					$current_renewal = $subscription->get_meta( '_subre_subscription_current_renewal_order', true );

					if ( $current_renewal ) {
						$current_renewal_order = wc_get_order( $current_renewal );
						if ( $current_renewal_order ) {
							if ( ! $current_renewal_order->is_paid() && $current_renewal_order->needs_payment() ) {
								wp_safe_redirect( $current_renewal_order->get_checkout_payment_url() );
								exit;
							}
						}
					}
					$renewal_id = SUBRE_SUBSCRIPTION_ORDER::create_renewal_order( $subscription );
					if ( $renewal_id && ! is_wp_error( $renewal_id ) ) {
						$renewal_order = wc_get_order( $renewal_id );
						update_post_meta( $subscription_id, '_subre_subscription_current_renewal_order', $renewal_id );
						wp_safe_redirect( $renewal_order->get_checkout_payment_url() );
						exit;
					} else {
						wc_add_notice( $renewal_id->get_error_message(), 'error' );
					}
				} elseif ( $user_can_cancel && ! $subscription_can_renew ) {
					wc_add_notice( esc_html__( 'Your subscription can no longer be renewed. Please contact us if you need assistance.', 'subre-product-subscription-for-woo' ), 'error' );
				} else {
					wc_add_notice( esc_html__( 'Invalid subscription.', 'subre-product-subscription-for-woo' ), 'error' );
				}
			}
			wp_safe_redirect( esc_url_raw( remove_query_arg( [ 'renew_subscription', 'order', 'subscription_id', '_wpnonce' ] ) ) );
			exit();
		}
	}

	/**
	 * @param $order_id
	 * @param $subscription_id
	 */
	public static function update_next_payment_after_successfully_renewing( $order_id, $subscription_id ) {
		/*Reset current renewal order*/
		update_post_meta( $subscription_id, '_subre_subscription_current_renewal_order', '' );
		/*Change status to active if needed*/
		$subscription      = wc_get_order( $subscription_id );
		$subscription_note = sprintf('%1s %2s %3s',
			esc_html__( 'Renewal order', 'subre-product-subscription-for-woo' ),
			subre_get_order_subscription_edit_link( $order_id ),
			esc_html__( 'paid', 'subre-product-subscription-for-woo' )
		);
		if ( ! SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, SUBRE_SUBSCRIPTION_ORDER::get_subscription_status_to_save( 'active' ), $subscription_note ) ) {
			$subscription->add_order_note( $subscription_note );
		}
		/*Update next payment meta*/
		$next_payment             = $subscription->get_meta( '_subre_subscription_next_payment', true );
		$subscription_period      = $subscription->get_meta( '_subre_subscription_period', true );
		$subscription_period_unit = $subscription->get_meta( '_subre_subscription_period_unit', true );
		$sub_expire               = $subscription->get_meta( '_subre_subscription_expire', true );
		$subscription_cycle       = $subscription_period * SUBRE_SUBSCRIPTION_PRODUCT_HELPER::get_unit_in_seconds( $subscription_period_unit );
		$current_due              = $next_payment;
		$next_payment             += $subscription_cycle;
		$last_renewal             = false;
		if ( $sub_expire ) {
			if ( $next_payment >= $sub_expire ) {
				$last_renewal = true;
			}
		}
		if ( ! $last_renewal ) {
			/*Only update next payment if it's not the last renewal*/
			update_post_meta( $subscription_id, '_subre_subscription_next_payment', $next_payment );
		} else {
			update_post_meta( $subscription_id, '_subre_subscription_last_payment', $current_due );
			update_post_meta( $subscription_id, '_subre_subscription_next_payment', '' );
		}
		do_action( 'subre_subscription_renewed_successfully', $order_id, $subscription );
	}

	/**
	 * @param $subscription_id
	 */
	private static function cancel_a_subscription( $subscription_id ) {
		SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'cancelled', esc_html__( 'Subscription cancelled by customer', 'subre-product-subscription-for-woo' ) );
		do_action( 'subre_subscription_cancelled', $subscription_id );
	}
}

new SUBRE_SUBSCRIPTION_ACTIONS();