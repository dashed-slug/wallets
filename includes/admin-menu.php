<?php

/**
 * This is the main "Wallets" admin screen that features the about section.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Admin_Menu' ) ) {
	class Dashed_Slug_Wallets_Admin_Menu {

		public function __construct() {
			add_action( is_plugin_active_for_network( 'wallets/wallets.php' ) ? 'network_admin_menu' : 'admin_menu', array( &$this, 'action_admin_menu' ) );

			$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
			if ( 'wallets-menu-wallets' == $page ) {
				add_action( 'admin_enqueue_scripts', array( &$this, 'action_admin_enqueue_scripts' ) );
			}

			if ( 'wallets-menu' == substr( $page, 0, 12 ) ) {
				add_action( 'in_admin_footer', array( &$this, 'footer' ) );
			}
		}

		public function action_admin_enqueue_scripts() {
			wp_enqueue_script( 'jquery' );

			wp_enqueue_script(
				'blockchain-info',
				plugins_url( 'pay-now-button-4.4.1.min.js', 'wallets/assets/scripts/pay-now-button-4.4.1.min.js' ),
				array( 'jquery' )
			);
		}

		public function action_admin_menu() {

			if ( current_user_can( 'manage_wallets' ) ) {
				add_menu_page(
					'Bitcoin and Altcoin Wallets',
					__( 'Wallets' ),
					'manage_wallets',
					'wallets-menu-wallets',
					array( &$this, 'wallets_page_cb' ),
					plugins_url( 'assets/sprites/wallet-icon.png', DSWALLETS_PATH . '/wallets.php' )
				);

				add_submenu_page(
					'wallets-menu-wallets',
					'Bitcoin and Altcoin Wallets',
					__( 'About' ),
					'manage_wallets',
					'wallets-menu-wallets',
					array( &$this, 'wallets_page_cb' )
				);

				do_action( 'wallets_admin_menu' );
			}
		}

		public function wallets_page_cb() {
			if ( ! current_user_can( 'manage_wallets' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'wallets' ) );
			} ?>


			<h1><?php echo 'Bitcoin and Altcoin Wallets'; ?></h1>

			<div class="notice notice-warning"><h2>
			<?php
				esc_html_e( 'IMPORTANT SECURITY DISCLAIMER:', 'wallets' );
			?>
			</h2>

			<p>
			<?php
				esc_html_e(
					'By using this free plugin you accept all responsibility for handling ' .
					'the account balances for all your users. Under no circumstances is dashed-slug.net ' .
					'or any of its affiliates responsible for any damages incurred by the use of this plugin. ' .
					'Every effort has been made to harden the security of this plugin, ' .
					'but its safe operation is your responsibility and depends on your site being secure overall. ' .
					'You, the administrator, must take all necessary precautions to secure your WordPress installation ' .
					'before you connect it to any live wallets. ' .
					'You are strongly advised to take the following actions (at a minimum):', 'wallets'
				);
			?>
			</p>
			<ol><li><a href="https://codex.wordpress.org/Hardening_WordPress" target="_blank" rel="noopener noreferrer">
			<?php
				esc_html_e( 'educate yourself about hardening WordPress security', 'wallets' );
			?>
			</a></li>
			<li><a href="https://infinitewp.com/addons/wordfence/?ref=260" target="_blank" rel="noopener noreferrer" title="
			<?php
				esc_attr_e(
					'This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.', 'wallets'
				);
			?>
			"><?php esc_html_e( 'install a security plugin such as Wordfence', 'wallets' ); ?></a></li>
			<li>
			<?php
				esc_html_e( 'Enable SSL on your site, if you have not already done.', 'wallets' );
			?>
			</li><li>
			<?php
				esc_html_e(
					'If you are connecting to an RPC API on a different machine than that ' .
					'of your WordPress server over an untrusted network, make sure to tunnel your connection via ssh or stunnel.',
					'wallets'
				);
			?>
			<a href="https://en.bitcoin.it/wiki/Enabling_SSL_on_original_client_daemon">
			<?php
				esc_html_e( 'See more here', 'wallets' );
			?>
			</a>.</li></ol><p>
			<?php
				esc_html_e(
					'By continuing to use the Bitcoin and Altcoin Wallets plugin, ' .
					'you indicate that you have understood and agreed to this disclaimer.', 'wallets'
				);
			?>
			</p></div>

			<div class="card">
				<h2><?php esc_html_e( 'Subscribe to the YouTube channel:', 'wallets' ); ?></h2>
				<div class="g-ytsubscribe" data-channelid="UCZ1XhSSWnzvB2B_-Cy1tTjA" data-layout="full" data-count="default"></div>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'The dashed-slug can also be found on SteemIt:', 'wallets' ); ?></h2>
				<a href="https://steemit.com/@dashed-slug.net">https://steemit.com/@dashed-slug.net</a>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Get the free PDF manual!', 'wallets' ); ?></h2>
				<ol>
					<li><?php echo __( 'Visit the dashed-slug <a href="https://dashed-slug.net/downloads">download area</a>.', 'wallets' ); ?></li>
					<li>
					<?php
						echo __(
							'Download the <strong>Bitcoin and Altcoin Wallets bundle</strong>. ' .
							'You will find the PDF file inside the ZIP download. ', 'wallets'
						);
						?>
					</li>
					<li><?php echo __( 'RTFM! :-)', 'wallets' ); ?></li>
				</ol>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Have your say!', 'wallets' ); ?></h2>

				<ol>
					<li><?php echo __( 'Did you find this plugin useful? Leave a review on <a href="https://wordpress.org/support/plugin/wallets/reviews/">wordpress.org</a>.', 'wallets' ); ?></li>
					<li><?php echo __( 'Do you need help? Did you find a bug? Visit the <a href="https://wordpress.org/support/plugin/wallets">wordpress.org support forum</a> for the main plugin or the <a href="https://www.dashed-slug.net/support/">dashed-slug.net</a> support forums for the extensions.', 'wallets' ); ?></li>
					<li><?php echo __( 'Something else on your mind? <a href="https://dashed-slug.net/contact">Contact me</a>.', 'wallets' ); ?></li>
				</ol>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Show your appreciation with a donation!', 'wallets' ); ?></h2>

				<p><?php esc_html_e( 'Want to help with development? Help me buy the coffee that makes this all possible!', 'wallets' ); ?></p>

				<ol>
					<li><?php echo __( 'Donate via <a href="https://flattr.com/profile/dashed-slug">flattr</a>', 'wallets' ); ?>.</li>
					<li>
					<?php
						echo __(
							'Donate a few shatoshi to the dashed-slug Bitcoin address: ' .
							'<a href="bitcoin:1DaShEDyeAwEc4snWq14hz5EBQXeHrVBxy?label=dashed-slug&message=donation">1DaShEDyeAwEc4snWq14hz5EBQXeHrVBxy</a>.', 'wallets'
						);
					?>

						<div
							style="font-size:16px;margin:0 auto;width:300px"
							class="blockchain-btn"
							data-address="1DaShEDyeAwEc4snWq14hz5EBQXeHrVBxy"
							data-shared="false">

							<div
								class="blockchain stage-begin">

								<img
									src="https://blockchain.info/Resources/buttons/donate_64.png" />
							</div>

							<div
								class="blockchain stage-loading"
								style="text-align:center">

								<img
									src="https://blockchain.info/Resources/loading-large.gif" />
							</div>

							<div
								class="blockchain stage-ready">

								<p
									align="center">Please Donate To Bitcoin Address: <b>[[address]]</b></p>

								<p
									align="center"
									class="qr-code"></p>
							</div>

							<div
								class="blockchain stage-paid">
								Donation of <b>[[value]] BTC</b> Received. Thank You.
							</div>

							<div
								class="blockchain stage-error">

								<font
									color="red">[[error]]</font>
							</div>
						</div>
					</li>
				</ol>

				<p><?php esc_html_e( 'Your support is greatly appreciated.', 'wallets' ); ?></p>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'App extensions', 'wallets' ); ?></h2>

				<p><?php esc_html_e( '"App extensions" are plugins that work on top of the Bitcoin and Altcoin Wallets plugin to provide new functionality to users.', 'wallets' ); ?></p>

				<?php $this->showcase_plugin_extensions( DSWALLETS_PATH . '/assets/data/features.json' ); ?>

			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Coin adapters', 'wallets' ); ?></h2>

				<p><?php esc_html_e( 'With "coin adapters" you can make the Bitcoin and Altcoin Wallets plugin work with other stand-alone and/or web wallets to add support for additional cryptocurrencies. ', 'wallets' ); ?></p>

				<?php $this->showcase_plugin_extensions( DSWALLETS_PATH . '/assets/data/adapters.json' ); ?>

			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Like the Facebook page to learn the latest news:', 'wallets' ); ?></h2>
				<iframe src="https://www.facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Fdashedslug%2F&tabs=timeline&width=500&height=500&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=false&appId=1048870338583588" width="500" height="500" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowTransparency="true"></iframe>
			</div>

			<div class="card" style="max-height: 500px; overflow-y: scroll">
				<h2><?php esc_html_e( 'Follow the dashed-slug on twitter to learn the latest news:', 'wallets' ); ?></h2>
				<a class="twitter-timeline" href="https://twitter.com/DashedSlug?ref_src=twsrc%5Etfw">Tweets by DashedSlug</a>
				<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
			</div>

			<div style="clear: left;"></div>
			<?php
		}

		private function showcase_plugin_extensions( $extensions_data_file ) {
			$extensions = json_decode( file_get_contents( $extensions_data_file ) );
			?>

			<ul class="wallets-extension">

			<?php
			foreach ( $extensions as $extension ) :
				$active = is_plugin_active( "{$extension->slug}/{$extension->slug}.php" );
				?>
				<li>
					<?php
					if ( $active ) :
?>
<strong><?php endif; ?>
					<a
						href="<?php echo esc_attr( $extension->homepage ); ?>?utm_source=wallets&utm_medium=plugin&utm_campaign=about">

						<?php echo esc_html( $extension->name ); ?></a>

					<?php
					if ( $active ) :
?>
</strong> (<?php esc_attr_e( 'Installed', 'wallets' ); ?>)<?php endif; ?>

					<a
						class="button"
						style="float: right;"
						href="<?php echo esc_attr( $extension->support ); ?>?utm_source=wallets&utm_medium=plugin&utm_campaign=about">

						<?php echo esc_html( 'Support Forum' ); ?>
					</a>

					<p
						class="description">

						<?php echo esc_html( $extension->description ); ?>
					</p>

					<div style="clear: right;"></div>
				</li>
			<?php endforeach; ?>
			</ul>
			<?php
		}

		public function footer() {
			?>
			<div class="card wallets-footer-message">
				<p>
				<?php
					echo __( 'Found <strong>Bitcoin and Altcoin Wallets</strong> useful? Want to help the project? ', 'wallets' );
				?>
				</p>
				<p>
				<?php
					echo __( 'Please leave a <a href="https://wordpress.org/support/view/plugin-reviews/wallets?filter=5#postform">★★★★★</a> rating on WordPress.org!', 'wallets' );
				?>
				</p>
			</div>
			<?php
		}

	}
	new Dashed_Slug_Wallets_Admin_Menu();
}

