<?php

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DSWallets_Admin_Menu_Adapter_List extends WP_List_Table {

	public function get_columns() {
		return array(
			// 'cb'        => '<input type="checkbox" />', // TODO bulk actions
			'icon'			=> esc_html__('Coin Icon', 'wallets' ),
			'symbol'		=> esc_html__('Coin Symbol', 'wallets' ),
			'name'			=> esc_html__('Coin Name', 'wallets' ),
			'balance'		=> esc_html__('Total Balance', 'wallets' ),
			'inaccounts'	=> esc_html__('Total in Accounts', 'wallets' ),
			'withdrawable'	=> esc_html__('Withdrawable Balance', 'wallets' ),
			'status'		=> esc_html__('Adapter Status', 'wallets' ),
		);
	}

	public function get_hidden_columns() {
		return array();
	}

	public function get_sortable_columns() {
        return array(
			'symbol'		=> array( 'symbol', false ),
			'name'			=> array( 'name', true ),
			'balance'		=> array( 'balance', false),
			'inaccounts'		=> array( 'inaccounts', false),
			'withdrawable'	=> array( 'withdrawable', false),
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
		$user_balance_sums = $this->get_user_balance_sums();

		foreach ( $adapters as $symbol => &$adapter ) {

			try {
				$balance = $adapter->get_balance();
				if ( isset( $user_balance_sums[ $symbol ] ) ) {
					$inaccounts = $user_balance_sums[ $symbol ];
					$withdrawable = $balance - $inaccounts;
				} else {
					$inaccounts = $withdrawable = __( 'n/a', 'wallets' );
				}
				$status = esc_html__( 'Responding', 'wallets' );
			} catch ( Exception $e ) {
				$inaccounts = $withdrawable = $balance = esc_html__( 'n/a', 'wallets' );
				$status = esc_html__( 'Not Responding', 'wallets' );
			}

			$format = $adapter->get_sprintf();

			$this->items[] = array(
				'icon' => $adapter->get_icon_url(),
				'symbol' => $adapter->get_symbol(),
				'name' => $adapter->get_name(),
				'balance' => sprintf( $format, $balance ),
				'inaccounts' => sprintf( $format, $inaccounts ),
				'withdrawable' => sprintf( $format, $withdrawable ),
				'status' => $status
			);
		};

		usort( $this->items, array( &$this, 'usort_reorder' ) );
	}

	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'symbol':
			case 'name':
			case 'balance':
			case 'inaccounts':
			case 'withdrawable':
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
			// 'deactivate-adapter' => esc_html( 'Deactivate', 'wallets' ), // TODO bulk actions
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

			'deactivate-adapter' => sprintf(
				'<a href="?page=%s&action=%s&symbol=%s&_wpnonce=%s" title="' .
				esc_attr__( 'Deactivate the plugin that provides this adapter', 'wallets') . '">' .
				__( 'Deactivate', 'wallets') . '</a>',

				esc_attr( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ),
				'deactivate-adapter',
				esc_attr( $item['symbol'] ),
				wp_create_nonce( 'wallets-deactivate-' . $item['symbol'] ) )
		);

		// Cannot deactivate BTC adapter since it's built-in to the plugin.
		// To avoid using it, simply don't set any RPC settings,
		// and dismiss the admin screen error messages.
		// Adapters that cannot contact their wallets are not shown in the frontend.
		if ( 'BTC' == $item['symbol'] ) {
			unset( $actions['deactivate'] );
		}

		return sprintf('%1$s %2$s', $item['symbol'], $this->row_actions( $actions ) );
	}

	private function get_user_balance_sums() {
		global $wpdb;

		$table_name_txs = "{$wpdb->prefix}wallets_txs";

		$results = $wpdb->get_results( "
			SELECT
				symbol,
				SUM(amount) AS total
			FROM
				$table_name_txs
			GROUP BY
				symbol
		" );

		$user_balance_sums = array();

		if ( false !== $results ) {
			foreach ( $results as $result ) {
				$user_balance_sums[ $result->symbol ] = floatval( $result->total );
			}
		}

		return $user_balance_sums;
	}
}