<?php

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DSWallets_Admin_Menu_Adapter_List extends WP_List_Table {

	public function get_columns() {
		return array(
			// 'cb' => '<input type="checkbox" />', // TODO bulk actions
			'adapter_name' => esc_html__( 'Adapter name', 'wallets' ),
			'icon' => esc_html__( 'Coin Icon', 'wallets' ),
			'symbol' => esc_html__( 'Coin Symbol', 'wallets' ),
			'name' => esc_html__( 'Coin Name', 'wallets' ),
			'balance' => esc_html__( 'Total Balance', 'wallets' ),
			'status' => esc_html__( 'Adapter Status', 'wallets' ),
		);
	}

	public function get_hidden_columns() {
		return array();
	}

	public function get_sortable_columns() {
        return array(
			'symbol' => array( 'symbol', false ),
			'name' => array( 'name', true ),
			'adapter_name' => array( 'name', false ),
			'balance' => array( 'balance', false),
        );
    }

    function usort_reorder( $a, $b ) {
		$order = empty( $_GET['order'] ) ? 'asc' : filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
		$orderby = empty( $_GET['orderby'] ) ? 'name' : filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );
		$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
		return $order === 'asc' ? $result : -$result;
    }

    public function prepare_items() {

		$this->_column_headers = array(
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
		);

		$adapters = apply_filters( 'wallets_coin_adapters', array() );
		$this->items = array();

		foreach ( $adapters as $symbol => &$adapter ) {

			try {
				$balance = $adapter->get_balance();
				$status = esc_html__( 'Responding', 'wallets' );
			} catch ( Exception $e ) {
				$inaccounts = $withdrawable = $balance = esc_html__( 'n/a', 'wallets' );
				$status = sprintf( esc_html__( 'Not Responding: %s', 'wallets' ), $e->getMessage() );
			}

			$format = $adapter->get_sprintf();

			$this->items[] = array(
				'icon' => $adapter->get_icon_url(),
				'symbol' => $adapter->get_symbol(),
				'name' => $adapter->get_name(),
				'adapter_name' => $adapter->get_adapter_name(),
				'balance' => sprintf( $format, $balance ),
				'status' => $status
			);
		};

		usort( $this->items, array( &$this, 'usort_reorder' ) );
	}

	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'symbol':
			case 'name':
			case 'adapter_name':
			case 'balance':
			case 'status':
				return esc_html( $item[ $column_name ] );
 			case 'icon':
				return '<img src="' . esc_attr( $item['icon'] ) . '" style="width: 32px"/>';
			default:
				return '';
		}
	}

	public function get_bulk_actions() {
		$actions = array(
			// TODO bulk actions
		);
		return $actions;
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="adaper[]" value="%s" />', $item['symbol'] );
	}

	public function column_symbol( $item ) {
		$actions = array(
			'settings' => sprintf(
				'<a href="?page=%s&action=%s&symbol=%s" title="' .
				esc_attr__( 'Settings specific to this adapter', 'wallets') . '">' .
				__( 'Settings', 'wallets' ) . '</a>',

				esc_attr( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ),
				'settings',
				esc_attr( $item['symbol'] ) ),

			'export' => sprintf(
				'<a href="?page=%s&action=%s&symbol=%s&_wpnonce=%s" title="' .
				esc_attr__( 'Export transactions to .csv', 'wallets') . '">' .
				__( 'Export', 'wallets' ) . '</a>',

				esc_attr( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ),
				'export',
				esc_attr( $item['symbol'] ),
				wp_create_nonce( 'wallets-export-tx-' . $item['symbol'] ) ),
		);

		return sprintf('%1$s %2$s', $item['symbol'], $this->row_actions( $actions ) );
	}
}