<?php

/**
 * The bank fiat currency adapter.
 *
 * Allows deposits/withdrawals via manual bank transfers.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

/**
 * The bank fiat currency adapter defines fiat currencies that are deposited/withdrawn via bank transfers.
 *
 * This adapter does not communicate with any wallets. It emails the admins who then process bank transfers manually.
 *
 * @since 6.0.0 Introduced.
 */
class Bank_Fiat_Adapter extends Fiat_Adapter {

	public function __construct( Wallet $wallet ) {
		$this->settings_schema = [];

		$currencies = get_currency_symbols_names_for_wallet( $wallet );

		foreach ( $currencies as $symbol => $name ) {

			$this->settings_schema[] = [
				'id'   => "{$symbol}_banknameaddress",
				'name' => sprintf(
					(string)
					__(
						'(%s) %s: Bank name and address',
						'wallets'
					),
					$symbol,
					$name
				),
				'type' => 'strings',
				'description' => sprintf(
					(string)
					__(
						'Full name and address of bank where you will be receiving %s deposits.',
						'wallets'
					),
					$name
				),
				'default' => '',
			];

			$this->settings_schema[] = [
				'id'   => "{$symbol}_bankaddressingmethod",
				'name' => sprintf(
					(string)
					__(
						'(%s) %s: Addressing method',
						'wallets'
					),
					$symbol,
					$name
				),
				'type' => 'select',
				'description' => sprintf(
					(string)
					__( 'Select which type of bank transfer to use to receive %s deposits.', 'wallets' ),
					$name
				),
				'options' => [
					'iban'    => __( 'SWIFT/BIC and IBAN (World)', 'wallets' ),
					'swacc'   => __( 'SWIFT/BIC and Account Number', 'wallets' ),
					'routing' => __( 'Routing Number and Account Number (USA/Americas)', 'wallets' ),
					'ifsc'    => __( 'IFSC and Account Number (India)', 'wallets' ),
				],
				'default' => 'iban',
			];

			$this->settings_schema[] = [
				'id'   => "{$symbol}_bankbranch",
				'name' => sprintf(
					(string)
					__(
						'(%s) %s: Bank branch',
						'wallets'
					),
					$symbol,
					$name
				),
				'type' => 'string',
				'description' => sprintf(
					(string)
					__(
						'Enter the details that uniquely specify the bank branch where you will receive %s deposits. ' .
						'Depending on your choice of addressing method, this will be: SWIFT/BIC or Routing Number or IFSC.',
						'wallets'
					),
					$name
				),
				'default' => '',
			];

			$this->settings_schema[] = [
				'id'   => "{$symbol}_bankaccount",
				'name' => sprintf(
					(string)
					__(
						'(%s) %s: Bank account',
						'wallets'
					),
					$symbol,
					$name
				),
				'type' => 'string',
				'description' => sprintf(
					(string)
					__(
						'Enter the details that uniquely specify your bank account where you will receive %s deposits. ' .
						'Depending on your choice of addressing method this can be IBAN or Account Number.',
						'wallets'
					),
					$name
				),
				'default' => '',
			];
		}

		parent::__construct( $wallet );
	}

	public function do_description_text(): void {
		?>

		<p><?php esc_html_e( 'This wallet adapter helps you manage manual bank transfers to and from your users.', 'wallets' ); ?></p>

		<ol>
			<li><?php esc_html_e( 'Create a new wallet. You can name it "bank wallet", since it will hold your bank details.', 'wallets' ); ?></li>

			<li><?php esc_html_e( 'Assign the Bank Fiat Adapter to your wallet. If you are seeing this message, you have already done this.', 'wallets' ); ?></li>

			<li><?php esc_html_e( 'Enter a fixer.io API key in the plugin\'s settings.', 'wallets' ); ?></li>

			<li><?php esc_html_e( 'Allow the cron job to create all the fiat currencies. This will take a few minutes.', 'wallets' ); ?></li>

			<li><?php esc_html_e( 'For each fiat currency that you are interested in, assign it to your bank wallet.', 'wallets' ); ?></li>

			<li><?php esc_html_e(
				'Edit again your bank wallet. This time, for each fiat currency that you have assigned to your wallet, ' .
				'you can enter details of the bank account where you can accept this currency.',
				'wallets'
			); ?></li>

			<li><?php esc_html_e( 'You can now use the special shortcodes [wallets_fiat_deposit] and [wallets_fiat_withdraw] in your pages.', 'wallets' ); ?></li>

		</ol>
		<?php
	}

	public function get_wallet_version(): string {
		return '6.3.2';
	}

	public function get_block_height( ?Currency $currency = null ): int {
		throw new \Exception( 'Not applicable' );
	}

	public function is_locked(): bool {
		return true;
	}

	public function get_new_address( ?Currency $currency = null ): Address {
		throw new \Exception( 'Not applicable' );
	}

	public function get_hot_balance( ?Currency $currency = null ): int {
		return 0;
	}

	public function get_hot_locked_balance( ?Currency $currency = null ): int {
		return 0;
	}

	public static function register() {

		// binds to wallets_email_bank_fiat_notify action,
		// and emails the admins about a new pending fiat withdrawal
		add_action(
			'wallets_email_bank_fiat_notify',
			/**
			 * @phan-suppress PhanTypeSuspiciousStringExpression
			 */
			function( Transaction $tx ): void {

				$specialized_part = "fiat_{$tx->category}_{$tx->status}_admins";

				try {
					$template_file_name = get_template_part( 'email', $specialized_part );

				} catch ( \Exception $e ) {

					error_log(
						"Could not find email template file email-{$specialized_part}.php . ".
						"Cannot notify admins about transaction: $tx"
					);
					return;
				}

				global $wpdb;
				$wpdb->query( 'SET autocommit=0' );

				ob_start();

				try {

					include $template_file_name;

				} catch ( \Exception $e ) {

					ob_end_clean();

					wp_mail_enqueue_to_admins(
						sprintf(
							(string)
							__( '%1$s thrown by %2$s', 'wallets' ),
							get_class( $e ),
							$template_file_name
						),
						<<<EMAIL
The email template file email-{$specialized_part}.php
threw exception with message:
{$e->getMessage()}

while notifying admins about transaction:
$tx
EMAIL
					);

					return;

				} finally {
					// We don't want email templates to modify DB state.
					// Admin should hook into wallets_email_notify
					// with an appropriate priority, to modify the transaction.
					$wpdb->query( 'ROLLBACK' );
					$wpdb->query( 'SET autocommit=1' );

				}

				$email_body = ob_get_clean();

				/**
				 * Bank transaction email subject filter.
				 *
				 * Use this filter to modify the subject string for the outgoing email.
				 * The default string is attached with priority 10, so anything higher than that
				 * will override it. The second argument is the transaction we notify about.
				 *
				 * @since 6.0.0 Introduced.
				 * @param string $subject The email subject.
				 * @param Transaction $tx The bank transaction that we are sending notifications about.
				 */
				$email_subject = (string) apply_filters( 'wallets_email_bank_fiat_subject', '', $tx );
				$email_headers = [
					'MIME-Version: 1.0',
					'Content-Type: text/html; charset=UTF-8',
				];

				if ( $email_body ) {

					wp_mail_enqueue_to_admins(
						$email_subject,
						$email_body,
						$email_headers
					);

				} else {
					error_log(
						sprintf(
							"wallets_email_notify: Don't know where to send email for %s %s transaction %d",
							$tx->status,
							$tx->category,
							$tx->post_id
						)
					);
				}

			},
			10, // priority
			2  // argument count
		);

		// binds to wallets_email_bank_fiat_notify action,
		// and emails the user about their new pending fiat withdrawal
		add_action(
			'wallets_email_bank_fiat_notify',
			function( Transaction $tx ): void {

				$specialized_part = "fiat_{$tx->category}_{$tx->status}_sender";

				try {
					$template_file_name = get_template_part( 'email', $specialized_part );

				} catch ( \Exception $e ) {
					error_log(
						"Could not find email template file email-{$specialized_part}.php . " .
						"Cannot notify the user about their request to withdraw to bank account: $tx"
					);
					return;
				}

				global $wpdb;
				$wpdb->query( 'SET autocommit=0' );

				ob_start();

				try {

					include $template_file_name;

				} catch ( \Exception $e ) {

					ob_end_clean();

					wp_mail_enqueue_to_admins(
						(string)
						sprintf(
							(string)
							__( '%1$s thrown by %2$s', 'wallets' ),
							get_class( $e ),
							$template_file_name
						),
						<<<EMAIL
The email template file email-{$specialized_part}.php
threw exception with message:
{$e->getMessage()}

while notifying the user about their request to withdraw to bank account:
$tx
EMAIL
					);
					return;

				} finally {
					// We don't want email templates to modify DB state.
					// Admin should hook into wallets_email_notify
					// with an appropriate priority, to modify the transaction.
					$wpdb->query( 'ROLLBACK' );
					$wpdb->query( 'SET autocommit=1' );
				}

				$email_body    = ob_get_clean();
				$email_to      = sanitize_email( $tx->user->user_email );

				/** This filter is documented in this file. See above. */
				$email_subject = (string) apply_filters( 'wallets_email_bank_fiat_subject', '', $tx );
				$email_headers = [
					'MIME-Version: 1.0',
					'Content-Type: text/html; charset=UTF-8',
				];

				if ( $email_to && $email_body ) {

					wp_mail_enqueue(
						$email_to,
						$email_subject,
						$email_body,
						$email_headers
					);

				} else {
					error_log(
						sprintf(
							"wallets_email_notify: Don't know where to send email for %s %s transaction %d",
							$tx->status,
							$tx->category,
							$tx->post_id
						)
					);
				}

			},
			10, // priority
			2  // argument count
		);

		add_filter(
			'wallets_email_bank_fiat_subject',
			function( string $subject, Transaction $tx ): string {
				return sprintf(
					// Translators: %1$s: Site name, %2$s: tx category (one of: 'deposit', 'withdawal', 'move'), %3$s: tx status (one of: pending, done, cancelled, failed)
					(string) __( '%1$s: A %2$s bank (fiat) transaction is now %3$s ', 'wallets' ),
					(string) get_bloginfo( 'name' ),
					$tx->category,
					$tx->status
				);
			},
			10, // priority
			2  // argument count
		);

		add_action( 'rest_api_init', function() {
			register_rest_route(
				'dswallets/v1',
				'/users/(?P<user_id>\d+)/currencies/(?P<currency_id>\d+)/banktransfers/withdrawal',
				[
					'methods'  => \WP_REST_SERVER::CREATABLE,
					'permission_callback' => function( \WP_REST_Request $request ) {

						if ( \DSWallets\Migration_Task::is_running() ) {

							/** This filter is documented in apis/wp-rest.php */
							$wallets_migration_api_message = apply_filters(
								'wallets_migration_api_message',
								__( 'The server is currently performing data migration. Please come back later!', 'wallets' )
							);

							return new \WP_Error(
								'migration_in_progress',
								$wallets_migration_api_message,
								[
									'status' => 503,
								]
							);
						}

						if ( ds_current_user_can( 'manage_wallets' ) ) {
							return true;
						}

						$user_id = absint( $request->get_param( 'user_id' ) );

						if ( $user_id != get_current_user_id() ) {
							return new \WP_Error(
								'access_not_allowed',
								(string) __( 'Only admins can access data belonging to other users!', 'wallets' ),
								[
									'status' => 403,
								]
							);
						}

						if ( ! get_userdata( $user_id ) ) {
							return new \WP_Error(
								'user_not_found',
								(string) __( 'Specified user was not found!', 'wallets' ),
								[
									'status' => 404,
								]
							);
						}

						if ( ! ds_current_user_can( 'has_wallets' ) ) {
							return new \WP_Error(
								'user_without_wallet',
								(string) __( 'Specified user not allowed to have wallets!', 'wallets' ),
								[
									'status' => 403,
								]
							);
						}

						return true;
					},
					'callback' => function( $data ) {

						$params      = $data->get_url_params();
						$user_id     = $params['user_id'];
						$currency_id = $params['currency_id'];

						$body_params            = $data->get_body_params();
						$amount                 = $body_params['amount'];
						$recipient_name_address = $body_params['recipientNameAddress'];
						$bank_name_address      = $body_params['bankNameAddress'];
						$addressing_method      = $body_params['addressingMethod'];
						$comment                = $body_params['comment'];

						if ( 'iban' == $addressing_method ) {

							$swift_bic = $body_params['swiftBic'];
							$iban      = $body_params['iban'];
							$label     = "{ \"iban\": \"$iban\", \"swiftBic\": \"$swift_bic\" }";

						} elseif ( 'swacc' == $addressing_method ) {

							$swift_bic      = $body_params['swiftBic'];
							$account_number = $body_params['accountNumber'];
							$label          = "{ \"swiftBic\": \"$swift_bic\", \"accountNumber\": \"$account_number\"}";

						} elseif ( 'routing' == $addressing_method ) {

							$routing_number = $body_params['routingNumber'];
							$account_number = $body_params['accountNumber'];
							$label          = "{ \"routingNumber\": \"$routing_number\", \"accountNumber\": \"$account_number\"}";

						} elseif ( 'ifsc' == $addressing_method ) {

							$ifsc         = $body_params['ifsc'];
							$indianAccNum = $body_params['indianAccNum'];
							$label        = "{ \"ifsc\": \"$ifsc\", \"indianAccNum\": \"$indianAccNum\"}";
						}

						try {
							$currency = Currency::load( $currency_id );

						} catch ( \Exception $e ) {

							return new \WP_Error(
								'currency_not_found',
								(string) __( 'Currency not found!', 'wallets' ),
								[
									'status' => 404,
								]
							);
						}

						$address = get_withdrawal_address_by_strings(
							$recipient_name_address,
							$bank_name_address
						);

						if (
							! $address
							|| $address->label != $label
							|| $address->user->ID != $user_id
						) {
							$address           = new Address();
							$address->address  = $recipient_name_address;
							$address->extra    = $bank_name_address;
							$address->type     = 'withdrawal';
							$address->currency = $currency;
							$address->user     = new \WP_User( $user_id );
							$address->label    = $label;

							try {
								$address->save();

							} catch ( \Exception $e ) {

								return new \WP_Error(
									'address_not_saved',
									sprintf(
										(string) __( 'New fiat withdrawal destination details not saved, due to: %s', 'wallets' ),
										$e->getMessage()
									),
									[
										'status' => 500,
									]
								);
							}
						}

						$wd                = new Transaction();
						$wd->category      = 'withdrawal';
						$wd->user          = $address->user;
						$wd->address       = $address;
						$wd->currency      = $currency;
						$wd->amount        = -$amount * 10 ** $currency->decimals;
						$wd->fee           = -$currency->fee_withdraw_site;
						$wd->comment       = $comment;
						$wd->timestamp     = time();
						$wd->status        = 'pending';

						if ( get_ds_option( 'wallets_confirm_withdraw_user_enabled' ) ) {
							$wd->nonce = create_random_nonce( NONCE_LENGTH );
						} else {
							$wd->nonce = '';
						}

						try {
							$wd->saveButDontNotify();

						} catch ( \Exception $e ) {

							return new \WP_Error(
								'transaction_not_saved',
								sprintf(
									(string) __( 'New fiat withdrawal not saved, due to: %s', 'wallets' ),
									$e->getMessage()
								),
								[
									'status' => 500,
								]
							);

						}

						/**
						 *  Action to notify user and admins about a bank transaction.
						 *
						 *  This is where we hook the code to render email templates
						 *  and enqueue emails.
						 *
						 *  Other notification mechanisms can also be attached here.
						 *
						 *  @since 6.0.0 Introduced.
						 *
						 *  @param Transaction $tx The bank transaction to notify about.
						 */
						do_action( 'wallets_email_bank_fiat_notify', $wd );

						return [
							'status' => 'pending',
						];

					},
					'args' => [
						'currency_id' => [
							'sanitize_callback' => 'absint',
						],
						'user_id' => [
							'sanitize_callback' => 'absint',
							'validate_callback' => function( $param, $request, $key ) {

								if ( ds_current_user_can( 'manage_wallets' ) ) {
									return true;
								}

								$user_id = absint( $param );

								if ( $user_id != get_current_user_id() ) {
									return false;
								}

								if ( ! get_userdata( $user_id ) ) {
									return false;
								}

								return true;
							},
						],
					],
				]
			);
		} );

		add_action( 'tool_box', function() {
			if ( ! ds_current_user_can( 'manage_wallets' ) ) {
				return;
			}

			if ( is_net_active() && ! is_main_site() ) {
				return;
			}

			$deposit_tools_url = (string) admin_url( 'tools.php?page=wallets-bank-fiat-deposits' );
			$withdraw_tools_url = (string) admin_url( 'tools.php?page=wallets-bank-fiat-withdrawals' );

			?>
			<div class="card tool-box">
				<h2><?php esc_html_e( 'Bitcoin and Altcoin Wallets: Bank Fiat Adapter', 'wallets' ); ?></h2>
				<p><?php esc_html_e( 'Manage bank transfers to and from your users\' bank accounts with these two tools.', 'wallets' ); ?></p>

				<ul>
					<li>
					<?php
						printf(
							(string) __(
								'Use the <a href="%s">Fiat Deposits Tool</a> to manually insert fiat currency deposits from a bank account.',
								'wallets'
							),
							$deposit_tools_url
						);
					?>
					</li>

					<li>
					<?php
						printf(
							(string) __(
								'Use the <a href="%s">Fiat Withdrawals Tool</a> to manually approve fiat currency withdrawals to a bank account.',
								'wallets'
							),
							$withdraw_tools_url
						);
					?>
					</li>
				</ul>

			</div>
			<?php
		} );

		add_action(
			'admin_enqueue_scripts',
			function() {

				wp_register_script(
					'wallets-admin-deposit-tool',
					get_asset_path( 'wallets-admin-deposit-tool' ),
					[ 'jquery' ],
					'6.3.2',
					true
				);
			}
		);

		add_action( 'admin_menu', function() {
			if ( is_net_active() && ! is_main_site() ) {
				return;
			}

			if ( ds_current_user_can( 'manage_wallets' ) ) {

				add_management_page(
					'Bitcoin and Altcoin Wallets deposits tool',
					'Fiat deposits',
					'manage_wallets',
					'wallets-bank-fiat-deposits',
					function() {
						$deposit_tools_url = (string) admin_url( 'tools.php?page=wallets-bank-fiat-deposits' );
						?>
						<h1>
						<?php
							esc_html_e(
								'Bitcoin and Altcoin Wallets: Fiat Deposits tool',
								'wallets'
							);
						?>
						</h1>

						<?php
						if (
							isset( $_REQUEST['action'] )
							&& 'create_fiat_deposit_form' == $_REQUEST['action']
						) {

							wp_enqueue_script( 'wallets-admin-deposit-tool' );

							?>
							<form method="POST" action="<?php esc_attr_e( $deposit_tools_url ); ?>">
								<input
									type="hidden"
									name="action"
									value="create_fiat_deposit_post" />

								<input
									type="hidden"
									name="_wpnonce"
									value="<?php echo wp_create_nonce( 'wallets-create-fiat-deposit' ); ?>" />

								<table>
									<tbody>
										<tr>
											<td>
												<label
													for="wallets_user_id">
													<?php esc_html_e( 'User ID (must be supplied in bank transfer comment)', 'wallets' ); ?>
												</label>
											</td>

											<td>
												<input
													type="number"
													min="1"
													required="required"
													id="wallets_user_id"
													name="wallets_user_id">
											</td>
										</tr>

										<tr>
											<td>
												<label
													for="wallets_txid">
													<?php esc_html_e( 'Bank TXID (as supplied by the bank)', 'wallets' ); ?>
												</label>
											</td>

											<td>
												<input
													type="text"
													id="wallets_txid"
													name="wallets_txid">
											</td>
										</tr>

										<tr>
											<td>
												<label
													for="wallets_currency_id">
													<?php esc_html_e( 'Fiat Currency', 'wallets' ); ?>
												</label>
											</td>

											<td>
												<select
													id="wallets_currency_id"
													name="wallets_currency_id"
													required="required">

													<option
														value=""
														disabled="disabled"
														selected="selected"><?php
															esc_html_e( 'Select a fiat currency to deposit', 'wallets' );
														?></option>
													<?php
													foreach ( get_all_fiat_currencies() as $currency ):
														if ( $currency->wallet && $currency->wallet->adapter && $currency->wallet->adapter instanceof Bank_Fiat_Adapter ):
														?>
														<option value="<?php esc_attr_e( $currency->post_id); ?>"><?php esc_html_e( $currency->name ); ?></option>
														<?php
														endif;
													endforeach;
													?>
												</select>
											</td>
										</tr>

										<tr>
											<td>
												<label
													for="wallets_amount">
													<?php esc_html_e( 'Amount', 'wallets' ); ?>
												</label>
											</td>

											<td>
												<input
													id="wallets_amount"
													name="wallets_amount"
													required="required"
													type="number"
													step="0.0001">
											</td>
										</tr>

										<tr>
											<td>
												<label
													for="wallets_banksendernameaddress">
													<?php esc_html_e( 'Sender name and address', 'wallets' ); ?>
												</label>
											</td>

											<td>
												<textarea
													id="wallets_banksendernameaddress"
													name="wallets_banksendernameaddress"></textarea>
											</td>

										</tr>

										<tr>
											<td>
												<label
													for="wallets_banknameaddress">
													<?php esc_html_e( 'Bank name and address', 'wallets' ); ?>
												</label>
											</td>

											<td>
												<textarea
													id="wallets_banknameaddress"
													name="wallets_banknameaddress"></textarea>
											</td>

										</tr>


										<tr>
											<td>
												<label
													for="wallets_bankaddressingmethod">
													<?php esc_html_e( 'Bank addressing method', 'wallets' ); ?>
												</label>
											</td>

											<td>
												<p>
													<input
														type="radio"
														name="wallets_bankaddressingmethod"
														required="required"
														value="iban">

													<?php
													_e(
														'SWIFT-<abbr title="Business Identifier Code">BIC</abbr> and <abbr title="International Bank Account Number">IBAN</abbr>',
														'wallets'
													);
													?>
												</p>

												<p>
													<input
														type="radio"
														name="wallets_bankaddressingmethod"
														required="required"
														value="swacc">

													<?php
													_e(
														'SWIFT-<abbr title="Business Identifier Code">BIC</abbr> and Account Number',
														'wallets'
													);
													?>
												</p>

												<p>
													<input
														type="radio"
														name="wallets_bankaddressingmethod"
														required="required"
														value="routing">

													<?php
													_e(
														'<abbr title="American Bankers\' Association">ABA</abbr> Routing number and Account number',
														'wallets'
													);
													?>
												</p>

												<p>
													<input
														type="radio"
														name="wallets_bankaddressingmethod"
														required="required"
														value="ifsc">

													<?php
													_e(
														'<abbr title="Indian Financial System Code">IFSC</abbr> and Account number',
														'wallets'
													);
													?>
												</p>
											</td>
										</tr>


										<tr>
											<td>
												<label
													for="wallets_bankbranch">
													<span
														class="iban">
														<?php _e( 'SWIFT-<abbr title="Business Identifier Code">BIC</abbr>', 'wallets' ); ?>
													</span>
													<span
														class="swacc">
														<?php _e( 'SWIFT-<abbr title="Business Identifier Code">BIC</abbr>', 'wallets' ); ?>
													</span>
													<span
														class="routing">
														<?php _e( '<abbr title="American Bankers\' Association">ABA</abbr> Routing number', 'wallets' ); ?>
													</span>
													<span
														class="ifsc">
														<?php _e( '<abbr title="Indian Financial System Code">IFSC</abbr>', 'wallets' ); ?>
													</span>
												</label>
											</td>

											<td>
												<input
													type="text"
													id="wallets_bankbranch"
													name="wallets_bankbranch">
											</td>
										</tr>

										<tr>
											<td>
												<label
													for="wallets_bankaccount">
													<span
														class="iban">
														<?php _e( 'Account <abbr title="International Bank Account Number">IBAN</abbr>', 'wallets' ); ?>
													</span>
													<span
														class="swacc">
														<?php _e( 'Account Number', 'wallets' ); ?>
													</span>
													<span
														class="routing">
														<?php _e( 'Account Number', 'wallets' ); ?>
													</span>
													<span
														class="ifsc">
														<?php _e( 'Account Number', 'wallets' ); ?>
													</span>
												</label>
											</td>

											<td>
												<input
													type="text"
													id="wallets_bankaccount"
													name="wallets_bankaccount">
											</td>
										</tr>

										<tr>
											<td>
												<label
													for="wallets_comment">
													<?php esc_html_e( 'Comment', 'wallets' ); ?>
												</label>
											</td>

											<td>
												<textarea
													id="wallets_comment"
													name="wallets_comment"></textarea>
											</td>

										</tr>

										<tr>
											<td>
												<label
													for="wallets_status">
													<?php esc_html_e( 'Status', 'wallets' ); ?>
												</label>
											</td>

											<td>
												<select
													id="wallets_status"
													name="wallets_status"
													required="required">

													<option
														value=""
														disabled="disabled"
														selected="selected"><?php
															esc_html_e( '(state)', 'wallets' );
														?></option>

													<?php foreach ( [ 'pending', 'done', 'failed', 'cancelled' ] as $status_slug ): ?>
													<option
														value="<?php echo( $status_slug ); ?>"><?php echo ucfirst( $status_slug); ?></option>
													<?php endforeach; ?>

												</select>
											</td>
										</tr>

										<tr>
											<td colspan="2">
												<input
													type="submit"
													class="button"
													value="<?php esc_attr_e( 'Create', 'wallets' ); ?>" />

												<a
													class="button"
													href="<?php esc_attr_e( $deposit_tools_url ); ?>">
													<?php esc_html_e( 'Go back', 'wallets' ); ?>
												</a>
											</td>
										</tr>

									</tbody>
								</table>
							</form>
							<?php

						} elseif (
							isset( $_REQUEST['action'] )
							&& 'create_fiat_deposit_post' == $_REQUEST['action']
						) {

							if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'wallets-create-fiat-deposit' ) ) {
								wp_die( 'Possible request forgery' );
							}

							$currency_id = absint( $_REQUEST['wallets_currency_id'] );
							$user_id     = absint( $_REQUEST['wallets_user_id'] );
							$user        = new \WP_User( $user_id );
							$txid        = $_REQUEST['wallets_txid'];
							$amount      = floatval( $_REQUEST['wallets_amount'] );

							try {
								$currency = Currency::load( $currency_id );

							} catch ( \Exception $e ) {

								wp_die(
									(string) __( 'Currency not found!', 'wallets' ),
									(string) __( 'Currency not found!', 'wallets' ),
									404
								);
							}

							if ( ! $user->exists() ) {
								wp_die(
									(string) __( 'The specified user was not found. Check the supplied user id!', 'wallets' ),
									(string) __( 'User not found!', 'wallets' ),
									404
								);
							}

							if ( ! is_numeric( $amount ) ) {
								wp_die(
									(string) __( 'The specified deposit amount must be a number', 'wallets' ),
									(string) __( 'Amount not numeric', 'wallets' ),
									400
								);
							}

							if ( $amount <= 0 ) {
								wp_die(
									(string) __( 'The specified deposit amount must be positive', 'wallets' ),
									(string) __( 'Amount not positive', 'wallets' ),
									400
								);
							}

							if ( ! $currency->is_fiat() ) {
								wp_die(
									(string) __( 'The specified currency is not a fiat currency.', 'wallets' ),
									(string) __( 'Currency not fiat', 'wallets' ),
									400
								);
							}

							if ( $txid && fiat_deposit_exists_by_txid_currency( $txid, $currency ) ) {
								wp_die(
									(string) __(
										'The specified bank transaction ID has already been inserted into the DB for the specified currency.',
										'wallets'
									),
									(string) __( 'Bank Transaction ID already exists', 'wallets' ),
									409
								);
							}

							$sender_name_address = $_REQUEST['wallets_banksendernameaddress'] ?? '';
							$bank_name_address   = $_REQUEST['wallets_banknameaddress']       ?? '';
							$addressing_method   = $_REQUEST['wallets_bankaddressingmethod']  ?? '';
							$bank_branch         = $_REQUEST['wallets_bankbranch']            ?? '';
							$bank_account        = $_REQUEST['wallets_bankaccount']           ?? '';
							$comment             = $_REQUEST['wallets_comment']               ?? '';
							$status              = $_REQUEST['wallets_status'];

							switch ( $status ) {
								case 'pending':
								case 'done':
								case 'cancelled':
								case 'failed':
									break;

								default:
									wp_die(
										(string) __(
											'The specified transaction status is invalid!',
											'wallets'
										),
										(string) __(
											'Invalid Transaction Status',
											'wallets'
										),
										400
									);
							}

							if ( 'iban' == $addressing_method ) {
								$label = "{ \"iban\": \"$bank_account\", \"swiftBic\": \"$bank_branch\" }";

							} elseif ( 'swacc' == $addressing_method ) {
								$label = "{ \"swiftBic\": \"$bank_branch\", \"accountNumber\": \"$bank_account\"}";

							} elseif ( 'routing' == $addressing_method ) {
								$label = "{ \"routingNumber\": \"$bank_branch\", \"accountNumber\": \"$bank_account\"}";

							} elseif ( 'ifsc' == $addressing_method ) {
								$label = "{ \"ifsc\": \"$bank_branch\", \"indianAccNum\": \"$bank_account\"}";

							} else {
								wp_die(
									(string) __( 'Addressing method invalid', 'wallets' ),
									(string) __( 'The specified addressing method was not valid', 'wallets' ),
									400
								);
							}

							// create address or load
							$address = get_withdrawal_address_by_strings(
								$sender_name_address,
								$bank_name_address
							);

							if ( ! $address || $address->label != $label ) {
								$address = new Address();
								$address->address  = $sender_name_address;
								$address->extra    = $bank_name_address;
								$address->type     = 'deposit';
								$address->currency = $currency;
								$address->user     = new \WP_User( $user_id );
								$address->label    = $label;
							}

							$deposit = new Transaction();

							$deposit->category = 'deposit';
							$deposit->user     = new \WP_User( $user_id );
							$deposit->txid     = $txid;
							$deposit->address  = $address;
							$deposit->currency = $currency;
							$deposit->amount   = intval( $amount * 10 ** $currency->decimals );
							$deposit->fee      = -$currency->fee_deposit_site;
							$deposit->comment  = $comment;
							$deposit->status   = $status;
							$deposit->nonce    = '';

							// triggers email templates
							$currency->wallet->adapter->do_deposit( $deposit );

							try {
								$address->save();
								$deposit->saveButDontNotify();

							} catch ( \Exception $e ) {

								wp_die(
									(string) __( 'Deposit Not Saved', 'wallets' ),
									(string) sprintf(
										(string) __( 'The deposit was not saved, due to: %s', 'wallets' ),
										$e->getMessage()
									)
								);
							}

							/** This action is documented in this file. See above. */
							do_action( 'wallets_email_bank_fiat_notify', $deposit );

							?>
							<script type="text/javascript">
							window.location = '<?php echo esc_js( $deposit_tools_url ); ?>';
							</script>
							<?php
							exit;

						} else {

							$deposits_list = new class extends \WP_List_Table {

								public function ajax_user_can() {
									return ds_current_user_can( 'manage_wallets' );
								}

								public function get_columns() {
									return array(
										'currency'      => esc_html__( 'Currency', 'wallets' ),
										'amount'        => esc_html__( 'Amount', 'wallets' ),
										'user'          => esc_html__( 'User', 'wallets' ),
										'txid'          => esc_html__( 'TXID', 'wallets' ),
										'status'        => esc_html__( 'Status', 'wallets' ),
										'created_time'  => esc_html__( 'Created', 'wallets' ),
										'action'        => esc_html__( 'Action', 'wallets' ),
									);
								}

								public function get_hidden_columns() {
									return [];
								}

								public function get_sortable_columns() {
									return [];
								}

								public function prepare_items() {
									$per_page     = $this->get_items_per_page( 'edit_post_per_page', 20 );
									$current_page = $this->get_pagenum();

									$this->_column_headers = [
										$this->get_columns(),
										$this->get_hidden_columns(),
										$this->get_sortable_columns(),
									];

									maybe_switch_blog();

									$fiat_ids = get_currency_ids( 'fiat' );

									$query_args = [
										'fields'         => 'ids',
										'post_type'      => 'wallets_tx',
										'post_status'    => [ 'publish', 'pending', 'draft' ],
										'orderby'        => 'ID',
										'order'          => 'DESC',
										'nopaging'       => false,
										'posts_per_page' => $per_page,
										'paged'          => $current_page,
										'meta_query'     => [
											'relation' => 'AND',
											[
												'key'   => 'wallets_category',
												'value' => 'deposit',
											],
											[
												'key'     => 'wallets_amount',
												'compare' => '>',
												'value'   => 0,
												'type'    => 'numeric',
											],
											[
												'key'     => 'wallets_fee',
												'compare' => '>=',
												'value'   => 0,
												'type'    => 'numeric',
											],
											[
												'key'     => 'wallets_address_id',
												'compare' => 'EXISTS',
											],
											[
												'key'     => 'wallets_currency_id',
												'compare' => 'IN',
												'value'   => $fiat_ids,
											],
										],
									];

									$query = new \WP_Query( $query_args );

									$this->items = Transaction::load_many( $query->posts );

									$this->set_pagination_args(
										[
											'total_items' => $query->found_posts,
											'per_page'    => $per_page,
										]
									);

									maybe_restore_blog();
								}

								public function column_currency( Transaction $deposit ) {
									esc_html_e( $deposit->currency->name );
								}

								public function column_amount( Transaction $deposit ) {
									esc_html_e( $deposit->get_amount_as_string( 'amount', true, true ) );
								}

								public function column_user( Transaction $deposit ) {
									if ( $deposit->user ):
										?>
										<a
											href="<?php esc_attr_e( get_edit_user_link( $deposit->user->ID ) ); ?>">
											<?php esc_html_e( $deposit->user->display_name ); ?>
										</a>
										<?php
									else:
										?>
										&mdash;
										<?php
									endif;
								}

								public function column_txid( Transaction $deposit ) {
									if ( $deposit->txid ):
										?>
										<code><?php esc_html_e( $deposit->txid ); ?></code>
										<?php
									else:
										?>
										&mdash;
										<?php
									endif;
								}

								public function column_status( $item ) {
									esc_html_e( $item->status );
								}

								public function column_created_time( Transaction $deposit ) {
									echo get_the_date( '', $deposit->post_id );
								}

								public function column_action( Transaction $deposit ) {
									if ( ! $deposit->nonce ):

										$actions = [];

										$actions['edit_fiat_wd'] = sprintf(
											'<a class="button" href="%s" title="%s">%s</a>',
											get_edit_post_link( $deposit->post_id ),
											(string) __( 'Edit the deposit details and change its status.', 'wallets' ),
											(string) __('&#x270e; Modify', 'wallets' )
										);

										echo $this->row_actions( $actions, true );
									else:
										esc_html_e( 'Not email-validated yet.', 'wallets' );
									endif;
								}
							};

							?>

							<p>
							<?php
								esc_html_e(
									'All the deposits for fiat currencies are listed here.',
									'wallets'
								);
							?>
							</p>

							<?php
								printf(
									'<a class="button" href="%s" title="%s">%s</a>',
									add_query_arg(
										[
											'page'       => 'wallets-bank-fiat-deposits',
											'action'     => 'create_fiat_deposit_form',
										],
										admin_url( 'tools.php' )
									),
									(string) __( 'Create a new fiat currency deposit from a bank transfer.', 'wallets' ),
									(string) __('&#x270e; Create', 'wallets' )
								);
							?>

							<div class="wrap">
							<?php
								$deposits_list->prepare_items();
								$deposits_list->display();
							?>
							</div>

							<?php

						}

					}
				);

				add_management_page(
					'Bitcoin and Altcoin Wallets withdrawals tool',
					'Fiat withdrawals',
					'manage_wallets',
					'wallets-bank-fiat-withdrawals',
					function() {

						if ( ! class_exists( '\WP_List_Table' ) ) {
							require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
						}
						$withdraw_tools_url = (string) admin_url( 'tools.php?page=wallets-bank-fiat-withdrawals' );

						?>
						<h1>
						<?php
							esc_html_e(
								'Bitcoin and Altcoin Wallets: Fiat Withdrawals tool',
								'wallets'
							);
						?>
						</h1>

						<?php

						if (
							isset( $_REQUEST['action'] )
							&& 'edit_fiat_wd_form' == $_REQUEST['action']
							&& isset( $_REQUEST['fiat_wd_id'] )
						) {

							$wd_id = absint( $_REQUEST['fiat_wd_id'] );

							try {
								$wd = Transaction::load( $wd_id );
							} catch ( \Exception $e ) {
								wp_die(
									sprintf(
										(string) __( 'Failed to load withdrawal with post_id %d due to: %s', 'wallets' ),
										$wd_id,
										$e->getMessage()
									),
									(string) __( 'Invalid fiat withdrawal', 'wallets' )
								);
							}

							if (
								'withdrawal' == $wd->category
								&& ! $wd->nonce
								&& $wd->currency->is_fiat()
							):

								$addressing_method = '';
								$payment_details = json_decode( $wd->address->label );
								if ( $payment_details ) {
									if ( isset( $payment_details->iban ) && isset( $payment_details->swiftBic ) ) {
										$addressing_method = 'iban';
									} elseif ( isset( $payment_details->swiftBic) && isset( $payment_details->accountNumber ) ) {
										$addressing_method = 'swacc';
									} elseif ( isset( $payment_details->routingNumber ) && isset( $payment_details->accountNumber ) ) {
										$addressing_method = 'routing';
									} elseif ( isset( $payment_details->ifsc ) && isset( $payment_details->indianAccNum ) ) {
										$addressing_method = 'ifsc';
									}
								}

								?>
								<form method="POST" action="<?php esc_attr_e( $withdraw_tools_url ); ?>">
									<input
										type="hidden"
										name="action"
										value="edit_fiat_wd_post" />

									<input
										type="hidden"
										name="fiat_wd_id"
										value="<?php echo $wd->post_id; ?>" />

									<input
										type="hidden"
										name="_wpnonce"
										value="<?php echo wp_create_nonce( "wallets-edit-fiat-withdrawal-$wd_id" ); ?>" />

									<table>
										<tbody>
											<tr>
												<th>
													<?php esc_html_e( 'Currency', 'wallets' ); ?>
												</th>
												<td>
													<p><?php esc_html_e( $wd->currency->name ); ?></p>
												</td>
											</tr>

											<tr>
												<th>
													<?php esc_html_e( 'Amount', 'wallets' ); ?>
												</th>
												<td>
													<code><?php esc_html_e( $wd->get_amount_as_string( 'amount', true ) ); ?></code>
												</td>
											</tr>

											<tr>
												<th>
													<?php esc_html_e( 'Withdrawal fee', 'wallets' ); ?>
												</th>
												<td>
													<code><?php esc_html_e( $wd->get_amount_as_string( 'fee', true ) ); ?></code>
												</td>
											</tr>

											<tr>
												<th>
													<?php esc_html_e( 'User', 'wallets' ); ?>
												</th>
												<td>
													<a
														href="<?php esc_attr_e( get_edit_user_link( $wd->user->ID ) ); ?>">
														<?php esc_html_e( $wd->user->display_name ); ?>
													</a>
												</td>
											</tr>


											<tr>
												<th>
													<?php esc_html_e( 'User name and address', 'wallets' ); ?>
												</th>
												<td>
													<pre><?php esc_html_e( $wd->address->address ); ?></pre>
												</td>
											</tr>

											<tr>
												<th>
													<?php esc_html_e( 'Bank name and address', 'wallets' ); ?>
												</th>
												<td>
													<pre><?php esc_html_e( $wd->address->extra ); ?></pre>
												</td>
											</tr>

											<tr>
												<th>
													<?php
													switch( $addressing_method ) {
														case 'iban':    esc_html_e( 'SWIIFT/BIC',     'wallets' ); break;
														case 'swacc':   esc_html_e( 'SWIIFT/BIC',     'wallets' ); break;
														case 'routing': esc_html_e( 'Routing number', 'wallets' ); break;
														case 'ifsc':    esc_html_e( 'IFSC',           'wallets' ); break;
														default:
															printf(
																(string) __( 'Unknown addressing method %s', 'wallets' ),
																$addressing_method
															);
													}
													?>
												</th>
												<td>
													<pre><?php
													switch( $addressing_method ) {
														case 'iban':    esc_html_e( $payment_details->swiftBic );      break;
														case 'swacc':   esc_html_e( $payment_details->swiftBic );      break;
														case 'routing': esc_html_e( $payment_details->routingNumber ); break;
														case 'ifsc':    esc_html_e( $payment_details->ifsc );          break;
														default:
															printf(
																(string) __( 'Unknown addressing method %s', 'wallets' ),
																(string) $addressing_method
															);
													}
													?></pre>
												</td>
											</tr>

											<tr>
												<th>
													<?php
													switch( $addressing_method ) {
														case 'iban':    esc_html_e( 'IBAN',           'wallets' ); break;
														case 'swacc':   esc_html_e( 'Account number', 'wallets' ); break;
														case 'routing': esc_html_e( 'Account number', 'wallets' ); break;
														case 'ifsc':    esc_html_e( 'Account number', 'wallets' ); break;
														default:
															printf(
																(string) __( 'Unknown addressing method %s', 'wallets' ),
																(string) $addressing_method
															);
													}
													?>
												</th>
												<td>
													<pre><?php
													switch( $addressing_method ) {
														case 'iban':    esc_html_e( $payment_details->iban ); break;
														case 'swacc':   esc_html_e( $payment_details->accountNumber ); break;
														case 'routing': esc_html_e( $payment_details->accountNumber ); break;
														case 'ifsc':    esc_html_e( $payment_details->indianAccNum ); break;
														default:
															printf(
																(string) __( 'Unknown addressing method %s', 'wallets' ),
																(string) $addressing_method
															);
													}
													?></pre>
												</td>
											</tr>

											<tr>
												<th>
													<label
														for="wallets_bank_fiat_txid">

														<?php esc_html_e( 'Bank Transaction ID', 'wallets' ); ?>
													</label>
												</th>
												<td>
													<input
														type="text"
														id="wallets_bank_fiat_txid"
														name="wallets_txid"
														placeholder="<?php esc_html_e( 'Enter bank TXID', 'wallets' ); ?>"
														value="<?php esc_attr_e( $wd->txid ); ?>" />
												</td>
											</tr>

											<tr>
												<th>
													<label
														for="wallets_bank_fiat_status">

														<?php esc_html_e( 'Status', 'wallets' ); ?>
													</label>
												</th>
												<td>
													<select
														id="wallets_bank_fiat_status"
														name="wallets_status">
														<option value="pending" <?php selected( 'pending' == $wd->status ); ?>><?php esc_html_e( 'Pending', 'wallets' ); ?></option>
														<option value="done" <?php selected( 'done' == $wd->status ); ?>><?php esc_html_e( 'Done', 'wallets' ); ?></option>
														<option value="cancelled" <?php selected( 'cancelled' == $wd->status ); ?>><?php esc_html_e( 'Cancelled', 'wallets' ); ?></option></form>
														<option value="failed" <?php selected( 'failed' == $wd->status ); ?>><?php esc_html_e( 'Failed', 'wallets' ); ?></option>
													</select>
												</td>
											</tr>

											<tr>
												<th>
													<?php esc_html_e( 'User comment', 'wallets' ); ?>
												</th>
												<td>
													<pre><?php esc_html_e( $wd->comment ); ?></pre>
												</td>
											</tr>


											<tr>
												<th>
													<label
														for="wallets_bank_fiat_error">

														<?php esc_html_e( 'Error message', 'wallets' ); ?>
													</label>
												</th>
												<td>
													<input
														type="text"
														id="wallets_bank_fiat_error"
														name="wallets_error"
														value="<?php esc_attr_e( $wd->error ); ?>" />

													<p class="description">
														<?php esc_html_e( 'If setting the withdrawal to "failed", optionally enter an error message for the user.', 'wallets' ); ?>
													</p>
												</td>
											</tr>

											<tr>
												<td style="text-align: center;">
													<input
														type="submit"
														class="button"
														value="<?php esc_attr_e( 'Update', 'wallets' ); ?>" />
												</td>
												<td style="text-align: center;">
													<a
														class="button"
														href="<?php esc_attr_e( $withdraw_tools_url ); ?>">
														<?php esc_html_e( 'Go back', 'wallets' ); ?>
													</a>
												</td>
											</tr>

										</tbody>
									</table>
								<?php
							endif;

						} elseif (
							isset( $_REQUEST['action'] )
							&& 'edit_fiat_wd_post' == $_REQUEST['action']
							&& isset( $_REQUEST['fiat_wd_id'] )
						) {
							// handle fiat wd edit request
							$wd_id = absint( $_REQUEST['fiat_wd_id'] );

							if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], "wallets-edit-fiat-withdrawal-$wd_id" ) ) {
								wp_die( 'Possible request forgery' );
							}

							try {
								$wd = Transaction::load( $wd_id );
							} catch ( \Exception $e ) {
								wp_die(
									sprintf(
										(string) __( 'Failed to load withdrawal with post_id %d due to: %s', 'wallets' ),
										$wd_id,
										$e->getMessage()
									),
									(string) __( 'Invalid fiat withdrawal', 'wallets' )
								);
							}

							if (
								'withdrawal' == $wd->category
								&& ! $wd->nonce
								&& $wd->currency->is_fiat()
							) {
								$txid   = $_POST['wallets_txid']   ?? null;
								$status = $_POST['wallets_status'] ?? null;
								$error  = $_POST['wallets_error']  ?? null;

								try {
									if ( ! is_null( $txid ) ) {
										$wd->txid = $txid;
									}

									if ( ! is_null( $status ) ) {
										$wd->status = $status;
									}

									if ( ! is_null( $error ) ) {
										$wd->error = $error;
									}

									$wd->save();

								} catch ( \Exception $e ) {
									wp_die(
										sprintf(
											(string) __( 'Failed to save withdrawal with post_id %d due to: %s', 'wallets' ),
											$wd_id,
											$e->getMessage()
										),
										(string) __( 'Could not save fiat withdrawal', 'wallets' )
									);
								}

								?>
								<script type="text/javascript">
								window.location = '<?php echo esc_js( $withdraw_tools_url ); ?>';
								</script>

								<?php
								exit;
							}

						} else {
							$withdrawals_list = new class extends \WP_List_Table {

								public function ajax_user_can() {
									return ds_current_user_can( 'manage_wallets' );
								}

								public function get_columns() {
									return array(
										'currency'      => esc_html__( 'Currency', 'wallets' ),
										'amount'        => esc_html__( 'Amount', 'wallets' ),
										'user'          => esc_html__( 'User', 'wallets' ),
										'txid'          => esc_html__( 'TXID', 'wallets' ),
										'status'        => esc_html__( 'Status', 'wallets' ),
										'created_time'  => esc_html__( 'Created', 'wallets' ),
										'action'        => esc_html__( 'Action', 'wallets' ),
									);
								}

								public function get_hidden_columns() {
									return [];
								}

								public function get_sortable_columns() {
									return [];
								}

								public function prepare_items() {
									$per_page     = $this->get_items_per_page( 'edit_post_per_page', 20 );
									$current_page = $this->get_pagenum();

									$this->_column_headers = [
										$this->get_columns(),
										$this->get_hidden_columns(),
										$this->get_sortable_columns(),
									];

									maybe_switch_blog();

									$fiat_ids = get_currency_ids( 'fiat' );

									$query_args = [
										'fields'         => 'ids',
										'post_type'      => 'wallets_tx',
										'post_status'    => [ 'publish', 'pending', 'draft' ],
										'orderby'        => 'ID',
										'order'          => 'DESC',
										'nopaging'       => false,
										'posts_per_page' => $per_page,
										'paged'          => $current_page,
										'meta_query'     => [
											'relation' => 'AND',
											[
												'key'   => 'wallets_category',
												'value' => 'withdrawal',
											],
											[
												'key'     => 'wallets_amount',
												'compare' => '<',
												'value'   => 0,
												'type'    => 'numeric',
											],
											[
												'key'     => 'wallets_fee',
												'compare' => '<=',
												'value'   => 0,
												'type'    => 'numeric',
											],
											[
												'key'     => 'wallets_address_id',
												'compare' => 'EXISTS',
											],
											[
												'key'     => 'wallets_currency_id',
												'compare' => 'IN',
												'value'   => $fiat_ids,
											],
										],
									];

									$query = new \WP_Query( $query_args );

									$this->items = Transaction::load_many( $query->posts );

									$this->set_pagination_args(
										[
											'total_items' => $query->found_posts,
											'per_page'    => $per_page,
										]
									);

									maybe_restore_blog();
								}

								public function column_currency( Transaction $wd ) {
									esc_html_e( $wd->currency->name );
								}

								public function column_amount( Transaction $wd ) {
									esc_html_e( $wd->get_amount_as_string( 'amount', true, true ) );
								}

								public function column_user( Transaction $wd ) {
									if ( $wd->user ):
										?>
										<a
											href="<?php esc_attr_e( get_edit_user_link( $wd->user->ID ) ); ?>">
											<?php esc_html_e( $wd->user->display_name ); ?>
										</a>
										<?php
									else:
										?>
										&mdash;
										<?php
									endif;
								}

								public function column_txid( Transaction $wd ) {
									if ( $wd->txid ):
										?>
										<code><?php esc_html_e( $wd->txid ); ?></code>
										<?php
									else:
										?>
										&mdash;
										<?php
									endif;
								}

								public function column_status( $item ) {
									esc_html_e( $item->status );
								}

								public function column_created_time( Transaction $wd ) {
									echo get_the_date( '', $wd->post_id );
								}

								public function column_action( Transaction $wd ) {
									if ( ! $wd->nonce ):

										$actions = [];

										$actions['edit_fiat_wd'] = sprintf(
											'<a class="button" href="%s" title="%s">%s</a>',
											add_query_arg(
												[
													'page'       => 'wallets-bank-fiat-withdrawals',
													'action'     => 'edit_fiat_wd_form',
													'fiat_wd_id' => $wd->post_id,
												],
												admin_url( 'tools.php' )
											),
											(string) __( 'Edit the withdrawal details and change its status.', 'wallets' ),
											(string) __('&#x270e; Modify', 'wallets' )
										);

										echo $this->row_actions( $actions, true );
									else:
										esc_html_e( 'Not email-validated yet.', 'wallets' );
									endif;
								}
							};

							?>

							<p>
							<?php
								esc_html_e(
									'All the withdrawals for fiat currencies are listed here.',
									'wallets'
								);
							?>
							</p>

							<div class="wrap">
							<?php
								$withdrawals_list->prepare_items();
								$withdrawals_list->display();
							?>
							</div>

							<?php
						}

					}
				);
			}
		} );



	}
}
Bank_Fiat_Adapter::register();
