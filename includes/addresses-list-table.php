<?php

/**
 * This is the deposit addresses list that appears in the main "Wallets" admin screen.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DSWallets_Admin_Menu_Addresses_List_Table extends WP_List_Table {

	const PER_PAGE = 10;

	private $order;
	private $orderby;
	private $network_active = false;

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
			'account_name'  => esc_html__( 'User', 'wallets' ),
			'symbol'        => esc_html__( 'Coin', 'wallets' ),
			'address'       => esc_html__( 'Address', 'wallets' ),
			'extra'	        => esc_html__( 'Extra info', 'wallets' ),
			'created_time'  => esc_html__( 'Time', 'wallets' ),
			'status'        => esc_html__( 'Status', 'wallets' ),
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
			'created_time'  => array( 'created_time', true ),
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
		$table_name_adds = Dashed_Slug_Wallets::$table_name_adds;

		// pagination
		$current_page = $this->get_pagenum();
		$total_items  = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT
				COUNT(*)
			FROM
				$table_name_adds
			WHERE
				blog_id = %d || %d
			",
				get_current_blog_id(),
				$this->network_active ? 1 : 0
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => self::PER_PAGE,
			)
		);

		// get data
		$sql_query   = $wpdb->prepare(
			"
			SELECT
				a.id,
				a.blog_id,
				a.symbol,
				a.address,
				a.extra,
				a.created_time,
				a.status,
				u.user_login AS account_name
			FROM
				$table_name_adds a
				LEFT JOIN
					{$wpdb->users} u ON u.ID = a.account
			WHERE
				( blog_id = %d || %d )
			ORDER BY
				$this->orderby $this->order
			LIMIT
				%d, %d
			",
			get_current_blog_id(),
			$this->network_active ? 1 : 0,
			self::PER_PAGE * ( $current_page - 1 ),
			self::PER_PAGE
		);
		$this->items = $wpdb->get_results( $sql_query, ARRAY_A );
	}

	public function column_default( $item, $column_name ) {
		return esc_html( $item[ $column_name ] );
	}

	public function column_address( $item ) {
		$uri_pattern = apply_filters( 'wallets_explorer_uri_add_' . $item['symbol'], '' );
		if ( $uri_pattern && preg_match( '/^[\w\d]+$/', $item['address'] ) ) {
			$uri          = sprintf( $uri_pattern, $item['address'] );
			$address_html = '<a href="' . esc_attr( $uri ) . '">' . $item['address'] . '</a>';
		} else {
			$address_html = $item['address'];
		}
		return $address_html;
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

	public function column_created_time( $item ) {
		return get_date_from_gmt( $item['created_time'] );
	}
}
