<?php

/**
 * This makes a new menu item available to the menu editor.
 * You can now show the user balances as part of your menu.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Frontend_Menu' ) ) {
	class Dashed_Slug_Wallets_Frontend_Menu {

		public function __construct() {
			add_action( 'admin_init', array( &$this, 'action_admin_init' ) );
			add_action( 'init', array( &$this, 'action_init' ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'action_admin_enqueue_scripts' ) );

			// customizer
			add_filter( 'customize_nav_menu_available_item_types', array( $this, 'on_customize_nav_menu_available_item_types' ), 10, 1 );
			add_filter( 'customize_nav_menu_available_items', array( $this, 'on_customize_nav_menu_available_items' ), 10, 4 );
		}

		public function action_init() {
			add_filter( 'walker_nav_menu_start_el', array( &$this, 'walker_nav_menu_start_el' ), 1, 4 );
		}

		public function action_admin_init() {
			if ( current_user_can( 'manage_wallets' ) ) {
				global $pagenow;
				if ( 'nav-menus.php' !== $pagenow ) {
					return;
				}

				add_filter( 'wp_setup_nav_menu_item', array( $this, 'wp_setup_nav_menu_item' ), 10, 1 );

				add_meta_box(
					'wallets_nav_balance_box_item_meta_box',
					__( 'Bitcoin and Altcoin Wallets balances', 'wallets' ),
					array( $this, 'balance_meta_box_render' ),
					'nav-menus',
					'side',
					'low'
				);
			}
		}

		public function action_admin_enqueue_scripts() {

			if ( file_exists( DSWALLETS_PATH . '/assets/scripts/wallets-admin-menu-item-4.4.1.min.js' ) ) {
				$script = 'wallets-admin-menu-item-4.4.1.min.js';
			} else {
				$script = 'wallets-admin-menu-item.js';
			}

			wp_enqueue_script(
				'wallets-admin-menu-item',
				plugins_url( $script, "wallets/assets/scripts/$script" ),
				array( 'jquery' ),
				'4.4.1',
				true
			);
		}

		public function wp_setup_nav_menu_item( $menu_item ) {
			if ( isset( $menu_item->type ) && 'balances' == $menu_item->type ) {
				$menu_item->type_label = __( 'Wallet balances', 'wallets' );
			}
			return $menu_item;
		}

		public function on_customize_nav_menu_available_item_types( $item_types ) {
			$item_types[] = array(
				'title'  => __( 'Wallet balances', 'wallets' ),
				'type'   => 'balances',
				'object' => 'wallets_nav_balances_box',
			);
			return $item_types;
		}

		public function on_customize_nav_menu_available_items( $items, $type, $object, $page ) {
			if ( 'bop_nav_search_box' === $type ) {
				$items[] = array(
					'id'         => 'wallets_nav_balances_box',
					'title'      => __( 'Balances box', 'wallets' ),
					'type'       => 'balances',
					'type_label' => __( 'Balances box', 'wallets' ),
					'object'     => '',
					'url'        => '#',
					'classes'    => 'wallets-nav-balances',
				);
			}
			return $items;
		}

		public function balance_meta_box_render() {
			global $_nav_menu_placeholder, $nav_menu_selected_id;

			$_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;

			?>
			<div class="customlinkdiv" id="balanceboxitemdiv">
				<div class="tabs-panel-active">
					<ul class="categorychecklist">
						<li>
							<input type="hidden" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-type]" value="balances">
							<input type="hidden" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-type_label]" value="<?php echo __( 'Crypto Wallet Balances', 'wallets' ); ?>">

							<input type="hidden" class="menu-item-title" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-title]" value="<?php echo __( 'Balance', 'wallets' ); ?>">
							<input type="hidden" class="menu-item-url" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-url]" value="#">
							<input type="hidden" class="menu-item-classes" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-classes]" value="wallets-nav-balance menu-item-has-children">

							<input type="checkbox" class="menu-item-object-id" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-object-id]" value="<?php echo $_nav_menu_placeholder; ?>" checked="true">
						</li>
					</ul>
				</div>

				<p class="button-controls">
					<span class="add-to-menu">
						<input type="submit"<?php wp_nav_menu_disabled_check( $nav_menu_selected_id ); ?> class="button-secondary right" value="<?php echo esc_attr__( 'Add to menu', 'wallets' ); ?>" name="add-balance-menu-item" id="submit-balanceboxitemdiv">
						<span class="spinner"></span>
					</span>
				</p>
			</div>
			<?php
		}

		public function walker_nav_menu_start_el( $item_output, $item, $depth, $args ) {

			if ( 'balances' == $item->type && current_user_can( Dashed_Slug_Wallets_Capabilities::HAS_WALLETS ) ) {

				ob_start();
				?>
				<a href="#"><?php echo esc_html( $item->title ); ?></a>
				<ul class="sub-menu">
				<?php
					$adapters = apply_filters( 'wallets_api_adapters', array() );
					foreach ( $adapters as $symbol => &$adapter ) :
						try {
							$balance = apply_filters( 'wallets_api_balance', 0, array( 'symbol' => $symbol ) );
						} catch ( Exception $e ) {
							continue;
						}

						$coin_name_str = esc_html( $adapter->get_name() );
						$pattern       = apply_filters( 'wallets_sprintf_pattern_' . $symbol, $adapter->get_sprintf() );
						$balance_str   = esc_html( sprintf( $pattern, $balance ) );
						$icon_url      = apply_filters( 'wallets_coin_icon_url_' . $symbol, $adapter->get_icon_url() );

						?>
						<li class="menu-item">
						<?php echo $args->link_before; ?>
						<a
							href="#"
							class="wallets-menu-icon"
							style="background-image: url(<?php echo esc_attr( $icon_url ); ?>)">
							<?php echo $args->before; ?>
							<span class="wallets-coin-name"><?php echo $coin_name_str; ?></span>
							<span class="wallets-balance"><?php echo $balance_str; ?></span>
							<?php echo $args->after; ?>
						</a>
						<?php echo $args->link_after; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php
				$item_output = ob_get_clean();

			} // end if balances
			return $item_output;
		} // end function walker_nav_menu_start_el

	}

	new Dashed_Slug_Wallets_Frontend_Menu;
}
