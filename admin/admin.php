<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Admin {

	public function __construct() {
		add_filter( 'plugin_action_links_subre-product-subscription-for-woo/subre-product-subscription-for-woo.php', array(
			$this,
			'settings_link'
		) );
		add_action( 'init', array( $this, 'init' ) );
	}

	public function settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=subre-product-subscription-for-woo" title="' . esc_html__( 'Settings', 'subre-product-subscription-for-woo' ) . '">' . esc_html__( 'Settings', 'subre-product-subscription-for-woo' ) . '</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}

	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'subre-product-subscription-for-woo' );
		load_textdomain( 'subre-product-subscription-for-woo', SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_LANGUAGES . "subre-product-subscription-for-woo-$locale.mo" );
		load_plugin_textdomain( 'subre-product-subscription-for-woo', false, SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_LANGUAGES );
	}

	public function init() {
		$this->load_plugin_textdomain();
		if ( class_exists( 'VillaTheme_Support' ) ) {
			new VillaTheme_Support(
				array(
					'support'    => 'https://wordpress.org/support/plugin/subre-product-subscription-for-woo/',
					'docs'       => 'http://docs.villatheme.com/?item=subre-product-subscription-for-woo',
					'review'     => 'https://wordpress.org/support/plugin/subre-product-subscription-for-woo/reviews/?rate=5#rate-response',
					'pro_url'    => '',
					'css'        => SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_CSS,
					'image'      => SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_IMAGES,
					'slug'       => 'subre-product-subscription-for-woo',
					'menu_slug'  => 'subre-product-subscription-for-woo',
					'survey_url' => 'https://script.google.com/macros/s/AKfycbzFUSL8LQlMK65VltDG1a-JVESk8IlYQSSgAvhSJLWH47obp8NA3MDUSgSS8z9iIwNgXA/exec',
					'version'    => SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_VERSION
				)
			);
		}
	}
}
