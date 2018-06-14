<?php

/**
 * This is the adapters list that appears in the main "Wallets" admin screen.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Dashed_Slug_Wallets_Adapters_List_Table extends WP_List_Table {

	public function get_columns() {
		return array(
			// 'cb' => '<input type="checkbox" />', // TODO bulk actions
			'adapter_name' => esc_html__( 'Adapter name', 'wallets' ),
			'coin'         => esc_html__( 'Coin', 'wallets' ),
			'balance'      => esc_html__( 'Wallet Balance', 'wallets' ),
			'balances'     => esc_html__( 'Sum of User Balances', 'wallets' ),
			'status'       => esc_html__( 'Adapter Status', 'wallets' ),
			'locked'       => esc_html__( 'Withdrawals lock', 'wallets' ),
			'pending_wds'  => esc_html__( 'Pending withdrawals', 'wallets' ),
		);
	}

	public function get_hidden_columns() {
		return array();
	}

	public function get_sortable_columns() {
		return array(
			'adapter_name' => array( 'name', true ),
			'coin'         => array( 'name', false ),
			'balance'      => array( 'balance', false ),
			'balances'     => array( 'balances', false ),
			'pending_wds'  => array( 'pending_wds', false ),
		);
	}

	function usort_reorder( $a, $b ) {
		$order   = empty( $_GET['order'] ) ? 'asc' : filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
		$orderby = empty( $_GET['orderby'] ) ? 'adapter_name' : filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );
		$result  = strcmp( $a[ $orderby ], $b[ $orderby ] );
		return 'asc' === $order ? $result : -$result;
	}

	public function prepare_items() {

		$this->_column_headers = array(
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
		);

		$this->items = array();

		$balances = Dashed_Slug_Wallets::get_balance_totals_per_coin();

		global $wpdb;
		$table_name_txs            = Dashed_Slug_Wallets::$table_name_txs;
		$pending_withdrawal_counts = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT
					symbol,
					COUNT(*) as c
				FROM
					{$table_name_txs}
				WHERE
					category = 'withdraw' AND
					status = 'pending' AND
					( blog_id = %d || %d )
				GROUP BY
					symbol
				",
				get_current_blog_id(),
				is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0
			),
			OBJECT_K
		);

		$adapters = apply_filters( 'wallets_api_adapters', array() );

		foreach ( $adapters as $symbol => &$adapter ) {

			try {
				$balance = $adapter->get_balance();
				$status  = esc_html__( 'Responding', 'wallets' );
			} catch ( Exception $e ) {
				$inaccounts = $withdrawable = $balance = esc_html__( 'n/a', 'wallets' );
				$status     = sprintf( esc_html__( 'Not Responding: %s', 'wallets' ), $e->getMessage() );
			}

			$format = $adapter->get_sprintf();

			$new_row = array(
				'sprintf'      => $format,
				'icon'         => $adapter->get_icon_url(),
				'symbol'       => $adapter->get_symbol(),
				'name'         => $adapter->get_name(),
				'adapter_name' => $adapter->get_adapter_name(),
				'balance'      => $balance,
				'status'       => $status,
				'settings_url' => $adapter->get_settings_url(),
				'unlocked'     => $adapter->is_unlocked(),
			);

			if ( isset( $balances[ $symbol ] ) ) {
				$new_row['balances'] = $balances[ $symbol ];
			}

			if ( isset( $pending_withdrawal_counts[ $symbol  ] ) ) {
				$new_row['pending_wds'] = $pending_withdrawal_counts[ $symbol ]->c;
			}

			$this->items[] = $new_row;
		};

		usort( $this->items, array( &$this, 'usort_reorder' ) );
	}

	public function column_default( $item, $column_name ) {
		if ( ! isset( $item[ $column_name ] ) ) {
			return '&mdash;';
		}

		switch ( $column_name ) {
			case 'adapter_name':
			case 'status':
			case 'pending_wds':
				return esc_html( $item[ $column_name ] );
			case 'balance':
			case 'balances':
				return
					sprintf( $item['sprintf'], $item[ $column_name ] );
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

	public function column_balances( $item ) {
		if ( ! isset( $item['balances'] ) ) {
			return '&mdash;';
		}

		return
			( $item['balance'] < $item['balances'] ? '<span style="color:red;">' : '<span>' ) .
			sprintf( $item['sprintf'], $item['balances'] ) .
			'</span>';
	}

	public function column_coin( $item ) {
		return
			sprintf(
				'<img src="%s" /> <span> %s (%s)</span>',
				esc_attr( $item['icon'] ),
				esc_attr( $item['name'] ),
				esc_attr( $item['symbol'] )
			);
	}

	public function column_locked( $item ) {
		if ( $item['unlocked'] ) {
			return '<span title="' . esc_attr__( 'Wallet unlocked. Withdrawals will be processed.', 'wallets' ) . '">&#x1f513;</span>';
		} else {
			return '<span title="' . esc_attr__( 'Wallet locked. Withdrawals will NOT be processed.', 'wallets' ) . '">&#x1f512;</span>';
		}
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="adaper[]" value="%s" />', $item['symbol'] );
	}

	public function column_adapter_name( $item ) {

		$actions = array();

		if ( $item['settings_url'] ) {
			$actions['settings'] = sprintf(
				'<a href="%s" title="%s">%s</a>',
				esc_attr( $item['settings_url'] ),
				esc_attr__( 'Settings specific to this adapter', 'wallets' ),
				__( 'Settings', 'wallets' )
			);

		}

		$actions['export'] = sprintf(
			'<a href="?page=%s&action=%s&symbol=%s&_wpnonce=%s" title="%s">%s</a>',
			esc_attr( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ),
			'export',
			esc_attr( $item['symbol'] ),
			wp_create_nonce( 'wallets-export-tx-' . $item['symbol'] ),
			esc_attr__( 'Export transactions to .csv', 'wallets' ),
			__( 'Export', 'wallets' )
		);

		$actions['new_deposits'] = sprintf(
			'<a href="?page=%s&action=%s&symbol=%s&_wpnonce=%s" onclick="return confirm(\'%s\')" title="%s">%s</a>',
			esc_attr( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ), // page
			'new_deposits', // action
			esc_attr( $item['symbol'] ), // symbol
			wp_create_nonce( 'wallets-new-deposits-' . $item['symbol'] ), // _wpnonce
			esc_attr__(
				'Are you sure you wish to mark all the current deposit addresses as old? ' .
				'New deposit addresses will be generated for this coin when next needed. ' .
				'The old deposit addresses will continue to accept deposits.', 'wallets'
			), // confirm string
			esc_attr__( 'Creates new deposit addresses. Use this after switching to a different adapter.', 'wallets' ), // title
			__( 'Renew deposit addresses', 'wallets' ) // link text
		);

		return sprintf( '%1$s %2$s', $item['adapter_name'], $this->row_actions( $actions ) );
	}
}
