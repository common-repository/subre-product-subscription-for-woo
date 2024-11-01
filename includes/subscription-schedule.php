<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_SUBSCRIPTION_SCHEDULE {
	private static $settings;

	public function __construct() {
		self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
		add_action( 'init', array( $this, 'schedule_actions' ) );
		add_action( 'subre_schedule_subscription_renewals_and_expiration', array( $this, 'schedule_subscriptions' ) );
		add_action( 'subre_overdue_subscriptions_check', array( $this, 'check_overdue_payments' ) );
		add_action( 'subre_process_renewal_order', array( $this, 'process_renewal_order' ) );
		add_action( 'subre_expire_a_subscription', array( $this, 'process_expired_subscription' ) );
	}

	public function check_overdue_payments() {
		$now = time();
		/*Past due/expired subscriptions but status is still active/trial*/
		$args      = array(
			'post_type'      => 'subre_subscription',
			'post_status'    => array(
				'wc-subre_active',
				'wc-subre_trial',
			),
			'meta_query'     => array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'relation' => 'AND',
						array(
							'key'     => '_subre_subscription_next_payment',
							'value'   => $now + 10 * MINUTE_IN_SECONDS,
							'compare' => '<=',
						),
						array(
							'key'     => '_subre_subscription_next_payment',
							'value'   => '',
							'compare' => '!=',
						),
					),
					array(
						'relation' => 'AND',
						array(
							'key'     => '_subre_subscription_expire',
							'value'   => $now,
							'compare' => '<=',
						),
						array(
							'key'     => '_subre_subscription_expire',
							'value'   => '',
							'compare' => '!=',
						),
					),
				),
			),
			'posts_per_page' => - 1,
			'fields'         => 'ids',
		);
		$the_query = new WP_Query( $args );
		self::log( 'Unhandled past due/expired subscriptions: ' . var_export( $the_query->posts, true ) );
		if ( $the_query->posts ) {
			foreach ( $the_query->posts as $subscription_id ) {
				$sub_expire = get_post_meta( $subscription_id, '_subre_subscription_expire', true );
				if ( $sub_expire < $now ) {
					SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'expired' );
				} else {
					SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'on-hold' );
				}
			}
		}
		/*Cancel subscriptions if on-hold more than xx day*/
		if ( 'cancel' === self::$settings->get_params( 'past_due_status' ) ) {
			$past_due_by = absint( self::$settings->get_params( 'past_due_by' ) );
			$args        = array(
				'post_type'      => 'subre_subscription',
				'post_status'    => 'wc-on-hold',
				'meta_query'     => array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => '_subre_subscription_next_payment',
						'value'   => $now - $past_due_by * DAY_IN_SECONDS,
						'compare' => '<=',
					),
					array(
						'key'     => '_subre_subscription_next_payment',
						'value'   => '',
						'compare' => '!=',
					),
				),
				'posts_per_page' => - 1,
				'fields'         => 'ids',
			);

			$the_query = new WP_Query( $args );
			self::log( 'On-hold more than ' . $past_due_by . ' day: ' . var_export( $the_query->posts, true ) );

			if ( $the_query->posts ) {
				foreach ( $the_query->posts as $subscription_id ) {
					/* translators: %s: number of days overdue */
					SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'cancelled', sprintf( _n( 'The subscription is past due by %s day', 'The subscription is past due by %s days', $past_due_by, 'subre-product-subscription-for-woo' ), $past_due_by ) );
				}
			}
		}
		$args      = array(
			'post_type'      => 'subre_subscription',
			'post_status'    => 'wc-subre_a_cancel',
			'meta_query'     => array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => '_subre_subscription_next_payment',
					'value'   => $now,
					'compare' => '<=',
				),
			),
			'posts_per_page' => - 1,
			'fields'         => 'ids',
		);
		$the_query = new WP_Query( $args );
		self::log( 'Awaiting cancel: ' . var_export( $the_query->posts, true ) );
		if ( $the_query->posts ) {
			foreach ( $the_query->posts as $subscription_id ) {
				SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'cancelled', esc_html__( 'The subscription was scheduled for cancel', 'subre-product-subscription-for-woo' ) );
			}
		}
	}

	/**
	 * @param $subscription_id
	 */
	public function process_expired_subscription( $subscription_id ) {
		if ( ! SUBRE_SUBSCRIPTION_ORDER::is_a_subscription( $subscription_id ) ) {
			return;
		}
		$subscription = wc_get_order( $subscription_id );
		if ( ! $subscription ) {
			return;
		}
		self::unschedule( $subscription_id, 'renew' );
		if ( SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'expired' ) ) {
			do_action( 'subre_subscription_is_expired', $subscription_id );
		}
	}

	/**
	 * @param $subscription_id
	 *
	 * @throws WC_Data_Exception
	 */
	public function process_renewal_order( $subscription_id ) {
		if ( ! SUBRE_SUBSCRIPTION_ORDER::is_a_subscription( $subscription_id ) ) {
			return;
		}
		$subscription = wc_get_order( $subscription_id );
		if ( $subscription ) {
			$current_renewal = get_post_meta( $subscription_id, '_subre_subscription_current_renewal_order', true );
			if ( $current_renewal && wc_get_order( $current_renewal ) ) {
				$renewal_id = $current_renewal;
				do_action( 'subre_renewal_order_payment_due', $renewal_id, $subscription );
			} else {
				$renewal_id = SUBRE_SUBSCRIPTION_ORDER::create_renewal_order( $subscription );
				if ( $renewal_id && ! is_wp_error( $renewal_id ) ) {
					do_action( 'subre_renewal_order_payment_due', $renewal_id, $subscription );
				} else {
					do_action( 'subre_renewal_order_failed_to_create', $renewal_id, $subscription );
				}
			}

			$renewal_order = wc_get_order( $renewal_id );

			if ( $renewal_order ) {
				if ( ! $renewal_order->is_paid() ) {
					if ( $renewal_order->needs_payment() ) {
						/*Send invoice if payment is needed so that customer can manually pay for renewal*/
						WC()->payment_gateways();
						WC()->shipping();
						WC()->mailer()->customer_invoice( $renewal_order );
					}
					$next_payment = $subscription->get_meta( '_subre_subscription_next_payment', true );
					if ( $next_payment <= time() ) {
						SUBRE_SUBSCRIPTION_ORDER::update_subscription_status( $subscription_id, 'on-hold',
							sprintf('%1s %2s %3s',
								esc_html__( 'Payment past due, subscription renewal order', 'subre-product-subscription-for-woo' ),
								subre_get_order_subscription_edit_link( $renewal_id ),
								esc_html__( 'unpaid', 'subre-product-subscription-for-woo' ) ) );
					}
				} else {
					SUBRE_SUBSCRIPTION_ORDER::regenerate_download_permissions( $subscription_id );
				}
			}
			/*Reset scheduled time if the event is called 1 to 23 hours earlier for some reason*/
			$scheduled_time = get_post_meta( $subscription_id, '_subre_subscription_scheduled_time', true );
			if ( $scheduled_time && time() - $scheduled_time <= 23 * HOUR_IN_SECONDS ) {
				update_post_meta( $subscription_id, '_subre_subscription_scheduled_time', 0 );
			}
		}
	}

	/**
	 * Every hour, check for subscriptions that will due in the next 24 hours then schedule a renewal for each subscription
	 */
	public function schedule_subscriptions() {
		$to        = strtotime( '+1 day' );
		$now       = time();
		$args      = array(
			'post_type'      => 'subre_subscription',
			'post_status'    => array(
				'wc-subre_active',
				'wc-subre_trial',
			),
			'meta_query'     => array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_subre_subscription_expire',
						'value'   => '',
						'compare' => '=',
					),
					array(
						'key'     => '_subre_subscription_expire',
						'value'   => '',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_subre_subscription_expire',
						'value'   => strtotime( gmdate( 'Y-m-d', $to ) ),
						'compare' => '>',
					),
				),
				array(
					'key'     => '_subre_subscription_next_payment',
					'value'   => $to,
					'compare' => '<',
				),
				array(
					'key'     => '_subre_subscription_next_payment',
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_subre_subscription_scheduled_time',
						'value'   => $now - DAY_IN_SECONDS,
						'compare' => '<',
					),
					array(
						'key'     => '_subre_subscription_scheduled_time',
						'value'   => '',
						'compare' => 'NOT EXISTS',
					),
				),
			),
			'posts_per_page' => - 1,
			'fields'         => 'ids',
		);
		$the_query = new WP_Query( $args );
		$renew     = $expire = array();
		if ( $the_query->found_posts > 0 ) {
			foreach ( $the_query->posts as $subscription_id ) {
				if ( get_post_meta( $subscription_id, '_subre_subscription_next_payment', true ) ) {
					$renew[] = $subscription_id;
					$action  = 'renew';
				} else {
					$action   = 'expire';
					$expire[] = $subscription_id;
				}
				self::schedule( $subscription_id, $action );
			}
		}
		self::log( 'Renew: ' . var_export( $renew, true ) );
		$args      = array(
			'post_type'      => 'subre_subscription',
			'post_status'    => array(
				'wc-subre_active',
			),
			'meta_query'     => array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => '_subre_subscription_expire',
					'value'   => $to,
					'compare' => '<',
				),
				array(
					'key'     => '_subre_subscription_expire',
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_subre_subscription_scheduled_time',
						'value'   => $now - DAY_IN_SECONDS,
						'compare' => '<',
					),
					array(
						'key'     => '_subre_subscription_scheduled_time',
						'value'   => '',
						'compare' => 'NOT EXISTS',
					),
				),
			),
			'posts_per_page' => - 1,
			'fields'         => 'ids',
		);
		$the_query = new WP_Query( $args );
		self::log( 'Expire: ' . var_export( array_merge( $expire, $the_query->posts ), true ) );
		if ( $the_query->found_posts > 0 ) {
			foreach ( $the_query->posts as $subscription_id ) {
				self::schedule( $subscription_id, 'expire' );
			}
		}
	}

	/**
	 * Unschedule subscription actions
	 *
	 * @param $subscription_id
	 * @param $action string 'renew'|'expire'|'cancel'
	 */
	public static function unschedule( $subscription_id, $action ) {
		$hook = $meta = '';
		switch ( $action ) {
			case 'renew':
				$hook = 'subre_process_renewal_order';
				break;
			case 'expire':
				$hook = 'subre_expire_a_subscription';
				break;
			case 'cancel':
				$hook = 'subre_cancel_a_subscription';
				break;
			default:
		}
		if ( $hook ) {
			as_unschedule_all_actions( $hook, array( 'subscription_id' => $subscription_id ) );
			update_post_meta( $subscription_id, '_subre_subscription_scheduled_time', 0 );
		}
	}

	/**
	 * Schedule subscription actions
	 *
	 * @param $subscription_id
	 * @param $action string 'renew'|'expire'|'cancel'
	 */
	public static function schedule( $subscription_id, $action ) {
		$hook = $meta = '';
		switch ( $action ) {
			case 'renew':
				$hook = 'subre_process_renewal_order';
				$meta = '_subre_subscription_next_payment';
				break;
			case 'expire':
				$hook = 'subre_expire_a_subscription';
				$meta = '_subre_subscription_expire';
				break;
			case 'cancel':
				$hook = 'subre_cancel_a_subscription';
				$meta = '_subre_subscription_next_payment';
				break;
			default:
		}
		if ( $hook ) {
			if ( ! as_next_scheduled_action( $hook, array( 'subscription_id' => $subscription_id ) ) ) {
				$now       = time();
				$timestamp = get_post_meta( $subscription_id, $meta, true );
				if ( $timestamp > $now ) {
					as_schedule_single_action( $timestamp, $hook, array( 'subscription_id' => $subscription_id ) );
					update_post_meta( $subscription_id, '_subre_subscription_scheduled_time', $now );
					WC()->mailer();//Must initialize WC email so that custom subscription emails can be triggered
					if ( $action === 'renew' ) {
						if ( SUBRE_SUBSCRIPTION_ORDER::is_automatic_payment_supported( SUBRE_SUBSCRIPTION_ORDER::get_subscription_payment_method( $subscription_id )['id'] ) ) {
							do_action( "subre_automatic_subscription_{$action}_scheduled", $subscription_id );
						} else {
							do_action( "subre_manual_subscription_{$action}_scheduled", $subscription_id );
						}
					}
					do_action( "subre_subscription_{$action}_scheduled", $subscription_id );
				}
			}
		}
	}

	/**
	 * Schedule recurring events
	 */
	public function schedule_actions() {
		if ( false === as_next_scheduled_action( 'subre_schedule_subscription_renewals_and_expiration' ) ) {
			as_unschedule_all_actions( 'subre_schedule_subscription_renewals_and_expiration' );
			as_schedule_recurring_action( time(), HOUR_IN_SECONDS, 'subre_schedule_subscription_renewals_and_expiration' );
		}
		if ( false === as_next_scheduled_action( 'subre_overdue_subscriptions_check' ) ) {
			as_unschedule_all_actions( 'subre_overdue_subscriptions_check' );
			as_schedule_recurring_action( time() + 10 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS, 'subre_overdue_subscriptions_check' );
		}
	}

	/**
	 * @param $content
	 * @param string $level
	 */
	private static function log( $content, $level = 'info' ) {
		SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Log::log( $content, 'schedule', $level );
	}
}

new SUBRE_SUBSCRIPTION_SCHEDULE();