<?php
/**
 * Class SUBRE_Email_Subscription_Expired file
 *
 * @package WooCommerce\Emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SUBRE_Email_Subscription_Expired', false ) ) {


	class SUBRE_Email_Subscription_Expired extends WC_Email {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'subre_subscription_expired';
			$this->title          = esc_html__( 'Subscription Expired', 'subre-product-subscription-for-woo' );
			$this->description    = esc_html__( 'Subscription Expired emails are sent to chosen recipient(s) when a subscription expired.', 'subre-product-subscription-for-woo' );
			$this->template_base  = SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_TEMPLATES;
			$this->template_html  = 'emails/subscription-expired.php';
			$this->template_plain = 'emails/plain/subscription-expired.php';
			$this->placeholders   = array(
				'{order_date}'   => '',
				'{order_number}' => '',
			);

			// Triggers for this email.
			add_action( 'subre_subscription_status_changed_to_expired', array( $this, 'trigger' ) );

			// Call parent constructor.
			parent::__construct();

			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param int $order_id The order ID.
		 * @param WC_Order|false $order Order object.
		 */
		public function trigger( $order_id, $order = false ) {
			$this->setup_locale();

			if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $order_id );
			}
			if ( is_a( $order, 'WC_Order' ) ) {
				$this->object                         = $order;
				$this->recipient                      = $this->object->get_billing_email();
				$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
				$this->placeholders['{order_number}'] = $this->object->get_order_number();
			}

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Get email subject.
		 *
		 * @return string
		 * @since 1.0.0
		 */
		public function get_default_subject() {
			return esc_html__( '[{site_title}]: Subscription #{order_number} has expired', 'subre-product-subscription-for-woo' );
		}

		/**
		 * Get email heading.
		 *
		 * @return string
		 * @since 1.0.0
		 */
		public function get_default_heading() {
			return esc_html__( 'Subscription Expired: #{order_number}', 'subre-product-subscription-for-woo' );
		}

		/**
		 * Get content html.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'              => $this->object,
					'subscription'       => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				), '', $this->template_base
			);
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'              => $this->object,
					'subscription'       => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				), '', $this->template_base
			);
		}

		/**
		 * Default content to show below main email content.
		 *
		 * @return string
		 * @since 1.0.0
		 */
		public function get_default_additional_content() {
			return esc_html__( 'Thanks for reading.', 'subre-product-subscription-for-woo' );
		}
	}

}

return new SUBRE_Email_Subscription_Expired();
