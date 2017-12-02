<?php

/**
 * This is the adapters list that appears in the main "Wallets" admin screen.
 */

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
			'coin' => esc_html__( 'Coin', 'wallets' ),
			'balance' => esc_html__( 'Wallet Balance', 'wallets' ),
			'balances' => esc_html__( 'Sum of User Balances', 'wallets' ),
			'status' => esc_html__( 'Adapter Status', 'wallets' ),
		);
	}

	public function get_hidden_columns() {
		return array();
	}

	public function get_sortable_columns() {
        return array(
			'adapter_name' => array( 'name', true ),
			'coin' => array( 'name', false),
			'balance' => array( 'balance', false),
			'balances' => array( 'balances', false),
        );
    }

    function usort_reorder( $a, $b ) {
		$order = empty( $_GET['order'] ) ? 'asc' : filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
		$orderby = empty( $_GET['orderby'] ) ? 'adapter_name' : filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );
		$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
		return $order === 'asc' ? $result : -$result;
    }

    private function get_balances() {
		global $wpdb;

		$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

		$user_balances_query = $wpdb->prepare( "
			SELECT
				SUM(amount) as balance,
				symbol
			FROM
				$table_name_txs
			WHERE
				( blog_id = %d || %d ) AND
				status = 'done'
			GROUP BY
				symbol
			",
			get_current_blog_id(),
			is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0
		);

		$results = $wpdb->get_results( $user_balances_query );
		$balances = array();
		foreach ( $results as $row ) {
			$balances[ $row->symbol ] = $row->balance;
		}
		return $balances;
    }

    public function prepare_items() {

		$this->_column_headers = array(
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
		);

		$adapters = Dashed_Slug_Wallets::get_instance()->get_coin_adapters();
		$this->items = array();

		$balances = $this->get_balances();

		foreach ( $adapters as $symbol => &$adapter ) {

			try {
				$balance = $adapter->get_balance();
				$status = esc_html__( 'Responding', 'wallets' );
			} catch ( Exception $e ) {
				$inaccounts = $withdrawable = $balance = esc_html__( 'n/a', 'wallets' );
				$status = sprintf( esc_html__( 'Not Responding: %s', 'wallets' ), $e->getMessage() );
			}

			$format = $adapter->get_sprintf();

			$new_row = array(
				'sprintf' => $format,
				'icon' => $adapter->get_icon_url(),
				'symbol' => $adapter->get_symbol(),
				'name' => $adapter->get_name(),
				'adapter_name' => $adapter->get_adapter_name(),
				'balance' => $balance,
				'status' => $status,
				'settings_url' => $adapter->get_settings_url(),
			);

			if ( isset( $balances[ $symbol ] ) ) {
				$new_row['balances'] = $balances[ $symbol ];
			} else {
				$new_row['balances'] = 0;
			}

			$this->items[] = $new_row;
		};

		usort( $this->items, array( &$this, 'usort_reorder' ) );
	}

	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'adapter_name':
			case 'status':
				return esc_html( $item[ $column_name ] );
			case 'balance':
				return
					( $item[ 'balance' ] < $item['balances'] ? '<span style="color:red;">' : '<span>' ) .
					sprintf( $item['sprintf'], $item[ $column_name ] ) .
					'</span>';
			case 'balances':
				return
					sprintf( $item['sprintf'], $item[ $column_name ] );
			case 'coin':
				return
					sprintf(
						'<img src="%s" /> ' .
						'<span> %s (%s)</span>',
						esc_attr( $item['icon'] ),
						esc_attr( $item['name'] ),
						esc_attr( $item['symbol'] )
					);
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

	public function column_adapter_name( $item ) {

		$actions = array();

		if ( $item['settings_url'] ) {
			$actions['settings'] = '<a href="' . esc_attr( $item['settings_url'] ) . '" title="' .
				esc_attr__( 'Settings specific to this adapter', 'wallets') . '">' .
				__( 'Settings', 'wallets' ) . '</a>';
		}

		$actions['export'] = sprintf(
				'<a href="?page=%s&action=%s&symbol=%s&_wpnonce=%s" title="' .
				esc_attr__( 'Export transactions to .csv', 'wallets') . '">' .
				__( 'Export transactions to .csv', 'wallets' ) . '</a>',

				esc_attr( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ),
				'export',
				esc_attr( $item['symbol'] ),
				wp_create_nonce( 'wallets-export-tx-' . $item['symbol'] ) );

		return sprintf('%1$s %2$s', $item['adapter_name'], $this->row_actions( $actions ) );
	}
}