<?php

/**
 * Migrate transactions from wallets versions less than 6.0.0.
 *
 * @since 6.0.0 Introduced.
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

/**
 * Migrates transactions and addresses from the old MySQL tables to custom posts.
 *
 * This cron job migrates the transactions and addresses from the custom MySQL tables
 * of version 5.x and below to custom posts for version 6 and greater.
 *
 * The process will create any currencies not already declared. It does its best to guess
 * the currency from the symbol name.
 *
 * While the process runs, the UIs are unavailable.
 *
 * The process is not destructive. i.e. the old data will continue to exist,
 * so you can safely roll back to version 5.x if needed.
 *
 * To repeat the migration process, delete the `wallets_migration_state` option.
 *
 * The process will generate transaction tags for all the tags found in the old tables.
 *
 * If a transaction or address fails to be converted for whatever reason, it is skipped.
 * Admins are notified by email about the contents of the transaction.
 *
 * If the old tables don't exist, migration is marked as finished and does not do anything.
 *
 * If the old tables do exist and migration has finished, admins are notified by email.
 *
 * Statistics about the migration progress are shown in the admin screens to all admins.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 *
 */
class Migration_Task extends Task {

	const MAX_BATCH_SIZE_ADDRESSES = 100;
	const MAX_BATCH_SIZE_TRANSACTIONS = 100;

	/** Name for the transactions MySQL table. */
	private $table_name_txs = '';

	/** Name for the deposit addresses MySQL table. */
	private $table_name_adds = '';

	/** Counters related to the current state of the migration progress. Are persisted with a WordPress option. */
	private static $state = [];

	/** Array of objects with fields: id, name, symbol */
	private static $coingecko_currencies = null;

	public function __construct() {
		$this->priority = 2;

		global $wpdb;
		$prefix = is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
		$this->table_name_txs  = "{$prefix}wallets_txs";
		$this->table_name_adds = "{$prefix}wallets_adds";

		if ( ! self::$state ) {
			self::$state = get_ds_option(
				'wallets_migration_state',
				[
					'type'           => 'none', // can be: transactions, balances, revert, none

					'add_count'      => false, // false means that addresses have not been counted yet
					'add_count_ok'   => 0, // count of successfully migrated addresses
					'add_count_fail' => 0, // count of addresses that could not be migrated
					'add_last_id'    => 0, // becomes false after address migration finishes

					'tx_count'       => false, // false means that txs have not been counted yet
					'tx_count_ok'    => 0, // count of successfully migrated transactions
					'tx_count_fail'  => 0, // count of transactions that could not be migrated
					'tx_last_id'     => 0, // becomes false after transaction migration finishes

					'bal_last_uid'   => 0, // becomes false after balances migration finishes
					'bal_pending'    => [], // tuples of [ [currency_id, amount], ... ] to be written asyncronously for curreny uid
				]
			);
		}

		parent::__construct();
	}

	public function run(): void {
		if ( is_net_active() && ! is_main_site() ) {
			$this->log( 'Migration task can run only on main blog!' );
			return;
		}

		if ( get_ds_transient( 'wallets_migration_snooze' ) ) {
			$this->log( 'Migration task is in snooze mode!' );
			return;
		}

		global $wpdb;

		$this->task_start_time = time();

		// try to load currency names-symbols mapping
		if ( ! self::$coingecko_currencies ) {
			self::$coingecko_currencies = get_coingecko_currencies();
		}

		// we don't want to run if crypto currencies are not known
		if ( ! self::$coingecko_currencies  ) {
			$this->log( 'Cryptocurrency data not yet loaded from coingecko.com.' );
			return;
		}

		// migration is running
		$this->log(
			sprintf(
				'Running migration task: %s',
				json_encode( self::$state )
			)
		);

		// re-count total address count if not already known
		if ( false === self::$state['add_count'] ) {

			$wpdb->flush();

			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $this->table_name_adds WHERE %d OR blog_id = %d",
					is_net_active() ? 1 : 0,
					get_current_blog_id()
				)
			);

			if ( $result ) {
				self::$state['add_count'] = absint( $result );
			} else {
				self::$state['add_count']   = false;
				self::$state['add_last_id'] = false;
			}

			if ( $wpdb->last_error ) {
				// probably the tables don't even exist
				// let's snooze migration so we don't fill up the logs with warnings

				set_ds_transient(
					'wallets_migration_snooze',
					true,
					HOUR_IN_SECONDS
				);
			}
		}

		// re-count total transaction count if not already known
		if ( false === self::$state['tx_count'] ) {

			$wpdb->flush();

			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $this->table_name_txs WHERE %d OR blog_id = %d",
					is_net_active() ? 1 : 0,
					get_current_blog_id()
				)
			);

			if ( $result ) {
				self::$state['tx_count'] = absint( $result );
			} else {
				self::$state['tx_count']   = false;
				self::$state['tx_last_id'] = false;
			}

			if ( $wpdb->last_error ) {
				// probably the tables don't even exist
				// let's snooze migration so we don't fill up the logs with warnings

				set_ds_transient(
					'wallets_migration_snooze',
					true,
					HOUR_IN_SECONDS
				);
			}
		}

		// check if migration has finished
		if ( false === self::$state['add_last_id'] && false === self::$state['tx_last_id'] ) {
			self::$state['type'] = 'none';
		}

		if ( false === self::$state['bal_last_uid'] ) {
			self::$state['type'] = 'none';
		}

		update_ds_option(
			'wallets_migration_state',
			self::$state
		);

		if ( 'none' === self::$state['type'] ) {
			return;
		}

		// MIGRATE ADDRESSES
		if ( in_array( self::$state['type'], [ 'transactions', 'balances' ] ) ) {
			$adds = [];

			// if address migration is not already finished
			if ( false !== self::$state['add_count'] && false !== self::$state['add_last_id'] ) {

				$this->log(
					sprintf(
						'Attempting to migrate addresses with ID greater than: %d',
						self::$state['add_last_id']
					)
				);


				try {
					// retrieve the next batch of addresses
					$adds = $this->get_address_rows(
						self::$state['add_last_id']
					);

				} catch ( \Exception $e ) {
					$this->log(
						sprintf(
							'Could not get_address_rows( %d ), due to: %s',
							self::$state['add_last_id'],
							$e->getMessage()
						),
						true
					);
					return;
				}

				// if the batch cointains addresses
				if ( $adds ) {

					foreach ( $adds as $add ) {

						// don't continue processing addresses if already too many seconds have elapsed
						if ( time() >= $this->task_start_time + $this->timeout ) {
							break;
						}

						self::$state['add_last_id'] = max( self::$state['add_last_id'], $add->id );

						try {
							// process this address to generate a post of type wallets-address
							$this->create_address( $add );

							self::$state['add_count_ok']++;

							$this->log(
								sprintf(
									'Address %d has been successfully migrated!',
									$add->id
								),
								true
							);

						} catch ( \Exception $e ) {

							self::$state['add_count_fail']++;

							$this->log(
								sprintf(
									'Could not migrate address %s due to: %s',
									json_encode( $add ),
									$e->getMessage()
								),
								true
							);

							wp_mail_enqueue_to_admins(
								sprintf(
									(string) __( 'Failed to migrate address - %s', 'wallets' ),
									(string) get_bloginfo()
								),
								print_r( $add, true ) . "\n\nError: " . $e->getMessage()
							);

							continue;
						}

					}
				} else {
					// if the last address batch was empty, we've reached the end of address migration
					self::$state['add_last_id'] = false;

					$a = self::$state;

					wp_mail_enqueue_to_admins(
						sprintf(
							(string) __( 'Deposit Address Migration - Bitcoin and Altcoin Wallets - %s', 'wallets' ),
							(string) get_bloginfo()
						),
						<<<EMAIL
A total of $a[add_count] deposit addresses were processed.

$a[add_count_ok] deposit addresses were migrated successfully.

$a[add_count_fail] deposit addresses could not be migrated.

Stand by while $a[type] are being migrated...
EMAIL
					);
				}

				update_ds_option(
					'wallets_migration_state',
					self::$state
				);

			}
		}


		// MIGRATE TRANSACTIONS
		if ( 'transactions' == self::$state['type'] ) {
			$txs = [];

			// if transaction migration is started and not already finished
			if ( false !== self::$state['tx_count'] && false !== self::$state['tx_last_id'] ) {

				$this->log(
					sprintf(
						'Attempting to migrate transactions with ID greater than: %d',
						self::$state['tx_last_id']
					)
				);

				try {

					// retrieve the next batch of transactions
					$txs = $this->get_transaction_rows(
						self::$state['tx_last_id']
					);

				} catch ( \Exception $e ) {
					$this->log(
						sprintf(
							'Failed to get_transaction_rows( %d ): %s',
							self::$state['tx_last_id'],
							$e->getMessage()
						),
						true
					);

					return;
				}

				// if the batch contains transactions
				if ( $txs ) {
					foreach ( $txs as $tx ) {

						// don't continue processing transactions if already too many seconds have elapsed
						if ( time() >= $this->task_start_time + $this->timeout ) {
							break;
						}

						self::$state['tx_last_id'] = max( self::$state['tx_last_id'], $tx->id );

						try {
							// process this transaction to generate a post of type wallets-tx
							$this->create_transaction( $tx );

							self::$state['tx_count_ok']++;

							$this->log(
								sprintf(
									'Transaction %d has been successfully migrated!',
									$tx->id
								),
								true
							);

						} catch ( \Exception $e ) {

							self::$state['tx_count_fail']++;

							$this->log(
								sprintf(
									'Failed to create transaction %s, due to: %s',
									json_encode( $tx ),
									$e->getMessage()
								),
								true
							);

							wp_mail_enqueue_to_admins(
								sprintf(
									(string) __( 'Failed to migrate transaction - %s', 'wallets' ),
									(string) get_bloginfo()
								),
								print_r( $tx, true ) . "\n\nError: " .$e->getMessage()
							);

							continue;
						}

					}

				} else {
					// if the last transaction batch was empty, we've reached the end of transaction migration
					self::$state['tx_last_id'] = false;

					$a = self::$state;

					wp_mail_enqueue_to_admins(
						sprintf(
							(string) __( 'Transaction Migration - Bitcoin and Altcoin Wallets - %s', 'wallets' ),
							(string) get_bloginfo()
						),
						<<<EMAIL
A total of $a[tx_count] transactions were processed:

$a[tx_count_ok] transactions were migrated successfully.

$a[tx_count_fail] transactions could not be migrated.

If there is any problem with user balances, you can revert the migration and try again.

If all data was migrated successfully, you can remove the old tables $this->table_name_adds and $this->table_name_txs to save space.

(But keep a backup just in case!!!)

EMAIL
					);
				}

				update_ds_option(
					'wallets_migration_state',
					self::$state
				);
			}
		}

		// MIGRATE BALANCES
		if ( 'balances' == self::$state['type'] ) {

			if ( empty( self::$state['bal_pending'] ) ) {
				$next_user_id = self::get_next_user_id_after( self::$state['bal_last_uid'] );

				if ( $next_user_id ) {
					self::$state['bal_last_uid'] = $next_user_id;
					self::$state['bal_pending'] = self::get_balance_rows( $next_user_id );
				} else {
					self::$state['type'] = 'none';
					self::$state['bal_last_uid'] = false; // signifies balance migration finsh
					self::$state['bal_pending'] = [];

					$a = self::$state;

					wp_mail_enqueue_to_admins(
						sprintf(
							(string) __( 'Balances Migration - Bitcoin and Altcoin Wallets - %s', 'wallets' ),
							(string) get_bloginfo()
						),
						<<<EMAIL
All user balances were migrated:

If there is any problem with user balances, you can revert the migration and try again.

If all data was migrated successfully, you can remove the old tables $this->table_name_adds and $this->table_name_txs to save space.

(But keep a backup just in case!!!)

EMAIL
					);
				}
			}

			// flush out any pending balances
			while ( is_array( self::$state['bal_pending'] ) && ! empty( self::$state['bal_pending'] ) ) {
				// don't continue processing addresses/txs if already too many seconds have elapsed
				if ( time() >= $this->task_start_time + $this->timeout ) {
					break;
				}


				$row = array_shift( self::$state['bal_pending'] );

				$currency = $this->get_or_make_currency( $row->symbol );
				$amount   = absint( round( $row->balance * 10 ** $currency->decimals ) );

				if ( $amount ) {
					$tx = new Transaction;
					$tx->category = 'move';
					$tx->user     = new \WP_User( self::$state['bal_last_uid'] );
					$tx->currency = $currency;
					$tx->amount   = $amount;
					$tx->comment  = sprintf(
						'Migrated %s user balance',
						$row->symbol
					);
					$tx->timestamp = time();
					$tx->status    = 'done';

					// write out the transaction and apply tags
					try {
						$tx = get_or_make_transaction( $tx, false );
					} catch ( \Exception $e ) {
						$this->log(
							sprintf(
								'Failed to get or make transaction %s, due to: %s',
								(string) $tx,
								$e->getMessage()
							),
							true
						);
					}

					try {
						$tx->tags = [ 'migrated', 'migrated-balance' ];
					} catch ( \Exception $e ) {
						$this->log(
							sprintf(
								'Failed to set tags %s to transaction %d, due to: %s',
								json_encode( $tags ),
								$tx->post_id,
								$e->getMessage()
							),
							true
						);
					}

					$this->log(
						sprintf(
							'Transferred balance of %f for user %d and ticker %s (currency %d) with transaction %d',
							$row->balance,
							self::$state['bal_last_uid'],
							$row->symbol,
							$tx->currency->post_id,
							$tx->post_id
						)
					);
				}
			}

			update_ds_option(
				'wallets_migration_state',
				self::$state
			);
		}

		// REVERT MIGRATION
		if ( 'revert' == self::$state['type'] ) {
			$finished = true;

			foreach ( [ 'wallets_tx', 'wallets_address', 'wallets_currency' ] as $post_type ) {

				$post_ids = self::get_ids_of_migrated_posts( $post_type );

				if ( ! $post_ids ) {

					$this->log(
						sprintf(
							'No more migrated posts of type %s need to be deleted.',
							$post_type
						)
					);

				} else {

					$finished = false;

					foreach ( $post_ids as $post_id ) {
						// don't continue processing addresses/txs if already too many seconds have elapsed
						if ( time() >= $this->task_start_time + $this->timeout ) {
							break 2;
						}

						$this->log(
							sprintf(
								'Deleting migration for post with type %s and ID: %d',
								$post_type,
								$post_id
							)
						);

						wp_delete_post( $post_id );

					}
				}
			}

			if ( $finished ) {
				// both addresses and txs are reverted, switch to no migration task
				self::$state['type'] = 'none';

				self::$state['add_count_ok'] = 0;
				self::$state['add_count_fail'] = 0;
				self::$state['add_last_id'] = 0;

				self::$state['tx_count_ok'] = 0;
				self::$state['tx_count_fail'] = 0;
				self::$state['tx_last_id'] = 0;

				self::$state['bal_last_uid']  = 0;
				self::$state['bal_pending']   = [];

				$admin_url = admin_url( 'tools.php?page=wallets_migration' );
				wp_mail_enqueue_to_admins(
					sprintf(
						(string) __( 'Reverted Migration - Bitcoin and Altcoin Wallets - %s', 'wallets' ),
						(string) get_bloginfo()
					),
					<<<EMAIL
All the migrated Currencies, Addresses and Transactions have been deleted.

You can now retry the migration process at: $admin_url.

EMAIL
				);

			}

			update_ds_option(
				'wallets_migration_state',
				self::$state
			);

		}

	}

	/**
	 * Tells you whether the migration task is currently active.
	 *
	 * While a task is active, there should be no transactions or other user changes to the DB.
	 * The APIs should therefore check for this and fail if it returns true.
	 *
	 * @return boolean
	 */
	public static function is_running() {
		// @phan-suppress-next-line PhanTypeInvalidDimOffset
		return ( self::$state['type'] ?? '' ) != 'none';
	}

	private static function get_next_user_id_after( int $user_id ): ?int {
		global $wpdb;

		$wpdb->flush();

		$query = $wpdb->prepare(
			"
			SELECT
				ID
			FROM
				{$wpdb->users}
			WHERE
				ID > %d
			",
			$user_id
		);

		$next_user_id = $wpdb->get_var( $query );

		if ( false === $next_user_id ) {
			throw new \Exception(
				sprintf(
					'%s: Failed with: %s',
					__METHOD__,
					$wpdb->last_error
				)
			);
		}

		return $next_user_id ? $next_user_id : null;
	}

	/**
	 * Gets a user ID and returns the SQL balances for each symbol.
	 *
	 * @param int $user_id The ID of the user.
	 * @return array Map of ticker symbols to balances as floats.
	 */
	private function get_balance_rows( int $user_id ): array {
		global $wpdb;

		$wpdb->flush();

		$query = $wpdb->prepare(
			"
			SELECT
				t.symbol AS symbol,
				SUM( IF( t.amount > 0, t.amount - t.fee, t.amount ) ) AS balance

			FROM
				{$this->table_name_txs} t

			WHERE
				t.status = 'done'
				AND t.account = %d
				AND ( %d OR t.blog_id = %d )

			GROUP BY
				t.symbol
			",
			$user_id,
			is_net_active() ? 1 : 0,
			get_current_blog_id()
		);

		$balances = $wpdb->get_results( $query, OBJECT_K );

		if ( false === $balances ) {
			throw new \Exception(
				sprintf(
					'%s: Failed with: %s',
					__METHOD__,
					$wpdb->last_error
				)
			);
		}

		return $balances;
	}


	/**
	 * Gets a batch of address rows from the old address table.
	 *
	 * @param int $last_id ID of last address already processed. This batch will begin immediately after.
	 * @param int $batch_size The requested batch size. Up to this many addresses are returned.
	 * @throws \Exception If the DB queries fail.
	 * @return array Array of objects representing the address rows.
	 */
	function get_address_rows( int $last_id, int $batch_size = self::MAX_BATCH_SIZE_ADDRESSES ): array {
		global $wpdb;

		$wpdb->flush();

		$query = $wpdb->prepare(
			"
			SELECT
				id,
				blog_id,
				account,
				symbol,
				address,
				extra,
				created_time,
				status
			FROM
				{$this->table_name_adds}
			WHERE
				id > %d
				AND ( %d OR blog_id = %d )
			ORDER BY
				id
			LIMIT
				%d
			",
			self::$state['add_last_id'],
			is_net_active() ? 1 : 0,
			get_current_blog_id(),
			$batch_size
		);

		$result = $wpdb->get_results( $query );

		if ( false === $result ) {
			throw new \Exception(
				sprintf(
					'%s: Failed with: %s',
					__METHOD__,
					$wpdb->last_error
				)
			);
		}

		return $result;
	}

	private function get_transaction_rows( int $last_id, int $batch_size = self::MAX_BATCH_SIZE_TRANSACTIONS ): array {
		global $wpdb;

		$wpdb->flush();

		$query = $wpdb->prepare(
			"
			SELECT
				id,
				blog_id,
				category,
				tags,
				account,
				other_account,
				address,
				extra,
				txid,
				symbol,
				amount,
				fee,
				comment,
				created_time,
				updated_time,
				confirmations,
				status,
				retries,
				admin_confirm,
				user_confirm,
				nonce
			FROM
				{$this->table_name_txs}
			WHERE
				id > %d
				AND ( %d OR blog_id = %d )
			ORDER BY
				id
			LIMIT
				%d
			",
			self::$state['tx_last_id'],
			is_net_active() ? 1 : 0,
			get_current_blog_id(),
			$batch_size
		);

		$result = $wpdb->get_results( $query );

		if ( false === $result ) {
			throw new \Exception(
				sprintf(
					'%s: Failed with: %s',
					__METHOD__,
					$wpdb->last_error
				)
			);
		}

		return $result;
	}

	public function create_transaction( object $row ): void {

		$transaction = new Transaction;
		$other_transaction = null; // only for tx pairs (category=move,trade)

		switch ( $row->category ) {
			case 'deposit':
			case 'move':
				$transaction->category = $row->category;
				break;

			case 'withdraw':
				$transaction->category = 'withdrawal';
				break;

			case 'trade':
				$transaction->category = 'move';
				break;

			default:
				throw new \Exception( "Invalid category $row->category" );
		}

		$transaction->user = new \WP_User( $row->account );
		if ( false === $transaction->user ) {
			throw new \Exception( "User $row->account not found" );
		}

		$transaction->currency = $this->get_or_make_currency( $row->symbol );

		if ( $row->comment ) {
			$transaction->comment = $row->comment;
		}
		$transaction->timestamp = get_date_from_gmt( $row->created_time, 'U' );

		$transaction->status = $this->map_status( $row->status );
		if ( 'unconfirmed' == $row->status ) {
			$transaction->nonce = $row->nonce;
		}

		$tags = array_diff( explode( ' ', $row->tags ), [ 'send', 'receive', 'move' ] );
		$tags[] = 'migrated';

		if ( 'deposit' == $row->category || 'withdraw' == $row->category ) {

			if ( $row->txid ) {
				$transaction->txid = $row->txid;
			}

			if ( $row->address ) {

				$address = new Address;
				$address->address = $row->address;
				if ( $row->extra ) {
					$address->extra = $row->extra;
				}

				$address->currency    = $transaction->currency;
				$address->user        = $transaction->user;
				$address->label       = "Migrated $row->symbol $row->category address (tx $row->id)";

				if ( 'deposit' == $row->category ) {
					$address->type = 'deposit';

				} elseif( 'withdraw' == $row->category ) {
					$address->type = 'withdrawal';

					// special handling for fiat withdrawals to bank account
					if ( preg_match( '/Route:(\d+) Acc:(\d+)/', $row->address, $matches ) ) {

						$routing_number = $matches[ 1 ];
						$account_number = $matches[ 2 ];
						$address->label = "{ \"routingNumber\": \"$routing_number\", \"accountNumber\": \"$account_number\"}";
						$address->address = $row->extra;   // Recipient's Name + Recipient's Address
						$address->extra   = $row->comment; // Recipient's Bank Name + Bank Address

						$tags[] = 'routing-us';

					} elseif ( preg_match( '/BIC:(\d+) IBAN:(\d+)/', $row->address, $matches ) ) {

						$swift_bic        = $matches[ 1 ];
						$iban             = $matches[ 2 ];
						$address->label   = "{ \"iban\": \"$iban\", \"swiftBic\": \"$swift_bic\" }";
						$address->address = $row->extra;   // Recipient's Name + Recipient's Address
						$address->extra   = $row->comment; // Recipient's Bank Name + Bank Address

						$tags[] = 'routing-swift';

					} elseif ( preg_match( '/IFSC:(\d+) Acc:(\d+)/', $row->address, $matches ) ) {
						$ifsc             = $matches[ 1 ];
						$account_number   = $matches[ 2 ];
						$address->label   = "{ \"ifsc\": \"$ifsc\", \"indianAccNum\": \"$account_number\"}";
						$address->address = $row->extra;   // Recipient's Name + Recipient's Address
						$address->extra   = $row->comment; // Recipient's Bank Name + Bank Address

						$tags[] = 'routing-ifsc';
					}

				}

				$transaction->address = get_or_make_address( $address );

				if ( 'withdraw' == $row->category ) {
					$transaction->amount = - absint( ( $row->amount + $row->fee ) * 10 ** $transaction->currency->decimals );
				} else {
					$transaction->amount =   absint( $row->amount * 10 ** $transaction->currency->decimals );
				}
				$transaction->fee = - absint( $row->fee    * 10 ** $transaction->currency->decimals );

			}

		} elseif ( 'move' == $row->category ) {

			if( $row->amount >= 0 ) {
				// we only read the debit transactions and infer the credit transactions
				return;
			}

			$transaction->amount    = - absint( ( $row->amount + $row->fee ) * 10 ** $transaction->currency->decimals );
			$transaction->fee       = - absint( $row->fee                    * 10 ** $transaction->currency->decimals );

			$other_transaction = new Transaction;
			$other_transaction->category = 'move';
			$other_transaction->user     = new \WP_User( $row->other_account );
			if ( false === $other_transaction->user ) {
				throw new \Exception( "User $row->other_account not found" );
			}
			$other_transaction->currency = $transaction->currency;
			$other_transaction->amount   = absint( ( $row->amount + $row->fee ) * 10 ** $transaction->currency->decimals );
			$other_transaction->fee      = 0;
			if ( $row->comment ) {
				$other_transaction->comment = $row->comment;
			}
			$other_transaction->timestamp = $transaction->timestamp = get_date_from_gmt( $row->created_time, 'U' );
			$other_transaction->status    = $this->map_status( $row->status );

		} elseif ( 'trade' == $row->category ) {

			$tags[] = 'exchange';

			if ( preg_match( '/T\-(\w+)\-(\w+)\-(O[\w\d]+)\-(O[\w\d]+)\-\d/', $row->txid, $matches ) ) {

				$tags[] = $matches[ 1 ] . '_' . $matches[ 2 ]; // add currency pair as tag
				$tags[] = $matches[ 3 ] . '-' . $matches[ 4 ]; // add matching order ids as tag

			} else {
				$this->log( "Could not parse trade TXID: $row->txid", true );
			}

			$transaction->comment .= " (tx $row->id)";

			if ( $row->amount < 0 ) {
				$transaction->amount    = - absint( ( $row->amount + $row->fee ) * 10 ** $transaction->currency->decimals );
				$transaction->fee       = - absint( $row->fee                    * 10 ** $transaction->currency->decimals );

			} else {
				$transaction->amount    = absint( ( $row->amount - $row->fee ) * 10 ** $transaction->currency->decimals );
				$transaction->fee       = 0;
			}

		}

		// write out the transaction and apply tags
		$tx = get_or_make_transaction( $transaction, false );

		try {
			$tx->tags = $tags;
		} catch ( \Exception $e ) {
			$this->log(
				sprintf(
					'Failed to set tags %s to transaction %d, due to: %s',
					json_encode( $tags ),
					$tx->post_id,
					$e->getMessage()
				),
				true
			);
		}

		// write out the other transaction, if any, and apply the same tags
		if ( $other_transaction ) {
			$other_transaction->parent_id = $tx->post_id;
			$other_tx = get_or_make_transaction( $other_transaction, false );

			try {

				$other_tx->tags = $tags;

			} catch ( \Exception $e ) {
				$this->log(
					sprintf(
						'Failed to set tags %s to transaction %d, due to: %s',
						json_encode( $tags ),
						$other_tx->post_id,
						$e->getMessage()
					),
					true
				);
			}

		}
	}

	public function create_address( object $row ): void {

		$currency = $this->get_or_make_currency( $row->symbol );

		$address = new Address;
		$address->address = $row->address;
		if ( $row->extra ) {
			$address->extra = $row->extra;
		}
		$address->type     = 'deposit';
		$address->currency = $currency;
		$address->user     = new \WP_User( $row->account );
		$address->label    = "Migrated $row->status $row->symbol address (add $row->id)";

		$address = get_or_make_address( $address );

		$address->tags = [ 'migrated' ];
	}

	private function get_or_make_currency( string $symbol ): Currency {

		$currency = get_first_currency_by_symbol( $symbol );

		$new = false;

		if ( ! $currency ) {

			$new = true;

			$currency = new Currency;

			$currency->name = $symbol; // ticker symbol is fallback value for name

			// assume bitcoin-like for now
			$currency->symbol   = $symbol;
			$currency->decimals = 8;
			$currency->pattern  = '%01.8f';


			// look for symbol's name in fiat currencies (from fixer.io)
			$fixerio_currencies = get_ds_option( 'wallets_fixerio_currencies_list', [
				'AED'=>'United Arab Emirates Dirham','AFN'=>'Afghan Afghani','ALL'=>'Albanian Lek','AMD'=>'Armenian Dram','ANG'=>'Netherlands Antillean Guilder','AOA'=>'Angolan Kwanza','ARS'=>'Argentine Peso','AUD'=>'Australian Dollar','AWG'=>'Aruban Florin','AZN'=>'Azerbaijani Manat','BAM'=>'Bosnia-Herzegovina Convertible Mark','BBD'=>'Barbadian Dollar','BDT'=>'Bangladeshi Taka','BGN'=>'Bulgarian Lev','BHD'=>'Bahraini Dinar','BIF'=>'Burundian Franc','BMD'=>'Bermudan Dollar','BND'=>'Brunei Dollar','BOB'=>'Bolivian Boliviano','BRL'=>'Brazilian Real','BSD'=>'Bahamian Dollar','BTN'=>'Bhutanese Ngultrum','BWP'=>'Botswanan Pula','BYN'=>'New Belarusian Ruble','BYR'=>'Belarusian Ruble','BZD'=>'Belize Dollar','CAD'=>'Canadian Dollar','CDF'=>'Congolese Franc','CHF'=>'Swiss Franc','CLF'=>'Chilean Unit of Account (UF)','CLP'=>'Chilean Peso','CNY'=>'Chinese Yuan','COP'=>'Colombian Peso','CRC'=>'Costa Rican Colón','CUC'=>'Cuban Convertible Peso','CUP'=>'Cuban Peso','CVE'=>'Cape Verdean Escudo','CZK'=>'Czech Republic Koruna','DJF'=>'Djiboutian Franc','DKK'=>'Danish Krone','DOP'=>'Dominican Peso','DZD'=>'Algerian Dinar','EGP'=>'Egyptian Pound','ERN'=>'Eritrean Nakfa','ETB'=>'Ethiopian Birr','EUR'=>'Euro','FJD'=>'Fijian Dollar','FKP'=>'Falkland Islands Pound','GBP'=>'British Pound Sterling','GEL'=>'Georgian Lari','GGP'=>'Guernsey Pound','GHS'=>'Ghanaian Cedi','GIP'=>'Gibraltar Pound','GMD'=>'Gambian Dalasi','GNF'=>'Guinean Franc','GTQ'=>'Guatemalan Quetzal','GYD'=>'Guyanaese Dollar','HKD'=>'Hong Kong Dollar','HNL'=>'Honduran Lempira','HRK'=>'Croatian Kuna','HTG'=>'Haitian Gourde','HUF'=>'Hungarian Forint','IDR'=>'Indonesian Rupiah','ILS'=>'Israeli New Sheqel','IMP'=>'Manx pound','INR'=>'Indian Rupee','IQD'=>'Iraqi Dinar','IRR'=>'Iranian Rial','ISK'=>'Icelandic Króna','JEP'=>'Jersey Pound','JMD'=>'Jamaican Dollar','JOD'=>'Jordanian Dinar','JPY'=>'Japanese Yen','KES'=>'Kenyan Shilling','KGS'=>'Kyrgystani Som','KHR'=>'Cambodian Riel','KMF'=>'Comorian Franc','KPW'=>'North Korean Won','KRW'=>'South Korean Won','KWD'=>'Kuwaiti Dinar','KYD'=>'Cayman Islands Dollar','KZT'=>'Kazakhstani Tenge','LAK'=>'Laotian Kip','LBP'=>'Lebanese Pound','LKR'=>'Sri Lankan Rupee','LRD'=>'Liberian Dollar','LSL'=>'Lesotho Loti','LTL'=>'Lithuanian Litas','LVL'=>'Latvian Lats','LYD'=>'Libyan Dinar','MAD'=>'Moroccan Dirham','MDL'=>'Moldovan Leu','MGA'=>'Malagasy Ariary','MKD'=>'Macedonian Denar','MMK'=>'Myanma Kyat','MNT'=>'Mongolian Tugrik','MOP'=>'Macanese Pataca','MRO'=>'Mauritanian Ouguiya','MUR'=>'Mauritian Rupee','MVR'=>'Maldivian Rufiyaa','MWK'=>'Malawian Kwacha','MXN'=>'Mexican Peso','MYR'=>'Malaysian Ringgit','MZN'=>'Mozambican Metical','NAD'=>'Namibian Dollar','NGN'=>'Nigerian Naira','NIO'=>'Nicaraguan Córdoba','NOK'=>'Norwegian Krone','NPR'=>'Nepalese Rupee','NZD'=>'New Zealand Dollar','OMR'=>'Omani Rial','PAB'=>'Panamanian Balboa','PEN'=>'Peruvian Nuevo Sol','PGK'=>'Papua New Guinean Kina','PHP'=>'Philippine Peso','PKR'=>'Pakistani Rupee','PLN'=>'Polish Zloty','PYG'=>'Paraguayan Guarani','QAR'=>'Qatari Rial','RON'=>'Romanian Leu','RSD'=>'Serbian Dinar','RUB'=>'Russian Ruble','RWF'=>'Rwandan Franc','SAR'=>'Saudi Riyal','SBD'=>'Solomon Islands Dollar','SCR'=>'Seychellois Rupee','SDG'=>'Sudanese Pound','SEK'=>'Swedish Krona','SGD'=>'Singapore Dollar','SHP'=>'Saint Helena Pound','SLL'=>'Sierra Leonean Leone','SOS'=>'Somali Shilling','SRD'=>'Surinamese Dollar','STD'=>'São Tomé and Príncipe Dobra','SVC'=>'Salvadoran Colón','SYP'=>'Syrian Pound','SZL'=>'Swazi Lilangeni','THB'=>'Thai Baht','TJS'=>'Tajikistani Somoni','TMT'=>'Turkmenistani Manat','TND'=>'Tunisian Dinar','TOP'=>'Tongan Paʻanga','TRY'=>'Turkish Lira','TTD'=>'Trinidad and Tobago Dollar','TWD'=>'New Taiwan Dollar','TZS'=>'Tanzanian Shilling','UAH'=>'Ukrainian Hryvnia','UGX'=>'Ugandan Shilling','USD'=>'United States Dollar','UYU'=>'Uruguayan Peso','UZS'=>'Uzbekistan Som','VEF'=>'Venezuelan Bolívar Fuerte','VND'=>'Vietnamese Dong','VUV'=>'Vanuatu Vatu','WST'=>'Samoan Tala','XAF'=>'CFA Franc BEAC','XAG'=>'Silver (troy ounce)','XAU'=>'Gold (troy ounce)','XCD'=>'East Caribbean Dollar','XDR'=>'Special Drawing Rights','XOF'=>'CFA Franc BCEAO','XPF'=>'CFP Franc','YER'=>'Yemeni Rial','ZAR'=>'South African Rand','ZMK'=>'Zambian Kwacha (pre-2013)','ZMW'=>'Zambian Kwacha','ZWL'=>'Zimbabwean Dollar',
			] );

			if ( $fixerio_currencies && isset( $fixerio_currencies[ strtoupper( $symbol ) ] ) && 'BTC' != $symbol ) {

				$currency->name = $fixerio_currencies[ strtoupper( $symbol ) ];
				$currency->decimals = 2;
				$currency->pattern  = '%01.2f';

			} else {

				// look for symbol's name in coingecko cryptocurrencies
				foreach ( self::$coingecko_currencies as $coingecko_currency ) {
					if ( strtolower( $coingecko_currency->symbol ) == strtolower( $symbol ) ) {
						$currency->coingecko_id = $coingecko_currency->id;
						$currency->name         = $coingecko_currency->name;
						break;
					}
				}
			}

			try {
				$currency->save();

				if ( ! $currency->post_id ) {
					throw new \Exception( 'post_id not defined!' );
				}

				$currency->tags = [ 'migrated' ];

				$this->log( "Created new currency $currency->name with symbol $currency->symbol", true );

			} catch ( \Exception $e ) {
				throw new \Exception(
					sprintf(
						'Could not %s for symbol %s, due to: %s',
						__METHOD__,
						$symbol,
						$e->getMessage()
					)
				);
			}
		}

		return $currency;
	}

	private function map_status( string $status ): string {
		switch ( $status ) {
			case 'unconfirmed':
				return 'cancelled';

			case 'pending':
				return 'pending';

			case 'done':
			case 'failed':
			case 'cancelled':
				return $status;

			default:
				throw new \Exception( "Invalid tx status encountered: $status" );
		}
	}

	private static function get_ids_of_migrated_posts( string $post_type ): array {
		maybe_switch_blog();

		$query_args = [
			'fields'         => 'ids',
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'nopaging'       => true,

			'tax_query' => [
				'relation' => 'AND',
				[
					'taxonomy' => "{$post_type}_tags",
					'field'    => 'slug',
					'terms'    => 'migrated',
				],
			],
		];

		$query = new \WP_Query( $query_args );

		maybe_restore_blog();

		return $query->posts;
	}

}
new Migration_Task; // @phan-suppress-current-line PhanNoopNew
