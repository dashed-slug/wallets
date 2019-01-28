<?php

/**
 * This is the User balances list that appears in the main "Wallets" admin screen.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DSWallets_Admin_Menu_Balances_List_Table extends WP_List_Table {

	const PER_PAGE = 10;

	private $order;
	private $orderby;
	private $network_active = false;
	private $adapters = false;

	public function __construct( $args = array() ) {
		parent::__construct( $args );

		$this->is_plugin_active_for_network( 'wallets/wallets.php' );

		// sorting vars
		$this->order   = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
		$this->orderby = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );
	}

	public function get_columns() {
		$columns = array(
			// 'cb' => '<input type="checkbox" />', // TODO bulk actions
			'account_name'      => esc_html__( 'User', 'wallets' ),
			'symbol'            => esc_html__( 'Coin', 'wallets' ),
			'balance'           => esc_html__( 'Balance', 'wallets' ),
			'available_balance' => esc_html__( 'Available balance', 'wallets' ),
		);

		if ( $this->network_active ) {
			$columns['blog_id'] = esc_html__( 'Blog ID', 'wallets' );
		}

		return $columns;
	}

	public function get_hidden_columns() {
		return array();
	}

	public function get_sortable_columns() {
		return array(
			'account_name'  => array( 'account_name', true ),
			'symbol'        => array( 'symbol', true ),
			'status'        => array( 'status', false ),
		);
	}

	public function prepare_items() {

		$this->_column_headers = array(
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
		);

		global $wpdb;
		$table_name_txs = Dashed_Slug_Wallets::$table_name_txs;

		// retrieve count for pagination
		$current_page = $this->get_pagenum();

		$count_query =
			"
			SELECT
				COUNT(*) AS c
			FROM
				( SELECT
					account,
					symbol
				FROM
					$table_name_txs ";

		if ( ! $this->network_active ) {
			$count_query .= $wpdb->prepare( 'WHERE blog_id = %d', get_current_blog_id() );
		}

		$count_query .= " ) AS t";

		$total_items = absint( $wpdb->get_var( $count_query ) );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => self::PER_PAGE,
			)
		);

		// retrieve user balances
		$balances_query = "
			SELECT
				u.user_login AS account_name,
				t.account,
				t.symbol,
				SUM( IF( t.amount > 0, t.amount - t.fee, t.amount ) ) AS balance

			FROM
				$table_name_txs t
				LEFT JOIN {$wpdb->users} u ON u.ID = t.account

			WHERE ";

		if ( ! $this->network_active ) {
			$balances_query .= $wpdb->prepare( 't.blog_id = %d AND', get_current_blog_id() );
		}

		$balances_query .= $wpdb->prepare( "
				t.status = 'done'

			GROUP BY
				t.account,
				t.symbol

			ORDER BY
				$this->orderby $this->order

			LIMIT
				%d, %d",

			self::PER_PAGE * ( $current_page - 1 ),
			self::PER_PAGE
		);

		$this->items = $wpdb->get_results( $balances_query, ARRAY_A );

		foreach ( $this->items as &$item ) {
			$item['available_balance'] = apply_filters(
				'wallets_api_available_balance',
				0,
				array(
					'user_id' => $item['account'],
					'symbol'  => $item['symbol'],
				)
			);
		}

		// also retrieve adapters so we can render amounts and pull coin names
		if ( ! $this->adapters ) {
			$this->adapters = apply_filters( 'wallets_api_adapters', array() );
		}
	}

	public function column_default( $item, $column_name ) {
		return esc_html( $item[ $column_name ] );
	}

	public function column_symbol( $item ) {
		if ( isset( $this->adapters[ $item['symbol'] ] ) ) {
			$adapter = $this->adapters[ $item['symbol'] ];
			return sprintf(
				'%s (%s)',
				$adapter->get_name(),
				$item['symbol']
			);
		} else {
			return $item['symbol'];
		}
	}

	public function column_balance( $item ) {
		if ( 0 == $item['balance'] ) {
			return '&mdash;'; // no amount
		}

		if ( isset( $this->adapters[ $item['symbol'] ] ) ) {
			$adapter = $this->adapters[ $item['symbol'] ];
			return sprintf( $adapter->get_sprintf(), $item['balance'] );
		} else {
			return $item['balance'];
		}
	}

	public function column_available_balance( $item ) {
		if ( 0 == $item['available_balance'] ) {
			return '&mdash;'; // no amount
		}

		if ( isset( $this->adapters[ $item['symbol'] ] ) ) {
			$adapter = $this->adapters[ $item['symbol'] ];
			return sprintf( $adapter->get_sprintf(), $item['available_balance'] );
		} else {
			return $item['available_balance'];
		}
	}

	public function column_account_name( $item ) {
		return Dashed_Slug_Wallets::user_link( $item['account_name'] );
	}

	public function get_bulk_actions() {
		$actions = array(
			// TODO bulk actions
		);
		return $actions;
	}
}
