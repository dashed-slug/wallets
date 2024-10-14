<?php

/**
 * The wallet adapter class
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

// Handler for rescrape button
if ( 'wallets_scrape_restart' == ( $_POST['action'] ?? '' ) ) {
	add_action(
		'admin_init',
		function() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				return;
			}

			$wallet_id = absint( $_POST['wallet_id'] );
			$new_height = absint( $_POST['wallets_height'] );
			if ( $wallet_id ) {
				error_log( "User requested to restart scraping for wallet $wallet_id" );
				$transient_name = "dsw_bitcoin_{$wallet_id}_height";
				set_ds_transient( $transient_name, $new_height );
			}

			$redirect_url = admin_url( "post.php?post=$wallet_id&action=edit" );
			wp_redirect( $redirect_url );
			exit;
		}
	);
};

/**
 * The Bitcoin core-like adapter is for communicating with Bitcoin core and with similar wallets.
 *
 * "Similar" wallets are those that are direct forks from Bitcoin. As long as the JSON-RPC API is the same,
 * this adapter can be used for a large number of full node wallets. Examples are Litecoin and Dogecoin.
 *
 * @since 6.0.0 Introduced.
 * @api
 */
class Bitcoin_Core_Like_Wallet_Adapter extends Wallet_Adapter {

	protected $sequence_id = 0;

	/**
	 * @var integer How many blocks behind to start scraping from on the wallet adapter's cron.
	 */
	protected $scrape_behind = 16;

	public function __construct( Wallet $wallet ) {
		$this->settings_schema = [
			[
				'id'            => 'rpc_ip',
				'name'          => __( 'IP address for the wallet', 'wallets' ),
				'type'          => 'string',
				'description'   => __( 'The IP of the machine running your wallet daemon. Set to 127.0.0.1 if you are running the daemon on the same machine as WordPress. If you want to enter an IPv6 address, enclose it in square brackets e.g.: [0:0:0:0:0:0:0:1].', 'wallets' ),
				'default'       => '127.0.0.1',
				'validation_cb' => [ $this, 'validate_tcp_ip_address' ],
			],
			[
				'id'            => 'rpc_port',
				'name'          => __( 'TCP port for the wallet', 'wallets' ),
				'type'          => 'number',
				'description'   => __( 'The TCP port where the wallet daemon listens for JSON-RPC connections. It should match the <code>rpcport</code> setting in your .conf file.', 'wallets' ),
				'min'           => 0,
				'max'           => 65535,
				'step'          => 1,
				'default'       => 8332,
			],
			[
				'id'            => 'rpc_user',
				'name'          => __( 'JSON-RPC username', 'wallets' ),
				'type'          => 'string',
				'description'   => __( 'The username part of the credentials to connect to the JSON-RPC port. It should match the <code>rpcuser</code> setting in your .conf file.', 'wallets' ),
				'default'       => '',
			],
			[
				'id'            => 'rpc_password',
				'name'          => __( 'JSON-RPC password', 'wallets' ),
				'type'          => 'secret',
				'description'   => __( 'The password part of the credentials to connect to the JSON-RPC port. It should match the <code>rpcpassword</code> setting in your .conf file. Note that this password will be stored on your MySQL DB.', 'wallets' ),
				'default'       => '',
			],
			[
				'id'            => 'rpc_passphrase',
				'name'          => __( 'Wallet Passphrase', 'wallets' ),
				'type'          => 'secret',
				'description'   => __( 'The passphrase used to unlock your wallet. Only needed for withdrawals. Leave empty if withdrawals are not needed or if the wallet is not encrypted with a passphrase. Note that this passphrase will be stored on your MySQL DB.', 'wallets' ),
				'default'       => '',
			],
			[
				'id'            => 'rpc_path',
				'name'          => __( 'JSON-RPC endpoint path', 'wallets' ),
				'type'          => 'string',
				'description'   => __( 'The path segment the JSON-RPC endpoint URI. Usually you will want to leave this empty.', 'wallets' ),
				'default'       => '',
			],
			[
				'id'            => 'rpc_ssl',
				'name'          => __( 'JSON-RPC SSL enabled', 'wallets' ),
				'type'          => 'boolean',
				'description'   => __( 'Check to enable JSON-RPC communication over SSL. This is deprecated in Bitcoin core, but other coins may still be using it. Only use it if you have specified <code>rpcssl=1</code> in your .conf file.', 'wallets' ),
				'default'       => false,
			],
			[
				'id'            => 'min_confirm',
				'name'          => __( 'Minimum number of confirmations for incoming deposits.', 'wallets' ),
				'type'          => 'number',
				'description'   => __( 'How many blocks should elapse before a deposit progresses from "pending" status to "done" status.', 'wallets' ),
				'min'           => 1,
				'max'           => 100,
				'step'          => 1,
				'default'       => 6,
			],
		];

		parent::__construct( $wallet );
	}

	// start of adapter API //

	public function do_description_text(): void {
		?>

		<blockquote>
		<?php
		esc_html_e(
			'The Bitcoin core-like adapter is for communicating with Bitcoin core and with similar wallets. ' .
			'"Similar" wallets are those that are direct forks from Bitcoin. As long as the JSON-RPC API is the same, ' .
			'this adapter can be used for a large number of full node wallets. Examples are Litecoin and Dogecoin.',
			'wallets'
		);
		?>
		</blockquote>

		<h3><?php esc_html_e( 'Recommended .conf file settings', 'wallets' ); ?></h3>

		<p><?php esc_html_e( '(based on the settings you have provided)', 'wallets' ); ?></p>

		<label style="display:inline-block;margin-bottom: 1em;">
			<?php
			$salt        = bin2hex( random_bytes( 16 ) );
			$result      = hash_hmac( 'sha256', $this->rpc_password, $salt );
			$salted_pass = $this->rpc_user . ':' . $salt . '$' . $result;
			?>
			<code><?php esc_html_e( 'rpcauth', 'wallets' ); ?></code>

			<span
				class="wallets-clipboard-copy"
				style="float:right;font-size:large;"
				onClick="jQuery(this).next()[0].select();document.execCommand('copy');"
				title="<?php esc_attr__( 'Copy to clipboard', 'wallets' ); ?>">&#x1F4CB;</span>

			<input
				style="clear:right;width:100%;"
				type="text"
				readonly="readonly"
				value="rpcauth=<?php esc_attr_e( $salted_pass ); ?>" />
		</label>

		<label style="display:inline-block;margin-bottom:1em;">
			<?php
			$cs = get_currencies_for_wallet( $this->wallet );

			foreach ( $cs as $currency ):
				if ( isset( $currency ) && $currency ):
					$walletnotify_url = (string) site_url( "wp-json/dswallets/v1/walletnotify/{$currency->post_id}/%s" );
					$blocknotify_url  = (string) site_url( "wp-json/dswallets/v1/blocknotify/{$currency->post_id}/%s" );
				else:
					$walletnotify_url = (string) site_url( "wp-json/dswallets/v1/walletnotify/CURRENCY_ID/%s" );
					$blocknotify_url  = (string) site_url( "wp-json/dswallets/v1/blocknotify/CURRENCY_ID/%s" );
			endif;
			?>
		</label>

		<label style="display:inline-block;margin-bottom: 1em;">
			<span>
				<?php
					printf(
						__( '<code>%s</code> for %s (%s) wallet', 'wallets' ),
						'walletnotify',
						$currency->name,
						$currency->symbol
					);
				?>
			</span>

			<span
				class="wallets-clipboard-copy"
				style="float:right;font-size:large;"
				onClick="jQuery(this).next()[0].select();document.execCommand('copy');"
				title="<?php esc_attr__( 'Copy to clipboard', 'wallets' ); ?>">&#x1F4CB;</span>

			<input
				style="clear:right;width:100%;"
				type="text"
				readonly="readonly"
				value="walletnotify=curl -s '<?php esc_attr_e( $walletnotify_url ); ?>' >/dev/null" />

		</label>

		<label style="display:inline-block;margin-bottom: 1em;">
			<span>
				<?php
					printf(
						__( '<code>%s</code> for %s (%s) wallet', 'wallets' ),
						'blocknotify',
						$currency->name,
						$currency->symbol
					);
				?>
			</span>

			<span
				class="wallets-clipboard-copy"
				style="float:right;font-size:large;"
				onClick="jQuery(this).next()[0].select();document.execCommand('copy');"
				title="<?php esc_attr__( 'Copy to clipboard', 'wallets' ); ?>">&#x1F4CB;</span>

			<input
				style="clear:right;width:100%;"
				type="text"
				readonly="readonly"
				value="blocknotify=curl -s '<?php esc_attr_e( $blocknotify_url ); ?>' >/dev/null" />
		</label>

		<?php endforeach; ?>

		<h3><?php esc_html_e( 'Scraping wallet for transactions', 'wallets' ); ?></h3>

		<p><?php esc_html_e( 'Normally the plugin is notified about transactions using the above curl commands.', 'wallets' ); ?></p>

		<p><?php esc_html_e( 'As a backup, the wallet adapter also scrapes the wallet for transactions in case anything is missed. This failsafe mechanism requires cron jobs to be running.', 'wallets' ); ?></p>

		<?php
			try {
				$transient_name = "dsw_bitcoin_{$this->wallet->post_id}_height";
				$block_height   = absint( get_ds_transient( $transient_name, 0 ) );
				$current_block_height = $this->get_block_height();
		?>

		<p><?php printf( __( 'The wallet is synced up to a block height of <code>%1$d</code>. The wallet adapter is currently scraping the wallet for transactions with block height of <code>%2$d</code> or more.', 'wallets' ), $current_block_height, $block_height  ); ?></p>

		<p><?php esc_html_e( 'If some transactions were missed, you may restart scraping from a specific block height. If you set the height too far back, scraping will take a long time.', 'wallets' ); ?></p>

		<?php
			$action_url =
				add_query_arg(
					[
						'action'    => 'wallets_scrape_restart',
						'wallet_id' => $this->wallet->post_id,
					],
					admin_url()
				);
		?>


		<div>

			<script>
				function wallets_scrape_restart() {
					let wallet_id = <?php echo absint( $this->wallet->post_id ); ?>;
					let wallet_height = jQuery('#wallets_rescrape_height').val();

					jQuery.ajax({
						method: 'post',
						data: {
							'action': 'wallets_scrape_restart',
							'wallet_id': wallet_id,
							'wallets_height': wallet_height,
						},
						complete: function() {
							location.reload();
						}
					});
				}
			</script>

			<label>

				<p>
					<?php esc_html_e( 'Re-scrape from height:', 'wallets' ); ?>

					<input
						style="width: 100%"
						type="number"
						min="1"
						max="<?php echo absint( $this->get_block_height() ); ?>"
						id="wallets_rescrape_height"
						value="<?php echo absint( $this->get_block_height() - $this->scrape_behind ); ?>" />

				</p>
			</label>

			<button
				class="button"
				type="button"
				onclick="wallets_scrape_restart();"
				style="width: 100%">
				<?php esc_html_e( 'Re-scrape', 'wallets' ); ?>
			</button>

		</div>
		<?php
			} catch ( \Exception $e ) {
				// don't show re-scrape form if wallet is not connected
			};
		?>


		<p>
			<?php
				printf(
					__( 'For more info, check the %s section of the documentation under the heading "I am unable to setup the transaction notification mechanism on a Bitcoin-like full node wallet.".', 'wallets' ),
					sprintf(
						'<a href="%s" target="_blank">%s</a>',
						add_query_arg(
							[
								'page'              => 'wallets_docs',
								'wallets-component' => 'wallets',
								'wallets-doc'       => 'troubleshooting'
							],
							admin_url( 'admin.php' )
						),
						__( 'Troubleshooting', 'wallets' )
					)
				);
			?>
		</p>
		<?php
	}

	public function get_wallet_version(): string {
		$result = $this->rpc( 'getwalletinfo' );
		return $result['walletversion'] ?? __( 'n/a', 'wallets' );
	}

	public function get_block_height( ?Currency $currency = null ): int {

		foreach ( ['getblockchaininfo', 'getinfo'] as $method ) {
			try {
				$result = $this->rpc( $method );
			} catch ( \Exception $e ) {
				continue;
			}

			foreach ( ['blocks', 'headers'] as $field ) {
				if ( isset( $result[ $field ] ) ) {
					return absint( $result[ $field ] );
				}
			}
		}

		throw new \Exception(
			sprintf(
				'%s: Could not retrieve block height count from wallet.',
				__CLASS__
			)
		);
	}

	private function lock(): void {
		try {
			$this->rpc( 'walletlock' );

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'%s: Could not lock wallet due to: %s',
					__METHOD__,
					$e->getMessage()
				)
			);
		}
	}

	private function unlock(): void {
		if ( $this->is_locked() ) {
			throw new \Exception(
				sprintf(
					'%s: Could not unlock wallet with the supplied passphrase',
					__METHOD__
				)
			);
		}
	}

	public function is_locked(): bool {
		$locked = true;

		// if we have a passphrase, try using it to unlock the wallet for one minute
		if ( $this->rpc_passphrase ) {
			try {
				$result = $this->rpc( 'walletpassphrase', $this->rpc_passphrase, 1 * MINUTE_IN_SECONDS );
			} catch ( \Exception $e ) {
				error_log(
					sprintf(
						'%s: Failed to unlock wallet using stored passphrase: %s',
						__CLASS__,
						$e->getMessage()
					)
				);
			}
		}

		// see if wallet is currently unlocked or if it was never locked
		foreach ( ['getwalletinfo', 'getinfo'] as $method ) {
			try {
				$result = $this->rpc( $method );

				if ( ! isset( $result['unlocked_until'] ) || $result['unlocked_until'] > 0 ) {
					$locked = false;
					break;
				}
			} catch ( \Exception $e ) {
				continue;
			}
		}

		return $locked;
	}

	public function do_withdrawals( array $withdrawals ): void {
		// checks if all transactions are pending withdrawals and of the same currency
		parent::do_withdrawals( $withdrawals );

		$result = false;

		if ( ! $withdrawals ) {
			return;

		} elseif ( 1 == count( $withdrawals ) ) {
			$this->unlock();

			// perform a single withdrawal
			$wd = array_shift( $withdrawals );

			$decimals = $wd->currency->decimals;

			try {
				try {
					$result = $this->rpc(
						'sendtoaddress',
						$wd->address->address,
						(float) -$wd->amount * 10 ** -$decimals,
						$wd->comment
					);
				} catch ( \Exception $e ) {
					if ( 500 == $e->getCode() ) {
						// Some wallets don't like string amounts and respond with "500: value is type str, expected real"
						//
						// Some wallets can work with float amounts, but floats can have too many digits,
						// due to rounding errors. Bitcoin core responds with "500: Invalid amount" in this case.
						//
						// If withdrawing a float amount fails, try again with a string amount.

						$result = $this->rpc(
							'sendtoaddress',
							$wd->address->address,
							number_format( -$wd->amount * 10 ** -$decimals, $decimals, '.', ''),
							$wd->comment
						);
					} else {
						throw $e; // something else went wrong, let the code higher up handle it
					}
				}
			} catch ( \Exception $e ) {
				// here we catch:
				// - any errors besides error 500 from the first attempt
				// - any errors from the second attempt, if it was executed

				$wd->status = 'failed';
				$wd->block  = 0;
				$wd->txid   = '';
				$wd->error  = sprintf(
					'%s: Withdrawal failed with HTTP status %d, message: %s',
					__METHOD__,
					$e->getCode(),
					$e->getMessage()
				);
			}

			$this->lock();

			if ( is_string( $result ) ) {
				$wd->status = 'done';
				$wd->block  = $this->get_block_height();
				$wd->txid   = $result;
				$wd->error  = '';
			}

		} else {
			$this->unlock();

			// batch withdrawal
			$amounts_float  = [];
			$amounts_string = [];
			$post_ids       = [];

			foreach ( $withdrawals as $wd ) {

				$decimals   = absint( $wd->currency->decimals );
				$post_ids[] = $wd->post_id;

				if ( ! isset( $amounts_float[ $wd->address->address ] ) ) {
					$amounts_float[ $wd->address->address ] = 0;
				}
				$amounts_float[ $wd->address->address ]  += (float) -$wd->amount * 10 ** -$decimals;

				if ( ! isset( $amounts_string[ $wd->address->address ] ) ) {
					$amounts_string[ $wd->address->address ] = 0;
				}
				$amounts_string[ $wd->address->address ] += number_format( -$wd->amount * 10 ** -$decimals, $decimals, '.', '' );
			}
			$comment = 'Batch withdrawal for transactions: ' . implode( ',', $post_ids );

			try {
				try {
					$result = $this->rpc(
						'sendmany',
						'', // dummy
						$amounts_float, // amounts assoc array with float amounts
						0, // minconf, ignored
						$comment // comment
					);

				} catch ( \Exception $e ) {
					if ( 500 == $e->getCode() ) {
						// Some wallets don't like string amounts and respond with "500: value is type str, expected real"
						//
						// Some wallets can work with float amounts, but floats can have too many digits,
						// due to rounding errors. Bitcoin core responds with "500: Invalid amount" in this case.
						//
						// If withdrawing a float amount fails, try again with a string amount.

						$result = $this->rpc(
							'sendmany',
							'', // dummy
							$amounts_string, // amounts assoc array with float amounts
							0, // minconf, ignored
							$comment // comment
						);
					} else {
						throw $e; // something else went wrong, let the code higher up handle it
					}
				}
			} catch ( \Exception $e ) {
				// here we catch:
				// - any errors besides error 500 from the first attempt
				// - any errors from the second attempt, if it was executed

				foreach ( $withdrawals as $wd ) {
					$wd->status = 'failed';
					$wd->block  = 0;
					$wd->txid   = '';
					$wd->error  = sprintf(
						'%s: Withdrawal failed with HTTP status %d, message: %s',
						__METHOD__,
						$e->getCode(),
						$e->getMessage()
					);
				}
			}

			$this->lock();

			if ( is_string( $result ) ) {
				foreach ( $withdrawals as $wd ) {
					$wd->status = 'done';
					try {
						$wd->block  = $this->get_block_height();
					} catch ( \Exception $e ) {
						error_log(
							sprintf(
								'%s: Unknown block height for withdrawal %d: %s',
								__METHOD__,
								$wd->post_id,
								$e->getMessage()
							)
						);
					}
					$wd->txid   = $result;
					$wd->error  = '';
				}
			}
		}
	}

	public function get_new_address( ?Currency $currency = null ): Address {
		try {
			$result = $this->rpc( 'getnewaddress' );

			if ( is_string( $result ) && $result ) {
				$a = new Address();
				$a->address = $result;
				$a->currency = $currency;

				return $a;
			}

			throw new \RuntimeException(
				sprintf(
					'%s: getnewaddress did not return a string!',
					__CLASS__
				)
			);
		} catch ( \Exception $e ) {
			throw new \RuntimeException(
				sprintf(
					'%s: Could not create new address due to: %s',
					__METHOD__,
					$e->getMessage()
				)
			);
		}
	}

	/**
	 *
	 * {@inheritDoc}
	 * @see \DSWallets\Wallet_Adapter::get_hot_balance()
	 */
	public function get_hot_balance( ?Currency $currency = null ): int {
		$balance = false;

		$result = $this->rpc( 'getbalance' );

		if ( ! is_numeric( $result ) ) {
			throw new \RuntimeException(
				sprintf(
					'%s: Could not get hot wallet balance!',
					__CLASS__
				)
			);
		}

		return (int) round( $result * 10 ** $currency->decimals );
	}

	public function get_hot_locked_balance( ?Currency $currency = null ): int {
		$result = [];

		foreach ( ['getinfo', 'getwalletinfo'] as $method ) {
			try {
				$result = array_merge( $result, $this->rpc( $method ) );
			} catch( \Exception $e ) {
				continue;
			}
		}

		if ( empty( $result ) ) {
			throw new \RuntimeException(
				sprintf(
					'%s: Could not get hot locked wallet balance!',
					__CLASS__
				)
			);
		}

		$locked_balance = 0;

		foreach ( array( 'newmint', 'stake', 'unconfirmed_balance', 'unconfirmedbalance', 'immature_balance' ) as $field ) {
			if ( isset( $result[ $field ] ) ) {
				$locked_balance += $result[ $field ];
			}
		}

		return $locked_balance * 10 ** $currency->decimals;
	}

	// end of adapter API //

	protected function get_url( bool $auth = false ): string {
		$scheme = $this->rpc_ssl ? 'https://' : 'http://';
		if ( $auth ) {
			$url = "$scheme{$this->rpc_user}:{$this->rpc_password}@{$this->rpc_ip}:{$this->rpc_port}/{$this->rpc_path}";
		} else {
			$url = "$scheme{$this->rpc_ip}:{$this->rpc_port}/{$this->rpc_path}";
		}
		return $url;
	}

	/**
	 * Perform an RPC call.
	 *
	 * @param string $method The bitcoin cli command.
	 * @param mixed ...$params The arguments to the command, as defined in bitcoin help.
	 * @throws \RuntimeException If the call fails for any reason (connectivity, authentication, access control, etc).
	 * @return mixed The returned data.
	 */
	protected function rpc( string $method, ...$params ) {
		$payload = json_encode( [
			'method' => $method,
			'params' => $params,
			'id'     => $this->sequence_id++,
		] );

		$error = false;

		if ( extension_loaded( 'curl' ) ) {

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $this->get_url() );
			curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
			curl_setopt( $ch, CURLOPT_USERPWD, "{$this->rpc_user}:{$this->rpc_password}" );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $ch, CURLOPT_TIMEOUT, absint( get_ds_option( 'wallets_http_timeout', DEFAULT_HTTP_TIMEOUT ) ) );
			curl_setopt( $ch, CURLOPT_MAXREDIRS, absint( get_ds_option( 'wallets_http_redirects', 5 ) ) );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-type: application/json' ] );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );

			$response = curl_exec( $ch );

			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

			if ( false === $response ) {
				$errno = curl_errno( $ch );
				$error = curl_strerror( $errno );
			}

			curl_close( $ch );

		} else {
			// me: mom, can we have curl library for http?
			// mom: we have http library at home
			// the http library at home:

			$result = wp_remote_post(
				$this->get_url( true ),
				[
					'timeout'     => absint( get_ds_option( 'wallets_http_timeout', 5 ) ),
					'user-agent'  => 'Bitcoin and Altcoin Wallets version 6.3.2',
					'headers'     => [
						'Accept-Encoding: gzip',
						'Content-type: application/json',
						'Authorization: ' . base64_encode( "{$this->rpc_user}:{$this->rpc_password}" ),
					],
					'redirection' => absint( get_ds_option( 'wallets_http_redirects', 5 ) ),
					'body'        => $payload,
				]
			);

			$response  = wp_remote_retrieve_body( $result );
			$http_code = wp_remote_retrieve_response_code( $result );
			$error     = $result instanceof \WP_Error ? $result->get_error_message() : '';
			if ( is_array( $error ) ) {
				$error = implode( ',', $error );
			}
		}

		if ( ( ! $error ) && $http_code ) {
			switch ( $http_code ) {
				case 400:
					$error = 'HTTP_BAD_REQUEST';
					break;
				case 401:
					$error = 'HTTP_UNAUTHORIZED';
					break;
				case 403:
					$error = 'HTTP_FORBIDDEN';
					break;
				case 404:
					$error = 'HTTP_NOT_FOUND';
					break;
				case 200:
					break;
				default:
					$error = "Returned HTTP status code $http_code";
			}
		}

		$json = json_decode( $response, true );

		if ( $json && isset( $json['error'] ) ) {
			$error .= ': ' . $json['error']['message'];
		}

		if ( $error ) {
			throw new \RuntimeException(
				sprintf(
					'%s: JSON-RPC command %s failed with: %s',
					get_called_class(),
					$method,
					$error
				),
				absint( $http_code )
			);
		}

		if ( false === $json ) {
			throw new \RuntimeException(
				sprintf(
					'%s: JSON-RPC command %s did not return valid JSON format: %s',
					get_called_class(),
					$method,
					$response
				),
				absint( $http_code )
			);

		}

		if ( empty( $json['error'] ) ) {
			return $json['result'] ?? null;
		} else {
			throw new \RuntimeException(
				sprintf(
					'%s: Wallet responded to JSON-RPC command %s with error: %s',
					get_called_class(),
					$method,
					$json['error']
				),
				$http_code
			);
		}
	}

	/**
	 * Processes an incoming blockchain transaction, and if it contains potential user deposits,
	 * it notifies the plugin so it can update the ledger (i.e. posts of wallets_tx post type).
	 *
	 * @param string $txid The blockchain transaction ID.
	 */
	public function walletnotify( string $txid, Currency $currency ): void {
		if ( ! $txid ) {
			return;
		}

		$count = 0;

		try {
			$txdata = $this->rpc(
				'gettransaction',
				$txid
			);

			if ( isset( $txdata['blockhash'] ) ) {

				$blockdata = $this->rpc(
					'getblock',
					$txdata['blockhash']
				);
			}

		} catch ( \Exception $e ) {
			// HTTP status 500: "Invalid or non-wallet transaction id"
			// Here we silence this one specific error because it's way too common and spams the logs
			if ( false === strpos( $e->getMessage(), 'Invalid or non-wallet' ) ) {
				error_log(
					sprintf(
						'%s: Could not obtain transaction data from wallet for TXID %s due to: %s',
						__METHOD__,
						$txid,
						$e->getMessage()
					)
				);
			}
			return;
		}

		if ( ! ( $txdata && $currency ) ) {
			error_log(
				sprintf(
					'%s: Cannot process transaction with TXID %s: Critical data missing',
					__METHOD__,
					$txid
				)
			);
			return;
		}

		foreach ( $txdata['details'] as $detail ) {

			if ( 'receive' == ( $detail['category'] ?? false ) ) {

				$address = get_deposit_address_by_strings( $detail['address'] );

				if ( ! $address ) {
					// In this wallet adapter, we can discard this deposit early,
					// since we don't know this address.
					// Even if we didn't, the plugin would check again later.
					// This ensures that other adapters don't insert invalid deposits.
					continue;
				}

				$tx = new Transaction();

				$tx->category  = 'deposit';
				$tx->txid      = $txdata['txid'];
				$tx->address   = $address;
				$tx->currency  = $currency;
				$tx->amount    = (int) ( $detail['amount'] * 10 ** $currency->decimals );
				$tx->fee       = - absint( $currency->fee_deposit_site );
				$tx->comment   = $detail['label'] ?? '';
				$tx->status    = 'pending';

				if ( isset( $blockdata ) && isset( $blockdata['height'] ) ) {
					$tx->block     = $blockdata['height'] ?? 0;

					$confirmations = $this->get_block_height() - $tx->block;
					if ( $confirmations >= $this->min_confirm ) {
						$tx->status = 'done';
					}
				}

				$tx->timestamp = $txdata['time'] ?? $txdata['timereceived'] ?? $txdata['blocktime'] ?? time();


				try {
					$post_id = $this->do_deposit( $tx );

					error_log(
						sprintf(
							'%s: Created/updated deposit with post_id %d and TXID %s',
							__METHOD__,
							$post_id ?? 0,
							$tx->txid
						)
					);

				} catch ( \Exception $e ) {
					error_log(
						sprintf(
							'%s: Failed to process potential deposit: %s',
							__METHOD__,
							$e->getMessage()
						)
					);
				}
			}
		}
	}

	/**
	 * Processes an incoming blockchain block, iterating through all of its blockchain transactions.
	 * If a transaction contains potential user deposits, this method notifies the plugin,
	 * so it can update the ledger (i.e. posts of wallets_tx post type).
	 *
	 * @param string $block_hash The blockchain hash for the block.
	 * @param Currency $currency The currency.
	 */
	public function blocknotify( string $block_hash, Currency $currency ): void {
		try {
			$block_data = $this->rpc( 'getblock', $block_hash );
		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'%s: Failed to get %s block with hash %s due to: %s',
					__METHOD__,
					$currency->name,
					$block_hash,
					$e->getMessage()
				)
			);
			return;
		}

		if ( ! isset( $block_data['tx'] ) ) {
			error_log(
				sprintf(
					'%s: Block with hash %s and height %d did not return an array of TXIDs.',
					__METHOD__,
					$block_hash,
					$block_data['height'] ?? 0
				)
			);
		}

		foreach ( $block_data['tx'] as $txid ) {
			if ( $txid && is_string( $txid ) ) {
				$this->walletnotify( $txid, $currency );
			}
		}
	}

	public function do_cron( ?callable $log = null ): void {
		if ( ! $log ) {
			$log = 'error_log';
		}

		$this->update_tx_states( $log );
		$this->scrape_blocks_incrementally( $log );
	}

	/**
	 * Updates transaction states based on blockchain height and required confirmations.
	 *
	 *
	 */
	private function update_tx_states( $log ): void {
		try {
			$cs = get_currencies_for_wallet( $this->wallet );
			foreach ( $cs as $currency ) {
				break;
			}

			if ( ! isset( $currency ) ) {
				call_user_func(
					$log,
					sprintf(
						'%s: No currency assigned to wallet %s (ID: %d)',
						__METHOD__,
						$this->wallet->name,
						$this->wallet->post_id
					)
				);
				return;
			}

			$max_batch_size = absint(
				get_ds_option(
					'wallets_withdrawals_max_batch_size',
					DEFAULT_CRON_WITHDRAWALS_MAX_BATCH_SIZE
				)
			);

			foreach ( get_pending_transactions_by_currency_and_category(
				$currency,
				'deposit',
				$max_batch_size
			) as $tx ) {

				call_user_func(
					$log,
					sprintf(
						'%s: Re-checking wallet status for transaction %s (TXID: %s)',
						__METHOD__,
						$tx->post_id,
						$tx->txid
					)
				);

				try {
					$this->walletnotify( $tx->txid, $currency );
				} catch ( \Exception $e ) {
					continue;
				}
			}

		} catch ( \Exception $e ) {
			call_user_func(
				$log,
				sprintf(
					'%s: Cannot update txs due to: %s',
					__METHOD__,
					$e->getMessage()
				)
			);
			return;
		}
	}

	/**
	 * Scrape blocks incrementally.
	 *
	 * Starts from the current block height
	 */
	private function scrape_blocks_incrementally( $log ): void {
		// This type of wallet adapter can only have one currency.
		$currencies = get_currencies_for_wallet( $this->wallet );

		foreach ( $currencies as $currency ) break;

		if ( ! isset( $currency ) ) {
			call_user_func(
				$log,
				sprintf(
					'%s: Cannot determine currency for wallet "%s" (ID: %d). Will not scrape blocks/transactions on cron.',
					__METHOD__,
					$this->wallet->name,
					$this->wallet->post_id
				)
			);
			return;
		}


		$transient_name = "dsw_bitcoin_{$this->wallet->post_id}_height";
		$block_height   = absint( get_ds_transient( $transient_name, 0 ) );

		if ( ! $block_height ) {

			$block_height = $this->get_block_height() - $this->scrape_behind; // scan the last 16 blocks

			set_ds_transient(
				$transient_name,
				$block_height,
				HOUR_IN_SECONDS  // once every hour
			);
		} else {

			$block_height++;

		}

		call_user_func(
			$log,
			sprintf(
				'%s: Querying next block %d for block hash',
				__METHOD__,
				$block_height
			)
		);

		try {
			$block_hash = $this->rpc( 'getblockhash', $block_height );

		} catch ( \Exception $e ) {

			call_user_func(
				$log,
				sprintf(
					'%s: Could not get block hash for height %d due to: %s',
					__METHOD__,
					$block_height,
					$e->getMessage()
				)
			);
			return;
		}

		if ( ! ( is_string( $block_hash ) && $block_hash ) ) {
			call_user_func(
				$log,
				sprintf(
					'%s: Could not determine block hash for block height %d',
					__METHOD__,
					$block_height
				)
			);
			return;
		}

		call_user_func(
			$log,
			sprintf(
				'%s: Querying block with hash %s (height: %d)',
				__METHOD__,
				$block_hash,
				$block_height
			)
		);

		$this->blocknotify( $block_hash, $currency );

		set_ds_transient( $transient_name, $block_height );

	}

	public static function register() {
		// this allows us to receive TXIDs from the wallet notify mechanism
		// via the WP REST API.
		add_action( 'rest_api_init', function() {
			register_rest_route(
				'dswallets/v1',
				'/walletnotify/(?P<currency_id>\d+)/(?P<txid>\w+)',
				[
					'methods'  => \WP_REST_SERVER::READABLE,
					'callback' => function( $data ) {

						if ( \DSWallets\Migration_Task::is_running() ) {

							/** This filter is documented in apis/wp-rest.php */
							$wallets_migration_api_message = apply_filters(
								'wallets_migration_api_message',
								'The server is currently performing data migration. Please come back later!'
							);

							return new \WP_Error(
								'migration_in_progress',
								$wallets_migration_api_message,
								[
									'status' => 503,
								]
							);
						}

						try {
							$currency = Currency::load( $data['currency_id'] );

						} catch ( \Exception $e ) {
							return new \WP_Error(
								'currency_not_found',
								'Currency not found!',
								[
									'status' => 404,
								]
							);
						}

						if ( ! $currency->wallet ) {
							return new \WP_Error(
								'wallet_for_currency_not_found',
								'The wallet for the specified currency was not found!',
								[
									'status' => 404,
								]
							);
						}

						if ( ! $currency->wallet->is_enabled ) {
							return new \WP_Error(
								'wallet_not_enabled',
								'The wallet for the specified currency is disabled by an admin!',
								[
									'status' => 503,
								]
							);
						}

						if ( ! ( $currency->wallet->adapter && $currency->wallet->adapter instanceof self ) ) {
							return new \WP_Error(
								'invalid_wallet_type',
								'The wallet for the specified currency is not compatible with Bitcoin core!',
								[
									'status' => 405,
								]
							);
						}

						error_log(
							sprintf(
								'%s: The adapter for wallet %d was notified about TXID %s for currency %s (%s)',
								__CLASS__,
								$currency->wallet->post_id,
								$data['txid'],
								$currency->name,
								$currency->symbol
							)
						);

						$currency->wallet->adapter->walletnotify( $data['txid'], $currency );

						return [
							'message' => 'The plugin is being notified about this TXID. ' .
							             'If a user is associated with one of its output addresses, ' .
							             'a deposit will be registered for this user.',
							'status'  => 200,

						];
					},
					'args' => [
						'currency_id' => [
							'sanitize_callback' => 'absint',
						],
						'txid' => [
							'validate_callback' => function( $param, $request, $key ) {
								return ctype_xdigit( $param );
							},
						],
					],
					'permission_callback' => '__return_true',
				]
			);

			register_rest_route(
				'dswallets/v1',
				'/blocknotify/(?P<currency_id>\d+)/(?P<blockhash>\w+)',
				[
					'methods'  => \WP_REST_SERVER::READABLE,
					'callback' => function( $data ) {
						if ( \DSWallets\Migration_Task::is_running() ) {

							/** This filter is documented in apis/wp-rest.php */
							$wallets_migration_api_message = apply_filters(
								'wallets_migration_api_message',
								'The server is currently performing data migration. Please come back later!'
							);

							return new \WP_Error(
								'migration_in_progress',
								$wallets_migration_api_message,
								[
									'status' => 503,
								]
							);
						}

						try {
							$currency = Currency::load( $data['currency_id'] );

						} catch ( \Exception $e ) {
							return new \WP_Error(
								'currency_not_found',
								'Currency not found!',
								[
									'status' => 404,
								]
							);
						}

						if ( ! $currency->wallet ) {
							return new \WP_Error(
								'wallet_for_currency_not_found',
								'The wallet for the specified currency was not found!',
								[
									'status' => 404,
								]
							);
						}

						if ( ! $currency->wallet->is_enabled ) {
							return new \WP_Error(
								'wallet_not_enabled',
								'The wallet for the specified currency is disabled by an admin!',
								[
									'status' => 503,
								]
							);
						}

						if ( ! ( $currency->wallet->adapter && $currency->wallet->adapter instanceof self ) ) {
							return new \WP_Error(
								'invalid_wallet_type',
								'The wallet for the specified currency is not compatible with Bitcoin core!',
								[
									'status' => 405,
								]
							);
						}

						error_log(
							sprintf(
								'The adapter for wallet %d is being notified about block %s for currency %s (%s)',
								$currency->wallet->post_id,
								$data['blockhash'],
								$currency->name,
								$currency->symbol
							)
						);

						$currency->wallet->adapter->blocknotify( $data['blockhash'], $currency );

						return [
							'message' => 'The plugin was notified about this block ' .
								'and will iterate over all the block\'s transactions. For each transaction, ' .
								'if a user is associated with one of its output addresses, ' .
								'a deposit will be registered for this user.',
							'status'  => 200,
						];
					},
					'args' => [
						'currency_id' => [
							'sanitize_callback' => 'absint',
						],
						'blockhash' => [
							'validate_callback' => function( $param, $request, $key ) {
								return ctype_xdigit( $param );
							},
						],
					],
					'permission_callback' => '__return_true',
				]
			);

		} );
	}

}
Bitcoin_Core_Like_Wallet_Adapter::register();
