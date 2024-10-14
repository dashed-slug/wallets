<?php

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

/**
 * Transaction class
 *
 * Represents a transaction on the plugin's ledger.
 * A transaction object can represent:
 * - a user's blockchain transaction (`category=withdrawal` or `category=deposit`)
 * - a credit to the user (`category=move` and `amount>0`)
 * - a debit to the user (`category=move` and `amount<0`)
 * - a fiat bank deposit or withdrawal (`category=withdrawal` or `category=deposit`, and `currency->is_fiat()===true` )
 *
 * Once you {@link \DSWallets\Transaction::save() save()} a transaction, depending on its state, it may be modified by cron tasks.
 *
 * e.g., a transaction with `category=withdrawal` and `status=pending`, may be picked up by {@link \DSWallets\Withdrawals_Task Withdrawals_Task}.
 *
 * # Credit a user
 *
 * Here we create a transaction to credit a specific user with `0.23` Litecoin.
 * Once the transaction is saved to the DB, it will cound towards that user's balance.
 * The balance does not get subtracted from any other account.
 *
 *		$user_id  = get_current_user_id();
 *		$user     = new \WP_User( $user_id );
 *		$litecoin = \DSWallets\get_first_currency_by_symbol( 'LTC' );
 *
 *		if ( $user->exists() && $litecoin ) {
 *
 *			$tx = new Transaction;
 *
 *			$tx->category = 'move';
 *			$tx->user     = $user;
 *			$tx->currency = $litecoin;
 *			$tx->amount   = 0.23 * 10 ** $litecoin->decimals;
 *			$tx->comment  = "Here's your Litecoin, user!";
 *			$tx->status   = 'done';
 *
 *			try {
 *				$tx->save();
 *			} catch ( \Exception $e ) {
 *				error_log( 'Could not save transaction due to: ' . $e->getMessage() );
 *			}
 *
 *		} else {
 *			error_log( 'User or litecoin currency not found' );
 *		}
 *
 * If we want to transfer funds from one user to another and charge fees, the easiest way is
 * {@link namespaces/dswallets.html#function_api_move_action the `wallets_api_move` action}
 * from the {@link files/build-apis-legacy-php.html legacy PHP API}.
 *
 *
 * # Debit a user and charge a fee
 *
 * Now let's create a transaction to debit the same user.
 * The user will be charged 10 USD plus fees, where fees are 5% of the amount.
 * The user will therefore be charged `10.50` USD. We need to set a negative amount and fee.
 * Both these values are integers so we need to shift the decimals.
 * Here's how to do this:
 *
 *		$user_id = get_current_user_id();
 *		$user = new \WP_User( $user_id );
 *		$usd = \DSWallets\get_first_currency_by_symbol( 'USD' );
 *
 *		if ( $user->exists() && $usd ) {
 *
 *			$tx = new Transaction;
 *
 *			$tx->category = 'move';
 *			$tx->user     = $user;
 *			$tx->currency = $usd;
 *			$tx->amount   = - 10 * 10 ** $usd->decimals; // two decimals
 *			$tx->fee      = - ( 0.05 * 10 ) * 10 ** $usd->decimals;
 *			$tx->comment  = "You have been charged $10 (+5% fee)";
 *			$tx->status   = 'done';
 *
 *			try {
 *				$tx->save();
 *			} catch ( \Exception $e ) {
 *				error_log( 'Could not save transaction due to: ' . $e->getMessage() );
 *			}
 *
 *		} else {
 *			error_log( 'User or United States Dollar currency not found' );
 *		}
 *
 * # Create a new deposit to a deposit address.
 *
 * We can add deposits to the ledger. Here we'll deposit 100 satoshis to the deposit address
 * and we'll withold 10% as deposit fee!!!!, so that only 90 satoshis are added to the user's balance.
 * This may have happened on an actual blockchain or not; it's up to us.
 * A Wallet Adapter would use such code to create an object for a newly discovered deposit.
 *
 * A reference to an {@link classes/DSWallets-PostTypes-Address.html Address} is required.
 *
 *		$bitcoin = \DSWallets\get_first_currency_by_symbol( 'BTC' );
 *
 *		foreach ( get_all_addresses_for_user_id( get_current_user_id() ) as $address ) {
 *
 *			if ( 'deposit' == $address->type && $bitcoin->post_id == $address->currency->post_id ) {
 *
 *				$deposit = new Transaction;
 *
 *				$deposit->category = 'deposit';
 *				$deposit->user     = new \WP_User( get_current_user_id() );
 *				$deposit->address  = $address;
 *				$deposit->currency = $bitcoin;
 *				$deposit->amount   = 90 * 10 ** $bitcoin->decimals;
 *				$deposit->fee      = -10 * 10 ** $bitcoin->decimals; // fee is always negative
 *				$deposit->comment  = "100 satoshis were deposited to you (minus 10% fee)";
 *				$deposit->status   = 'done';
 *
 *				try {
 *
 *					$tx->save();
 *
 *					break; // we'll only do this once
 *
 *				} catch ( \Exception $e ) {
 *					error_log( 'Could not save transaction due to: ' . $e->getMessage() );
 *				}
 *			}
 *		}
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */
class Transaction extends Post_Type {

	/**
	 * TX "category".
	 *
	 * One of: `deposit`, `withdrawal`, `move`.
	 * Not to be confused with WP taxonomies. This is a slug.
	 *
	 * @var string
	 */
	private $category = 'move';

	/**
	 * User
	 *
	 * The transaction counts towards this user's balance.
	 *
	 * @var ?\WP_User
	 */
	private $user = null;

	/**
	 * Blockchain TXID
	 *
	 * Only for blockchain transactions, null otherwise.
	 *
	 * @var ?string
	 */
	private $txid = null;

	/**
	 * Only for deposits and withdrawals, falsy otherwise.
	 *
	 * @var ?Address
	 */
	private $address = null;

	/**
	 * Currency transacted.
	 *
	 * @var ?Currency
	 */
	private $currency = null;

	/**
	 * Amount transacted, represented as an integer.
	 *
	 * The amount must be shifted by `$this->decimals` decimal places to get the actual float amount.
	 *
	 * @var int
	 */
	private $amount = 0;

	/**
	 * Fee charged, represented as an integer.
	 * The amount must be shifted by `$this->decimals` decimal places to get the actual float amount.
	 *
	 * @var int
	 */
	private $fee = 0;

	/**
	 * Blockchain fee.
	 *
	 * Fee charged on the blockchain for the entire transaction, as integer.
	 * The amount must be shifted by `$this->decimals` decimal places to get the actual float amount.
	 *
	 * @var int
	 */
	private $chain_fee = 0;

	/**
	 * Transaction comment.
	 *
	 * Free text string, stored on the post_title column in the DB.
	 *
	 * @var string
	 */
	private $comment = '';

	/**
	 * Blockchain max height.
	 *
	 * Blockchain height for calculating number of confirmations.
	 * Only for deposits and withdrawals, falsy otherwise.
	 *
	 * @var int
	 */
	private $block = 0;

	/**
	 * Timestamp.
	 *
	 * Unix timestamp according to the blockchain.
	 * If not available, returns the post's creation date timestamp.
	 *
	 * @var int
	 */
	private $timestamp = 0;

	/**
	 * Confirmation nonce.
	 *
	 * For email confirmations of transactions.
	 * If empty, the transaction is confirmed or does not require confirmation.
	 * Useful only for pending transactions.
	 *
	 * @var string
	 */
	private $nonce = '';

	/**
	 * Status
	 *
	 * One of: `pending`, `done`, `cancelled`, `failed`.
	 * If value is `failed`, then `$error` must also be set.
	 *
	 * @see $this->error
	 * @var string
	 */
	private $status = 'pending';

	/**
	 * Error message.
	 *
	 * For transactions with failed status, can contain a useful error message.
	 *
	 * @see $this->status
	 * @var string
	 */
	private $error = '';

	/**
	 * Parent `post_id`.
	 *
	 * If it's a credit transaction, the debit transaction to which it corresponds.
	 * Credit transactions have category = move and amount >= 0.
	 * Debit transactions have category = move and amount < 0.
	 * @var int
	 */
	private $parent_id = 0;


	/**
	 * Array of tags.
	 *
	 * Tags correspond to term slugs in the wallets_tx_tags taxonomy.
	 *
	 * @var ?string[]
	 */
	private $tags = null;

	/**
	 * Whether the TX data has changed since last DB load.
	 *
	 * When save() is called, data should be saved only if dirty.
	 *
	 * @var boolean Whether data needs saving.
	 */
	private $dirty = false;

	/**
	 * Factory to construct a transaction in one go from database values.
	 *
	 * @param int $post_id The ID of the post in the database
	 * @param string $post_title The post's title to be used as transaction comment
	 * @param string $post_status The status of the post, to populate the transaction state field
	 * @param string $post_parent The post's parent post, for transaction counterparts
	 * @param array $postmeta Key-value pairs
	 * @param string[] $tags The slugs of the terms on taxonomy wallets_tx_tags
	 *
	 * @return Transaction The constructed instance of the transaction object
	 */
	public static function from_values( int $post_id, string $post_title, string $post_status, int $post_parent, array $postmeta, array $tags ): Transaction {

		$tx = new self;

		// populate fields
		$tx->post_id    = $post_id;
		$tx->category   = $postmeta['wallets_category'] ?? 'move';
		$tx->txid       = $postmeta['wallets_txid'] ?? '';

		$address_id = $postmeta['wallets_address_id'] ?? null;
		if ( $address_id ) {
			try {
				$tx->address = Address::load( $address_id ) ?? null;
			} catch ( \Exception $e ) {
				$tx->address = null;
			}
		}

		$currency_id = $postmeta['wallets_currency_id'] ?? null;
		if ( $currency_id ) {
			try {
				$tx->currency = Currency::load( $currency_id ) ?? null;
			} catch ( \Exception $e ) {
				$tx->currency = null;
			}
		}

		$tx->amount    = intval( $postmeta['wallets_amount'] ?? 0 );
		$tx->fee       = intval( $postmeta['wallets_fee'] ?? 0 );
		$tx->chain_fee = absint( $postmeta['wallets_chain_fee'] ?? 0 );
		$tx->comment   = $post_title;
		$tx->block     = absint( $postmeta['wallets_block'] ?? 0);
		$tx->timestamp = absint( $postmeta['wallets_timestamp'] ?? 0 );
		$tx->nonce     = $postmeta['wallets_nonce'] ?? '';
		$tx->error     = $postmeta['wallets_error'] ?? '';
		$tx->parent_id = $post_parent;

		switch ( $post_status ) {
			case 'publish':
				$tx->status = 'done';
				break;
			case 'auto-draft':
			case 'pending':
				$tx->status = 'pending';
				break;
			case 'draft':
				$tx->status = $tx->error ? 'failed' : 'cancelled';
				break;
			default:
				throw new \Exception( sprintf( "Transaction $post_id has invalid post status $post_status" ) );
		}

		if ( ! isset( $postmeta['wallets_user'] ) ) {
			throw new \Exception( sprintf( "Transaction $post_id has no user field" ) );
		}

		$user_id = absint( $postmeta['wallets_user'] );

		if ( $user_id ) {
			$tx->user = new \WP_User( $user_id );
		}

		$tx->tags = $tags;

		return $tx;
	}

	/**
	 * Retrieve many transactions by their post_ids.
	 *
	 * Any post_ids not found are skipped silently.
	 *
	 * @param int[] $post_ids The post IDs
	 * @param ?string $unused Do not use. Ignored.
	 *
	 * @return Transaction[] The transactions
	 * @throws \Exception If DB access or instantiation fails.
	 *
	 * @since 6.2.6 Introduced.
	 */
	public static function load_many( array $post_ids, ?string $unused = null ): array {
		return parent::load_many( $post_ids, 'wallets_tx' );
	}

	/**
	 * Load a Transaction from its custom post entry.
	 *
	 * @inheritdoc
	 * @see Post_Type::load()
	 * @return Transaction
	 * @throws \Exception If not found or failed to instantiate.
	 */
	public static function load( int $post_id ): Transaction {
		$one = self::load_many( [ $post_id ] );
		if ( 1 !== count( $one ) ) {
			throw new \Exception( 'Not found' );
		}
		foreach ( $one as $o ) {
			return $o;
		}
	}

	/**
	 * Save the transaction state on the DB.
	 *
	 * Assigns a new `post_id` if not already assigned, or edits existing transaction if `post_id` is set.
	 *
	 * This delegate to save() does not trigger any notification emails.
	 *
	 * @see $this->save()
	 *
	 */
	public function saveButDontNotify(): void {
		remove_action( 'transition_post_status', [ __CLASS__, 'status_transition'], 10 );
		$this->save();
		add_action( 'transition_post_status', [ __CLASS__, 'status_transition'], 10, 3 );
	}

	/**
	 * Save the transaction state on the DB.
	 *
	 * Assigns a new `post_id` if not already assigned, or edits existing transaction if `post_id` is set.
	 */
	public function save(): void {
		if ( $this->post_id && ! $this->dirty ) {
			return;
		}

		maybe_switch_blog();

		$post_status = 'draft';
		switch ( $this->status ) {
			case 'done':
				$post_status = 'publish';
				break;
			case 'auto-draft':
			case 'pending':
				$post_status = 'pending';
				break;
			case 'failed':
			case 'cancelled':
			default:
				$post_status = 'draft';
				break;
		}

		if ( $this->currency && $this->currency->name && ! $this->comment ) {
			switch ( $this->category ) {
				case 'deposit':
					$category = __( 'deposit', 'wallets' ); break;
				case 'withdrawal':
					$category = __( 'withdrawal', 'wallets' ); break;
				case 'move':
					$category = __( 'internal transfer', 'wallets' ); break;
				default:
					$category = __( 'transaction', 'wallets' ); break;
			}

			$this->comment = sprintf(
				__( '%s %s', 'wallets' ),
				$this->currency->name,
				$category
			);
		}

		$user_id = $this->user ? $this->user->ID : 0;

		$postarr = [
			'ID'          => $this->post_id ?? null,
			'post_title'  => $this->comment,
			'post_status' => $post_status,
			'post_type'   => 'wallets_tx',
			'post_parent' => $this->parent_id,
		];

		// https://developer.wordpress.org/reference/hooks/save_post/#avoiding-infinite-loops
		remove_action( 'save_post', [ __CLASS__, 'save_post' ] );
		$result = wp_insert_post( $postarr );
		add_action( 'save_post', [ __CLASS__, 'save_post' ], 10, 3 );
		maybe_restore_blog();

		global $wpdb;

		$has_meta = false;
		if ( $this->post_id ) {

			$wpdb->flush();

			$has_meta = $wpdb->get_var(
				$wpdb->prepare( "
					SELECT
						COUNT(*)
					FROM
						{$wpdb->postmeta}
					WHERE
						post_id = %d AND
						meta_key LIKE 'wallets_%'
					",
					$this->post_id
				)
			) > 0;
		}

		if ( $has_meta ) {
			// If the post already has metadata on the DB,
			// then we update all the post meta in one go,
			// which is much faster than wp_insert_post which
			// loops over each meta and does a separate update.

			$meta_query = $wpdb->prepare(
				"UPDATE
					{$wpdb->postmeta}
				SET
					meta_value =
					CASE
						WHEN meta_key = 'wallets_user' THEN %d
						WHEN meta_key = 'wallets_category' THEN %s
						WHEN meta_key = 'wallets_txid' THEN %s
						WHEN meta_key = 'wallets_address_id' THEN %d
						WHEN meta_key = 'wallets_currency_id' THEN %d
						WHEN meta_key = 'wallets_amount' THEN %d
						WHEN meta_key = 'wallets_fee' THEN %d
						WHEN meta_key = 'wallets_chain_fee' THEN %d
						WHEN meta_key = 'wallets_block' THEN %d
						WHEN meta_key = 'wallets_timestamp' THEN %d
						WHEN meta_key = 'wallets_nonce' THEN %s
						WHEN meta_key = 'wallets_error' THEN %s
					END
				WHERE post_id = %d;
				",
				$user_id,
				$this->category,
				$this->txid,
				$this->address->post_id ?? 0,
				$this->currency->post_id ?? 0,
				$this->amount,
				$this->fee,
				$this->chain_fee,
				$this->block,
				$this->timestamp,
				$this->nonce,
				$this->error,
				$this->post_id
			);
		} else {
			$meta_query = $wpdb->prepare(
				"INSERT INTO
					{$wpdb->postmeta}(post_id,meta_key,meta_value)
				VALUES
					(%d,'wallets_user',%d),
					(%d,'wallets_category',%s),
					(%d,'wallets_txid',%s),
					(%d,'wallets_address_id',%d),
					(%d,'wallets_currency_id',%d),
					(%d,'wallets_amount',%d),
					(%d,'wallets_fee',%d),
					(%d,'wallets_chain_fee',%d),
					(%d,'wallets_block',%d),
					(%d,'wallets_timestamp',%d),
					(%d,'wallets_nonce',%s),
					(%d,'wallets_error',%s)
				;",
				$result, $user_id,
				$result, $this->category,
				$result, $this->txid,
				$result, $this->address->post_id ?? 0,
				$result, $this->currency->post_id ?? 0,
				$result, $this->amount,
				$result, $this->fee,
				$result, $this->chain_fee,
				$result, $this->block,
				$result, $this->timestamp,
				$result, $this->nonce,
				$result, $this->error
			);
		}

		$wpdb->flush();

		$meta_result = $wpdb->query( $meta_query );

		if ( false === $meta_result ) {

			throw new \Exception(
				sprintf(
					'%s: Failed saving transaction with: %s',
					__FUNCTION__,
					$wpdb->last_error
				)
			);
		}

		if ( ! $this->post_id && $result && is_integer( $result ) ) {
			$this->post_id = $result;

		} elseif ( $result instanceof \WP_Error ) {

			throw new \Exception(
				sprintf(
					'Could not save %s to DB: %s',
					__CLASS__,
					$result->get_error_message()
				)
			);
		}

		$this->dirty = false;

	}

	/**
	 * Sets a field of this Transaction object.
	 *
	 * {@inheritDoc}
	 * @see \DSWallets\Post_Type::__set()
	 * @param $name Can be: `post_id`, `category`, `user`, `txid`, `address`, `currency`, `amount`,
	 *              `fee`, `chain_fee`, `comment`, `block`, `timestamp`, `nonce`,
	 *              `status`, `error`, `parent_id`.
	 * @throws \InvalidArgumentException If value is not appropriate for field or if field does not exist.
	 */
	public function __set( $name, $value ) {

		if ( 'category' == $name ) {
			if ( 'deposit' == $value || 'withdrawal' == $value || 'move' == $value ) {
				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->category = $value;
			} else {
				throw new \InvalidArgumentException( 'Category must be one of: deposit, withdrawal, move.' );
			}

		} elseif ( 'user' == $name ) {

			if ( is_null( $value ) || $value instanceof \WP_User ) {
				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->user = $value;
			} else {
				throw new \InvalidArgumentException( 'User must be a WP_User!' );
			}


		} elseif ( 'txid' == $name ) {
			if ( ! $value ) {
				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->txid = null;
				return;
			}

			if ( is_string( $value ) ) {

				if ( 'deposit' == $this->category || 'withdrawal' == $this->category ) {
					$this->dirty = $this->dirty || ( $this->{$name} != $value );
					$this->txid = $value;
				} else {
					throw new \InvalidArgumentException( 'Category must be one of: deposit, withdrawal before setting TXID.' );
				}
			} else {
				throw new \InvalidArgumentException( 'TXID must be a string' );
			}

		} elseif ( 'address' == $name ) {
			if ( ( $value && $value instanceof Address ) || is_null( $value ) ) {
				$this->dirty = $this->dirty || ( ( $this->address->post_id ?? null ) != $value->post_id );
				$this->address = $value;
			} else {
				throw new \InvalidArgumentException( 'Address must be an Address!' );
			}

		} elseif ( 'currency' == $name ) {
			if ( ( $value && $value instanceof Currency ) || is_null( $value ) ) {
				$this->dirty = $this->dirty || ( ( $this->currency->post_id ?? null ) != $value->post_id );
				$this->currency = $value;
			} else {
				throw new \InvalidArgumentException( 'Currency must be a Currency!' );
			}

		} elseif ( 'amount' == $name ) {
			if ( is_numeric( $value ) && ( $value == intval( $value ) ) ) {

				if ( 'deposit' == $this->category && $value < 0 ) {
					throw new \InvalidArgumentException( "Amount must be non-negative for deposits!" );
				}

				if ( 'withdrawal' == $this->category ) {

					if ( $value > 0 ) {
						throw new \InvalidArgumentException( "Amount must be non-positive for withdrawals!" );
					}
				}

				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->{$name} = intval( $value );
			} else {
				throw new \InvalidArgumentException( "Value for field '$name' must be an integer number!" );
			}

		} elseif ( 'fee' == $name ) {
			if ( is_numeric( $value ) && ( $value == intval( $value ) ) && $value <= 0 ) {

				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->{$name} = intval( $value );
			} else {
				throw new \InvalidArgumentException( "Fee must be a non-positive integer!" );
			}

		} elseif ( 'chain_fee' == $name ) {
			if ( is_numeric( $value ) && ( $value == intval( $value ) ) && $value >= 0 ) {

				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->{$name} = intval( $value );
			} else {
				throw new \InvalidArgumentException( "Chain fee must be a non-negative integer!" );
			}

		} elseif ( 'comment' == $name ) {
			if ( is_string( $value ) ) {
				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->comment = $value;
			} elseif ( ! $value ) {
				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->comment = '';
			} else {
				throw new \InvalidArgumentException( 'Comment must be a string!' );
			}

		} elseif ( 'block' == $name ) {
			$this->dirty = $this->dirty || ( $this->{$name} != $value );
			$this->block = absint( $value );

		} elseif ( 'timestamp' == $name ) {
			$this->dirty = $this->dirty || ( $this->{$name} != $value );
			$this->timestamp = absint( $value );

		} elseif ( 'nonce' == $name ) {
			if ( is_null( $value ) ) {
				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->nonce = '';
			} elseif ( is_string( $value ) ) {
				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->nonce = $value;
			} else {
				throw new \InvalidArgumentException( 'Nonce must be a string!' );
			}

		} elseif ( 'status' == $name ) {
			switch ( $value ) {
				case 'pending':
				case 'done':
				case 'failed':
				case 'cancelled':
					$this->dirty = $this->dirty || ( $this->{$name} != $value );
					$this->status = $value;
					break;
				default:
					throw new \InvalidArgumentException( 'Status must be one of: pending, done, failed, cancelled.' );
			}

		} elseif ( 'error' == $name ) {
			if ( $value ) {
				if ( ! is_string( $value ) ) {
					throw new \InvalidArgumentException( 'Error message must be a string!' );
				}

				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->error  = $value;
				$this->status = 'failed';

			} else {
				$this->dirty = $this->dirty || ( $this->{$name} != $value );
				$this->error = '';
			}

		} elseif ( 'parent_id' == $name ) {
			$this->dirty = $this->dirty || ( $this->{$name} != $value );
			$this->parent_id = absint( $value );

		} elseif ( 'tags' == $name ) {
			if ( ! is_array( $value ) )
				throw new \InvalidArgumentException( 'Tags is not an array of custom taxonomy term slugs!' );

			if ( ! $this->post_id ) {
				throw new \Exception( 'Can only add tags to a transaction after it is created on the DB' );
			}

			maybe_switch_blog();

			$term_ids   = [];
			$this->tags = [];

			foreach ( array_unique( array_filter( $value ) ) as $tag_slug ) {
				if ( ! is_string( $tag_slug ) ) {

					maybe_restore_blog();

					throw new \InvalidArgumentException( 'Provided tag is not a string!' );
				}

				// look for custom post type tag by slug
				$term = get_term_by( 'slug', $tag_slug, 'wallets_tx_tags' );

				if ( $term ) {
					// use existing term
					$term_ids[] = $term->term_id;

				} else {
					// create term
					$new_term = wp_insert_term( $tag_slug, 'wallets_tx_tags', [ 'slug' => $tag_slug ] );

					if ( is_array( $new_term ) && isset( $new_term['term_id'] ) ) {
						$term_ids[] = $new_term['term_id'];
					} elseif ( is_wp_error( $term ) ) {

						maybe_restore_blog();

						throw new \Exception(
							sprintf(
								'Could not create new term with slug "%s" for transaction %d, due to: %s',
								$tag_slug,
								$this->post_id,
								$term->get_error_message()
							)
						);
					}

					$this->tags[] = $value;
				}

			}

			$result = wp_set_object_terms( $this->post_id, $term_ids, 'wallets_tx_tags' );

			if ( $result instanceof \WP_Error ) {

				maybe_restore_blog();

				throw new \Exception(
					sprintf(
						'Could not add terms %s to transaction %d because: %s',
						implode( ',', $term_ids),
						$this->post_id,
						$result->get_error_message()
					)
				);
			}

			maybe_restore_blog();

		} else {
			parent::__set( $name, $value );
		}
	}

	public function __get( $name ) {
		switch ( $name ) {
			case 'category':
			case 'user':
			case 'txid':
			case 'address':
			case 'currency':
			case 'comment':
			case 'nonce':
			case 'status':
			case 'error':
				return $this->{$name};

			case 'amount':
			case 'fee':
			case 'chain_fee':
				return intval( $this->{$name} );

			case 'post_id':
			case 'block':
			case 'parent_id':
				return absint( $this->{$name} );

			case 'timestamp':
				if ( $this->timestamp ) {
					return absint( $this->timestamp );
				} else {
					// fall back to returning DB creation time for transaction if no blockchain time
					$date = get_post_datetime( $this->post_id, 'date', 'gmt' );
					if ( $date ) {
						return $date->getTimestamp();
					}
					return 0;
				}

			case 'tags':

				if ( ! is_null( $this->tags ) ) {
					return $this->tags;
				}

				maybe_switch_blog();

				$terms = wp_get_post_terms( $this->post_id, 'wallets_tx_tags' );

				maybe_restore_blog();

				$this->tags = array_map(
					function( $tag ) {
						return $tag->slug;
					},
					$tags
				);

				return $this->tags;

			case 'is_dirty':
				return (bool) $this->dirty;

			default:
				throw new \InvalidArgumentException( "No field $name in Transaction!" );
		}
	}

	/**
	 * Get amount as string.
	 *
	 * Values such as the transacted amount, the fee, and the chain_fee are stored as integers,
	 * in order to avoid floating point errors.
	 *
	 * This convenience function will render the specified field as a string with the correct amount
	 * of decimal places for the transaction's currency.
	 *
	 * @param string $field One of 'amount', 'fee', 'chain_fee', 'amountplusfee'.
	 * @param bool $use_pattern If true, will use the sprintf pattern from the currency. If false, will simply render the decimal amount.
	 * @param bool $positive Whether to force the result to be positive (absolute value).
	 * @throws \InvalidArgumentException If an invalid field is specified.
	 * @return string The amount, rendered.
	 */
	public function get_amount_as_string( string $field = 'amount', bool $use_pattern = false, bool $positive = false ): string {
		switch( $field ) {
			case 'amount':
			case 'fee':
			case 'chain_fee':
				$value = $this->{$field};

				if ( $positive ) {
					$value = abs( $value );
				}

				if ( ! ( $this->currency instanceof Currency ) ) {
					return "$value";
				}

				$value *= 10 ** -$this->currency->decimals;

				if ( $use_pattern && $this->currency->pattern ) {
					return sprintf( $this->currency->pattern ?? '%f', $value );
				} else {
					return number_format( $value, $this->currency->decimals, '.', '' );
				}

			case 'amountplusfee':
				$value = $this->amount + $this->fee;

				if ( $positive ) {
					$value = abs( $value );
				}

				if ( ! ( $this->currency instanceof Currency ) ) {
					return "$value";
				}

				$value *= 10 ** -$this->currency->decimals;

				if ( $use_pattern && $this->currency->pattern ) {
					return sprintf( $this->currency->pattern ?? '%f', $value );
				} else {
					return number_format( $value, $this->currency->decimals, '.', '' );
				}

			default:
				throw new \InvalidArgumentException(
					sprintf(
						'Cannot render %s as string for transaction %d!',
						$field,
						$this->post_id
					)
				);
		}
	}

	public function get_other_tx(): ?Transaction {
		if ( $this->parent_id ) {
			return self::load( $this->parent_id );
		} else {
			return $this->post_id ? get_tx_with_parent( $this->post_id ) : null;
		}
	}
	/**
	 * @inheritdoc
	 * @see Post_Type::__toString()
	 */
	public function __toString(): string {
		if ( 'move' == $this->category ) {
			$other = $this->get_other_tx();
		} else {
			$other = null;
		}

		return sprintf(
			'[[wallets_tx ID:%d type:"%s" status:"%s" currency:"%s" amount:"%s"%s]]',
			$this->post_id ?? 'null',
			'move' == $this->category ? ( $this->amount > 0 ? 'credit' : 'debit' ) : $this->category,
			$this->status,
			$this->currency ? ( $this->currency->name ?? $this->currency->post_id ?? 'null' ) : 'null',
			$this->get_amount_as_string( 'amount', true, true ),
			$other ? " counterpart:$other->post_id" : ''
		);
	}

	public function get_confirmation_link(): ?string {
		if ( $this->nonce ) {
			return rest_url( "dswallets/v1/transactions/validate/$this->nonce" );
		} else {
			return null;
		}
	}

	public static function register() {
		parent::register();

		add_action( 'transition_post_status', [ __CLASS__, 'status_transition'], 10, 3 );

		if ( is_admin() ) {
			add_action( 'manage_wallets_tx_posts_custom_column', [ __CLASS__, 'render_custom_column'  ], 10, 2 );
			add_filter( 'manage_wallets_tx_posts_columns',       [ __CLASS__, 'manage_custom_columns' ] );

			add_action(
				'admin_init',
				function() {
					global $pagenow;

					if ( 'edit.php' == $pagenow && isset( $_GET['post_type' ] ) && 'wallets_tx' == $_GET['post_type'] && ! isset( $_GET['orderby'] ) ) {

						$url = add_query_arg(
							[
								'orderby' => 'date',
								'order'   => 'desc',
							],
							$_SERVER['REQUEST_URI']
						);

						wp_redirect( $url, 302, 'Bitcoin and Altcoin Wallets' );
						exit;
					}
				}
			);

			add_action(
				'pre_get_posts',
				function( $query ) {

					if ( ! is_admin() ) {
						return;
					}

					if ( 'wallets_tx' != ( $query->query['post_type'] ?? '' ) ) {
						return;
					}

					if ( $query->is_main_query() ) {

						$mq = [ 'relation' => 'AND' ];

						if ( isset( $_GET['wallets_user_id'] ) ) {
							$mq[] = [
								'key'   => 'wallets_user',
								'value' => absint( $_GET['wallets_user_id'] ),
							];
						}

						if ( isset( $_GET['wallets_currency_id'] ) ) {

							$mq[] = [
								'key'   => 'wallets_currency_id',
								'value' => absint( $_GET['wallets_currency_id'] ),
							];
						}

						if ( isset( $_GET['wallets_category'] ) ) {

							switch ( $_GET['wallets_category'] ) {

								case 'deposit':
								case 'withdrawal':
								case 'move':

									$mq[] = [
										'key'   => 'wallets_category',
										'value' => $_GET['wallets_category'],
									];
									break;
							}
						}

						if ( isset( $_GET['wallets_status'] ) ) {

							switch ( $_GET['wallets_status'] ) {

								case 'pending':
									$query->set( 'post_status', 'pending' );
									break;

								case 'done':
									$query->set( 'post_status', 'publish' );
									break;

								case 'cancelled':

									$query->set( 'post_status', 'draft' );

									$mq[] = [
										'key'   => 'wallets_error',
										'value' => '',
									];
									break;

								case 'failed':
									$query->set( 'post_status', 'draft' );

									$mq[] = [
										'key'     => 'wallets_error',
										'compare' => '!=',
										'value'   => '',
									];
									break;

								default:

							}
						}

						if ( $query->is_search() && $query->is_main_query() ) {

							$term = trim( $query->query_vars['s'] );

							// Unfortunately it's hard to search against post_title OR post meta values in WP

							if ( $term && strlen( $term ) > 16 && ctype_xdigit( $term ) ) {

								// Here the term is likely a TXID, so we'll only search against TXIDs

								$mq[] = [
									'key'     => 'wallets_txid',
									'compare' => 'LIKE',
									'value'   => $term,
								];

								$query->query_vars['s'] = '';
							}
						}

						$query->set( 'meta_query', $mq );

					}
				}
			);

			add_filter(
				'views_edit-wallets_tx',
				function( $links ) {

					unset( $links['publish'] );
					unset( $links['draft'] );
					unset( $links['trash'] );
					unset( $links['pending'] );

					if ( isset( $_GET['author'] ) && get_current_user_id() != $_GET['author'] ) {

						$author = new \WP_User( $_GET['author'] );

						$url = add_query_arg(
							'author',
							$_GET['author'],
							$_SERVER['REQUEST_URI']
						);

						$link_text = sprintf( __( 'User: %s', 'wallets' ), $author->display_name );

						$links[ "wallets_author_$author->ID" ] = sprintf(
							'<a href="%s" class="current wallets_author current" aria-current="page">%s</a>',
							esc_attr( $url ),
							esc_html( $link_text )
						);

					}

					foreach ( [
						'deposit'    => [ 'icon' => '&#8600;', 'text' => __( 'Deposit', 'wallets' ) ],
						'withdrawal' => [ 'icon' => '&#8599;', 'text' => __( 'Withdrawal', 'wallets' ) ],
						'move'       => [ 'icon' => '&#8596;', 'text' => __( 'Internal transfer (move)', 'wallets' ) ],
					] as $cat => $cat_data ) {

						$url = add_query_arg(
							'wallets_category',
							$cat,
							$_SERVER['REQUEST_URI']
						);

						$link_text = sprintf(
							__( '%s Type: %s', 'wallets' ),
							$cat_data['icon'],
							$cat_data['text']
						);

						if ( ( $_GET['wallets_category'] ?? '' ) == $cat ) {
							$pattern = '<a href="%s" class="wallets_category %s current" aria-current="page">%s</a>';
						} else {
							$pattern = '<a href="%s" class="wallets_category %s">%s</a>';
						}

						$links[ "wallets_category_$cat" ] = sprintf(
							$pattern,
							esc_attr( $url ),
							esc_attr( $cat ),
							esc_html( $link_text )
						);
					}


					foreach ( [
						'pending'   => '&#8987;',
						'done'      => '&#9989;',
						'cancelled' => '&#10062;',
						'failed'    => '&#9888;',

					] as $status => $icon ) {

						$url = add_query_arg(
							'wallets_status',
							$status,
							$_SERVER['REQUEST_URI']
						);

						$link_text = sprintf( __( '%s Status: %s', 'wallets' ), $icon, $status );

						if ( ( $_GET['wallets_status'] ?? '' ) == $status ) {
							$pattern = '<a href="%s" class="wallets_status %s current" aria-current="page">%s</a>';
						} else {
							$pattern = '<a href="%s" class="wallets_status %s">%s</a>';
						}

						$links[ "wallets_status_$status" ] = sprintf(
							$pattern,
							esc_attr( $url ),
							esc_attr( $status ),
							esc_html( $link_text )
						);
					}

					foreach ( get_all_currencies() as $currency ) {

						$url = add_query_arg(
							'wallets_currency_id',
							$currency->post_id,
							$_SERVER['REQUEST_URI']
						);

						$link_text = sprintf(
							__( '%s Currency: %s (%s)', 'wallets' ),
							'&curren;',
							$currency->name,
							$currency->symbol
						);

						if ( $currency->post_id == ( $_GET['wallets_currency_id'] ?? '' ) ) {
							$pattern = '<a href="%s" class="wallets_currency %s current" aria-current="page">%s</a>';
						} else {
							$pattern = '<a href="%s" class="wallets_currency %s">%s</a>';
						}

						$links[ "wallets_currency_$currency->post_id" ] = sprintf(
							$pattern,
							esc_attr( $url ),
							esc_attr( "currency_{$currency->post_id}" ),
							esc_html( $link_text )
						);
					}

					// @phan-suppress-next-line PhanAccessMethodInternal
					foreach ( get_terms( [ 'taxonomy' => 'wallets_tx_tags', 'hide_empty' => true ] ) as $term ) {

						$url = add_query_arg(
							'wallets_tx_tags',
							$term->slug,
							$_SERVER['REQUEST_URI']
						);

						$link_text = sprintf( __( '%s Tag: %s', 'wallets' ), '&#127991;', $term->name );

						if ( in_array( $term->slug, explode( ',', ( $_GET['wallets_tx_tags'] ?? '' ) ) ) ) {
							$pattern = '<a href="%s" class="wallets_tx_tag %s current" aria-current="page">%s</a>';
						} else {
							$pattern = '<a href="%s" class="wallets_tx_tag %s">%s</a>';
						}

						$links[ "wallets_tx_tag_$term->slug" ] = sprintf(
							$pattern,
							esc_attr( $url ),
							esc_attr( $term->slug ),
							esc_attr( $link_text )
						);

					}

					return $links;
				}
			);

			add_filter(
				'page_row_actions',
				function( $actions, $post ) {

					if ( 'wallets_tx' == $post->post_type ) {

						$user = new \WP_User( $post->wallets_user );

						$url = add_query_arg(
							'wallets_user_id',
							$user->ID,
							$_SERVER['REQUEST_URI']
						);


						$link_text = sprintf(
							__( 'Show all for user %s', 'wallets' ),
							$user->display_name
						);

						$actions['author'] = sprintf(
							'<a href="%s">%s</a>',
							esc_attr( $url ),
							esc_html( $link_text )
						);
					}

					return $actions;
				},
				10,
				2
			);

			add_filter(
				'bulk_actions-edit-wallets_tx',
				function( $actions ) {
					if ( ds_current_user_can( 'manage_wallets' ) ) {
						if ( get_ds_option( 'wallets_cron_approve_withdrawals' ) ) {
							$actions['approved_by_admin'] = __( 'Approve', 'wallets' );
						}
					}
					return $actions;
				}
			);

			add_action(
				'handle_bulk_actions-edit-wallets_tx',
				function( $redirect_to, $doaction, $post_ids ) {
					if ( ds_current_user_can( 'manage_wallets' ) ) {

						$query_args = [
							'fields'      => 'ids',
							'post_type'   => 'wallets_tx',
							'post_status' => [ 'pending' ],
							'post__in'    => $post_ids,
							'meta_query'  => [
								'relation' => 'AND',
								[
									'key'   => 'wallets_category',
									'value' => 'withdrawal',
								],
								[
									'key'     => 'wallets_admin_approved',
									'compare' => 'NOT EXISTS',
								],
							],
						];

						$query = new \WP_Query( $query_args );

						$eligible_post_ids = array_values( $query->posts );

						foreach ( $post_ids as $post_id ) {
							update_post_meta( $post_id, 'wallets_admin_approved', true );
						}

						$redirect_to = add_query_arg( 'approved_by_admin', count( $eligible_post_ids ), $redirect_to );
					}

					return $redirect_to;
				},
				10,
				3
			);

			add_action(
				'admin_notices',
				function() {

					$updated_count = $_REQUEST['approved_by_admin'] ?? 0;

					if ( $updated_count > 0 ) {
						$message = sprintf(
							_n(
								'%s pending withdrawal approved by admin.',
								'%s pending withdrawals approved by admin.',
								$updated_count,
								'wallets'
							),
							number_format_i18n( $updated_count )
						);

						?>
						<div class="notice notice-success is-dismissible"><p><?php esc_html_e( $message ) ?></p></div>
						<?php
					}

				}
			);
		}

	}

	public static function register_post_type() {
		register_post_type( 'wallets_tx', [
			'label'              => __( 'Transactions', 'wallets' ),
			'labels'             => [
				'name'               => __( 'Transactions',                   'wallets' ),
				'singular_name'      => __( 'Transaction',                    'wallets' ),
				'menu_name'          => __( 'Transactions',                   'wallets' ),
				'name_admin_bar'     => __( 'Transactions',                   'wallets' ),
				'add_new'            => __( 'Add New',                        'wallets' ),
				'add_new_item'       => __( 'Add New Transaction',            'wallets' ),
				'edit_item'          => __( 'Edit Transaction',               'wallets' ),
				'new_item'           => __( 'New Transaction',                'wallets' ),
				'view_item'          => __( 'View Transaction',               'wallets' ),
				'search_items'       => __( 'Search Transactions',            'wallets' ),
				'not_found'          => __( 'No transactions found',          'wallets' ),
				'not_found_in_trash' => __( 'No transactions found in trash', 'wallets' ),
				'all_items'          => __( 'All Transactions',               'wallets' ),
				'parent_item'        => __( 'Parent Transaction',             'wallets' ),
				'parent_item_colon'  => __( 'Parent Transaction:',            'wallets' ),
				'archive_title'      => __( 'Transactions',                   'wallets' ),
			],
			'public'             => true,
			'show_ui'            => ! is_net_active() || is_main_site(),
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'rewrite'            => [ 'slug' => 'txs' ],
			'show_in_nav_menus'  => false,
			'menu_icon'          => 'dashicons-sort',
			'supports'           => [
				'title',
				'revisions',
			],
			'map_meta_cap'       => true,
			'capability_type'	 => [ 'wallets_tx', 'wallets_txs' ],
		] );

		if ( count_users()['total_users'] < MAX_DROPDOWN_LIMIT ) {
			add_post_type_support( 'wallets_address', 'author' );
		}

	}

	public static function register_taxonomy() {
		register_taxonomy(
			'wallets_tx_tags',
			'wallets_tx',
			[
				'hierarchical' => false,
				'show_in_rest' => true,
				'show_in_nav_menus' => false,
				'labels' => [
					'name'              => _x( 'TX Tags', 'taxonomy general name',  'wallets' ),
					'singular_name'     => _x( 'TX Tag',  'taxonomy singular name', 'wallets' ),
					'search_items'      => __( 'Search TX Tags',  'wallets' ),
					'all_items'         => __( 'All TX Tags',     'wallets' ),
					'edit_item'         => __( 'Edit TX Tag',     'wallets' ),
					'update_item'       => __( 'Update TX Tag',   'wallets' ),
					'add_new_item'      => __( 'Add New TX Tag',  'wallets' ),
					'new_item_name'     => __( 'New TX Tag Name', 'wallets' ),
					'menu_name'         => __( 'TX Tags',         'wallets' ),
					'show_ui'           => false,
				],
			]
		);
	}

	public static function register_meta_boxes() {
		global $post;

		if ( ! $post ) return;

		if ( 'wallets_tx' !== $post->post_type ) return;

		try {
			$tx = self::load( $post->ID );
		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'%s: Cannot instantiate %d to show metaboxes, due to: %s',
					__CLASS__,
					$post->ID,
					$e->getMessage()
				)
			);
			return;
		}

		remove_meta_box(
			'slugdiv',
			'wallets_tx',
			'normal'
		);

		add_meta_box(
			'wallets-transaction-attributes',
			__( 'Transaction Attributes', 'wallets' ),
			[ self::class, 'meta_box_attributes' ],
			'wallets_tx',
			'normal',
			'high',
			$tx
		);

		if ( 'deposit' == $tx->category || 'withdrawal' == $tx->category ) {
			if ( $tx->currency && $tx->currency->is_fiat() ) {

				add_meta_box(
					'wallets-transaction-bank-fiat-specific',
					__( 'Bank Transaction Attributes', 'wallets' ),
					[ self::class, 'meta_box_attributes_bank_fiat' ],
					'wallets_tx',
					'normal',
					'default',
					$tx
				);

			} else {

				add_meta_box(
					'wallets-transaction-blockchain-specific',
					__( 'Blockchain-specific Transaction Attributes', 'wallets' ),
					[ self::class, 'meta_box_attributes_blockchain' ],
					'wallets_tx',
					'normal',
					'default',
					$tx
				);

				add_meta_box(
					'wallets-transaction-explorer-link',
					__( 'Block explorer links', 'wallets' ),
					[ self::class, 'meta_box_explorer_link' ],
					'wallets_tx',
					'side',
					'default',
					$tx
				);
			}
		} elseif ( 'move' == $tx->category ) {

			if ( $tx->get_other_tx() ) {
				add_meta_box(
					'wallets-transaction-other-tx',
					__( 'Counterpart transaction', 'wallets' ),
					[ self::class, 'meta_box_other_tx' ],
					'wallets_tx',
					'side',
					'default',
					$tx
				);
			}
		}

		if (
			( 'move' == $tx->category || 'withdrawal' == $tx->category )
			&& 'pending' == $tx->status
			&& $tx->nonce
			&& $tx->amount < 0
		) {

			add_meta_box(
				'wallets-transaction-nonce',
				__( 'Confirmation link', 'wallets' ),
				[ self::class, 'meta_box_nonce' ],
				'wallets_tx',
				'side',
				'high',
				$tx
			);
		}

		add_meta_box(
			'wallets-transaction-currency',
			__( 'Currency', 'wallets' ),
			[ self::class, 'meta_box_currency' ],
			'wallets_tx',
			'side',
			'default',
			$tx
		);

		if ( 'move' != $tx->category ) {

			add_meta_box(
				'wallets-transaction-address',
				__( 'Address', 'wallets' ),
				[ self::class, 'meta_box_address' ],
				'wallets_tx',
				'side',
				'default',
				$tx
			);
		}

		if ( 'withdrawal' == $tx->category && 'pending' == $tx->status ) {

			add_meta_box(
				'wallets-transaction-pending-wd',
				__( 'Pending withdrawal checks', 'wallets' ),
				[ self::class, 'meta_box_pending_wd' ],
				'wallets_tx',
				'normal',
				'default',
				$tx
			);
		}
	}

	/**
	 * @internal
	 */
	public static function meta_box_explorer_link( $post, $meta_box ) {
		$tx = $meta_box['args'];

		if ( $tx->currency ):
			if ( $tx->currency->explorer_uri_tx ):
				$url = sprintf(
					$tx->currency->explorer_uri_tx,
					$tx->txid
				);
				$parse  = parse_url( $url );
				$domain = $parse['host'] ?? sprintf( __( '%s block explorer', 'wallets' ), $tx->currency->name );
				?>
				<p><?php esc_html_e( 'Visit transaction on the block explorer: ', 'wallets' ); ?></p>
				<a
					target="_blank"
					class="button"
					rel="noopener noreferrer external"
					href="<?php esc_attr_e( $url ); ?>">
					<?php
					esc_html_e( $domain );
					?>
				</a>
			<?php else: ?>
				<p>
				<?php
					esc_html_e(
						'The currency associated with this transaction does not specify a block explorer for transactions.',
						'wallets'
					);
				?>
				</p>
				<a
					target="_blank"
					class="button"
					href="<?php esc_attr_e( get_edit_post_link( $tx->currency->post_id, 'nodisplay' ) ); ?>#wallets-currency-explorer-uri-tx">
					<?php esc_html_e( 'Edit currency', 'wallets' ); ?>
				</a>

			<?php endif; ?>
		<?php else: ?>
			<p>
			<?php
				esc_html_e(
					'No currency is associated with this address.',
					'wallets'
				);
			?>
			</p>
		<?php
		endif;

		if ( $tx->currency ):
			if ( $tx->address ):
				if ( $tx->currency->explorer_uri_add ):
					$url = sprintf(
						$tx->currency->explorer_uri_add,
						$tx->address->address,
						$tx->address->extra
					);
					$parse  = parse_url( $url );
					$domain = $parse['host'] ?? sprintf( __( '%s block explorer', 'wallets' ), $tx->currency->name );
					?>
					<p><?php esc_html_e( 'Visit address on the block explorer: ', 'wallets' ); ?></p>
					<a
						target="_blank"
						class="button"
						rel="noopener noreferrer external"
						href="<?php esc_attr_e( $url); ?>">
						<?php
						esc_html_e( $domain );
						?>
					</a>
				<?php else: ?>
					<p>
					<?php
						esc_html_e(
							'The currency associated with this address does not specify a block explorer for addresses.',
							'wallets'
						);
					?>
					</p>
				<?php endif; ?>
			<?php else: ?>
				<p>
				<?php
					esc_html_e(
						'No address is associated with this transaction.',
						'wallets'
					);
				?>
				</p>
			<?php endif; ?>
		<?php else: ?>
			<p>
			<?php
				esc_html_e(
					'No currency is associated with this transaction.',
					'wallets'
				);
			?>
			</p>
		<?php
		endif;
	}

	public static function meta_box_other_tx( $post, $meta_box ) {
		$tx = $meta_box['args'];
		$other_tx = $tx->get_other_tx();

		?>
		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'This transaction is associated with the following counterpart transaction:', 'wallets' ); ?></span>

			<p>
			<?php
				edit_post_link(
					$other_tx->comment ? $other_tx->comment : __( 'View transaction', 'wallets' ),
					null,
					null,
					$other_tx->post_id
				);
			?>
			</p>

		</label>
		<?php
	}

	public static function meta_box_attributes( $post, $meta_box ) {
		$tx = $meta_box['args'];
		?>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'User', 'wallets' ); ?></span>

			<input
				id="wallets-transaction-user"
				name="wallets_user"
				type="text"
				value="<?php echo $tx->user->user_login ?? ''; ?>"
				class="wallets-login-suggest"
				autocomplete="off" />

			<p class="description"><?php esc_html_e(
				'The transaction will affect this user\'s balance.',
				'wallets'
			); ?></p>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Type', 'wallets' ); ?></span>

			<select
				id="wallets-transaction-category"
				name="wallets_category"
				title="<?php esc_attr_e( 'Type', 'wallets' ); ?>" >

				<?php foreach ( [ 'move', 'deposit', 'withdrawal' ] as $c ): ?>
				<option
					value="<?php esc_attr_e( $c ); ?>"
					<?php selected( $c, $tx->category ); ?>
					><?php esc_html_e( $c ); ?>
				</option>
				<?php endforeach; ?>

			</select>

			<p class="description"><?php esc_html_e(
				'For transactions that are "deposits" or "withdrawals", there is a correspondence to a blockchain transaction. ' .
				'Internal transfers are off-chain transactions that exist only on your DB and facilitate transfer of ' .
				'value between users. These transactions are not associated with blockchain transactions.',
				'wallets'
			); ?></p>
		</label>

		<div class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Status', 'wallets' ); ?></span>

			<?php foreach ( ['pending', 'done', 'cancelled', 'failed' ] as $status ): ?>
			<label style="display:inline-block;min-width:10em;">
				<input
					type="radio"
					id="wallets-transaction-status"
					name="wallets_status"
					value="<?php esc_attr_e( $status ); ?>"
					title="<?php esc_attr_e( 'Status' ); ?>"
					<?php if ( 'failed' == $status && ! $tx->error ): ?>disabled="disabled"<?php endif; ?>
					<?php checked( $tx->status, $status ); ?>>

					<?php esc_html_e( ucfirst( $status ))?>
			</label>
			<?php endforeach; ?>

			<p class="description"><?php esc_html_e(
				'Transaction status. Pending transactions affect the user\'s available balance, ' .
				'and are eligible for execution by cron jobs. ' .
				'Done transactions affect the user\'s balance. ' .
				'Failed and cancelled transactions do not affect any balance. ' .
				'Failed transactions include an error message indicating reason for failure.',
				'wallets'
			); ?></p>
		</div>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Currency', 'wallets' ); ?></span>

			<?php
				wp_dropdown_pages( [
					'post_type'         => 'wallets_currency',
					'id'                => 'wallets-currency-id',
					'name'              => 'wallets_currency_id',
					'selected'          => isset( $tx->currency ) ? $tx->currency->post_id : null,
				] );

			?>
			<p class="description"><?php esc_html_e(
				'The currency associated with this transaction. ' .
				'The transaction is/was executed on this currency\'s designated wallet.',
				'wallets'
			); ?></p>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Amount', 'wallets' ); ?></span>

			<?php if ( $tx->currency && $tx->currency->decimals ): ?>
			<input
				id="wallets-transaction-amount"
				name="wallets_amount"
				title="<?php esc_attr_e( 'Amount', 'wallets' ); ?>"
				type="number"
				value="<?php echo $tx->get_amount_as_string(); ?>"
				<?php if ( $tx->currency->decimals ): ?>onclick="this.value=Number(this.value).toFixed(<?php echo absint( $tx->currency->decimals ); ?>)"<?php endif; ?>
				<?php if ( 'deposit' == $tx->category ): ?>
				min="0"
				<?php elseif ( 'withdrawal' == $tx->category ): ?>
				max="0"
				<?php endif; ?>
				step="<?php echo number_format( 10 ** - absint( $tx->currency->decimals ), $tx->currency->decimals, '.', '' ); ?>" />

				<p class="description"><?php esc_html_e(
					'Amount to transact without fees. Must be POSITIVE for deposits and NEGATIVE for withdrawals. For internal transfers (moves), a positive amount is a credit and a negative amount is a debit transaction.',
					'wallets'
				); ?></p>

			<?php else: ?>

			<p style="color:red"><?php esc_html_e(
				'First set the currency for this transaction, and hit "Publish"!',
				'wallets'
			); ?></p>

			<?php endif; ?>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Fee paid to site', 'wallets' ); ?></span>

			<?php if ( $tx->currency && $tx->currency->decimals ): ?>
			<input
				id="wallets-transaction-fee"
				name="wallets_fee"
				title="<?php esc_attr_e( 'Fee paid to site', 'wallets' ); ?>"
				type="number"
				value="<?php echo $tx->get_amount_as_string( 'fee' ); ?>"
				<?php if ( $tx->currency->decimals ): ?>onclick="this.value=Number(this.value).toFixed(<?php echo absint( $tx->currency->decimals ); ?>)"<?php endif; ?>
				max="0"
				step="<?php echo number_format( 10 ** - absint( $tx->currency->decimals ), $tx->currency->decimals, '.', '' ); ?>"
				<?php disabled( 'deposit' == $tx->category || ( 'move' == $tx->category && $tx->amount > 0 ) ); ?> />

				<p class="description"><?php esc_html_e(
					'The fee to subtract from the sender. This amount must be NEGATIVE. For deposits, and internal credits, it is ignored.',
					'wallets'
				); ?></p>

			<?php else: ?>

			<p style="color:red"><?php esc_html_e(
				'First set the currency for this transaction, and hit "Publish"!',
				'wallets'
			); ?></p>

			<?php endif; ?>
		</label>

		<?php if ( 'move' == $tx->category ): ?>
			<?php $possible_transaction_counterparts = get_possible_transaction_counterparts( $tx ); ?>

		<label class="wallets_meta_box_label">


			<?php if ( count( $possible_transaction_counterparts ) < MAX_DROPDOWN_LIMIT ): ?>
			<span><?php esc_html_e( 'Parent transaction', 'wallets' ); ?></span>

			<select
				id="wallets-parent-id"
				name="wallets_parent_id">

				<option value="0"><?php esc_html_e( 'None', 'wallets' ); ?></option>

				<?php foreach ( $possible_transaction_counterparts as $txc_id => $txc ): ?>
				<option
					value="<?php esc_attr_e( $txc_id );?>"
					<?php selected( $txc_id == $tx->parent_id ); ?>>
					<?php esc_html_e( $txc->post_title ); ?>
				</option>
				<?php endforeach; ?>

			</select>
			<?php else: ?>
			<span><?php esc_html_e( 'Parent transaction post ID', 'wallets' ); ?></span>
			<input
				id="wallets-parent-id"
				name="wallets_parent_id"
				type="number"
				min="0"
				value="<?php echo absint( $tx->parent_id ); ?>" />

			<?php endif; ?>

			<p class="description"><?php esc_html_e(
				'For internal transfers (moves), credits (i.e. amount>0) have their corresponding debit transaction assigned as parent. ' .
				'If this is a debit transaction (i.e. amount<0), do not assign a parent.',
				'wallets'
			); ?></p>

		</label>
		<?php
		endif;
	}

	public static function meta_box_attributes_bank_fiat( $post, $meta_box ) {
		$tx = $meta_box['args'];
		?>

		<label class="wallets_meta_box_label">

			<span><?php esc_html_e( 'Recipient name and home address', 'wallets' ); ?></span>

			<textarea
				id="wallets-address"
				name="wallets_address"
				title="<?php esc_attr_e( 'Address', 'wallets' ); ?>"
				onkeyup="let e = document.getElementById('wallets-transaction-address-link'); if ( e ) e.remove();"
			><?php echo esc_textarea( $tx->address ? $tx->address->address : '' ); ?></textarea>

			<p class="description"><?php esc_html_e(
				'For fiat currencies, this is the name and address of the user.',
				'wallets'
			); ?></p>

		</label>

		<label class="wallets_meta_box_label">

			<span><?php esc_html_e( 'Bank name and address', 'wallets' ); ?></span>

			<textarea
				id="wallets-extra"
				name="wallets_extra"
				title="<?php esc_attr_e( 'Address extra field', 'wallets' ); ?>"
				onkeyup="let e = document.getElementById('wallets-transaction-address-link'); if ( e ) e.remove();"
			><?php echo esc_textarea( $tx->address ? $tx->address->extra : '' ); ?></textarea>

			<p class="description"><?php esc_html_e(
				'For fiat currencies, this is the name and address of the user\'s bank. Other bank details are stored in the address\'s label.',
				'wallets'
			); ?></p>

		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Transaction ID (TXID)', 'wallets' ); ?></span>

			<input
				id="wallets-transaction-txid"
				name="wallets_txid"
				title="<?php esc_attr_e( 'Transaction ID', 'wallets' ); ?>"
				type="text"
				value="<?php esc_attr_e( $tx->txid ); ?>" />

			<p class="description"><?php esc_html_e(
				'For fiat currencies, this field holds the transaction ID as given by the bank.',
				'wallets'
			); ?></p>

		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Admin error message', 'wallets' ); ?></span>

			<textarea
				id="wallets-transaction-error"
				disabled="disabled"
				style="width: 100%;"
				title="<?php esc_attr_e( 'Admin error message', 'wallets' ); ?>"
			><?php echo esc_textarea( $tx->error ); ?></textarea>

			<p class="description"><?php esc_html_e(
				'Last error that occurred while trying to execute this transaction',
				'wallets'
			); ?></p>
		</label>
		<?php
	}

	public static function meta_box_attributes_blockchain( $post, $meta_box ) {
		$tx = $meta_box['args'];
		?>

		<label class="wallets_meta_box_label">

			<span><?php esc_html_e( 'Address string', 'wallets' ); ?></span>

			<input
				id="wallets-address"
				name="wallets_address"
				title="<?php esc_attr_e( 'Address', 'wallets' ); ?>"
				type="text"
				onkeyup="let e = document.getElementById('wallets-transaction-address-link'); if ( e ) e.remove();"
				value="<?php esc_attr_e( $tx->address->address ?? '' ); ?>" />

			<p class="description"><?php esc_html_e(
				'Enter a blockchain address that this deposit or withdrawal will be associated with.',
				'wallets'
			); ?></p>

		</label>

		<label class="wallets_meta_box_label">

			<span><?php esc_html_e( $tx->currency->extra_field_name ); ?></span>

			<input
				id="wallets-extra"
				name="wallets_extra"
				title="<?php esc_attr_e( 'Address extra field', 'wallets' ); ?>"
				type="text"
				onkeyup="let e = document.getElementById('wallets-transaction-address-link'); if ( e ) e.remove();"
				value="<?php esc_attr_e( $tx->address->extra ?? '' ); ?>" />


			<p class="description"><?php esc_html_e(
				'Optional extra field that some blockchains use (e.g. Monero Payment ID, Ripple Destination Tag, etc.)',
				'wallets'
			); ?></p>

		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Transaction ID (TXID)', 'wallets' ); ?></span>

			<input
				id="wallets-transaction-txid"
				name="wallets_txid"
				title="<?php esc_attr_e( 'Transaction ID', 'wallets' ); ?>"
				type="text"
				value="<?php esc_attr_e( $tx->txid ); ?>" />

			<p class="description"><?php esc_html_e(
				'For transactions that correspond to a blockchain transaction (i.e. deposits/withdrawals), ' .
				'this field holds the transaction ID (TXID) string from the blockchain.',
				'wallets'
			); ?></p>

		</label>

		<?php if ( 'deposit' == $tx->category || 'withdrawal' == $tx->category ): ?>
		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Transaction fee paid on the blockchain', 'wallets' ); ?></span>

			<?php if ( $tx->currency && $tx->currency->decimals ): ?>
			<input
				id="wallets-transaction-chain-fee"
				disabled="disabled"
				<?php esc_attr_e( 'Transaction fee paid on the blockchain', 'wallets' ); ?>"
				type="number"
				value="<?php echo $tx->get_amount_as_string( 'chain_fee' ); ?>"
				/>

			<p class="description"><?php esc_html_e(
				'This is the miner fee paid on the blockchain for this transaction. This may be very different to ' .
				'the fee paid to the site. For example, a blockchain transaction with multiple outputs can perform ' .
				'many deposits to many user addresses by paying only one miner fee, but each deposit on your site ' .
				'can have its own deposit fee, depending on your wallet settings.',
				'wallets'
			); ?></p>

			<?php else: ?>

			<p style="color:red"><?php esc_html_e(
				'First set the currency for this transaction, and hit "Publish"!',
				'wallets'
			); ?></p>

			<?php endif; ?>
		</label>
		<?php endif; ?>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Block height for this transaction', 'wallets' ); ?></span>

			<input
				id="wallets-transaction-block"
				disabled="disabled"
				title="<?php esc_attr_e( 'Block height for this transaction', 'wallets' ); ?>"
				type="number"
				value="<?php esc_attr_e( $tx->block ); ?>"
				min="0" />

			<p class="description"><?php esc_html_e(
				'The block height that this transaction was executed on. ' .
				'Depending on the wallet adapter, this information may not always be available.',
				'wallets'
			); ?></p>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Timestamp', 'wallets' ); ?></span>

			<input
				id="wallets-transaction-timestamp"
				disabled="disabled"
				title="<?php esc_attr_e( 'Timestamp for this transaction according to the blockchain', 'wallets' ); ?>"
				type="number"
				value="<?php esc_attr_e( $tx->timestamp ); ?>"
				min="0" />

			<p class="description"><?php esc_html_e(
				'UNIX timestamp for this transaction according to the blockchain',
				'wallets'
			); ?></p>
		</label>

		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Wallet adapter error message', 'wallets' ); ?></span>

			<textarea
				id="wallets-transaction-error"
				disabled="disabled"
				style="width: 100%;"
				title="<?php esc_attr_e( 'Wallet adapter error message', 'wallets' ); ?>"
			><?php echo esc_textarea( $tx->error ); ?></textarea>


			<p class="description"><?php esc_html_e(
				'Last error that occurred while trying to execute this transaction',
				'wallets'
			); ?></p>
		</label>
		<?php
	}

	/**
	 * @internal
	 */
	public static function meta_box_currency( $post, $meta_box ) {
		$tx = $meta_box['args'];
		?>
		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Currency transacted:', 'wallets' ); ?></span>

			<p>
			<?php
			if ( isset( $tx->currency->post_id ) && $tx->currency->post_id ) {
				edit_post_link(
					$tx->currency->name ? $tx->currency->name : __( 'Currency', 'wallets' ),
					null,
					null,
					$tx->currency->post_id
				);
			} else {
				esc_html_e(
					'This transaction is, strangely enough, not associated with a currency! This is bad!',
					'wallets'
				);
			}
			?>
			</p>

		</label>
		<?php
	}

	/**
	 * @internal
	 */
	public static function meta_box_nonce( $post, $meta_box ) {
		$tx   = $meta_box['args'];
		$link = $tx->get_confirmation_link();

		?>
		<label class="wallets_meta_box_label">

			<p>
			<?php
				esc_html_e(
					'This pending transaction has not yet been confirmed by the sending user. To confirm it, follow the following link:',
					'wallets'
				);
			?>
			</p>

			<a
				target="_blank"
				href="<?php esc_attr_e( $link ); ?>"><?php esc_html_e( $link ); ?></a>

		</label>
		<?php
	}

	/**
	 * @internal
	 */
	public static function meta_box_address( $post, $meta_box ) {
		$tx = $meta_box['args'];
		?>
		<label class="wallets_meta_box_label">
			<span><?php esc_html_e( 'Address associated with this transaction:', 'wallets' ); ?></span>

			<p>
			<?php
			if ( isset( $tx->address->post_id ) && $tx->address->post_id ) {
				edit_post_link(
					$tx->address->label ? $tx->address->label : $tx->address->address,
					null,
					null,
					$tx->address->post_id
				);
			} else {
				esc_html_e(
					'This transaction is, strangely enough, not associated with an address! This is bad!',
					'wallets'
				);
			}
			?>
			</p>

		</label>
		<?php
	}

	/**
	 * @internal
	 */
	public static function meta_box_pending_wd( $post, $meta_box ) {
		$wd = $meta_box['args'];

		try {

			/** This action is documented in cron/class-withdrawals-task.php */
			do_action(
				'wallets_withdrawal_pre_check',
				$wd
			);

		} catch ( \Exception $e ) {
			?>
				<p style="color:red;"><?php esc_html_e( 'A withdrawal pre-check has raised an error: ', 'wallets' ); ?></p>
				<p style="color:red;"><?php esc_html_e( $e->getMessage() ); ?></p>

			<?php

			return;
		}

		?>
		<p style="color:green;"><?php esc_html_e( 'All withdrawal pre-checks have passsed', 'wallets' ); ?></p>

		<hr />

		<?php

		if (
			/** This filter is documented in cron/class-withdrawals-task.php */
			apply_filters(
				'wallets_withdrawals_pre_check',
				[ $wd ],
				function( $log ) {
					?><p><code><?php esc_html_e( $log ); ?></code></p><?php
				}
			)
		): ?>

			<p style="color:green;"><?php esc_html_e( 'All pre-filters pass', 'wallets' ); ?></p>

		<?php else: ?>

			<p style="color:red;"><?php esc_html_e( 'A withdrawal pre-filter has failed on this transaction', 'wallets' ); ?></p>

		<?php endif;
	}


	/**
	 * Save post meta values taken from metabox inputs
	 */
	public static function save_post( $post_id, $post, $update ) {
		if ( $post && 'wallets_tx' == $post->post_type ) {

			if ( 'trash' == ( $_GET['action'] ?? null ) ) {
				return;
			}

			// https://wordpress.stackexchange.com/a/96055/17616
			if ( wp_verify_nonce( $_POST['_inline_edit'] ?? '', 'inlineeditnonce' ) ) {
				return;
			}

			// disable Auto-Drafts
			if ( 'auto-draft' == $post->post_status ) {
				return;
			}

			try {
				if ( $update ) {
					if ( isset( self::$object_cache[ $post_id ] ) ) {
						unset( self::$object_cache[ $post_id ] );
					};

					$tx = self::load( $post_id );
				} else {
					$tx = new Transaction;
				}

				if ( 'editpost' == ( $_POST['action'] ?? '' ) ) {

					$tx->post_id = $post_id;

					$tx->__set( 'category', $_POST['wallets_category'] ?? $tx->category );

					if ( $_POST['wallets_user'] ?? null ) {
						$user = get_user_by( 'login', $_POST['wallets_user'] );

						if ( $user ) {
							$tx->__set( 'user', $user );
						} else {
							$tx->__set( 'user', null );
						}
					} else {
						$tx->__set( 'user', null );
					}

					if ( in_array( $tx->category, [ 'deposit', 'withdrawal' ] ) ) {
						$tx->__set( 'txid', $_POST['wallets_txid'] ?? '' );
					} else {
						$tx->__set( 'txid', null );
					}

					// we first process the currency so that when we process the address we can compare currency post_ids
					if ( $_POST['wallets_currency_id'] ?? null ) {
						$tx->__set( 'currency', Currency::load( absint( $_POST['wallets_currency_id'] ) ) );
					} else {
						$tx->__set( 'currency', null );
					}

					if ( isset( $_POST['wallets_address'] ) && $_POST['wallets_address'] ) {
						$a = $_POST['wallets_address'];
						$e = $_POST['wallets_extra'] ?? null;

						$address = null;
						// attempt to find existing address with these strings
						if ( 'deposit' == $tx->category ) {
							$address = get_deposit_address_by_strings( $a, $e );
						} elseif ( 'withdrawal' == $tx->category ) {
							$address = get_withdrawal_address_by_strings( $a, $e );
						}

						if ( ! $address || ( $tx->currency->post_id != $address->currency->post_id ) ) {
							// did not find address with these exact strings
							$address = new Address();
							$address->__set( 'address',  $a );
							$address->__set( 'extra',    $e );
							$address->__set( 'currency', $tx->currency );
							$address->__set( 'user',     $tx->user );

							try {
								$address->save();

							} catch ( \Exception $e ) {
								throw new \Exception(
									sprintf(
										'%s: Could not create address %s for withdrawal transaction with ID %d: %s',
										__METHOD__,
										$address->address,
										$tx->post_id ?? 0,
										$e->getMessage()
									)
								);
							}
						}
						// assign newly created address or existing address to transaction
						$tx->__set( 'address', $address );

					} else {
						// user left the address string blank
						$tx->__set( 'address', null );
					}

					if ( $tx->currency && $tx->currency->decimals ) {
						$tx->__set( 'amount', intval( round( ( $_POST['wallets_amount'] ?? 0 ) * 10 ** $tx->currency->decimals ) ) );
						if ( isset( $_POST['wallets_fee'] ) ) {
							$tx->__set( 'fee',   -absint( round( floatval( $_POST['wallets_fee'] ) * 10 ** $tx->currency->decimals ) ) );
						}
					}

					$tx->__set( 'comment', $_POST['post_title'] ?? '' );

					$tx->__set( 'status', $_POST['wallets_status'] ?? 'pending' );

					if ( $tx->amount > 0 ) {
						$tx->__set( 'parent_id', $_POST['wallets_parent_id'] ?? 0 );
					} else {
						$tx->__set( 'parent_id', 0 );
					}
				}

				$tx->save();


			} catch ( \Exception $e ) {
				wp_die(
					sprintf( __( 'Could not save %s to DB due to: %s', 'wallets' ), __CLASS__, $e->getMessage() ),
					sprintf( 'Failed to save %s', __CLASS__ )
				);
			}

		} // end if post && post type

	}

	/**
	 * Here we do all sorts of hacky shenanigans to detect the true status transition for a tx.
	 *
	 * The complication is that the post status can change for any number of reasons and without warning.
	 * For example, if you set a status to pending and hit publish, the post status for the transaction
	 * will be first set to publish by WordPress, then the plugin code will set it back to draft.
	 *
	 * @param string $new_status The "new" status, as reported by the transition_post_status action.
	 * @param string $old_status The "old" status, as reported by the transition_post_status action.
	 * @param \WP_Post $post The transaction post object.
	 */
	public static function status_transition( string $new_status, string $old_status, \WP_Post $post ) {
		if ( 'wallets_tx' != $post->post_type ) {
			return;
		}
		static $old_statuses_original = [];
		static $new_statuses_final = [];
		static $action_hooked = false;

		$new_statuses_final[ $post->ID ] = $new_status;
		if ( ! isset( $old_statuses_original[ $post->ID ] ) ) {
			$old_statuses_original[ $post->ID ] = $old_status;

		}

		if ( ! $action_hooked ) {
			add_action(
				'shutdown',
				function() use ( &$old_statuses_original, &$new_statuses_final ) {

					// Ensure that any tx status values come straight from DB and not from cache
					foreach ( Post_Type::$object_cache as $id => $object ) {
						if ( $object instanceof Transaction ) {
							unset( Post_Type::$object_cache[ $id ] );
						}
					}
					Transaction::load_many( array_keys( $old_statuses_original ) );

					foreach ( array_keys( $old_statuses_original ) as $post_id ) {
						if ( $old_statuses_original[ $post_id ] != $new_statuses_final[ $post_id ] ) {

							try {
								$tx = Transaction::load( $post_id );

								if ( $tx->category && 'move' != $tx->category && $tx->amount ) {

									/**
									 *  Action to notify user and admins about a blockchain transaction.
									 *
									 *  This is where we hook the code to render email templates
									 *  and enqueue emails.
									 *
									 *  Other notification mechanisms can also be attached here.
									 *
									 *  @since 6.0.0 Introduced.
									 *
									 *  @param Transaction $tx The blockchain transaction to notify about.
									 */
									do_action( 'wallets_email_notify', $tx );

								} elseif ( 'move' == $tx->category ) {
									$counterpart_tx = $tx->get_other_tx();

									if ( $counterpart_tx && $counterpart_tx->status != $tx->status ) {

										// both txs must have same status
										$counterpart_tx->status = $tx->status;

										try {
											$counterpart_tx->saveButDontNotify();
										} catch ( \Exception $e ) {
											error_log( "Could not set status of $counterpart_tx to be equal to $tx->status" );
										}
									}

									if ( $tx->amount > 0 ) {

										/** This action is documented in this file. See above. */
										do_action( 'wallets_email_notify', $tx ); // notify about the credit tx

										if ( $counterpart_tx ) {
											/** This action is documented in this file. See above. */
											do_action( 'wallets_email_notify', $counterpart_tx ); // notify about the corresponding debit tx
										}

									} elseif ( $tx->amount < 0 && ! $counterpart_tx ) {
										/** This action is documented in this file. See above. */
										do_action( 'wallets_email_notify', $tx ); // notify about a dangling debit tx (without a corresponding credit tx)
									}
								}

							} catch ( \Exception $e ) {
								// we can cancel the notification by binding to the wallets_email_notify action
								// with priority < 10 and throwing an exception.
								// the error is logged but the transaction will continue to be saved
								// and the email will not be sent
								error_log(
									sprintf(
										'status_transition: Encountered exception on wallets_email_notify action: %s',
										$e->getMessage()
									)
								);
							}
						}
					}
				}
			);
			$action_hooked = true;
		}
	}

	public static function manage_custom_columns( $columns ) {
		unset( $columns['author'] ); // we want to call it user instead

		$columns['tx_user']       = esc_html__( 'User', 'wallets' );
		$columns['tx_currency']   = esc_html__( 'Currency', 'wallets' );
		$columns['tx_status']     = esc_html__( 'Status', 'wallets' );
		$columns['tx_type']       = esc_html__( 'Type', 'wallets' );
		$columns['tx_amount']     = esc_html__( 'Amount', 'wallets' );
		$columns['tx_address']    = esc_html__( 'Address', 'wallets' );
		$columns['tx_txid']       = esc_html__( 'TXID', 'wallets' );

		if ( get_ds_option( 'wallets_cron_approve_withdrawals', DEFAULT_CRON_APPROVE_WITHDRAWALS ) ) {
			$columns['tx_approved'] = esc_html__( 'Approved by admin', 'wallets' );
		}

		return $columns;
	}

	public static function render_custom_column( $column, $post_id ) {
		$tx  = null;
		try {
			$tx = self::load( $post_id );
		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					__( 'Cannot initialize transaction %d to render column, due to: %s', 'wallets' ),
					$post_id,
					$e->getMessage()
				)
			);
			?>&mdash;<?php
			return;
		}

		if ( 'tx_currency' == $column ):
			if ( $tx->currency ) {
				edit_post_link(
					$tx->currency->name,
					null,
					null,
					$tx->currency->post_id
				);
			} else {
				?>&mdash;<?php
			}

		elseif ( 'tx_status' == $column ):

			if ( 'error' == $tx->status ):
				?><code title="<?php esc_attr_e( $tx->error ); ?>"><?php esc_html_e( $tx->status ); ?></code><?php

			elseif ( 'pending' == $tx->status && $tx->nonce && in_array( $tx->category, [ 'withdrawal', 'move' ] ) ):
				?>
				<code>pending</code>
				<?php
					esc_html_e(
						'(Not yet confirmed by sender)',
						'wallets'
					);

			else:
				?><code><?php esc_html_e( $tx->status ); ?></code><?php
			endif;

		elseif ( 'tx_type' == $column ):
			esc_html_e( $tx->category );

		elseif ( 'tx_amount' == $column ):
			if ( $tx->currency ) {
				printf(
					$tx->currency->pattern ?? '%f',
					$tx->amount * 10 ** -$tx->currency->decimals
				);
			} else {
				?>&mdash;<?php
			}

		elseif ( 'tx_address' == $column ):
			if ( $tx->address ) {

				/** This filter is documented in post-types/class-currency.php */
				$pattern = apply_filters( "wallets_explorer_uri_add_{$tx->currency->symbol}", $tx->currency->explorer_uri_add );

				if ( $pattern ) {
					?>
					<a
						href="<?php esc_attr_e( sprintf( $pattern, $tx->address->address, $tx->address->extra ) ); ?>"
						rel="noopener noreferrer external sponsored"
						target="_blank">
						<?php
						esc_html_e( $tx->address->extra ? sprintf( '%s (%s)', $tx->address->address, $tx->address->extra ) : $tx->address->address );
						?>
					</a>
					<?php

				} else {

					edit_post_link(
						$tx->address->label ? $tx->address->label : $tx->address->address,
						null,
						null,
						$tx->address->post_id
					);
				}
			} else {
				?>&mdash;<?php
			}

		elseif ( 'tx_txid' == $column ):
			if ( $tx->txid ) {

				$pattern = apply_filters( "wallets_explorer_uri_tx_{$tx->currency->symbol}", $tx->currency->explorer_uri_tx );

				if ( $pattern ) {
					?>
						<a
							target="_blank"
							rel="noopener noreferrer external sponsored"
							href="<?php esc_attr_e( sprintf( $pattern, $tx->txid ) ); ?>">

							<code>
							<?php
								esc_html_e( $tx->txid );
							?>
							</code>
						</a>
					<?php

				} else {
					?>
					<code>
					<?php
						esc_html_e( $tx->txid );
					?>
					</code>
					<?php
				}

			} else {
				?>
				&mdash;
				<?php
			}

		elseif( 'tx_user' == $column ):
			if ( $tx->user ):
				?>
				<a
					href="<?php esc_attr_e( get_edit_user_link( $tx->user->ID ) ); ?>">
					<?php esc_html_e( $tx->user->display_name ); ?>
				</a>
				<?php
			else:
				?>&mdash;<?php
			endif;

		elseif( 'tx_approved' == $column ):

			if ( 'pending' == $tx->status && 'withdrawal' == $tx->category ):
				if ( get_post_meta( $tx->post_id, 'wallets_admin_approved', true ) ):
					printf(
						'&#x2713; %s',
						__( 'Approved', 'wallets' )
					);
				endif;
			endif;

		endif;
	}
}

add_action(
	'edit_form_top',
	function() {
		if ( ! ds_current_user_can( 'manage_wallets' ) ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( 'wallets_tx' ==  $current_screen->post_type && 'post' == $current_screen->base ):
		?>
			<a
				class="wallets-docs button"
				target="_wallets_docs"
				href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#transactions' ) ); ?>">
				<?php esc_html_e( 'See the Transactions CPT documentation', 'wallets' ); ?></a>

			<?php
		endif;
	}
);

add_action(
	'manage_posts_extra_tablenav',
	function() {
		if ( ! ds_current_user_can( 'manage_wallets' ) ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( 'wallets_tx' ==  $current_screen->post_type && 'edit' == $current_screen->base ):
		?>
			<a
				class="wallets-docs button"
				target="_wallets_docs"
				href="<?php esc_attr_e( admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#transactions' ) ); ?>">
				<?php esc_html_e( 'See the Transactions CPT documentation', 'wallets' ); ?></a>

			<?php
		endif;
	}
);
