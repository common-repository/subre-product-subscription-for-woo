<?php
/**
 * Plugin Name: SUBRE – Product Subscription for WooCommerce
 * Plugin URI: https://villatheme.com/extensions/subre-woocommerce-product-subscription/
 * Description: Convert WooCommerce simple products(physical or downloadable/virtual) to subscription products and allow recurring payments
 * Version: 1.0.7
 * Author: VillaTheme(villatheme.com)
 * Author URI: http://villatheme.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: subre-product-subscription-for-woo
 * Copyright 2022 - 2024 VillaTheme.com. All rights reserved.
 * Requires Plugins: woocommerce
 * Tested up to: 6.5
 * WC tested up to: 8.7
 * Requires PHP: 7.0
 **/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION', '1.0.7' );
define( 'SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DIR', plugin_dir_path( __FILE__ ) );
define( 'SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_INCLUDES', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DIR . 'includes' . DIRECTORY_SEPARATOR );

/**
 * Class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO
 */
class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO {
	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'before_woocommerce_init', [ $this, 'custom_order_tables_declare_compatibility' ] );
	}

	public function init() {
		if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
			include_once SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_INCLUDES . 'support.php';
		}

		$environment = new \VillaTheme_Require_Environment( [
				'plugin_name'     => 'SUBRE – Product Subscription for WooCommerce',
				'php_version'     => '7.0',
				'wp_version'      => '5.0',
				'require_plugins' => [
					[
						'slug'             => 'woocommerce',
						'name'             => 'WooCommerce',
						'required_version' => '7.0',
					],
				]
			]
		);

		if ( $environment->has_error() ) {
			return;
		}

		if ( is_plugin_active( 'subre-product-subscription-for-woocommerce/subre-product-subscription-for-woocommerce.php' ) ) {
			return;
		}

		require_once SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_INCLUDES . 'define.php';
	}

	/**
	 * Check required WordPress version and flush rewrite rules when the plugin is activated
	 */
	public function activate() {
		update_option( 'woocommerce_queue_flush_rewrite_rules', 'yes' );
	}

	/**
	 * Unschedule all recurring events when the plugin is deactivated
	 */
	public function deactivate() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'subre_schedule_subscription_renewals_and_expiration' );
			as_unschedule_all_actions( 'subre_overdue_subscriptions_check' );
		}
	}

	public function custom_order_tables_declare_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );

		}
	}
}

new SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO();