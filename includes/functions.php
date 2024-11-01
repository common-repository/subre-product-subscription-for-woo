<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Function include all files in folder
 *
 * @param $path   Directory address
 * @param $ext    array file extension what will include
 * @param $prefix string Class prefix
 */
if ( ! function_exists( 'vi_include_folder' ) ) {
	function vi_include_folder( $path, $prefix = '', $ext = array( 'php' ) ) {

		/*Include all files in payment folder*/
		if ( ! is_array( $ext ) ) {
			$ext = explode( ',', $ext );
			$ext = array_map( 'trim', $ext );
		}
		$sfiles = scandir( $path );
		foreach ( $sfiles as $sfile ) {
			if ( $sfile != '.' && $sfile != '..' ) {
				if ( is_file( $path . "/" . $sfile ) ) {
					$ext_file  = pathinfo( $path . "/" . $sfile );
					$file_name = $ext_file['filename'];
					if ( $ext_file['extension'] ) {
						if ( in_array( $ext_file['extension'], $ext ) ) {
							$class = preg_replace( '/\W/i', '_', $prefix . ucfirst( $file_name ) );

							if ( ! class_exists( $class ) ) {
								require_once $path . $sfile;
								if ( class_exists( $class ) ) {
									new $class;
								}
							}
						}
					}
				}
			}
		}
	}
}

if ( ! function_exists( 'woocommerce_version_check' ) ) {
	function woocommerce_version_check( $version = '3.0' ) {
		global $woocommerce;

		if ( version_compare( $woocommerce->version, $version, ">=" ) ) {
			return true;
		}

		return false;
	}
}
if ( ! function_exists( 'subre_get_order_subscription_edit_link' ) ) {
	/**
	 * @param $order_subscription_id
	 * @param bool $url_only
	 * @param bool $new_tab
	 * @param string $class
	 * @param string $id
	 *
	 * @return string
	 */
	function subre_get_order_subscription_edit_link( $order_subscription_id, $url_only = false, $new_tab = true, $class = '', $id = '' ) {
		$url = add_query_arg( array(
			'post'   => $order_subscription_id,
			'action' => 'edit'
		), admin_url( 'post.php' ) );
		if ( $url_only ) {
			return $url;
		} else {
			return '<a class="' . esc_attr( $class ) . '" target="' . ( $new_tab ? '_blank' : '_self' ) . '" id="' . esc_attr( $id ) . '" href="' . esc_url( $url ) . '" target="_blank">#' . esc_html( $order_subscription_id ) . '</a>';
		}
	}
}
if ( ! function_exists( 'subre_get_subscription_view_link' ) ) {
	/**
	 * @param $subscription_id
	 * @param bool $url_only
	 * @param bool $new_tab
	 * @param string $class
	 * @param string $id
	 *
	 * @return string
	 */
	function subre_get_subscription_view_link( $subscription_id, $url_only = false, $new_tab = true, $class = '', $id = '' ) {
		$url = wc_get_endpoint_url( SUBRE_PRODUCT_SUBSCRIPTION_FOR_WOO_DATA::get_instance()->get_params( 'view_subscription_endpoint' ), $subscription_id, wc_get_page_permalink( 'myaccount' ) );
		if ( $url_only ) {
			return $url;
		} else {
			return '<a class="' . esc_attr( $class ) . '" target="' . ( $new_tab ? '_blank' : '_self' ) . '" id="' . esc_attr( $id ) . '" href="' . esc_url( $url ) . '" target="_blank">#' . esc_html( $subscription_id ) . '</a>';
		}
	}
}