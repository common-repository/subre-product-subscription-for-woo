<?php
/**
 * View Order
 *
 * Shows the details of a particular order on the account page.
 *
 * This template can be overridden by copying it to yourtheme/subre-product-subscription-for-woo/myaccount/view-subscription.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.0.0
 */

defined( 'ABSPATH' ) || exit;

$notes           = $subscription->get_customer_order_notes();
$subscription_id = $subscription->get_id();
?>

<?php if ( $notes ) : ?>
    <h2><?php esc_html_e( 'Subscription updates', 'subre-product-subscription-for-woo' ); ?></h2>
    <ol class="woocommerce-OrderUpdates commentlist notes">
		<?php foreach ( $notes as $note ) : ?>
            <li class="woocommerce-OrderUpdate comment note">
                <div class="woocommerce-OrderUpdate-inner comment_container">
                    <div class="woocommerce-OrderUpdate-text comment-text">
                        <p class="woocommerce-OrderUpdate-meta meta"><?php echo esc_html( date_i18n( esc_html__( 'l jS \o\f F Y, h:ia', 'subre-product-subscription-for-woo' ), strtotime( $note->comment_date ) ) ); ?></p>
                        <div class="woocommerce-OrderUpdate-description description">
							<?php echo SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                        <div class="clear"></div>
                    </div>
                    <div class="clear"></div>
                </div>
            </li>
		<?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php do_action( 'subre_view_subscription', $subscription_id ); ?>
