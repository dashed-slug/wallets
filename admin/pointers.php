<?php

/**
 * Displays helpful pointers for new users in the admin screens.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

add_action(
	'in_admin_header',
	function() {
		if ( ! ds_current_user_can( 'manage_wallets' ) ) {
			return;
		}

		if ( \DSWallets\Migration_Task::is_running() ) {
			return;
		}

		$pointer = null;
		$target = null;

		maybe_switch_blog(); // in a net-active multisite situation we want to point to links in the main blog's admin screens

		ob_start();

		if (
			! get_user_meta(
				get_current_user_id(),
				'wallets_pointers_dismissed_1',
				true
			)
		):

			$pointer = 'wallets_pointers_dismissed_1';
			$target  = 'menu-posts-wallets_wallet';
			$align   = 'top';
			?>
			<h3><?php esc_html_e( 'Bitcoin and Altcoin Wallets', 'wallets' ); ?></h3>
			<h4><?php esc_html_e( 'Step 1: Setup a wallet back-end', 'wallets' ); ?></h4>

			<p><?php esc_html_e( 'Welcome to Bitcoin and Altcoin Wallets for WordPress.', 'wallets' ); ?></p>
			<p><?php _e( 'The first step is to connect to one or more <em>wallets</em>.', 'wallets' ); ?></p>
			<p><?php _e( 'A <em>Wallet</em> entry holds settings and credentials for connecting to your wallet.', 'wallets' ); ?></p>
			<p><?php _e(
				'You can define Wallets and link them to <em>Wallet Adapters</em>. ' .
				'Wallet adapters are special software that helps the plugin communicate with a wallet\'s API.',
				'wallets'
			); ?></p>

			<nav class="wallets-pointer-buttons">
				<a
					href="<?php echo admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=install' ) ?>"
					class="button">
					<?php esc_html_e( 'Installation instructions', 'wallets' ); ?>
				</a>
				<a
					href="<?php echo admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#wallets' ) ?>"
					class="button">
					<?php esc_html_e( 'Learn about Wallets', 'wallets' ); ?>
				</a>
				<a
					href="<?php echo admin_url( 'edit.php?post_type=wallets_wallet' ); ?>"
					class="button button-primary">
					<?php esc_html_e( 'Go to wallets', 'wallets' ); ?>
				</a>
			</nav>

		<?php
		elseif (
			! get_user_meta(
				get_current_user_id(),
				'wallets_pointers_dismissed_2',
				true
			)
		):

			$pointer = 'wallets_pointers_dismissed_2';
			$target  = 'menu-posts-wallets_currency';
			$align   = 'top';
			?>

			<h3><?php esc_html_e( 'Bitcoin and Altcoin Wallets', 'wallets' ); ?></h3>
			<h4><?php esc_html_e( 'Step 2: Setup currencies', 'wallets' ); ?></h4>

			<p>
			<?php
			_e(
				'Once a wallet is connected, it must be linked to a <em>Currency</em>. '.
				'A Currency post specifies important information about a currency, such as its '.
				'name, ticker symbol, amount of decimals, associated wallet, and exchange rates.',
				'wallets'
			);
			?></p>

			<div>
				<ul style="list-style: disc;">
					<li style="margin-left: 3em;">
					<?php
					printf(
						__(
							'Some wallet adapters, such as the %s, can host multiple currencies. ' .
							'However, most wallets will correspond to only one currency.',
							'wallets'
						),
						sprintf(
							'<a href="%s" target="_blank">%s</a>',
							'https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/coinpayments-adapter-extension/?utm_source=wallets&utm_medium=plugin&utm_campaign=pointers',
							__( 'CoinPayments wallet adapter', 'wallets' )
						)
					);
					?>
					</li>

					<li style="margin-left: 3em;"><?php esc_html_e( 'The Bitcoin currency will be created shortly for you, as an example. You must attach it to a wallet.', 'wallets' ); ?></li>

					<li style="margin-left: 3em;"><?php
						printf(
							__( 'Most cryptocurrencies can be created manually. Simply go to <em>Currencies</em> &rarr; %s.', 'wallets' ),
							sprintf(
								'<a href="%s"><em>%s</em></a>',
								admin_url( 'post-new.php?post_type=wallets_currency' ),
								__( 'Add new', 'wallets' )
							)
						);
					?></li>

					<li style="margin-left: 3em;">
					<?php
						printf(
							__( 'Fiat currencies can be auto-created once you setup a fixer.io API key in the %s.', 'wallets' ),
							sprintf(
								'<a href="%s">%s</a>',
								admin_url( 'options-general.php?page=wallets_settings_page&tab=fiat' ),
								__( 'Fiat settings', 'wallets' )
							)
						);
					?>
					</li>

				</ul>
			</div>

			<nav class="wallets-pointer-buttons">
				<a
					href="<?php echo admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=install' ) ?>"
					class="button">
					<?php esc_html_e( 'Installation instructions', 'wallets' ); ?>
				</a>
				<a
					href="<?php echo admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#currencies' ) ?>"
					class="button">
					<?php esc_html_e( 'Learn about Currencies', 'wallets' ); ?>
				</a>
				<a
					href="<?php echo admin_url( 'options-general.php?page=wallets_settings_page&tab=fiat' ) ?>"
					class="button">
					<?php esc_html_e( 'Go to Fiat settings', 'wallets' ); ?>
				</a>
				<a
					href="<?php echo admin_url( 'edit.php?post_type=wallets_currency' ); ?>"
					class="button button-primary">
					<?php esc_html_e( 'Go to currencies', 'wallets' ); ?>
				</a>
			</nav>

		<?php
		elseif (
			! get_user_meta(
				get_current_user_id(),
				'wallets_pointers_dismissed_3',
				true
			)
		):

			$pointer = 'wallets_pointers_dismissed_3';
			$target  = 'menu-settings';
			$align   = 'bottom';
			?>

			<h3><?php esc_html_e( 'Bitcoin and Altcoin Wallets', 'wallets' ); ?></h3>
			<h4><?php esc_html_e( 'Step 3: Review the settings', 'wallets' ); ?></h4>

			<p><?php esc_html_e( 'Please take the time to review the plugin\'s settings.', 'wallets' ); ?></p>

			<p>
			<?php
				printf(
					__( 'Pay special attention to the %s tab. This is where you can control user access to the plugin features.', 'wallets' ),
					sprintf(
						'<a href="%s">%s</a>',
						admin_url( 'options-general.php?page=wallets_settings_page&tab=caps' ),
						__( 'Capabilities', 'wallets' )
					)
				);
			?>
			</p>


			<nav class="wallets-pointer-buttons">
				<a
					href="<?php echo admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=install' ) ?>"
					class="button">
					<?php esc_html_e( 'Installation instructions', 'wallets' ); ?>
				</a>
				<a
					href="<?php echo admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=settings' ) ?>"
					class="button">
					<?php esc_html_e( 'Learn about Settings', 'wallets' ); ?>
				</a>
				<a
					href="<?php echo admin_url( 'options-general.php?page=wallets_settings_page' ); ?>"
					class="button button-primary">
					<?php esc_html_e( 'Go to settings', 'wallets' ); ?>
				</a>
			</nav>

		<?php
		elseif (
			! get_user_meta(
				get_current_user_id(),
				'wallets_pointers_dismissed_4',
				true
			)
		):

			$pointer = 'wallets_pointers_dismissed_4';
			$target  = 'menu-pages';
			$align   = 'top';
			?>

			<h3><?php esc_html_e( 'Bitcoin and Altcoin Wallets', 'wallets' ); ?></h3>
			<h4><?php esc_html_e( 'Step 4: Setup the frontend UI elements', 'wallets' ); ?></h4>

			<p><?php esc_html_e( 'You can insert the various UI elements into page content using shortcodes.', 'wallets' ); ?></p>

			<p><?php esc_html_e( 'To control the styling and appearance of the UI elements, visit the Customizer.', 'wallets' ); ?></p>

			<nav class="wallets-pointer-buttons">
				<a
					href="<?php echo admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=install' ) ?>"
					class="button">
					<?php esc_html_e( 'Installation instructions', 'wallets' ); ?>
				</a>
				<a
					href="<?php echo admin_url( 'admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=frontend' ) ?>"
					class="button">
					<?php esc_html_e( 'Learn about the Frontend', 'wallets' ); ?>
				</a>
				<a
					href="<?php echo admin_url( 'customize.php' ); ?>"
					class="button">
					<?php esc_html_e( 'Go to Customizer', 'wallets' ); ?>
				</a>


				<?php
					$content = <<<SHORTCODES
[wallets_balance]
[wallets_deposit]
[wallets_withdraw]
[wallets_transactions]
SHORTCODES;

					$url = add_query_arg(
						array(
							'post_title'  => __( 'Wallet', 'wallets' ),
							'content'     => $content,
							'post_status' => 'publish',
							'post_type'   => 'page',
							'post_author' => get_current_user_id(),
							'submit'      => 'Create new page',
						),
						admin_url( 'post-new.php' )
					);

				?>
				<a
					href="<?php esc_attr_e( $url ); ?>"
					class="button button-primary">
					<?php esc_html_e( 'Create a wallet page', 'wallets' ); ?>
				</a>

		<?php
		endif;
		$content = ob_get_clean();

		maybe_restore_blog();

		if ( $content && $pointer && $target ) {

			wp_enqueue_script( 'jquery' );
			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_script( 'wp-pointer' );

			$content = strtr( $content, [
				"\n" => '',
				'/' => '\/',
				'"' => '\"',
			] );

			?>
			<script type="text/javascript">
				jQuery( function() {
					let options = {
						content: "<?php echo $content; ?>",
						position: {
							<?php if ( 'top' == $align ): ?>
							edge: 'top',
							align: 'left',
							<?php elseif ( 'bottom' == $align ): ?>
							edge: 'left',
							align: 'bottom',
							<?php endif; ?>
						},
						pointerClass: 'wp-pointer arrow-<?php echo $align; ?>',
						pointerWidth: 420, // this feels like the right amount of width right now
						close: function() {
							jQuery.post(
								ajaxurl,
								{
									pointer: '<?php echo esc_js( $pointer ); ?>',
									action: 'dismiss-wp-pointer',
								}
							);
						},
					};

					jQuery( '#<?php echo esc_js( $target ); ?>' ).first().pointer( options ).pointer( 'open' );
				} );
			</script>
			<?php
		}
	}
);

add_action(
	'admin_init',
	function() {
		if ( ! ds_current_user_can( 'manage_wallets' ) ) {
			return;
		}

		if ( isset( $_POST['action'] ) && 'dismiss-wp-pointer' == $_POST['action'] ) {

			if ( preg_match( '/^wallets_pointers_dismissed_\d$/', $_POST['pointer'] ) ) {

				update_user_meta(
					get_current_user_id(),
					$_POST['pointer'],
					true
				);
			}
		}
	}
);
