<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_SUBSCRIPTION_EMAIL {
	private static $settings;

	public function __construct() {
		self::$settings = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance();
		add_filter( 'woocommerce_email_classes', array( $this, 'add_emails' ) );
		add_filter( 'woocommerce_template_directory', array( $this, 'woocommerce_template_directory' ), 10, 2 );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'subscription_renewal_info' ), 10, 4 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'subscriptions_created_from_order' ), 10, 4 );
	}

	/**
	 * Let WooCommerce know the correct email templates to override
	 *
	 * @param $template_directory
	 * @param $template
	 *
	 * @return string
	 */
	public function woocommerce_template_directory( $template_directory, $template ) {
		$subre_emails = array_merge( glob( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_TEMPLATES . 'emails/*.php' ), glob( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_TEMPLATES . 'emails/plain/*.php' ) );
		if ( $subre_emails ) {
			foreach ( $subre_emails as $subre_email ) {
				if ( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_TEMPLATES . $template === $subre_email ) {
					$template_directory = 'subre-product-subscription-for-woo';
					break;
				}
			}
		}

		return $template_directory;
	}

	/**
	 * @param $order WC_Order
	 * @param $sent_to_admin
	 * @param $plain_text
	 * @param $email WC_Email
	 *
	 * @throws Exception
	 */
	public function subscription_renewal_info( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! $order ) {
			return;
		}
		if ( ! SUBRE_SUBSCRIPTION_ORDER::is_a_renewal_order( $order->get_id() ) ) {
			return;
		}
		wc_get_template(
			'emails/subscription-renewal-info.php',
			array(
				'order'           => $order,
				'email'           => $email,
				'subscription_id' => get_post_parent( $order->get_id() )->ID,
				'sent_to_admin'   => $sent_to_admin,
				'plain_text'      => $plain_text,
			),
			'subre-product-subscription-for-woo' . DIRECTORY_SEPARATOR,
			SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_TEMPLATES
		);
	}

	/**
	 * @param $order WC_Order
	 * @param $sent_to_admin
	 * @param $plain_text
	 * @param $email WC_Email
	 *
	 * @throws Exception
	 */
	public function subscriptions_created_from_order( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ! $order || $plain_text ) {
			return;
		}
		if ( $email && is_a( $email, 'WC_Email' ) && in_array( $email->id, [ 'customer_processing_order', 'new_order' ], true ) ) {
			$args             = array(
				'post_type'      => 'subre_subscription',
				'post_status'    => 'any',
				'posts_per_page' => count( $order->get_items() ),
				'post_parent'    => $order->get_id(),
				'fields'         => 'ids',
			);
			$the_query        = new WP_Query( $args );
			$subscription_ids = $the_query->posts;
			if ( $subscription_ids ) {
				wc_get_template(
					'emails/subscriptions-list.php',
					array(
						'order'            => $order,
						'email'            => $email,
						'subscription_ids' => $subscription_ids,
						'sent_to_admin'    => $sent_to_admin,
					),
					'subre-product-subscription-for-woo' . DIRECTORY_SEPARATOR,
					SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_TEMPLATES
				);
			}
		}
	}

	/**
	 * Register subscription emails
	 *
	 * @param $emails
	 *
	 * @return mixed
	 */
	public function add_emails( $emails ) {
		$emails['SUBRE_Email_Auto_Renewal_Reminder']           = include SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_INCLUDES . '/emails/class-wc-email-auto-renewal-reminder.php';
		$emails['SUBRE_Email_Manual_Renewal_Reminder']         = include SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_INCLUDES . '/emails/class-wc-email-manual-renewal-reminder.php';
		$emails['SUBRE_Email_Subscription_Cancelled']          = include SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_INCLUDES . '/emails/class-wc-email-subscription-cancelled.php';
		$emails['SUBRE_Email_Customer_Subscription_Cancelled'] = include SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_INCLUDES . '/emails/class-wc-email-customer-subscription-cancelled.php';
		$emails['SUBRE_Email_Subscription_Expired']            = include SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_INCLUDES . '/emails/class-wc-email-subscription-expired.php';
		$emails['SUBRE_Email_Customer_Subscription_Expired']   = include SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_INCLUDES . '/emails/class-wc-email-customer-subscription-expired.php';

		return $emails;
	}
}

new SUBRE_SUBSCRIPTION_EMAIL();