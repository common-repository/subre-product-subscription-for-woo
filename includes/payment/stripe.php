<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Stripe Gateway by WooCommerce
 *
 * Class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Payment_Stripe
 */
class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Payment_Stripe {
	protected $retry_interval = 1;

	public function __construct() {
		add_action( 'subre_renewal_order_payment_due', array( $this, 'handle_payment' ), 10, 2 );

		add_action( 'subre_renewal_order_payment_stripe_failed', array( $this, 'process_failed_payment' ), 10, 2 );
		add_action( 'subre_subscription_parent_order_payment_complete', array( $this, 'copy_payment_data_to_subscription' ), 10, 2 );
		add_action( 'subre_new_renewal_order_created', array( $this, 'copy_payment_data' ), 10, 3 );
	}

	/**
	 * @param $order_id
	 * @param $subscription WC_Order
	 */
	public function copy_payment_data_to_subscription( $order_id, $subscription ) {
		$order = wc_get_order( $order_id );
		if ( self::is_payment_method_supported( $order->get_payment_method( 'edit' ), false ) && $order->get_meta( '_stripe_source_id', true ) ) {
			update_post_meta( $subscription->get_id(), '_stripe_source_id', $order->get_meta( '_stripe_source_id', true ) );
			update_post_meta( $subscription->get_id(), '_stripe_customer_id', $order->get_meta( '_stripe_customer_id', true ) );
		}
	}

	/**
	 * @param $subscription_or_renewal_order WC_Order
	 * @param $subscription WC_Order
	 * @param $payment_order WC_Order
	 */
	public function copy_payment_data( $subscription_or_renewal_order, $subscription, $payment_order ) {
		if ( self::is_payment_method_supported( $subscription_or_renewal_order->get_payment_method( 'edit' ), false ) && $payment_order->get_meta( '_stripe_source_id', true ) ) {
			update_post_meta( $subscription_or_renewal_order->get_id(), '_stripe_source_id', $payment_order->get_meta( '_stripe_source_id', true ) );
			update_post_meta( $subscription_or_renewal_order->get_id(), '_stripe_customer_id', $payment_order->get_meta( '_stripe_customer_id', true ) );
		}
	}

	/**
	 * @param $order WC_Order
	 * @param $exception WC_Stripe_Exception
	 */
	public function process_failed_payment( $order, $exception ) {
		$order_id = $order->get_id();
		SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Log::log( sprintf('%1s%2s %3s%4s',
			esc_html__( 'Payment failed for renewal order #', 'subre-product-subscription-for-woo' ),
			$order_id,
			esc_html__( 'of subscription #', 'subre-product-subscription-for-woo' ),
			get_post_parent( $order_id )->ID ) );
	}

	/**
	 * @param $response
	 * @param $order WC_Order
	 */
	public function process_subscription_after_payment( $response, $order ) {
		$order_id         = $order->get_id();
		$subscription_obj = get_post_parent( $order_id );
		if ( ! $subscription_obj ) {
			return;
		}
		$subscription = wc_get_order( $subscription_obj->ID );
		if ( ! $subscription ) {
			return;
		}
		if ( ! $order->is_paid() ) {
			return;
		}
		SUBRE_SUBSCRIPTION_ACTIONS::update_next_payment_after_successfully_renewing( $order_id, $subscription_obj->ID );
	}

	/**
	 * Handle scheduled subscription renewal payment
	 *
	 * @param $order_id
	 * @param $subscription
	 */
	public function handle_payment( $order_id, $subscription ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( ! $order->needs_payment() ) {
			return;
		}
		$payment_method = $order->get_payment_method( 'edit' );
		if ( ! self::is_payment_method_supported( $payment_method ) ) {
			return;
		}
		if ( 'stripe_sepa' === $payment_method && ! $order->get_meta( '_stripe_source_id' ) ) {
			return;
		}
		$parent_order_obj = get_post_parent( $subscription->get_id() );
		if ( ! $parent_order_obj ) {
			return;
		}
		$this->process_subscription_payment( $order );
	}

	private static function is_payment_method_supported( $payment_method, $check_active = true ) {
		$support = false;
		if ( in_array( $payment_method, self::get_supported_payment_methods(), true ) ) {
			$support = true;
			if ( $check_active ) {
				if ( ! isset( WC()->payment_gateways()->get_available_payment_gateways()[ $payment_method ] ) ) {
					$support = false;
				}
			}
		}

		return $support;
	}

	public static function get_supported_payment_methods() {
		return array( 'stripe', 'stripe_sepa' );
	}

	/**
	 * @param $renewal_order WC_Order
	 * @param bool $retry
	 * @param bool $previous_error
	 *
	 * @throws WC_Data_Exception
	 */
	public function process_subscription_payment( $renewal_order, $retry = true, $previous_error = false ) {
		if ( class_exists( 'WC_Stripe_Order_Handler' ) && is_callable( array( 'WC_Stripe_Order_Handler', 'get_instance' ) ) ) {
			$stripe_order_handler = WC_Stripe_Order_Handler::get_instance();
			try {
				$order_id = $renewal_order->get_id();
				$amount   = $renewal_order->get_total();
				// Check for an existing intent, which is associated with the order.
				if ( $stripe_order_handler->has_authentication_already_failed( $renewal_order ) ) {
					return;
				}

				// Get source from order
				$prepared_source = $stripe_order_handler->prepare_order_source( $renewal_order );
				$source_object   = $prepared_source->source_object;

				if ( ! $prepared_source->customer ) {
					throw new WC_Stripe_Exception(
						'Failed to process renewal for order ' . $renewal_order->get_id() . '. Stripe customer id is missing in the order',
						esc_html__( 'Customer not found', 'subre-product-subscription-for-woo' )
					);
				}

				WC_Stripe_Logger::log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );

				/*
				 * If we're doing a retry and source is chargeable, we need to pass
				 * a different idempotency key and retry for success.
				 */
				if ( is_object( $source_object ) && empty( $source_object->error ) && $stripe_order_handler->need_update_idempotency_key( $source_object, $previous_error ) ) {
					add_filter( 'wc_stripe_idempotency_key', [ $stripe_order_handler, 'change_idempotency_key' ], 10, 2 );
				}

				if ( ( $stripe_order_handler->is_no_such_source_error( $previous_error ) || $stripe_order_handler->is_no_linked_source_error( $previous_error ) ) && apply_filters( 'wc_stripe_use_default_customer_source', true ) ) {
					// Passing empty source will charge customer default.
					$prepared_source->source = '';
				}

				// If the payment gateway is SEPA, use the charges API.
				// TODO: Remove when SEPA is migrated to payment intents.
				if ( 'stripe_sepa' === $renewal_order->get_payment_method( 'edit' ) ) {
					$request            = $stripe_order_handler->generate_payment_request( $renewal_order, $prepared_source );
					$request['capture'] = 'true';
					$request['amount']  = WC_Stripe_Helper::get_stripe_amount( $amount, $request['currency'] );
					$response           = WC_Stripe_API::request( $request );

					$is_authentication_required = false;
				} else {
					$stripe_order_handler->lock_order_payment( $renewal_order );
					$response                   = $stripe_order_handler->create_and_confirm_intent_for_off_session( $renewal_order, $prepared_source, $amount );
					$is_authentication_required = $stripe_order_handler->is_authentication_required_for_payment( $response );
				}

				// It's only a failed payment if it's an error and it's not of the type 'authentication_required'.
				// If it's 'authentication_required', then we should email the user and ask them to authenticate.
				if ( ! empty( $response->error ) && ! $is_authentication_required ) {
					// We want to retry.
					if ( $stripe_order_handler->is_retryable_error( $response->error ) ) {
						if ( $retry ) {
							// Don't do anymore retries after this.
							if ( 5 <= $this->retry_interval ) {
								$this->process_subscription_payment( $renewal_order, false, $response->error );
							}

							sleep( $this->retry_interval );

							$this->retry_interval ++;

							$this->process_subscription_payment( $renewal_order, true, $response->error );
						} else {
							$localized_message = esc_html__( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'subre-product-subscription-for-woo' );
							$renewal_order->add_order_note( $localized_message );
							throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
						}
					}

					$localized_messages = WC_Stripe_Helper::get_localized_messages();

					if ( 'card_error' === $response->error->type ) {
						$localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
					} else {
						$localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
					}

					$renewal_order->add_order_note( $localized_message );

					throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message );
				}

				// Either the charge was successfully captured, or it requires further authentication.
				if ( $is_authentication_required ) {
					do_action( 'wc_gateway_stripe_process_payment_authentication_required', $renewal_order, $response );

					$error_message = esc_html__( 'This transaction requires authentication.', 'subre-product-subscription-for-woo' );
					$renewal_order->add_order_note( $error_message );

					$charge   = end( $response->error->payment_intent->charges->data );
					$id       = $charge->id;
					$order_id = $renewal_order->get_id();

					$renewal_order->set_transaction_id( $id );
					/* translators: %s is the charge Id */
					$renewal_order->update_status( 'failed', sprintf( esc_html__( 'Stripe charge awaiting authentication by user: %s.', 'subre-product-subscription-for-woo' ), $id ) );
					if ( is_callable( [ $renewal_order, 'save' ] ) ) {
						$renewal_order->save();
					}
				} else {
					// The charge was successfully captured
					do_action( 'wc_gateway_stripe_process_payment', $response, $renewal_order );

					// Use the last charge within the intent or the full response body in case of SEPA.
					$stripe_order_handler->process_response( isset( $response->charges ) ? end( $response->charges->data ) : $response, $renewal_order );
					do_action( 'subre_renewal_order_payment_stripe_processed', $response, $renewal_order );
				}

				// TODO: Remove when SEPA is migrated to payment intents.
				if ( 'stripe_sepa' !== $renewal_order->get_payment_method( 'edit' ) ) {
					$stripe_order_handler->unlock_order_payment( $renewal_order );
				}
			} catch ( WC_Stripe_Exception $e ) {
				do_action( 'subre_renewal_order_payment_stripe_failed', $renewal_order, $e );
				WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

				do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );

				/* translators: error message */
				$renewal_order->update_status( 'failed' );
			}
		}
	}
}