<?php

/**
 * Tool that assists the admin to move a percentage of all funds offline for security.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

add_action( 'tool_box', function()  {
	if ( ! ds_current_user_can( 'manage_wallets' ) ) {
		return;
	}

	if ( is_net_active() && ! is_main_site() ) {
		return;
	}

	$cold_storage_tools_url = (string) admin_url( 'tools.php?page=wallets-cold-storage' );

	?>
	<div class="card tool-box">
		<h2><?php esc_html_e( 'Bitcoin and Altcoin Wallets: Cold Storage tool', 'wallets' ); ?></h2>

		<p><?php
			printf(
				(string) __(
					'Use the <a href="%s">Cold Storage tool</a> to move a percentage of all funds offline for security.',
					'wallets'
				),
				$cold_storage_tools_url
			);
		?></p>

		<a
			class="wallets-docs button"
			target="_wallets_docs"
			href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=tools#cold-storage' ) ); ?>">
			<?php esc_html_e( 'See the Cold Storage documentation', 'wallets' ); ?></a>

	</div>

	<?php
} );

add_action( 'admin_menu', function() {
	if ( is_net_active() && ! is_main_site() ) {
		return;
	}

	if ( ! ds_current_user_can( 'manage_wallets' ) ) {
		return;
	}

	$cold_storage_tools_url = (string) admin_url( 'tools.php?page=wallets-cold-storage' );

	add_management_page(
		'Bitcoin and Altcoin Wallets cold storage tool',
		'Cold storage',
		'manage_wallets',
		'wallets-cold-storage',
		function() use ( $cold_storage_tools_url ) {
			?>
			<h1>
			<?php
				esc_html_e(
					'Bitcoin and Altcoin Wallets: Cold storage tool',
					'wallets'
				);
			?>
			</h1>

			<?php
				$currencies_list = new class extends \WP_List_Table {
					const PER_PAGE = 20;

					private $order;
					private $orderby;

					public function __construct( $args = [] ) {
						parent::__construct( $args );

						// sorting vars
						$this->order   = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
						$this->orderby = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

						if ( ! $this->order && ! $this->orderby ) {
							$this->orderby = 'created_time';
							$this->order = '';
						}
					}

					public function ajax_user_can() {
						return ds_current_user_can( 'manage_wallets' );
					}

					public function get_columns() {
						return array(
							'adapter'      => esc_html__( 'Adapter type', 'wallets' ),
							'currency'     => esc_html__( 'Currency', 'wallets' ),
							'hotbalance'   => esc_html__( 'Hot Wallet balance', 'wallets' ),
							'userbalances' => esc_html__( 'User balances', 'wallets' ),
							'bar'          => esc_html__( '% of user balances backed by hot wallet', 'wallets' ),
							'action'       => esc_html__( 'Action', 'wallets' ),
						);
					}

					public function get_hidden_columns() {
						return [];
					}

					public function get_sortable_columns() {
						return array(
							'currency'     => array( 'currency', true ),
							'hotbalance'   => array( 'hotbalance', false ),
							'userbalances' => array( 'userbalances', false ),
						);
					}

					public function prepare_items() {
						$this->_column_headers = array(
							$this->get_columns(),
							$this->get_hidden_columns(),
							$this->get_sortable_columns(),
						);

						$currencies = get_all_cryptocurrencies();
						$balances   = get_all_balances_assoc_for_user();

						$this->set_pagination_args(
							array(
								'total_items' => count( $currencies ),
								'per_page'    => self::PER_PAGE,
							)
						);

						$current_page = $this->get_pagenum();

						$currencies_slice = array_slice(
							$currencies,
							( $current_page - 1 ) * self::PER_PAGE,
							self::PER_PAGE,
							false
						);

						$this->items = [];
						foreach ( $currencies_slice as $currency ) {
							$row = new \stdClass();
							$row->currency = $currency;
							$row->hotbalance   = 0;
							if ( $currency->wallet && $currency->wallet->adapter ) {
								try {
									$row->hotbalance = $currency->wallet->adapter->get_hot_balance( $currency );
								} catch ( \Exception $e ) {
									error_log( "Failed to get hot balance for currency {$currency->post_id}: " . $e->getMessage() );
									continue;
								}
							}
							$row->userbalances = $balances[ $currency->post_id ] ?? 0;

							$this->items[] = $row;
						}
					}

					public function column_adapter( \stdClass $row ): void {
						if ( $row->currency->wallet && $row->currency->wallet->adapter ):
						?><code><?php esc_html_e( str_replace( '\\', '\\&#x200b;', get_class( $row->currency->wallet->adapter ) ) ); ?></code><?php
						else:
							?>&mdash;<?php
						endif;
					}

					public function column_currency( \stdClass $row ): void{
						?>
						<a href="<?php esc_attr_e( get_edit_post_link( $row->currency->post_id ) ); ?>"><?php esc_html_e( $row->currency->name ); ?></a>
						<?php
					}

					public function column_hotbalance( \stdClass $row ): void{
						$amount = $row->hotbalance * 10 ** - $row->currency->decimals;
						$amount_string = sprintf( $row->currency->pattern, $amount );
						esc_html_e( $amount_string );
					}

					public function column_userbalances( \stdClass $row ): void{
						$amount = $row->userbalances * 10 ** - $row->currency->decimals;
						$amount_string = sprintf( $row->currency->pattern, $amount );
						esc_html_e( $amount_string );
					}

					public function column_bar( \stdClass $row ): void{
						if ( isset( $row->userbalances ) && $row->userbalances ):
						?>
						<span><?php
							printf(
								'%01.2f%%',
								max( 0, min( 100, 100 *  $row->hotbalance / $row->userbalances ) )
							);
						?></span>

						<progress
							max="<?php esc_attr_e( $row->userbalances ); ?>"
							value="<?php esc_attr_e( min( $row->userbalances, $row->hotbalance ) ); ?>">
						</progress>
						<?php

						else:

						?>&mdash;<?php

						endif;
					}

					public function column_action( \stdClass $row ): void{

						$actions = [];

						$actions['cs_withdraw'] = sprintf(
							'<a class="button" href="%s" title="%s">%s</a>',
							add_query_arg(
								[
									'action'              => 'wallets_cold_storage_withdraw_form',
									'page'                => 'wallets-cold-storage',
									'wallets_currency_id' => $row->currency->post_id,
								],
								admin_url( 'tools.php' )
							),
							(string) __( 'Withdraw from hot wallet to cold storage', 'wallets' ),
							(string) __( 'Withdraw', 'wallets' )
						);

						$actions['cs_deposit'] = sprintf(
							'<a class="button" href="%s" title="%s">%s</a>',
							add_query_arg(
								[
									'action'              => 'wallets_cold_storage_deposit',
									'page'                => 'wallets-cold-storage',
									'wallets_currency_id' => $row->currency->post_id,
								],
								admin_url( 'tools.php' )
							),
							(string) __( 'Deposit to hot wallet from cold storage', 'wallets' ),
							(string) __( 'Deposit', 'wallets' )
						);

						echo $this->row_actions( $actions, true );
					}

				};

				?>
				<p>
				<?php
					esc_html_e(
						'Use the cold storage tool to keep a percentage of user balances online, and store the rest safely in an offline wallet.',
						'wallets'
					);
				?>
				</p>

				<a
					class="wallets-docs button"
					target="_wallets_docs"
					href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=tools#cold-storage' ) ); ?>">
					<?php esc_html_e( 'See the Cold Storage documentation', 'wallets' ); ?></a>

			<?php

			$currencies_list->prepare_items();

			if ( isset( $_REQUEST['action'] ) ) {
				$currency_id = absint( $_REQUEST['wallets_currency_id'] );

				try {
					$currency = Currency::load( $currency_id );
				} catch ( \Exception $e ) {
					wp_die(
						$e->getMessage(),
						sprintf(
							(string) __(
								'Could not load currency %d',
								'wallets'
							),
							$currency_id
						)
					);
				}

				if ( 'wallets_cold_storage_deposit' == $_REQUEST['action'] ) {

					deposit_form_cb( $currency );

				} elseif( 'wallets_cold_storage_withdraw_form' == $_REQUEST['action'] ) {

					withdraw_form_cb( $currency );

				} elseif( 'wallets_cold_storage_withdraw_handler' == $_REQUEST['action'] ) {

					withdraw_handler( $currency );

					?>
					<script type="text/javascript">
					window.location = '<?php echo esc_js( $cold_storage_tools_url ); ?>';
					</script>
					<?php
					exit;
				}

			}

			?>
			<div class="wrap">
			<?php
				$currencies_list->display();
			?>
			</div>
			<?php

			affiliate_banners();
		}
	);
} );

function deposit_form_cb( Currency $currency ): void {

	$deposit_address = get_ds_option( "wallets_cs_address_{$currency->post_id}" );
	$deposit_extra   = get_ds_option( "wallets_cs_extra_{$currency->post_id}" );

	if ( ! $deposit_address ) {
		if ( $currency->wallet && $currency->wallet->adapter ) {
			try {
				$address = $currency->wallet->adapter->get_new_address( $currency );

				update_ds_option(
					"wallets_cs_address_{$currency->post_id}",
					$address->address
				);

				update_ds_option(
					"wallets_cs_extra_{$currency->post_id}",
					$address->extra
				);

				$deposit_address = $address->address;
				$deposit_extra   = $address->extra;

			} catch ( \Exception $e ) {
				wp_die(
					$e->getMessage(),
					sprintf(
						(string) __( 'Cannot get a deposit address from the wallet for currency %d, due to: %s', 'wallets' ),
						$currency->post_id,
						$e->getMessage()
					)
				);
			}
		}

		if ( ! $deposit_address ) {
			wp_die(
				sprintf(
					(string) __( 'Cannot get a deposit address from the wallet for currency %s (%s).', 'wallets' ),
					$currency->name,
					$currency->symbol
				),
				(string) __( 'Cannot create deposit address', 'wallets' )
			);
		}
	}

	?>
	<div
		class="card"
		style="text-align:center;margin:10px auto;">

		<h2><?php
			esc_html_e(
				sprintf(
					(string) __(
						'Replenish your %s hot wallet',
						'wallets'
					),
					$currency->name
				)
			);
		?></h2>

		<?php if ( $currency->icon_url ): ?>
		<img
			style="width:64px"
			src="<?php esc_attr_e( $currency->icon_url ); ?>" />
		<?php endif; ?>

		<p><?php esc_html_e(
			sprintf(
				(string) __(
					'To replenish your %1$s hot wallet, send funds from your %1$s cold storage to the following address:',
					'wallets'
				),
				$currency->name
			)
		); ?></p>

		<div
			class="qrcode"
			style="text-align: center;"
			data-address="<?php esc_attr_e( $deposit_address ); ?>"></div>

		<input
			type="text"
			readonly="readonly"
			onClick="this.select();"
			value="<?php esc_attr_e( $deposit_address ); ?>"
			style="width: 100%; text-align: center;" />

		<?php if ( $deposit_extra ) : ?>

		<input
			type="text"
			readonly="readonly"
			onClick="this.select();"
			value="<?php esc_attr_e( $deposit_extra ); ?>"
			style="width: 100%; text-align: center;" />

		<?php endif; ?>
	</div>

	<?php
};

function withdraw_form_cb( Currency $currency ): void {
	?>
	<form
		method="post"
		class="card"
		style="text-align:center;margin:10px auto;">

		<h2><?php
			printf(
				(string) __(
					'Withdraw %s to cold storage:',
					'wallets'
				),
				$currency->name
			);
		?></h2>

		<?php if ( $currency->icon_url ): ?>
		<img
			style="width:64px"
			src="<?php esc_attr_e( $currency->icon_url ); ?>" />
		<?php endif; ?>

		<p><?php esc_html_e(
			sprintf(
				(string) __(
					'Use this form to transfer funds from your %1$s hot wallet, to your %1$s cold storage wallet',
					'wallets'
				),
				$currency->name
			)
		); ?></p>

		<?php if ( $currency->wallet && $currency->wallet->adapter ): ?>

			<?php wp_nonce_field( "wallets_cs_withdraw_{$currency->post_id}", 'wallets_cs_nonce' ); ?>

			<input
				type="hidden"
				name="action"
				value="wallets_cold_storage_withdraw_handler" />

			<input
				type="hidden"
				name="wallets_currency_id"
				value="<?php esc_attr_e( $currency->post_id ); ?>" />

			<input
				type="number"
				name="wallets_cs_amount"
				placeholder="<?php esc_html_e( 'Amount to withdraw', 'wallets' ); ?>"
				min="0"
				step="<?php echo number_format(
					10 ** - ( $currency->decimals ?? 8 ),
					$currency->decimals,
					'.',
					''
				); ?>"
				max="<?php echo number_format(
					$currency->wallet->adapter->get_hot_balance( $currency ) * 10 ** - ( $currency->decimals ?? 8 ),
					$currency->decimals ?? 8,
					'.',
					''
				); ?>"
				onclick="this.value=Number(this.value).toFixed(this.step.split('.')[1].length)" />

			<input
				type="text"
				name="wallets_cs_address"
				placeholder="<?php esc_html_e( 'External address', 'wallets' ); ?>"
				size="35" />

			<?php
			$extra_field = $currency->wallet->adapter->get_extra_field_name( $currency );
			if ( $extra_field ):
			?>

			<input
				type="text"
				name="wallets_cs_extra"
				placeholder="<?php
					esc_attr_e(
						sprintf(
							__(
								'Εxtra field: %s',
								'wallets'
							),
							$extra_field
						)
					);
				?>"
				size="35" />

			<?php
			endif;
			?>

			<input
				type="submit"
				class="button"
				value="<?php esc_html_e( 'Withdraw to cold storage', 'wallets' ); ?>" />

			<p><?php esc_html_e( 'All the admins with the manage_wallets capability will be notified about the outcome of this transaction by email.', 'wallets' ); ?></p>
		<?php else: ?>

		<p><?php esc_html_e( 'The hot wallet for this currency is offline.', 'wallets' ); ?></p>

		<?php endif; ?>

	</form>

	<?php
};

function withdraw_handler( Currency $currency ): void {
	$amount  = $_REQUEST['wallets_cs_amount']  ?? 0;
	$address = $_REQUEST['wallets_cs_address'] ?? '';
	$extra   = $_REQUEST['wallets_cs_extra']   ?? '';
	$nonce   = $_REQUEST['wallets_cs_nonce']   ?? '';

	wp_verify_nonce(
		$nonce,
		"wallets_cs_withdraw_{$currency->post_id}"
	);

	try {

		$a = new Address();
		$a->address = $address;
		if ( $extra ) {
			$a->extra   = $extra;
		}

		$t = new class extends Transaction {
			public function save(): void {
				// NOOP
			}
		};

		$t->category = 'withdrawal';
		$t->address  = $a;
		$t->currency = $currency;
		$t->amount   = intval( -$amount * 10 ** $currency->decimals );
		$t->status   = 'pending';
		$t->nonce    = '';


		$currency->wallet->adapter->do_withdrawals( [ $t ] );

		if ( 'done' == $t->status ) {

			wp_mail_enqueue_to_admins(
				sprintf(
					"Moved {$currency->pattern} %s to cold storage address %s",
					$amount,
					$currency->name,
					$address
				),
				sprintf(
					"You have successfully moved {$currency->pattern} %s to cold storage address %s %s. The TXID is: %s",
					$amount,
					$currency->name,
					$address,
					$extra ? "and extra field $extra" : '',
					$t->txid
				)
			);

		} else {

			wp_mail_enqueue_to_admins(
				sprintf(
					"Failed to move {$currency->pattern} %s to cold storage address %s %s",
					$amount,
					$currency->name,
					$address,
					$extra ? "and extra field $extra" : ''
				),
				sprintf(
					"You have attempted to move {$currency->pattern} %s to cold storage address %s %s.\nStatus: %s\nError message: %s",
					$amount,
					$currency->name,
					$address,
					$extra ? "and extra field $extra" : '',
					$t->status,
					$t->error ? $t->error : 'n/a'
				)
			);
			return;
		}

	} catch ( \Exception $e ) {

		wp_mail_enqueue_to_admins(
			sprintf(
				"Failed to move {$currency->pattern} %s to cold storage address %s",
				$amount,
				$currency->name,
				$address
			),
			sprintf(
				"You have attempted to move {$currency->pattern} %s to cold storage address %s %s.\nStatus: %s\nException: %s",
				$amount,
				$currency->name,
				$address,
				$extra ? "and extra field $extra" : '',
				$t->status,
				$e->getMessage()
			)
		);

		return;
	}

	error_log(
		sprintf(
			"Moved {$currency->pattern} %s to cold storage address %s %s. The TXID is: %s",
			$amount,
			$currency->name,
			$address,
			$extra ? "and extra field $extra" : '',
			$t->txid
		)
	);

};


function affiliate_banners(): void {
	?>

	<div style="text-align: center;">

		<h2><?php esc_html_e( 'Need a hardware wallet?', 'wallets' ); ?></h2>


		<a
			href="https://shop.ledger.com?r=fd5d"
			title="<?php
				esc_attr_e(
					'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.',
					'wallets'
				);
		?>">

			<img
				width="320" height="50"
				alt="Ledger Nano X - Keep your crypto secure, everywhere"
				src="http://www.ledgerwallet.com/affiliate/image/320/50" />
		</a>

		<br />

		<a
			href="https://shop.trezor.io/product/trezor-model-t-reserve?offer_id=113&aff_id=3798"
			title="<?php
			esc_attr_e(
				'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.',
				'wallets'
			);
		?>">

			<img
				width="100" height="100"
				alt="TTrezor Model T Reserve"
				src="https://shop.trezor.io/static/img/product/Trezor-Model-T-front-view-screen-lock.png" />

		</a>

		<p><?php esc_html_e( 'You are responsible for the money people deposit on your site. ', 'wallets' ); ?></p>
		<p><?php esc_html_e( 'Get a hardware wallet and sleep easier!', 'wallets' ); ?></p>

	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', function() {
	if ( isset( $_REQUEST['action'] ) && 'wallets_cold_storage_deposit' == $_REQUEST['action'] ) {

		wp_enqueue_script( 'wallets-admin-cs-tool' );

	}
} );
