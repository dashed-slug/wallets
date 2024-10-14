<?php

/**
 * The wallet adapter class
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

/**
 * The wallet adapter is an object that abstracts communication with a wallet backend.
 *
 * Wallet backends can represent connections to one (e.g. Bitcoin core, Litecoin core, etc) or more (e.g. CoinPayments) actual coin wallets.
 * The adapter encapsulates any connection settings. It receives these settings from the wallets-wallet custom post type.
 * The settings are passed as an array into the constructor.
 * The adapter exposes methods that allow processing transactions and retrieving info from the wallet.
 *
 * @since 6.0.0 Introduced.
 * @api
 */
abstract class Wallet_Adapter {

	protected $wallet;
	protected $settings = [];
	protected $settings_schema = [];

	public function __construct( Wallet $wallet ) {

		if ( ! $wallet instanceof Wallet ) {
			throw new \InvalidArgumentException( 'Wallet must be a wallet!' );
		}
		$this->wallet = $wallet;

		if ( ! is_array( $wallet->adapter_settings ) ) {
			throw new \InvalidArgumentException( 'Settings must be array!' );
		}

		// set all settings and validate
		foreach ( $wallet->adapter_settings as $field => $value ) {
			$this->__set( $field, $value );
		}
	}

	/** Get a setting automagically
	 *
	 * @param string $id The ID of the setting.
	 * @return mixed|NULL The setting's value, or the setting's default value, or NULL if the setting was not found.
	 */
	public function __get( string $id ) {
		if ( 'wallet' == $id ) {
			return $this->wallet;

		} elseif ( isset( $this->settings[ $id ] ) ) {
			return $this->settings[ $id ];

		} else {
			foreach ( $this->settings_schema as $s ) {
				if ( $s['id'] == $id ) {
					if ( isset( $s['default'] ) ) {
						return $s['default'];
					}
					break;
				}
			}
		}
		return null;
	}

	public function __set( $id, $value ) {
		$schema = false;

		foreach ( $this->settings_schema as $s ) {
			if ( $id == $s['id'] ) {
				$schema = $s;
				break;
			}
		}

		if ( $schema ) {
			$valid = true;

			// validate
			switch ( $schema['type'] ) {
				case 'string':
				case 'strings':
					$valid = $valid && is_string( $value );
					break;

				case 'number':
					$valid = $valid && is_numeric( $value );
					if ( isset( $schema['min'] ) ) {
						$valid = $valid && ( $value >= $schema['min'] );
					}
					if ( isset( $schema['max'] ) ) {
						$valid = $valid && ( $value <= $schema['max'] );
					}
					break;

				case 'secret':
					if ( empty( $value ) ) {
						// if value is empty string, we don't overwrite old secrets
						return;
					}
					$valid = $valid && is_string( $value );
					break;

				case 'select':
					$valid = $valid && isset( $schema['options'][ $value ] );
					break;

				case 'bool':
				case 'boolean':
					$valid = $valid && ( 'on' == $value || ! $value );
					break;
			}

			if ( is_callable( $schema['validation_cb'] ?? null ) ) {
				$valid = $valid && call_user_func( $schema['validation_cb'], $value );
			}

			if ( $valid ) {
				$this->settings[ $id ] = $value;
			} else {
				throw new \InvalidArgumentException(
					sprintf(
						'%s: Invalid value %s for field "%s".',
						get_called_class(),
						json_encode( $value ),
						$id
					)
				);
			}
		}
	}

	/**
	 * Do periodic tasks related to this adapter.
	 *
	 * The plugin will call this periodically.
	 * Adapters can optionally perform arbitrary periodic tasks, as needed.
	 * Add here any housekeeping tasks specific to the type of wallet that this adapter connects to.
	 *
	 * @param ?callable $log The log function.
	 *
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public function do_cron( ?callable $log = null ): void { }

	/**
	 * Render a short description of what this adapter is about.
	 *
	 * A short description of this adapter, explaining what type of wallet it connects to.
	 *
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public abstract function do_description_text(): void;

	/**
	 * Return the extra field for addresses associated with this currency, if any.
	 *
	 * For example: Destination Tag, Payment ID, Memo, etc.
	 *
	 * @param ?Currency $currency The currency to query for. Useful only for adapters to multi-currency wallets.
	 * @return null|string The name of the field, or null if the field is not used.
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public function get_extra_field_name( ?Currency $currency = null ): ?string {
		return null;
	}

	/**
	 * Return the settings schema for this adapter.
	 *
	 * This schema can be used to render input form fields in the wallets-wallet metaboxes.
	 *
	 * @return array[] The schema is an array of arrays with the following fields each: id, name, type, description.
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public final function get_settings_schema(): array {
		return $this->settings_schema;
	}

	/**
	 * Wallet backend version
	 *
	 * @return string The version of the currently connected wallet, if available.
	 *
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public abstract function get_wallet_version(): string;


	/**
	 * Block height
	 *
	 * @param ?Currency $currency If the wallet supports multiple currencies, pass the desired currency here.
	 *
	 * @return ?int The block height up to which the connected wallet is synced currently or null if block height cannot be determined.
	 *
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public abstract function get_block_height( ?Currency $currency = null ): ?int;

	/**
	 * Determines if withdrawals are locked.
	 *
	 * A wallet can be locked for withdrawals if the necessary secrets are not available,
	 * or for any other reasaon.
	 *
	 * @return bool True if withdrawals cannot be performed at this time.
	 *
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public abstract function is_locked(): bool;

	/**
	 * Perform additional tasks before an internal debit transaction.
	 *
	 * Most adapters will NOT need to override this.
	 *
	 * Every time an internal transfer is executed, the corresponding adapter is notified first.
	 * If the adapter returns false, then the transaction will be marked with "failed" status.
	 * The adapter can also set an error message in the transaction:
	 *
	 * 		$debit->error = 'I do not like this tranasction!';
	 *
	 * The adapter can get the corresponding credit transaction with:
	 *
	 * 		$credit = $debit->get_other_tx();
	 *
	 * @param Transaction $debit The debit transaction (the one that removes balance from the sender).
	 * @return bool Whether the internal transfer should proceed.
	 */
	public function do_move( Transaction $debit ): bool {
		return true;
	}

	/**
	 * Perform a batch of withdrawals, possibly in one transaction.
	 *
	 * The method takes an array of Transactions and executes them.
	 * The method sets the following data into the transactions before returning them:
	 * - actual fees paid
	 * - TXID
	 * - block height
	 *
	 * The Transactions passed must all be of the same currency. The adapter
	 * does not have to be able to cater to different currencies in one call.
	 * Subclasses can call this parent implementation to perform these checks,
	 * before proceeding to withdraw a batch of transactions.
	 *
	 * @param Transaction[] $withdrawals Array of transactions to process. The transactions may be modified.
	 *
	 * @throws \InvalidArgumentException If not all transactions are withdrawals or of the same currency.
	 *
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public function do_withdrawals( array $withdrawals ): void {

		$currency = null;

		foreach ( $withdrawals as $w ) {

			if ( is_null( $currency ) ) {
				$currency = $w->currency;
			} else {
				if ( $currency->post_id != $w->currency->post_id ) {
					throw new \InvalidArgumentException(
						sprintf(
							'%s: Can only process withdrawals in one currency per batch. Currencies %s and %s encountered',
							__METHOD__,
							$currency->name,
							$w->currency->name
						)
					);
				}
			}

			if ( 'withdrawal' != $w->category || 'pending' != $w->status ) {
				throw new \InvalidArgumentException(
					sprintf(
						'%s: Can only process pending withdrawals, not TX with id: %d',
						__METHOD__,
						$w->post_id
					)
				);
			}
		}

		// We ensure that withdrawals are stored in the db,
		// and that their state is not dirty,
		// before we attempt to execute them.
		// If this fails for some reason, the save method will throw.
		foreach ( $withdrawals as $w ) {
			$w->save();
		}
	}

	/**
	 * Process a potential deposit transaction.
	 *
	 * This takes a Transaction object that is still in memory and looks up
	 * the deposit addresses to see if it corresponds to a user address.
	 * If the deposit concerns an existing user, the deposit is stored to the DB
	 * and the address's action slug is triggered with the transaction data as
	 * its first argument. Existing deposits are not re-created.
	 *
	 * Concrete implementations of this abstact class must call this method to
	 * notify the plugin of any potential deposits. Since the adapter does not know
	 * which deposits correspond to users, it can notify the plugin about all potential deposits.
	 * The plugin will discard any invalid deposits.
	 *
	 * @param $potential_deposit A Transaction that does not yet have a user assigned to it and is not saved to the DB.
	 * @return ?int The Transaction post_id or null if the Transaction is of no interest to us/was not saved.
	 */
	protected final function do_deposit( Transaction $potential_deposit ): ?int {

		if ( ! $potential_deposit instanceof Transaction ) {
			throw new \InvalidArgumentException(
				sprintf(
					'%s: Received non-transaction!',
					__METHOD__
				)
			);
		}

		if ( isset( $potential_deposit->user ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'%s: Received transaction with user already assigned!',
					__METHOD__
				)
			);
		}

		if ( isset( $potential_deposit->post_id ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'%s: Received transaction that is already stored on DB!',
					__METHOD__
				)
			);
		}

		// We don't want the plugin to be re-processing any deposits
		// having timestamps earlier than the start of the last migration.
		if ( $potential_deposit->timestamp ?? 0 ) {
			$deposit_cutoff = get_ds_option( 'wallets_deposit_cutoff', 0 );
			if ( $deposit_cutoff ) {
				if ( $potential_deposit->timestamp < $deposit_cutoff ) {

					throw new \Exception(
						sprintf(
							'%s: Received deposit with timestamp %d which is earlier than deposit cutoff %d',
							__METHOD__,
							$potential_deposit->timestamp,
							$deposit_cutoff
						)
					);
				}
			}
		}

		maybe_switch_blog();

		$existing_deposit = get_deposit_transaction_by_txid_and_address(
			$potential_deposit->txid,
			$potential_deposit->address->address,
			$potential_deposit->address->extra
		);

		if ( $existing_deposit ) {
			// we already know about this deposit

			$must_trigger_action = false;
			if ( 'done' == $potential_deposit->status && 'done' != $existing_deposit->status ) {
				$must_trigger_action = true;
			}

			$dirty = false;
			if ( 'pending' == $existing_deposit->status && 'pending' != $potential_deposit->status ) {
				$existing_deposit->status = $potential_deposit->status;
				$dirty = true;
			}

			// update these only if a new value is passed from the adapter
			foreach ( [ 'amount', 'fee', 'chain_fee', 'comment', 'error' ] as $field ) {
				if ( $potential_deposit->{$field} ) {
					$existing_deposit->{$field} = $potential_deposit->{$field};
					$dirty = true;
				}
			}

			// update these only if not already set and a new value is passed from the adapter
			foreach ( [ 'block', 'timestamp', ] as $field ) {
				if ( ! $existing_deposit->{$field} && $potential_deposit->{$field} ) {
					$existing_deposit->{$field} = $potential_deposit->{$field};
					$dirty = true;
				}
			}

			if ( $dirty ) {
				try {
					$existing_deposit->save();

				} catch ( \Exception $e ) {
					error_log(
						sprintf(
							'%s: ERROR: Could not save deposit to %s',
							__METHOD__,
							$potential_deposit->address
						)
					);
				}
			}

			if ( $must_trigger_action ) {

				/**
				 * Actions to notify about an incoming deposit.
				 *
				 * Whenever a wallet adapter discovers a deposit, it must call do_deposit().
				 *
				 * The first time a deposit is encountered, it will trigger, depending on its status, one of:
				 * - wallets_incoming_deposit_pending
				 * - wallets_incoming_deposit_done
				 *
				 * If a deposit has already been encountered and has now switched from pending to done,
				 * then it will trigger once:
				 * - `wallets_incoming_deposit_done`
				 *
				 * In both cases, the deposit is a DSWallets\Transaction . It can be captured thusly:
				 *
				 * add_action( "wallets_incoming_deposit_pending", function( $tx ) {
				 *     error_log( "Discovered pending deposit: $tx" );
				 * } );
				 *
				 * and:
				 *
				 * add_action( "wallets_incoming_deposit_done", function( $tx ) {
				 *     error_log( "Discovered deposit: $tx" );
				 * } );
				 */
				do_action(
					"wallets_incoming_deposit_{$existing_deposit->status}",
					$existing_deposit
				);
			}

			maybe_restore_blog();

			return $existing_deposit->post_id;
		}

		$address = get_deposit_address_by_strings(
			$potential_deposit->address->address,
			$potential_deposit->address->extra
		);

		if ( ! $address ) {
			// deposit address is not known, so we discard it
			return null;
		}

		// populate the missing fields
		$potential_deposit->address = $address;
		$potential_deposit->user    = $address->user;
		$potential_deposit->fee     = -absint( $potential_deposit->currency->fee_deposit_site );

		try {

			if ( ! $potential_deposit->user ) {
				throw new \Exception(
					sprintf(
						'Address %s with ID %d does not have a user assigned to it.',
						$address->address,
						$address->post_id
					)
				);
			}

			$potential_deposit->save();


		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'%s: ERROR: Could not save deposit to %s, due to: %s',
					__METHOD__,
					$potential_deposit->address,
					$e->getMessage()
				)
			);
		}

		/** This action is documented in this file. See above. */
		do_action(
			"wallets_incoming_deposit_{$potential_deposit->status}",
			$potential_deposit
		);

		maybe_restore_blog();

		return $potential_deposit->post_id;
	}

	/**
	 * Create a new address for this wallet.
	 *
	 * Will generate a new address
	 *
	 * @param ?Currency $currency If the wallet supports multiple currencies, pass the desired currency here.
	 *
	 * @return Address The object encapsulating the new address.
	 *
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public abstract function get_new_address( ?Currency $currency = null ): Address;

	/**
	 * Retrieve the hot wallet balance.
	 *
	 * @param ?Currency $currency If the wallet supports multiple currencies, pass the desired currency here.
	 *
	 * @return int The balance as an integer.
	 *
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public abstract function get_hot_balance( ?Currency $currency = null ): int;

	/**
	 * Retrieve the hot wallet balance that is available for immediate use.
	 *
	 * Available balance excludes any funds that are not mature.
	 * Immature funds are those that originate from transaction that have a low confimation count,
	 * or any funds that are mined too recently, or locked in other pending withdrawals in the wallet.
	 *
	 * @param ?Currency $currency If the wallet supports multiple currencies, pass the desired currency here.
	 *
 	 * @return int The available balance as an integer.
 	 *
	 * @since 6.0.0 Introduced.
	 * @api
	 */
	public function get_hot_available_balance( ?Currency $currency = null ): int {
		return $this->get_hot_balance( $currency ) - $this->get_hot_locked_balance( $currency );
	}

	public abstract function get_hot_locked_balance( ?Currency $currency = null ): int;

	/**
	 * Validate an IP address.
	 *
	 * @param string $address
	 * @return bool
	 * @throws \InvalidArgumentException If the IP address is not valid.
	 */
	protected function validate_tcp_ip_address( string $address ): bool {
		return false !== filter_var(
			trim( $address, '[]' ),
			FILTER_VALIDATE_IP,
			[
				'flags' => FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6
			]
		);
	}

}
