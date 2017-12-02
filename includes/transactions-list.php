<?php

/**
 * This is the transactions list that appears in the main "Wallets" admin screen.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class DSWallets_Admin_Menu_TX_List extends WP_List_Table {

	const PER_PAGE = 10;

	public function get_columns() {
		return array(
			// 'cb' => '<input type="checkbox" />', // TODO bulk actions
			'txid' => esc_html__( 'Transaction ID', 'wallets' ),
			'category' => esc_html__( 'Type', 'wallets' ),
			'symbol' => esc_html__( 'Coin', 'wallets' ),
			'amount' => esc_html__( 'Amount (+fee)', 'wallets' ),
			'fee' => esc_html__( 'Fee', 'wallets' ),
			'from' => esc_html__( 'From', 'wallets' ),
			'to' => esc_html__( 'To', 'wallets' ),
			'comment' => esc_html__( 'Comment', 'wallets' ),
			'tags' => esc_html__( 'Tags', 'wallets' ),
			'created_time' => esc_html__( 'Time', 'wallets' ),
			'confirmations' => esc_html__( 'Confirmations', 'wallets' ),
			'status' => esc_html__( 'Status', 'wallets' ),
			'retries' => esc_html__( 'Retries left', 'wallets' ),
			'admin_confirm' => esc_html__( 'Accepted by admin', 'wallets' ),
			'user_confirm' => esc_html__( 'Verified by user', 'wallets' ),
		);
	}

	public function get_hidden_columns() {
		return array();
	}

	public function get_sortable_columns() {
        return array(
			'created_time' => array( 'created_time', true ),
			'amount' => array( 'amount', false ),
			'confirmations' => array( 'confirmations', false ),
			'retries' => array( 'retries', false ),
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
		$total_items = $wpdb->get_var( $wpdb->prepare(
			"
			SELECT
				COUNT(*)
			FROM
				$table_name_txs
			WHERE
				blog_id = %d
			",
			get_current_blog_id() ) );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => self::PER_PAGE
		) );

		// sorting
		$order = empty( $_GET['order'] ) ? 'asc' : filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
		$orderby = empty( $_GET['orderby'] ) ? 'created_time' : filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );

		// get data
		$sql_query = $wpdb->prepare(
			"
			SELECT
				txs.id,
				txs.txid,
				txs.category,
				txs.symbol,
				txs.amount,
				txs.fee,
				txs.address,
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
				blog_id = %d
			ORDER BY
				$orderby $order
			LIMIT
				%d, %d
			",
			get_current_blog_id(),
			self::PER_PAGE * ( $current_page - 1 ),
			self::PER_PAGE
		);
		$this->items = $wpdb->get_results( $sql_query, ARRAY_A );
	}

	public function column_default( $item, $column_name ) {

		switch( $column_name ) {

			case 'txid':
			case 'category':
			case 'symbol':
			case 'comment':
			case 'tags':
			case 'created_time':
			case 'status':
				return esc_html( $item[ $column_name ] );

			case 'admin_confirm':
			case 'user_confirm':
				return mb_convert_encoding( $item[ $column_name ] ? '&#x2611;' : '&#x2610;', 'UTF-8', 'HTML-ENTITIES' );

			case 'from':
				if ( 'deposit' == $item['category'] ) {
					return  esc_html( $item['address'] );
				} elseif ( 'withdraw' == $item['category'] ) {
					return esc_html( $item['account_name'] );
				} elseif ( 'move' == $item['category'] ) {
					return esc_html( $item['account_name'] );
				}
				break;

			case 'to':
				if ( 'deposit' == $item['category'] ) {
					return  esc_html( $item['account_name'] );
				} elseif ( 'withdraw' == $item['category'] ) {
					return esc_html( $item['address'] );
				} elseif ( 'move' == $item['category'] ) {
					return esc_html( $item['other_account_name'] );
				}
				break;

			case 'amount':
			case 'fee':
				try {
					$adapter = Dashed_Slug_Wallets::get_instance()->get_coin_adapters( $item['symbol'] );
					return sprintf( $adapter->get_sprintf(), $item[ $column_name ] );
				} catch ( Exception $e ) {
					return $item[ $column_name ];
				}

			case 'confirmations':
				return 'move' == $item['category'] ? '' : esc_html( $item[ 'confirmations' ] );

			case 'retries':
				if ( 'withdraw' != $item['category'] ) {
					return '';
				}
				if ( 'unconfirmed' == $item['status'] || 'pending' == $item['status'] ) {
					return intval( $item['retries'] );
				}
				return '';

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

	public function column_admin_confirm( $item ) {
		// sorting vars
		$order = empty( $_GET['order'] ) ? 'asc' : filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
		$orderby = empty( $_GET['orderby'] ) ? 'created_time' : filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );


		$actions = array();

		if ( 'unconfirmed' == $item['status'] || 'pending' == $item['status'] ) {

			if ( ( 'withdraw' == $item['category'] && get_option( 'wallets_confirm_withdraw_admin_enabled' ) ) ||
				( 'move' == $item['category'] && get_option( 'wallets_confirm_move_admin_enabled' ) ) ) {

				if ( $item['admin_confirm'] ) {
					$actions['admin_unconfirm'] = sprintf( '<a href="%s" title="%s">%s</a>',
						add_query_arg(
							array(
								'page' => 'wallets-menu-transactions',
								'action' => 'admin_unconfirm',
								'tx_id' => $item['id'],
								'paged' => $this->get_pagenum(),
								'order' => $order,
								'orderby' => $orderby,
								'_wpnonce' => wp_create_nonce( 'wallets-admin-unconfirm-' . $item['id'] )
							),
							admin_url( 'admin.php' )
						),
						__( 'Mark this transaction as NOT CONFIRMED by admin. Will NOT be retried if admin confirmation is required.', 'wallets' ),
						__( 'Admin unaccept', 'wallets' )
					);
				} else {
					$actions['admin_confirm'] = sprintf( '<a href="%s" title="%s">%s</a>',
						add_query_arg(
							array(
								'page' => 'wallets-menu-transactions',
								'action' => 'admin_confirm',
								'tx_id' => $item['id'],
								'paged' => $this->get_pagenum(),
								'order' => $order,
								'orderby' => $orderby,
								'_wpnonce' => wp_create_nonce( 'wallets-admin-confirm-' . $item['id'] )
							),
							admin_url( 'admin.php' )
						),
						__( 'Transaction will be marked as CONFIRMED by admin.', 'wallets' ),
						__( 'Admin accept', 'wallets' )
					);
				}
			}
		}

		$checkbox = mb_convert_encoding( $item[ 'admin_confirm' ] ? '&#x2611;' : '&#x2610;', 'UTF-8', 'HTML-ENTITIES' );

		return sprintf( '%s %s', $checkbox, $this->row_actions( $actions, true ) );

	}

	public function column_user_confirm( $item ) {
		// sorting vars
		$order = empty( $_GET['order'] ) ? 'asc' : filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
		$orderby = empty( $_GET['orderby'] ) ? 'created_time' : filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );

		$actions = array();

		if ( 'unconfirmed' == $item['status'] || 'pending' == $item['status'] ) {

			if ( ( 'withdraw' == $item['category'] && get_option( 'wallets_confirm_withdraw_user_enabled' ) ) ||
				( 'move' == $item['category'] && get_option( 'wallets_confirm_move_user_enabled' ) ) ) {

				if ( $item['user_confirm'] ) {
					$actions['user_unconfirm'] = sprintf( '<a href="%s" title="%s">%s</a>',
						add_query_arg(
							array(
								'page' => 'wallets-menu-transactions',
								'action' => 'user_unconfirm',
								'tx_id' => $item['id'],
								'paged' => $this->get_pagenum(),
								'order' => $order,
								'orderby' => $orderby,
								'_wpnonce' => wp_create_nonce( 'wallets-user-unconfirm-' . $item['id'] )
							),
							admin_url( 'admin.php' )
						),
						__( 'Mark this transaction as NOT CONFIRMED by user. A new confirmation email will be sent to the user.', 'wallets' ),
						__( 'User unaccept', 'wallets' )
					);
				} else {
					$actions['user_confirm'] = sprintf( '<a href="%s" title="%s">%s</a>',
						add_query_arg(
							array(
								'page' => 'wallets-menu-transactions',
								'action' => 'user_confirm',
								'tx_id' => $item['id'],
								'paged' => $this->get_pagenum(),
								'order' => $order,
								'orderby' => $orderby,
								'_wpnonce' => wp_create_nonce( 'wallets-user-confirm-' . $item['id'] )
							),
							admin_url( 'admin.php' )
						),
						__( 'Transaction will be marked as CONFIRMED by user.', 'wallets' ),
						__( 'User accept', 'wallets' )
					);
				}
			}
		}

		$checkbox = mb_convert_encoding( $item[ 'user_confirm' ] ? '&#x2611;' : '&#x2610;', 'UTF-8', 'HTML-ENTITIES' );

		return sprintf( '%s %s', $checkbox, $this->row_actions( $actions, true ) );

	}

	public function column_retries( $item ) {
		// sorting vars
		$order = empty( $_GET['order'] ) ? 'asc' : filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
		$orderby = empty( $_GET['orderby'] ) ? 'created_time' : filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );

		$actions = array();
		if ( 'done' != $item['status'] && 'deposit' != 'category' ) {
			$actions['reset_retries'] = sprintf( '<a href="%s" title="%s">%s</a>',
				add_query_arg(
					array(
						'page' => 'wallets-menu-transactions',
						'action' => 'reset_retries',
						'tx_id' => $item['id'],
						'paged' => $this->get_pagenum(),
						'order' => $order,
						'orderby' => $orderby,
						'_wpnonce' => wp_create_nonce( 'wallets-reset-retries-' . $item['id'] )
					),
					admin_url( 'admin.php' )
					),
				__( 'Reset the number of retries for this transaction', 'wallets' ),
				__( 'Reset retries', 'wallets' )
			);
		}

		return sprintf( '%d %s', $item['retries'], $this->row_actions( $actions, true ) );

	}
}