<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly

if ( ! $atts['symbol'] ) {
	throw new Exception( "Static view of this shortcode requires a symbol attribute!" );
}

$search_params = array(
	'symbol'     => $atts['symbol'],
	'user_id'    => $atts['user_id'],
	'count'      => $atts['rowcount'],
);

if ( $atts['categories'] ) {
	$search_params['categories'] = $atts['categories'];
}

if ( $atts['tags'] ) {
	$search_params['tags'] = $atts['tags'];
}
$transactions = apply_filters( 'wallets_api_transactions', null, $search_params );

$adapters = apply_filters( 'wallets_api_adapters', array() );
$adapter = array_key_exists( $atts['symbol'], $adapters ) ? $adapters[ $atts['symbol'] ] : false;
unset( $search_params, $adapters );

?>

	<form class="dashed-slug-wallets transactions static transactions-static wallets-ready">
		<?php
			do_action( 'wallets_ui_before' );
			do_action( 'wallets_ui_before_transactions' );

		?>

		<label class="coin">
			<?php echo apply_filters( 'wallets_ui_text_coin', esc_html__( 'Coin', 'wallets-front' ) ); ?>:
			<input
				type="text"
				readonly="readonly"
				value="<?php echo esc_attr( $atts['symbol'] ); ?>"
				/>
		</label>

		<p style="text-align: center;">&mdash;</p>

		<table>
			<thead>
				<tr>
					<?php foreach ( $atts['columns'] as $column ): ?>
					<th class="<?php echo esc_attr( $column ); ?>">
						<?php

						switch( $column ) {
							case 'type':
								echo apply_filters( 'wallets_ui_text_type', esc_html__( 'Type', 'wallets-front' ) );
								break;

							case 'tags':
								echo apply_filters( 'wallets_ui_text_tags', esc_html__( 'Tags', 'wallets-front' ) );
								break;

							case 'time':
								echo apply_filters( 'wallets_ui_text_time', esc_html__( 'Time', 'wallets-front' ) );
								break;

							case 'amount':
								echo apply_filters( 'wallets_ui_text_amountplusfee', esc_html__( 'Amount (+fee)', 'wallets-front' ) );
								break;

							case 'fee':
								echo apply_filters( 'wallets_ui_text_fee', esc_html__( 'Fee', 'wallets-front' ) );
								break;

							case 'from_user':
								echo apply_filters( 'wallets_ui_text_from', esc_html__( 'From', 'wallets-front' ) );
								break;

							case 'to_user':
								echo apply_filters( 'wallets_ui_text_to', esc_html__( 'To', 'wallets-front' ) );
								break;

							case 'txid':
								echo apply_filters( 'wallets_ui_text_txid', esc_html__( 'Tx ID', 'wallets-front' ) );
								break;

							case 'comment':
								echo apply_filters( 'wallets_ui_text_comment', esc_html__( 'Comment', 'wallets-front' ) );
								break;

							case 'confirmations':
								echo apply_filters( 'wallets_ui_text_confirmations', esc_html__( 'Confirmations', 'wallets-front' ) );
								break;

							case 'status':
								echo apply_filters( 'wallets_ui_text_status', esc_html__( 'Status', 'wallets-front' ) );
								break;

							case 'retries':
								echo apply_filters( 'wallets_ui_text_retriesleft', esc_html__( 'Retries&nbsp;left', 'wallets-front' ) );
								break;

							case 'admin_confirm':
								echo apply_filters( 'wallets_ui_text_adminconfirm', esc_html__( 'Admin&nbsp;confirm', 'wallets-front' ) );
								break;

							case 'user_confirm':
								echo apply_filters( 'wallets_ui_text_userconfirm', esc_html__( 'User&nbsp;confirm', 'wallets-front' ) );
								break;
						};

						?>
					</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>

				<?php foreach ( $transactions as $tx ): ?>
				<tr class="<?php echo esc_attr( $tx->category ); ?> <?php echo esc_attr( $tx->status ); ?>">
					<?php foreach ( $atts['columns'] as $column ): ?>
					<td class="<?php echo esc_attr( $column ); ?>">
					<?php

					switch( $column ) {

						case 'type':
							echo $tx->category;
							break;

						case 'time':
							echo get_date_from_gmt( $tx->created_time );
							break;

						case 'amount':
						case 'fee':
							$pattern = $adapter ? $adapter->get_sprintf() : '%01.8f';
							echo esc_html( sprintf( $pattern, $tx->{$column} ) );
							break;

						case 'from_user':
						case 'to_user':

							if ( 'move' == $tx->category ) {
								if ( $tx->amount > 0 ) {
									$tx->from_user = $tx->other_account;
									$tx->to_user   = $tx->account;
								} else {
									$tx->from_user = $tx->account;
									$tx->to_user   = $tx->other_account;
								}
								if ( $atts['user_id'] == $tx->{$column} ) {
									echo apply_filters( 'wallets_ui_text_me', esc_attr__( 'me', 'wallets-front' ) );
									break;
								}
							}
							if ( ( 'deposit'  == $tx->category && 'from_user' == $column )   ||
								 ( 'withdraw' == $tx->category && 'to_user'   == $column ) ) {

								$uri_pattern = apply_filters( 'wallets_explorer_uri_add_' . $tx->symbol, '' );
								if ( $uri_pattern && preg_match( '/^[\w\d]+$/', $tx->address ) ) {
									$uri          = sprintf( $uri_pattern, $tx->address );
									$address_html = '<a href="' . esc_attr( $uri ) . '">' . $tx->address . '</a>';
								} else {
									$address_html = $tx->address;
								}
								if ( $tx->extra ) {
									$address_html .= " ({$tx->extra})";
								}
								echo $address_html;
								break;
							}
							echo esc_html( $tx->other_account_name );
							break;

						case 'txid':
							if ( 'move' != $tx->category && preg_match( '/^[\w\d]+$/', $tx->txid ) ) {
								$uri_pattern = apply_filters( 'wallets_explorer_uri_tx_' . $tx->symbol, '' );
								if ( $uri_pattern ) {
									$uri = sprintf( $uri_pattern, $tx->txid );
									echo '<a href ="' . esc_attr( $uri ) . '">' . $tx->txid . '</a>';
									break;
								}
							}
							echo esc_html( $tx->txid );
							break;

						case 'admin_confirm':
						case 'user_confirm':
							echo $tx->{$column} ? '&#x2611;' : '&#x2610;';
							break;

						default:
							echo $tx->{$column} ? esc_html( $tx->{$column} ) : '&mdash;';
					};
					?>
					</td>
					<?php endforeach; ?>
				</tr>
				<?php endforeach; ?>

			</tbody>
		</table>
		<?php
			do_action( 'wallets_ui_after_transactions' );
			do_action( 'wallets_ui_after' );
		?>
	</form>
<?php

unset( $transactions, $adapter );

?>
