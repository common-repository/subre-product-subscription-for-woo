<?php
/**
 * Subscriptions table in WooCommerce new order and processing order emails
 *
 * This template can be overridden by copying it to yourtheme/subre-product-subscription-for-woo/emails/subscriptions-list.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @version 1.0.0
 */

$text_align = is_rtl() ? 'right' : 'left';
?>
<h2><?php
	if ( $sent_to_admin ) {
		printf( esc_html( _n( 'New subscription', 'New subscriptions', count( $subscription_ids ), 'subre-product-subscription-for-woo' ) ) );
	} else {
		printf( esc_html( _n( 'Your subscription', 'Your subscriptions', count( $subscription_ids ), 'subre-product-subscription-for-woo' ) ) );
	}
	?></h2>
<div style="margin-bottom: 40px;">
    <table class="td" cellspacing="0" cellpadding="6"
           style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"
           border="1">
        <thead>
        <tr>
            <th class="td" scope="col"
                style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Subscription', 'subre-product-subscription-for-woo' ); ?></th>
            <th class="td" scope="col"
                style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Recurring', 'subre-product-subscription-for-woo' ); ?></th>
            <th class="td" scope="col"
                style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Next Payment', 'subre-product-subscription-for-woo' ); ?></th>
            <th class="td" scope="col"
                style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Expiry date', 'subre-product-subscription-for-woo' ); ?></th>
        </tr>
        </thead>
        <tbody>
		<?php
		foreach ( $subscription_ids as $subscription_id ) {
			$subscription = wc_get_order( $subscription_id );
			?>
            <tr>
                <td class="td"
                    style="text-align:<?php echo esc_attr( $text_align ); ?>;">
					<?php echo wp_kses_post( $sent_to_admin ? subre_get_order_subscription_edit_link( $subscription_id ) : subre_get_subscription_view_link( $subscription_id ) ); ?></td>
                <td class="td"
                    style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php echo wp_kses_post( SUBRE_SUBSCRIPTION_ORDER::get_formatted_subscription_recurring_amount( $subscription ) ); ?></td>
                <td class="td"
                    style="text-align:<?php echo esc_attr( $text_align ); ?>;">
					<?php
					$next_payment = get_post_meta( $subscription_id, '_subre_subscription_next_payment', true );
					if ( $next_payment ) {
						SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $next_payment, false );
					}
					?>
                </td>
                <td class="td"
                    style="text-align:<?php echo esc_attr( $text_align ); ?>;">
					<?php
					$sub_expire = get_post_meta( $subscription_id, '_subre_subscription_expire', true );
					if ( $sub_expire ) {
						SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::render_date( $sub_expire, false );
					} else {
						echo '-';
					}
					?>
                </td>
            </tr>
			<?php
		}
		?>
        </tbody>
    </table>
    <p>
		<?php
		if ( ! $sent_to_admin ) {
			if ( SUBRE_SUBSCRIPTION_ORDER::is_automatic_payment_supported( $order->get_payment_method( 'edit' ) ) ) {
				/* translators: %s: subscription text */
				printf( esc_html__( 'Your %s will be renewed automatically.', 'subre-product-subscription-for-woo' ), esc_html( _n( 'subscription', 'subscriptions', count( $subscription_ids ), 'subre-product-subscription-for-woo' ) ) );
			} else {
				/* translators: %s: subscription text */
				printf( esc_html__( 'You will have to renew your %s manually before each renewal date. A reminder email will be sent 1 day before a renewal payment dues.', 'subre-product-subscription-for-woo' ), esc_html( _n( 'subscription', 'subscriptions', count( $subscription_ids ), 'subre-product-subscription-for-woo' ) ) );
			}
			echo '&nbsp;';
			/* translators: %s: link to my account subscription */
			printf( esc_html__( 'To view and manage all your subscriptions, please go to %s.', 'subre-product-subscription-for-woo' ), '<a target="_blank" href="' . esc_url( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Frontend_My_Account::get_myaccount_subscriptions_url() ) . '">' . esc_html__( 'My account/Subscriptions', 'subre-product-subscription-for-woo' ) . '</a>' );
		}
		?>
    </p>
</div>