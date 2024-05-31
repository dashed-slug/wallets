<?php

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

/**
 * Tool that assists the admin to monitor and control the Migration cron task.
 *
 * The tool lets the admin select parameters that will control the migration cron task.
 * It also allows the admin to monitor this task.
 *
 *
 * @see Migration_Task
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

add_action(
	'tool_box',
	function() {
		if ( ! ds_current_user_can( 'manage_wallets' ) ) {
			return;
		}

		if ( is_net_active() && ! is_main_site() ) {
			return;
		}

		?>
		<div class="card tool-box">
			<h2><?php esc_html_e( 'Bitcoin and Altcoin Wallets: Wallets Migration', 'wallets' ); ?></h2>

			<p><?php
				printf(
					__(
						'Use the <a href="%s">Migration tool</a> to monitor and control the migration of data ' .
						'(addresses, and either transations or balances), from earlier versions of the ' .
						'Bitcoin and Altcoin Wallets plugin.',
						'wallets'
					),
					admin_url( 'tools.php?page=wallets_migration' )
				);
			?></p>

			<a
				class="wallets-docs button"
				target="_wallets_docs"
				href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=migration' ) ); ?>">
				<?php esc_html_e( 'See the Migration documentation', 'wallets' ); ?></a>

		</div>

		<?php
	}
);

function migration_type_descriptions( $type ): string {
	global $wpdb;

	$prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
	$table_name_txs  = "{$prefix}wallets_txs";
	$table_name_adds = "{$prefix}wallets_adds";

	switch ( $type ) {
		case 'transactions':
			return sprintf(
				__(
					'All address SQL rows in the %1$s table will be converted to CPTs of type "wallets_adds". ' .
					'All transaction SQL rows in the %2$s table will be converted to CPTs of type "wallets_txs". ' .
					'Any SQL ticker symbols that do not yet correspond to currency CPTs will be created. The plugin will try to guess which currency it is using CoinGecko and fixer.io data. ' .
					'The "migrated" tag will be attached to all newly created posts. ' .
					'This will run very slow IF you have many transactions on the DB.',
					'wallets'
				),
				$table_name_adds,
				$table_name_txs
			);

		case 'balances':
			return sprintf(
				__(
					'All eligible rows in the %1$s custom SQL table will be converted to posts of type "wallets_adds". ' .
					'Any SQL ticker symbols that do not yet correspond to currency CPTs will be created. The plugin will try to guess which currency it is using CoinGecko and fixer.io data. ' .
					'For each user and currency, ONE post of type "wallets_txs" will be created. ' .
					'This one transaction post will transfer the entire user balance for that currency. ' .
					'The user\'s past transactions from the %2$s custom SQL table will NOT be migrated. ' .
					'The "migrated" tag will be attached to all newly created posts. ' .
					'This will run reasonably fast, even if you have many transactions on the DB.',
					'wallets'
				),
				$table_name_adds,
				$table_name_txs
			);

		case 'revert':
			return __(
				'All posts of types "wallets_currency", "wallets_address" and "wallets_tx" having the "migrated" tag will be deleted. ' .
				'Once all posts are deleted, you will be able to retry the migration. ',
				'wallets'
			);

		case 'none':
			return 'Migration task is stopped. No data will be migrated.';

	}
}

add_action(
	'admin_menu',
	function() {
		if ( is_net_active() && ! is_main_site() ) {
			return;
		}

		if ( ! ds_current_user_can( 'manage_wallets' ) ) {
			return;
		}

		add_management_page(
			'Bitcoin and Altcoin Wallets migration',
			'Wallets Migration',
			'manage_wallets',
			'wallets_migration',
			function() {

				$migration_state = get_ds_option(
					'wallets_migration_state',
					[
						'type'           => 'none',
						'add_count'      => false, // false means that addresses have not been counted yet
						'add_count_ok'   => 0,
						'add_count_fail' => 0,
						'add_last_id'    => 0, // becomes false after address migration finishes

						'tx_count'       => false, // false means that txs have not been counted yet
						'tx_count_ok'    => 0,
						'tx_count_fail'  => 0,
						'tx_last_id'     => 0, // becomes false after transaction migration finishes

						'bal_last_uid'   => 0, // becomes false after balances migration finishes
						'bal_pending'    => [], // tuples of [ [currency_id, amount], ... ] to be written asyncronously for curreny uid
					]
				);

				if ( isset( $_POST['wallets_migration_type'] ) ) {

					if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'wallets_migration_task' ) ) {
						wp_die( 'Please try again!', 'Nonce validation failed' );
					}

					if ( ! in_array( $_POST['wallets_migration_type'], [ 'transactions', 'balances', 'revert', 'none' ] ) ) {
						wp_die( 'Invalid migration type', 'Bitcoin and Altcoin Wallets Wallets Migration' );
						exit;
					}

					$migration_state['type'] = $_POST['wallets_migration_type'];

					if ( in_array( $_POST['wallets_migration_type'], [ 'transactions', 'balances', 'revert' ] ) ) {

						// reset address counts
						$migration_state['add_count']      = false;
						$migration_state['add_count_ok']   = 0;
						$migration_state['add_count_fail'] = 0;
						$migration_state['add_last_id']    = 0;

						// reset transaction counts
						$migration_state['tx_count']      = false;
						$migration_state['tx_count_ok']   = 0;
						$migration_state['tx_count_fail'] = 0;
						$migration_state['tx_last_id']    = 0;

						// reset balances counters
						$migration_state['bal_last_uid']  = 0;
						$migration_state['bal_pending']   = [];
					}


					update_ds_option(
						'wallets_migration_state',
						$migration_state
					);

					if ( 'balances' == $_POST['wallets_migration_type'] ) {

						// We don't want the plugin to be re-processing any deposits
						// having timestamps earlier than the start of the last migration.
						$deposit_cutoff = get_ds_option( 'wallets_deposit_cutoff', 0 );
						if ( time() > $deposit_cutoff ) {
							update_ds_option( 'wallets_deposit_cutoff', time() );
						}
					}

				}

				?>
				<h1>
				<?php
					esc_html_e(
						'Bitcoin and Altcoin Wallets: Migration tool',
						'wallets'
					);
				?>
				</h1>

				<?php
					$currencies_list = new class extends \WP_List_Table {


						public function __construct( $args = [] ) {
							parent::__construct( $args );
						}

						public function ajax_user_can() {
							return ds_current_user_can( 'manage_wallets' );
						}

						public function get_columns() {
							return array(
								'sql_symbol'       => esc_html__( 'SQL symbol', 'wallets' ),
								'cpt_currency'     => esc_html__( 'CPT Currency', 'wallets' ),
								'sql_balance_sums' => esc_html__( 'SQL Sum of User balances', 'wallets' ),
								'cpt_balance_sums' => esc_html__( 'CPT Sum of User balances', 'wallets' ),
							);
						}

						public function get_hidden_columns() {
							return [];
						}

						public function prepare_items() {
							$this->_column_headers = array(
								$this->get_columns(),
								$this->get_hidden_columns(),
								$this->get_sortable_columns(),
							);

							$this->items = [];

							global $wpdb;

							$prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
							$table_name_txs  = "{$prefix}wallets_txs";
							$table_name_adds = "{$prefix}wallets_adds";

							$wpdb->flush();

							$balances_query =
								"
									SELECT
										symbol AS sql_symbol,
										SUM( IF( t.amount > 0, t.amount - t.fee, t.amount ) ) AS sql_balance_sums

									FROM
										{$table_name_txs} t
									WHERE
										account IN (SELECT ID FROM {$wpdb->users}) AND
								";

							if ( ! is_net_active() ) {
								$balances_query .= $wpdb->prepare(
									't.blog_id = %d AND ', get_current_blog_id()
								);
							}

							$balances_query .=
								"t.status = 'done' GROUP BY t.symbol ORDER BY t.symbol";

							$result = $wpdb->get_results( $balances_query );

							if ( false === $result ) {
								throw new \Exception(
									sprintf(
										'%s: Failed with: %s',
										__METHOD__,
										$wpdb->last_error
									)
								);
							}

							// get sql symbols and balance sums

							$this->items = $wpdb->get_results( $balances_query, OBJECT_K );

							$cpt_balances = get_all_balances_assoc_for_user();

							// match to currencies
							foreach ( $this->items as $symbol => &$row ) {

								$row->cpt_currency = get_first_currency_by_symbol( $symbol );

								if ( $row->cpt_currency && $row->cpt_currency->post_id && isset( $cpt_balances[ $row->cpt_currency->post_id ] ) ) {
									$row->cpt_balance_sums =
										$cpt_balances[ $row->cpt_currency->post_id ] *
										10 ** - $row->cpt_currency->decimals;
								} else {
									$row->cpt_balance_sums = $row->cpt_currency ? 0 : null;
								}
							}

						}

						public function column_sql_symbol( \stdClass $row ): void {
							if ( $row->sql_symbol ):
								printf( '<code>%s</code>', $row->sql_symbol );
							else:
								?>&mdash;<?php
							endif;
						}

						public function column_cpt_currency( \stdClass $row ): void {
							if ( $row->cpt_currency ):

								edit_post_link(
									$row->cpt_currency->name,
									null,
									null,
									$row->cpt_currency->post_id
								);

							else:
								?>
								<span style="color:red;"><?php
									esc_html_e(
										sprintf(
											__( '%1$s MISSING - May not be migrated correctly %1$s', 'wallets' ),
											'&#x26A0;'
										)
									);
								?>
								</span>
								<?php
							endif;
						}

						public function column_sql_balance_sums( \stdClass $row ): void {
							if ( $row->sql_balance_sums ):
								printf( '<code>%01.8f</code>', $row->sql_balance_sums );
							else:
								?>&mdash;<?php
							endif;
						}

						public function column_cpt_balance_sums( \stdClass $row ): void {
							if ( ! is_null( $row->cpt_balance_sums ) ):
								printf( "<code>{$row->cpt_currency->pattern}</code>", $row->cpt_balance_sums );
							else:
								?>&mdash;<?php
							endif;
						}

					}

					?>
					<p>
					<?php
						esc_html_e(
							'The migration tool helps the admin to migrate user data from past versions of the Bitcoin and Altcoin Wallets plugin.',
							'wallets'
						);
					?>
					</p>

					<a
						class="wallets-docs button"
						target="_wallets_docs"
						href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=migration' ) ); ?>">
						<?php esc_html_e( 'See the Migration documentation', 'wallets' ); ?></a>

				<?php

				$currencies_list->prepare_items();

				?>
				<div
					style="border:1px solid black;margin:1em 0;padding:1em;"
					class="wrap">
					<h2><?php esc_html_e( 'Migration status overview', 'wallets' ); ?></h2>

					<p>
						<strong><?php esc_html_e( 'BEFORE you initiate migration:', 'wallets' ); ?></strong>
						<span><?php esc_html_e( 'Ensure that all currencies that you wish to migrate have been created.', 'wallets' )?></span>
						<span><?php esc_html_e( 'The plugin will try to create missing Currencies, but it can make mistakes. It\'s much better if you first create all the currencies manually.', 'wallets' )?></span>
					</p>

					<p>
						<strong><?php esc_html_e( 'AFTER migration finishes:', 'wallets' ); ?></strong>
						<span><?php esc_html_e( 'Check that the sums of user balances match. The SQL values must be same as the CPT values.', 'wallets' )?></span>
					</p>

					<?php $currencies_list->display(); ?>

					<?php if ( in_array( $migration_state['type'], [ 'transactions', 'balances', 'revert', 'none' ] ) ): ?>
					<dl>
						<dt><?php esc_html_e( 'Current cron job', 'wallets' ); ?></dt>
						<dd><?php esc_html_e( $migration_state['type'] ); ?></dd>

						<?php if ( in_array( $migration_state['type'], [ 'transactions', 'balances' ] ) ): ?>

						<dt><?php esc_html_e( 'Total addresses in SQL tables', 'wallets' ); ?></dt>
						<dd><?php esc_html_e( json_encode( $migration_state['add_count'] ) ); ?></dd>

						<dt><?php esc_html_e( 'Addresses migrated to CPT successfully', 'wallets' ); ?></dt>
						<dd><?php esc_html_e( json_encode( $migration_state['add_count_ok'] ) ); ?></dd>

						<dt><?php esc_html_e( 'Addresses that could not be migrated to CPT', 'wallets' ); ?></dt>
						<dd><?php esc_html_e( json_encode( $migration_state['add_count_fail'] ) ); ?></dd>

						<?php endif; ?>

						<?php if ( 'transactions' == $migration_state['type'] ): ?>

						<dt><?php esc_html_e( 'Total transactions in SQL tables', 'wallets' ); ?></dt>
						<dd><?php esc_html_e( json_encode( $migration_state['tx_count'] ) ); ?></dd>

						<dt><?php esc_html_e( 'Transactions migrated to CPT successfully', 'wallets' ); ?></dt>
						<dd><?php esc_html_e( json_encode( $migration_state['tx_count_ok'] ) ); ?></dd>

						<dt><?php esc_html_e( 'Transactions that could not be migrated to CPT', 'wallets' ); ?></dt>
						<dd><?php esc_html_e( json_encode( $migration_state['tx_count_fail'] ) ); ?></dd>

						<?php endif; ?>

						<?php if ( 'balances' == $migration_state['type'] ): ?>

						<dt><?php esc_html_e( 'Currently migrating balances for user ID', 'wallets' ); ?></dt>
						<dd><?php echo absint( $migration_state['bal_last_uid'] ); ?></dd>

						<dt><?php esc_html_e( 'Number of balances remaining to be created for this user', 'wallets' ); ?></dt>
						<dd><?php echo count( $migration_state['bal_pending'] ); ?></dd>

						<?php endif; ?>

					</dl>
				</div>
				<?php endif; ?>

				<div
					style="border:1px solid black;margin:1em 0;padding:1em;"
					class="wrap">

					<h2><?php esc_html_e( 'Migration control', 'wallets' ); ?></h2>

					<p>
						<strong>
						<?php
							printf(
								__(
									'DO NOT press any buttons before you read the <a target="_blank" href="%s">documentation</a> carefully.',
									'wallets'
								),
								admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=migration' )
							);
						?>
						</strong>
					</p>

					<form method="POST">

						<?php wp_nonce_field( 'wallets_migration_task' ); ?>

						<fieldset>
							<p>
								<button
									class="button"
									name="wallets_migration_type"
									<?php disabled( 'none' != $migration_state['type'] || ( false === $migration_state['add_last_id'] && false === $migration_state['tx_last_id'] ) || ( false === $migration_state['bal_last_uid'] ) ); ?>
									value="transactions">
									<?php
										esc_html_e( 'Migrate all addresses and all transactions (slow)', 'wallets' );
									?>
								</button>

								<?php printf( migration_type_descriptions( 'transactions' ) ); ?>
							</p>
						</fieldset>

						<fieldset>
							<p>
								<button
									class="button"
									name="wallets_migration_type"
									<?php disabled( 'none' != $migration_state['type'] || ( false === $migration_state['add_last_id'] && false === $migration_state['tx_last_id'] ) || ( false === $migration_state['bal_last_uid'] ) ); ?>
									value="balances">
									<?php
										esc_html_e( 'Migrate all addresses and balances only (fast)', 'wallets' );
									?>
								</button>
								<?php printf( migration_type_descriptions( 'balances' ) ); ?>
							</p>
						</fieldset>

						<fieldset>
							<p>
								<button
									class="button"
									name="wallets_migration_type"
									<?php disabled( 'revert' == $migration_state['type'] ); ?>
									value="revert">
									<?php
										esc_html_e( 'Revert a previous migration', 'wallets' );
									?>
								</button>
								<?php printf( migration_type_descriptions( 'revert' ) ); ?>
							</p>

						</fieldset>

						<fieldset>
							<p>
								<button
									class="button"
									name="wallets_migration_type"
									<?php disabled( 'none' == $migration_state['type'] ); ?>
									value="none">
									<?php
										esc_html_e( 'Pause/stop current migration', 'wallets' );
									?>
								</button>
								<?php printf( migration_type_descriptions( 'none' ) ); ?>
							</p>
						</fieldset>

					</form>
				</div>
				<?php
			}
		);
	}
);


// Shown only while the migration state is not yet entered via the tool
// Shown only if the two tables exist
// Tells the user to go to the tool
add_action(
	'admin_notices',
	function() {
		if ( ! ds_current_user_can( 'manage_wallets' ) ) {
			return;
		}

		// check if already in the tool page
		global $pagenow;
		if ( 'tools.php' == $pagenow && array_key_exists( 'page', $_GET ) && 'wallets_migration' === $_GET['page'] ) {
			return;
		}

		// check if migration already finished
		$migration_state = get_ds_option( 'wallets_migration_state' );

		if (
			$migration_state
			&& 'none' === $migration_state['type']
			&& false === $migration_state['add_last_id']
			&& (
				false === $migration_state['tx_last_id']
				|| false === $migration_state['bal_last_uid']
			)
		) {
			return;
		}

		global $wpdb;

		$prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
		$table_name_txs  = "{$prefix}wallets_txs";
		$table_name_adds = "{$prefix}wallets_adds";

		if (
			$table_name_txs  != $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_txs}'"  ) ||
			$table_name_adds != $wpdb->get_var( "SHOW TABLES LIKE '{$table_name_adds}'" )
		) {
			// SQL tables don't exist, no point in notifying the user
			return;
		}

		maybe_switch_blog();

		$tool_url = admin_url( 'tools.php?page=wallets_migration' );
		$docs_url = admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=migration' );

		maybe_restore_blog();

		?>
		<div class="notice notice-success is-dismissible">
			<h1><?php esc_html_e( 'Bitcoin and Altcoin Wallets data migration tool', 'wallets' ); ?></h1>

			<p><?php esc_html_e(
				'Your DB contains custom SQL data (transactions and addresses) from a previous version of the plugin.',
				'wallets'
			); ?></p>

			<?php if ( $migration_state ): ?>

			<p><?php printf(
				'<strong>Current job:</strong> %1$s<hr />To view the progress, visit the <a href="%2$s">%3$s</a>.',
				migration_type_descriptions( $migration_state['type'] ),
				$tool_url,
				__( 'migration tool', 'wallets' )
			); ?></p>

			<?php else: ?>
			<p><?php printf(
				'You should migrate the old SQL data to the new CPT format using the <a href="%1$s">%2$s</a>.',
				admin_url( 'tools.php?page=wallets_migration' ),
				__( 'migration tool', 'wallets' )
			); ?></p>
			<?php endif; ?>

			<a
				class="button"
				style="margin: 1.5em;"
				href="<?php esc_attr_e( $tool_url ); ?>"
				rel="bookmark nofollow"><?php esc_html_e(
				'Migration tool',
				'wallets'
			); ?></a>

			<a
				class="button"
				target="_blank"
				style="margin: 1.5em;"
				href="<?php esc_attr_e( $docs_url ); ?>"
				rel="bookmark nofollow"><?php
					esc_html_e(
						'More info...',
						'wallets'
					);
				?></a>

		</div>
		<?php
	}
);
