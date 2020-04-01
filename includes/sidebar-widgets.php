<?php

/**
 * Make the shortcodes also available as widgets.
 *
 */

if ( ! class_exists( 'Dashed_Slug_Wallets_Widget' ) ) {
	class Dashed_Slug_Wallets_Widget extends WP_Widget {

		private $widget;
		private $description;
		private $capabilities;
		private $templates = array();

		public static function widgets_init() {
			register_widget( 'Dashed_Slug_Wallets_Widget_API_Key' );
			register_widget( 'Dashed_Slug_Wallets_Widget_Deposit' );
			register_widget( 'Dashed_Slug_Wallets_Widget_Withdraw' );
			register_widget( 'Dashed_Slug_Wallets_Widget_Move' );
			register_widget( 'Dashed_Slug_Wallets_Widget_Balance' );
			register_widget( 'Dashed_Slug_Wallets_Widget_Transactions' );
			register_widget( 'Dashed_Slug_Wallets_Widget_AccountValue' );
			register_widget( 'Dashed_Slug_Wallets_Widget_TotalBalances' );
			register_widget( 'Dashed_Slug_Wallets_Widget_ExchangeRates' );
		}

		/**
		 * Sets up the widgets name etc
		 */
		public function __construct( $widget, $title, $desc, $caps, $classname ) {
			$this->widget       = $widget;
			$this->description  = $desc;
			$this->capabilities = $caps;

			$name            = preg_replace( '/^wallets_/', '', $widget );

			$this->user_id   = get_current_user_id();
			$this->rowcount  = 10;

			// option arrays for dropdowns
			$this->templates  = $this->get_templates( $name, Dashed_Slug_Wallets_Template_Loader::get_plugin_templates_directory() );
			$this->adapters   = apply_filters( 'wallets_api_adapters', array() );
			$this->all_cats   = array( 'deposit', 'move', 'withdraw', 'trade' );

			$widget_ops = array(
				'classname'   => $classname,
				'description' => $desc,
			);

			parent::__construct(
				strtolower( $classname ),
				$title,
				$widget_ops
			);
		}

		private function get_templates( $name, $templates_dir ) {
			$templates = array();
			$files = array_diff( scandir( $templates_dir ), array( '.', '..', 'index.php' ) );
			foreach ( $files as &$file ) {
				if ( ! is_dir( "$templates_dir/$file" ) ) {
					if ( 0 === strpos( $file, $name ) ) {
						$templates[] = basename( $file, '.php' );
					}
				}
			}
			return $templates;
		}

		/**
		 * Outputs the content of the widget
		 *
		 * @param array $args
		 * @param array $instance
		 */
		public function widget( $args, $instance ) {
			if ( is_user_logged_in() ) {
				$allowed = true;
				foreach ( $this->capabilities as $capability ) {
					$allowed = $allowed && user_can( $this->user_id, $capability );
				}
				if ( ! isset( $instance['template'] ) || false === array_search( $instance['template'], $this->templates ) ) {
					$instance['template'] = '';
				}

				if ( $allowed ) : ?>
				<div class="widget widget-wallets widget-<?php echo str_replace( '_', '-', $this->widget ); ?>">
					<h3 class="widget-heading"><?php esc_html_e( $this->name, 'wallets' ); ?></h3>
					<?php
					$shortcode = "[{$this->widget}";
					$shortcode .= " template=\"{$instance['template']}\"";

					foreach ( array( 'symbol', 'columns', 'qrsize', 'user_id', 'rowcount', 'categories', 'tags' ) as $field ) {
						if ( isset( $instance[ $field ] ) && $instance[ $field ] ) {
							$shortcode .= " $field=\"$instance[$field]\"";
						}
					}
					$shortcode .= ']';

					echo do_shortcode( $shortcode );
					?>
				</div>
				<?php
				endif;
			}
		}

		/**
		 * Outputs the options form on admin
		 *
		 * @param array $instance The widget options
		 */
		public function form( $instance ) {
			if ( ! isset( $instance['template'] ) ) {
				$instance['template'] = $this->widget;
			}
			if ( ! isset( $instance['columns'] ) ) {
				$instance['columns'] = implode( ',', Dashed_Slug_Wallets_Shortcodes::$tx_columns );
			}
			if ( ! isset( $instance['symbol'] ) ) {
				$instance['symbol'] = '';
			}

			?>
			<label>
				<?php esc_html_e( 'Template', 'wallets' ); ?><br />
				<select
					id="<?php echo $this->get_field_id( 'template' ); ?>"
					name="<?php echo $this->get_field_name( 'template' ); ?>"
					class="widefat"
					style="width:100%;">

					<?php foreach ( $this->templates as $template ) : ?>
					<option <?php selected( $instance['template'], $template ); ?> value="<?php echo esc_attr( basename( $template ) ); ?>"><?php echo esc_html( $template ); ?></option>
					<?php endforeach; ?>

				</select>
			</label>

			<label>
				<?php esc_html_e( 'User', 'wallets' ); ?><br />

				<?php wp_dropdown_users( array(
					'id'       => $this->get_field_id(   'user_id' ),
					'name'     => $this->get_field_name( 'user_id' ),
					'selected' => isset( $instance['user_id'] ) ? $instance['user_id'] : 0,

				) ); ?>

				<span class="description"><?php esc_html_e(
					'Only for some static shortcode views, the user whose data to show. ' .
					'For all other shortcodes, this is ignored.',
					'wallets'
				); ?></span>
			</label>

			<label>
				<?php esc_html_e( 'Coin', 'wallets' ); ?><br />

				<select
					id="<?php echo $this->get_field_id( 'symbol' ); ?>"
					name="<?php echo $this->get_field_name( 'symbol' ); ?>"
					class="widefat"
					style="width:100%;">

					<?php foreach ( $this->adapters as $adapter ) : ?>
					<option
						<?php selected( $instance['symbol'], $adapter->get_symbol() ); ?>
						value="<?php echo $adapter->get_symbol(); ?>">

						<?php echo esc_html( sprintf( '%s (%s)', $adapter->get_name(), $adapter->get_symbol() ) ); ?>
					</option>
					<?php endforeach; ?>

				</select>

				<span class="description"><?php esc_html_e(
					'Only for some static shortcode views, the coin to display data for. ' .
					'For all other shortcodes, this is ignored.',
					'wallets'
				); ?></p>

			</label>
			<?php
		}

		/**
		 * Processing widget options on save
		 *
		 * @param array $new_instance The new options
		 * @param array $old_instance The previous options
		 */
		public function update( $new_instance, $old_instance ) {
			$instance = array();
			$instance['template'] = $new_instance['template'];

			if ( isset( $new_instance['user_id'] ) ) {
				$instance['user_id'] = absint( $new_instance['user_id'] );
			}
			if ( isset( $new_instance['symbol'] ) ) {
				$instance['symbol'] = $new_instance['symbol'];
			}

			return $instance;
		}
	}

	class Dashed_Slug_Wallets_Widget_API_Key extends Dashed_Slug_Wallets_Widget {
		public function __construct() {
			parent::__construct(
				'wallets_api_key',
				__( 'View/refresh JSON-API key', 'wallets-front' ),
				__( 'A box that displays the user\'s API key token, and lets the user renew the token.', 'wallets' ),
				array( 'has_wallets' ),
				__CLASS__
			);
		}
	}

	class Dashed_Slug_Wallets_Widget_Deposit extends Dashed_Slug_Wallets_Widget {
		public function __construct() {
			parent::__construct(
				'wallets_deposit',
				__( 'Deposit to wallet', 'wallets-front' ),
				__( 'A form that will let the user know which address ' .
					'they can send coins to if they wish to make a deposit.',
					'wallets' ),
				array( 'has_wallets' ),
				__CLASS__
			);
		}

		public function form( $instance ) {
			parent::form( $instance );

			if ( ! isset( $instance['qrsize'] ) ) {
				$instance['qrsize'] = 10;
			}

			?>
			<label>
				<?php esc_html_e( 'QR code size (px)', 'wallets' ); ?><br />
				<input
					type="number"
					min="0"
					max="1024"
					step="1"
					id="<?php echo $this->get_field_id( 'qrsize' ); ?>"
					name="<?php echo $this->get_field_name( 'qrsize' ); ?>"
					value="<?php echo esc_attr( absint( $instance['qrsize'] ) ); ?>" />

				<span class="description"><?php esc_html_e(
					'Size of the deposit QR code, in pixels. If this is left empty, no size is set.',
					'wallets'
				); ?></span>
			</label>
			<?php
		}

		/**
		 * Processing widget options on save
		 *
		 * @param array $new_instance The new options
		 * @param array $old_instance The previous options
		 */
		public function update( $new_instance, $old_instance ) {
			$instance = parent::update( $new_instance, $old_instance );

			if ( isset( $new_instance['qrsize'] ) ) {
				$instance['qrsize'] = absint( $new_instance['qrsize'] );
			}
			return $instance;
		}
	}

	class Dashed_Slug_Wallets_Widget_Withdraw extends Dashed_Slug_Wallets_Widget {
		public function __construct() {
			parent::__construct(
				'wallets_withdraw',
				__( 'Withdraw from wallet', 'wallets-front' ),
				__( 'A form that will let the user withdraw funds.', 'wallets' ),
				array( 'has_wallets', 'withdraw_funds_from_wallet' ),
				__CLASS__
			);
		}
	}

	class Dashed_Slug_Wallets_Widget_Move extends Dashed_Slug_Wallets_Widget {
		public function __construct() {
			parent::__construct(
				'wallets_move',
				__( 'Transfer to user wallet', 'wallets-front' ),
				__( 'A form that lets the user transfer coins to other users on your site.', 'wallets' ),
				array( 'has_wallets', 'send_funds_to_user' ),
				__CLASS__
			);
		}
	}

	class Dashed_Slug_Wallets_Widget_Balance extends Dashed_Slug_Wallets_Widget {
		public function __construct() {
			parent::__construct(
				'wallets_balance',
				__( 'Wallet balance', 'wallets-front' ),
				__( "The current user's balances in all enabled coins.", 'wallets' ),
				array( 'has_wallets' ),
				__CLASS__
			);
		}
	}

	class Dashed_Slug_Wallets_Widget_Transactions extends Dashed_Slug_Wallets_Widget {
		public function __construct() {
			parent::__construct(
				'wallets_transactions',
				__( 'Wallet transactions', 'wallets-front' ),
				__(
					'An interactive table that shows past deposits, withdrawals and transfers for the user.',
					'wallets'
				),
				array( 'has_wallets', 'list_wallet_transactions' ),
				__CLASS__
			);
		}

		public function form( $instance ) {
			parent::form( $instance );

			if ( ! isset( $instance['categories'] ) ) {
				$instance['categories'] = '';
			}

			?>
			<label>
				<?php esc_html_e( 'Columns', 'wallets' ); ?><br />
					<input
						type="text"
						id="<?php echo $this->get_field_id( 'columns' ); ?>"
						name="<?php echo $this->get_field_name( 'columns' ); ?>"
						value="<?php echo esc_attr(
							isset( $instance['columns'] ) ?
							$instance['columns'] :
							implode( ',', Dashed_Slug_Wallets_Shortcodes::$tx_columns ) );
						?>" />

					<span class="description"><?php esc_html_e(
						'Some transaction templates such as the default template accept a columns argument. '.
						'This is a comma separated list of the transaction columns that you want displayed. '.
						'Valid values are: type, tags, time, amount, fee, from_user, to_user, txid, comment,' .
						'confirmations, status, retries, admin_confirm, user_confirm.',

						'wallets'
					); ?></span>
			</label>

			<label>
				<?php esc_html_e( 'Categories', 'wallets' ); ?><br />

				<select
					multiple="multiple"
					id="<?php echo $this->get_field_id( 'categories' ); ?>"
					name="<?php echo $this->get_field_name( 'categories' ); ?>[]"
					class="widefat"
					style="width:100%;">

					<?php

					$selected_categories = explode( ',', $instance['categories'] );

					foreach ( array( 'deposit', 'withdraw', 'move', 'trade' ) as $category ) : ?>
					<option
						<?php selected( false !== array_search( $category, $selected_categories ) ); ?>
						value="<?php echo $category; ?>">

						<?php echo $category; ?>
					</option>
					<?php endforeach; ?>

				</select>

				<span class="description"><?php esc_html_e(
					'Only for static shortcode views, the transaction categories to display. ' .
					'For all other shortcode views, this is ignored.',
					'wallets'
				); ?></span>

			</label>

			<label>
				<?php esc_html_e( 'Tags', 'wallets' ); ?><br />

					<input
						type="text"
						id="<?php echo $this->get_field_id( 'tags' ); ?>"
						name="<?php echo $this->get_field_name( 'tags' ); ?>"
						value="<?php echo esc_attr( isset( $instance['tags'] ) ? $instance['tags'] : '' ); ?>" />

				<span class="description"><?php esc_html_e(
					'Only for static shortcode views, comma-separated list of the transaction tags to search for. ' .
					'Transactions with any one of these tags are returned. ' .
					'For all other shortcode views, this is ignored.',
					'wallets'
				); ?></span>

			</label>

			<label>
				<?php esc_html_e( 'Rows count', 'wallets' ); ?><br />

				<input
					type="number"
					min="2"
					max="1024"
					step="1"
					id="<?php echo $this->get_field_id( 'rowcount' ); ?>"
					name="<?php echo $this->get_field_name( 'rowcount' ); ?>"
					value="<?php echo isset( $instance['rowcount'] ) ? esc_attr( $instance['rowcount'] ) : 10; ?>" />

				<span class="description"><?php esc_html_e(
					'Only for static shortcode views, the maximum number of transactions to display. ' .
					'For all other shortcode views, this is ignored.',
					'wallets'
				); ?></span>

			</label>
			<?php
		}

		public function update( $new_instance, $old_instance ) {
			$instance = parent::update( $new_instance, $old_instance );

			if ( isset( $new_instance['columns'] ) ) {
				$columns = explode( ',', $new_instance['columns'] );
				$columns = array_map( 'trim', $columns );
				$columns = array_intersect( $columns, Dashed_Slug_Wallets_Shortcodes::$tx_columns );
				$instance['columns'] = implode( ',', $columns );
			}

			if ( isset( $new_instance['categories'] ) ) {
				$categories = array_map( 'trim', $new_instance['categories'] );
				$categories = array_intersect( $categories, array( 'deposit', 'withdraw', 'move', 'trade' ) );
				$instance['categories'] = implode( ',', $categories );
			}

			if ( isset( $new_instance['tags'] ) ) {
				$tags = explode( ',', $new_instance['tags'] );
				$tags = array_map( 'trim', $tags );
				$instance['tags'] = implode( ',', $tags );
			}

			if ( isset( $new_instance['qrsize'] ) ) {
				$instance['qrsize'] = $new_instance['qrsize'] ? absint( $new_instance['qrsize'] ) : '';
			}

			if ( isset( $new_instance['rowcount'] ) ) {
				$instance['rowcount'] = $new_instance['rowcount'] ? absint( $new_instance['rowcount'] ) : 10;
			}
			// TODO

			return $instance;
		}
	}

	class Dashed_Slug_Wallets_Widget_AccountValue extends Dashed_Slug_Wallets_Widget {
		public function __construct() {
			parent::__construct(
				'wallets_account_value',
				__( 'Account value', 'wallets-front' ),
				__( 'Shows the account\'s total value expressed in the default fiat currency.', 'wallets' ),
				array( 'has_wallets' ),
				__CLASS__
			);
		}
	}

	class Dashed_Slug_Wallets_Widget_TotalBalances extends Dashed_Slug_Wallets_Widget {
		public function __construct() {
			parent::__construct(
				'wallets_total_balances',
				__( 'Total user balances', 'wallets-front' ),
				__( 'Shows the total user balances for each coin.', 'wallets' ),
				array( 'has_wallets' ),
				__CLASS__
			);
		}
	}

	class Dashed_Slug_Wallets_Widget_ExchangeRates extends Dashed_Slug_Wallets_Widget {
		public function __construct() {
			parent::__construct(
				'wallets_rates',
				__( 'Exchange rates', 'wallets-front' ),
				__(
					'Shows exchange rates for online cryptocurrencies, expressed in the default fiat currency.',
					'wallets'
				),
				array( 'has_wallets' ),
				__CLASS__
			);
		}
	}

	// bind all widgets
	add_action( 'widgets_init', 'Dashed_Slug_Wallets_Widget::widgets_init' );

} // end if class exists

