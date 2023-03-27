<?php

namespace DSWallets;


/**
 * This makes a new menu item available to the menu editor.
 * You can now show the user balances as part of your menu.
 *
 * @since 2.5.0
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );


add_action(
	'init',
	function() {
		add_filter(
			'walker_nav_menu_start_el',
			function( $item_output, $item, $depth, $args ) {

				if ( 'balances' == $item->type ) {

					ob_start();
					?>
					<a href="#"><?php esc_html_e( $item->title ); ?></a>
					<ul class="sub-menu">
					<?php
						$balances = get_all_balances_assoc_for_user();

						foreach ( $balances as $currency_id => $balance ):
							try {
								$currency = Currency::load( $currency_id );
							} catch ( \Exception $e ) {
								continue;
							}

							?>
							<li class="menu-item">
							<?php echo $args->link_before; ?>
							<a
								href="#"
								class="wallets-menu-icon"
								style="background-image: url(<?php esc_attr_e( (string) $currency->icon_url ); ?>)">
								<?php echo $args->before; ?>
								<span class="wallets-coin-name wallets-currency-name"><?php esc_html_e( (string) $currency->name ); ?></span>
								<span class="wallets-balance"><?php esc_html_e( sprintf( (string) $currency->pattern, $balance * 10 ** - $currency->decimals ) ); ?></span>
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
			},
			1,
			4
		);
	}
);

add_action(
	'wp_after_admin_bar_render', // we want this to run after admin_enqueue_scripts
	function() {
		if ( ds_current_user_can( 'manage_wallets' ) ) {
			global $pagenow;
			if ( 'nav-menus.php' !== $pagenow ) {
				return;
			}

			wp_enqueue_script( 'wallets-admin-menu-item' );

			add_filter(
				'wp_setup_nav_menu_item',
				function( $menu_item ) {

					if ( isset( $menu_item->type ) && 'balances' == $menu_item->type ) {
						$menu_item->type_label = (string) __( 'Wallet balances', 'wallets' );
					}
					return $menu_item;
				},
				10,
				1
			);

			add_meta_box(
				'wallets_nav_balance_box_item_meta_box',
				(string) __( 'Bitcoin and Altcoin Wallets balances', 'wallets' ),
				function() {
					global $_nav_menu_placeholder, $nav_menu_selected_id;

					$_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;

					?>
					<div class="customlinkdiv" id="balanceboxitemdiv">
						<div class="tabs-panel-active">
							<ul class="categorychecklist">
								<li>
									<input type="hidden" name="menu-item[<?php echo $_nav_menu_placeholder ?? ''; ?>][menu-item-type]" value="balances">
									<input type="hidden" name="menu-item[<?php echo $_nav_menu_placeholder ?? ''; ?>][menu-item-type_label]" value="<?php _e( 'Crypto Wallet Balances', 'wallets' ); ?>">

									<input type="hidden" class="menu-item-title" name="menu-item[<?php echo $_nav_menu_placeholder ?? ''; ?>][menu-item-title]" value="<?php _e( 'Balance', 'wallets' ); ?>">
									<input type="hidden" class="menu-item-url" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-url]" value="#">
									<input type="hidden" class="menu-item-classes" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-classes]" value="wallets-nav-balance menu-item-has-children">

									<input type="checkbox" class="menu-item-object-id" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-object-id]" value="<?php echo $_nav_menu_placeholder ?? ''; ?>" checked="checked">
								</li>
							</ul>
						</div>

						<p class="button-controls">
							<span class="add-to-menu">
								<input type="submit"<?php wp_nav_menu_disabled_check( $nav_menu_selected_id ); ?> class="button-secondary right" value="<?php esc_attr_e( 'Add to menu', 'wallets' ); ?>" name="add-balance-menu-item" id="submit-balanceboxitemdiv">
								<span class="spinner"></span>
							</span>
						</p>
					</div>
					<?php
				},
				'nav-menus',
				'side',
				'low'
			);
		}
	}
);

// customizer
add_filter(
	'customize_nav_menu_available_item_types',
	function( $item_types ) {
		$item_types[] = array(
			'title'  => __( 'Wallet balances', 'wallets' ),
			'type'   => 'balances',
			'object' => 'wallets_nav_balances_box',
		);
		return $item_types;
	},
	10,
	1
);

add_filter(
	'customize_nav_menu_available_items',
	function( $items, $type, $object, $page ) {
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
	},
	10,
	4
);



