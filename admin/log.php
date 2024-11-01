<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_Admin_Log {

	public function __construct() {

	}

	public static function log( $content, $source = 'debug', $level = 'debug' ) {
		$content = wp_strip_all_tags( $content );
		$log     = wc_get_logger();
		$log->log( $level,
			$content,
			array(
				'source' => 'subre-product-subscription-for-woo-' . $source,
			)
		);
	}
}
