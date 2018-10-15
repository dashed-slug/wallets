<?php

/**
 * This is the transactions list that appears in the main "Wallets" admin screen.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DSWallets_Admin_Menu_TX_List extends WP_List_Table {

	const PER_PAGE = 10;

	private $order;
	private $orderby;

	public function __construct( $args = array() ) {
		parent::__construct( $args );

		// sorting vars
		$this->order   = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
		$this->orderby = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );
	}

	public function get_columns() {
		return array(
			// 'cb' => '<input type="checkbox" />', // TODO bulk actions
			'txid'          => esc_html__( 'TXID', 'wallets' ),
			'category'      => esc_html__( 'Type', 'wallets' ),
			'symbol'        => esc_html__( 'Coin', 'wallets' ),
			'amountnofee'   => esc_html__( 'Amount', 'wallets' ),
			'fee'           => esc_html__( 'Fee', 'wallets' ),
			'amount'        => esc_html__( 'Amount (+fee)', 'wallets' ),
			'from'          => esc_html__( 'From', 'wallets' ),
			'to'            => esc_html__( 'To', 'wallets' ),
			'comment'       => esc_html__( 'Comment', 'wallets' ),
			'tags'          => esc_html__( 'Tags', 'wallets' ),
			'created_time'  => esc_html__( 'Time', 'wallets' ),
			'confirmations' => esc_html__( 'Confirms', 'wallets' ),
			'status'        => esc_html__( 'Status', 'wallets' ),
			'retries'       => esc_html__( 'Retries', 'wallets' ),
			'admin_confirm' => esc_html__( 'Accepted by admin', 'wallets' ),
			'user_confirm'  => esc_html__( 'Verified by user', 'wallets' ),
		);
	}

	public function get_hidden_columns() {
		return array();
	}

	public function get_sortable_columns() {
		return array(
			'created_time'  => array( 'created_time', true ),
			'amount'        => array( 'amount', false ),
			'amountnofee'   => array( 'amountnofee', false ),
			'confirmations' => array( 'confirmations', false ),
			'status'        => array( 'status', false ),
			'admin_confirm' => array( 'admin_confirm', false ),
			'user_confirm'  => array( 'user_confirm', false ),
			'retries'       => array( 'retries', false ),
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

		// pagination
		$current_page = $this->get_pagenum();
		$total_items  = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT
				COUNT(*)
			FROM
				$table_name_txs
			WHERE
				( blog_id = %d || %d )
			",
				get_current_blog_id(),
				is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0
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
				txs.id,
				txs.txid,
				txs.category,
				txs.symbol,
				txs.amount,
				txs.fee,
				txs.amount + ABS( txs.fee ) * IF( txs.amount > 0, -1, 1) AS amountnofee,
				txs.address,
				txs.extra,
				txs.comment,
				txs.confirmations,
				txs.tags,
				txs.created_time,
				txs.status,
				txs.retries,
				txs.admin_confirm,
				txs.user_confirm,
				u1.user_login account_name,
				u2.user_login other_account_name
			FROM
				$table_name_txs txs
			LEFT JOIN {$wpdb->users} u1 ON u1.ID = txs.account
			LEFT JOIN {$wpdb->users} u2 ON u2.ID = txs.other_account
			WHERE
				( blog_id = %d || %d )
			ORDER BY
				$this->orderby $this->order
			LIMIT
				%d, %d
			",
			get_current_blog_id(),
			is_plugin_active_for_network( 'wallets/wallets.php' ) ? 1 : 0,
			self::PER_PAGE * ( $current_page - 1 ),
			self::PER_PAGE
		);
		$this->items = $wpdb->get_results( $sql_query, ARRAY_A );
	}

	public function column_default( $item, $column_name ) {

		switch ( $column_name ) {

			case 'category':
			case 'symbol':
			case 'tags':
			case 'status':
			case 'from':
			case 'to':
				return esc_html( $item[ $column_name ] );

			case 'admin_confirm':
			case 'user_confirm':
				return $item[ $column_name ] ? '&#x2611;' : '&#x2610;';

			case 'amount':
			case 'fee':
			case 'amountnofee':
				if ( 0 == $item[ $column_name ] ) {
					return '&mdash;'; // no amount
				}
				$adapters = apply_filters( 'wallets_api_adapters', array() );
				if ( isset( $adapters[ $item['symbol'] ] ) ) {
					$adapter = $adapters[ $item['symbol'] ];
					return sprintf( $adapter->get_sprintf(), $item[ $column_name ] );
				} else {
					return $item[ $column_name ];
				}

			default:
				return '&mdash;';
		}
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

	public function column_comment( $item ) {
		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( $item[ 'comment' ] ),
			wp_trim_words( $item[ 'comment' ], 5 )
		);
	}

	public function column_from( $item ) {
		if ( 'deposit' == $item['category'] ) {
			$uri_pattern = apply_filters( 'wallets_explorer_uri_add_' . $item['symbol'], '' );
			if ( $uri_pattern && preg_match( '/^[\w\d]+$/', $item['address'] ) ) {
				$uri          = sprintf( $uri_pattern, $item['address'] );
				$address_html = '<a href="' . esc_attr( $uri ) . '">' . $item['address'] . '</a>';
			} else {
				$address_html = $item['address'];
			}
			if ( $item['extra'] ) {
				$address_html .= " ({$item['extra']})";
			}
			return $address_html;

		} elseif ( 'withdraw' == $item['category'] ) {
			return Dashed_Slug_Wallets::user_link( $item['account_name'] );

		} elseif ( 'move' == $item['category'] ) {
			return Dashed_Slug_Wallets::user_link( $item['account_name'] );

		} elseif ( 'trade' == $item['category'] ) {
			return Dashed_Slug_Wallets::user_link( $item['account_name'] );
		}

	}

	public function column_to( $item ) {
		if ( 'deposit' == $item['category'] ) {
			return  Dashed_Slug_Wallets::user_link( $item['account_name'] );

		} elseif ( 'withdraw' == $item['category'] ) {
			$uri_pattern = apply_filters( 'wallets_explorer_uri_add_' . $item['symbol'], '' );
			if ( $uri_pattern && preg_match( '/^[\w\d]+$/', $item['address'] ) ) {
				$uri          = sprintf( $uri_pattern, $item['address'] );
				$address_html = '<a href="' . esc_attr( $uri ) . '">' . $item['address'] . '</a>';
			} else {
				$address_html = $item['address'];
			}
			if ( $item['extra'] ) {
				$address_html .= " ({$item['extra']})";
			}
			return $address_html;

		} elseif ( 'move' == $item['category'] ) {
			return Dashed_Slug_Wallets::user_link( $item['other_account_name'] );

		} elseif ( 'trade' == $item['category'] ) {
			return Dashed_Slug_Wallets::user_link( $item['other_account_name'] );
		}
	}

	public function column_txid( $item ) {
		if ( 'move' != $item['category'] && preg_match( '/^[\w\d]+$/', $item['txid'] ) ) {
			$uri_pattern = apply_filters( 'wallets_explorer_uri_tx_' . $item['symbol'], '' );
			if ( $uri_pattern ) {
				$uri = sprintf( $uri_pattern, $item['txid'] );
				return '<a href ="' . esc_attr( $uri ) . '">' . $item['txid'] . '</a>';
			}
		}
		return esc_html( $item['txid'] );
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="adaper[]" value="%s" />', $item['symbol'] );
	}

	public function column_confirmations( $item ) {
		if ( 'move' == $item['category'] ) {
			return  '';
		} elseif ( 'trade' == $item['category'] ) {
			return '';
		} else {
			return esc_html( $item['confirmations'] );
		}
	}

	public function column_retries( $item ) {
		if ( 'deposit' == $item['category'] ) {
			return '';
		} elseif ( 'trade' == $item['category'] ) {
			return '';
		} else {
			return esc_html( $item['retries'] );
		}
	}

	public function column_status( $item ) {
		$actions = array();
		if ( ! ( 'trade' == $item['category'] ) ) { // cannot cancel trades with other people
			if ( 'cancelled' != $item['status'] && 'failed' != $item['status'] ) { // cannot cancel already cancelled or failed txs
				if ( ! ( 'withdraw' == $item['category'] && 'done' == $item['status'] ) ) { // cannot cancel if already on blockchain
					$actions['cancel_tx'] = sprintf(
						'<a class="button" href="%s" title="%s">%s</a>',
						add_query_arg(
							array(
								'page'     => 'wallets-menu-transactions',
								'action'   => 'cancel_tx',
								'tx_id'    => $item['id'],
								'paged'    => $this->get_pagenum(),
								'order'    => $this->order,
								'orderby'  => $this->orderby,
								'_wpnonce' => wp_create_nonce( 'wallets-cancel-tx-' . $item['id'] ),
							),
							call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php' )
						),
						__( 'Transaction will be CANCELLED.', 'wallets' ),
						__( '&#x1F5D9; Cancel', 'wallets' )
					);
				}
			}
		}

		if ( 'trade' !== $item['category'] ) { // cannot retry trades
			if ( 'cancelled' == $item['status'] || 'failed' == $item['status'] ) {
				$actions['retry_tx'] = sprintf(
					'<a class="button" href="%s" title="%s">%s</a>',
					add_query_arg(
						array(
							'page'     => 'wallets-menu-transactions',
							'action'   => 'retry_tx',
							'tx_id'    => $item['id'],
							'paged'    => $this->get_pagenum(),
							'order'    => $this->order,
							'orderby'  => $this->orderby,
							'_wpnonce' => wp_create_nonce( 'wallets-retry-tx-' . $item['id'] ),
						),
						call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php' )
					),
					__( 'Transaction will be RETRIED.', 'wallets' ),
					__( '&#8635; Retry', 'wallets' )
				);
			}
		}
		return $item['status'] . '<br />' . $this->row_actions( $actions, true );
	}

	public function column_admin_confirm( $item ) {
		if ( 'trade' == $item['category'] ) {
			return '';
		}

		$actions = array();

		if ( 'unconfirmed' == $item['status'] || 'pending' == $item['status'] ) {

			if ( ( 'withdraw' == $item['category'] && Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_admin_enabled' ) ) ||
				( 'move' == $item['category'] && Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_admin_enabled' ) ) ) {

				if ( $item['admin_confirm'] ) {
					$actions['admin_unconfirm'] = sprintf(
						'<a class="button" href="%s" title="%s">%s</a>',
						add_query_arg(
							array(
								'page'     => 'wallets-menu-transactions',
								'action'   => 'admin_unconfirm',
								'tx_id'    => $item['id'],
								'paged'    => $this->get_pagenum(),
								'order'    => $this->order,
								'orderby'  => $this->orderby,
								'_wpnonce' => wp_create_nonce( 'wallets-admin-unconfirm-' . $item['id'] ),
							),
							call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php' )
						),
						__( 'Mark this transaction as NOT CONFIRMED by admin. Will NOT be retried if admin confirmation is required.', 'wallets' ),
						__( '&#x2717; Admin unaccept', 'wallets' )
					);
				} else {
					$actions['admin_confirm'] = sprintf(
						'<a class="button" href="%s" title="%s">%s</a>',
						add_query_arg(
							array(
								'page'     => 'wallets-menu-transactions',
								'action'   => 'admin_confirm',
								'tx_id'    => $item['id'],
								'paged'    => $this->get_pagenum(),
								'order'    => $this->order,
								'orderby'  => $this->orderby,
								'_wpnonce' => wp_create_nonce( 'wallets-admin-confirm-' . $item['id'] ),
							),
							call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php' )
						),
						__( 'Transaction will be marked as CONFIRMED by admin.', 'wallets' ),
						__( '&#x2713; Admin accept', 'wallets' )
					);
				}
			}
		}

		$checkbox = $item['admin_confirm'] ? '&#x2611;' : '&#x2610;';

		return sprintf( '%s %s', $checkbox, $this->row_actions( $actions, true ) );

	}

	public function column_user_confirm( $item ) {
		if ( 'trade' == $item['category'] ) {
			return '';
		}

		$actions = array();

		if ( 'unconfirmed' == $item['status'] || 'pending' == $item['status'] ) {

			if ( ( 'withdraw' == $item['category'] && Dashed_Slug_Wallets::get_option( 'wallets_confirm_withdraw_user_enabled' ) ) ||
				( 'move' == $item['category'] && Dashed_Slug_Wallets::get_option( 'wallets_confirm_move_user_enabled' ) ) ) {

				if ( $item['user_confirm'] ) {
					$actions['user_unconfirm'] = sprintf(
						'<a class="button" href="%s" title="%s">%s</a>',
						add_query_arg(
							array(
								'page'     => 'wallets-menu-transactions',
								'action'   => 'user_unconfirm',
								'tx_id'    => $item['id'],
								'paged'    => $this->get_pagenum(),
								'order'    => $this->order,
								'orderby'  => $this->orderby,
								'_wpnonce' => wp_create_nonce( 'wallets-user-unconfirm-' . $item['id'] ),
							),
							call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php' )
						),
						__( 'Mark this transaction as NOT CONFIRMED by user. A new confirmation email will be sent to the user.', 'wallets' ),
						__( '&#x2717; User unaccept', 'wallets' )
					);
				} else {
					$actions['user_confirm'] = sprintf(
						'<a class="button" href="%s" title="%s">%s</a>',
						add_query_arg(
							array(
								'page'     => 'wallets-menu-transactions',
								'action'   => 'user_confirm',
								'tx_id'    => $item['id'],
								'paged'    => $this->get_pagenum(),
								'order'    => $this->order,
								'orderby'  => $this->orderby,
								'_wpnonce' => wp_create_nonce( 'wallets-user-confirm-' . $item['id'] ),
							),
							call_user_func( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_url' : 'admin_url', 'admin.php' )
						),
						__( 'Transaction will be marked as CONFIRMED by user.', 'wallets' ),
						__( '&#x2713; User accept', 'wallets' )
					);
				}
			}
		}

		$checkbox = $item['user_confirm'] ? '&#x2611;' : '&#x2610;';

		return sprintf( '%s %s', $checkbox, $this->row_actions( $actions, true ) );

	}

}
