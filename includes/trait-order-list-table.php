<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\Admin\Orders\ListTable;

trait SUBRE_TRAIT_ORDER_LIST_TABLE {
	private $list_table;

	public function render_column( $column_id, $order ) {
		if ( null === $this->list_table ) {
			$this->list_table = wc_get_container()->get( ListTable::class );
		}
		if ( is_callable( array( $this->list_table, 'render_column' ) ) ) {
			$this->list_table->render_column( $column_id, $order );
		} elseif ( is_callable( array( $this->list_table, "column_{$column_id}" ) ) ) {
			$this->list_table->{"column_{$column_id}"}( $order );
		}
	}
}
