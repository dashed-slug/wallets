<?php

/**
 * Renders shortcodes.
 *
 * A wallets shortcode begins with `[wallets_` and can have a number of attributes.
 *
 * The optional `template` attribute determines which template is rendered (the "specialized template part" in WordPress theme lingo).
 * Therefore, `[wallets_balance]` renders the template `templates/wallets_balance.php`,
 * while `[wallets_balance template="list"] renders the template `templates/wallets_balance-list.php`.
 *
 * If an error occurs, such as insufficient capabilities, the UI may not render any templates, but instead
 * only display an error message.
 *
 * Error message strings are filterable via the `wallets_ui_text_shortcode_error` filter. e.g.
 *
 *    add_filter( 'wallets_ui_text_shortcode_error', function( $error_message ) {
 *        return '<p>UI cannot be shown!</p>';
 *    }
 *
 * Another attribute is `user_id`. The attributes `user_id` or `user` can be used to specify a user that's different
 * than the current one. In such situations, the UI is "static", i.e. rendered once on the server side and not updated.
 * On the other hand, if the UI displays data about the currently logged user, then
 * the UI is "dynamic", i.e. it is rendered on the frontend after data is loaded via the WP-REST API.
 *
 * @since 2.0.0 Templates now encapsulate associated CSS and JS code inline with the markup.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @phan-suppress PhanTypePossiblyInvalidDimOffset
 */

namespace DSWallets;


// don't load directly
defined( 'ABSPATH' ) || die( -1 );

/**
 * The possible values for the `columns` attribute for the `wallets_transactions` shortcode.
 *
 * @see shortcode_wallets_transactions()
 */
const TRANSACTIONS_COLS = [ 'type', 'tags', 'time', 'currency', 'amount', 'fee', 'address', 'txid', 'comment', 'status', 'user_confirm' ];

/**
 * Force caching plugins to not cache the current page.
 *
 * This works by setting the DONOTCACHEPAGE constant.
 * The constant is respected by W3 Total Cache, WP Super Cache, and possibly other plugins.
 *
 * @throws \Exception If the DONOTCACHEPAGE constant is already set to a falsy value.
 */
function do_not_cache_page() {
	if ( defined( 'DONOTCACHEPAGE' ) ) {
		if ( ! DONOTCACHEPAGE ) {
			global $wp;
			$msg = sprintf(
				'WARNING: Using a wallets_* shortcode which requires disabled caching, but caching is enabled with DONOTCACHEPAGE constant. The UI may not work correctly. Page: %s',
				home_url( $wp->request )
			);
			error_log( $msg );
			throw new \Exception( $msg );
		}
	} else {
		define( 'DONOTCACHEPAGE', true );
	}
}

add_filter(
	'no_texturize_shortcodes',
	function( array $default_no_texturize_shortcodes ) {

		$default_no_texturize_shortcodes[] = 'wallets_balance';
		$default_no_texturize_shortcodes[] = 'wallets_rates';
		$default_no_texturize_shortcodes[] = 'wallets_status';
		$default_no_texturize_shortcodes[] = 'wallets_deposit';
		$default_no_texturize_shortcodes[] = 'wallets_total_balances';
		$default_no_texturize_shortcodes[] = 'wallets_account_value';
		$default_no_texturize_shortcodes[] = 'wallets_move';
		$default_no_texturize_shortcodes[] = 'wallets_withdraw';
		$default_no_texturize_shortcodes[] = 'wallets_fiat_withdraw';
		$default_no_texturize_shortcodes[] = 'wallets_fiat_deposit';
		$default_no_texturize_shortcodes[] = 'wallets_transactions';

		return $default_no_texturize_shortcodes;
	}
);

// To render JS code in templates, we need it to not be passed through wp_texturize,
// because it escapes characters such as <, >, and &.
//
// Unfortunately, the filter `no_texturize_shortcodes` does not work in Gutenberg due to:
// https://github.com/WordPress/gutenberg/issues/37754
//
// We therefore have to disable wp_texturize globally. Hopefully this will not cause too many issues with
// other themes/plugins, until this is fixed in WordPress.

if ( wp_is_block_theme() ) {
	add_filter( 'run_wptexturize', '__return_false' ); // TODO: remove this once #37754 is fixed
}


add_action(
	'wp',
	function() {

		if ( ! ( is_page() || get_ds_option( 'wallets_shortcodes_in_posts' ) ) ) {
			return;
		}

		/**
		 * The `[wallets_balance]` shortcode displays user balances.
		 *
		 * Additional templates for this shortcode:
		 * - `[wallets_balance template="list"]` - Displays all the balances as a list.
		 * - `[wallets_balance template="textonly"]` - Displays a balance for the specified currency as a single <span> that can be embedded in text.
		 *
		 * The current user, or the specified user must have the following capabilities:
		 * - `has_wallets`
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[user_id]`               WordPress user ID for the user whose balances to display.
		 *   - `[user]`                  Can be either a `login`, `slug`, or `email` of the user whose balances to display.
		 *   - `[symbol]`                Ticker symbol of the currency to display, or to set as default in the UI.
		 *   - `[currency_id]`           `post_id` of the currency to display, or to set as default in the UI.
		 *   - `[coingecko_id]`          CoinGecko ID of the currency to display, or to set as default in the UI.
		 *   - `[template]`              Specialized template part.
		 *   - `[show_zero_balances]`    Whether to show zero balances by default. Applies to the list template.
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_balance`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_balance( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'user_id'            => get_current_user_id(), // The ID of the user whose data to show.
				'user'               => null, // The login name, slug, or email of the user whose data to show.
				'symbol'             => null, // The symbol of the currency whose data to show.
				'currency_id'        => null, // The currency_id of the currency whose data to show.
				'coingecko_id'       => null, // The coingecko_id of the currency whose data to show.
				'template'           => null, // The specialized template part.
				'show_zero_balances' => false, // For balance list, whether to show zero balances or not.
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$defaults['show_zero_balances'] = (bool) $defaults['show_zero_balances'];

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();

			try {
				do_not_cache_page();

				parse_atts_for_user( $atts );

				parse_atts_for_currency( $atts );

				// If the current user is not the user to display, we
				// are going to load the data statically, and disable the
				// reload button.
				$atts['static'] = get_current_user_id() != $atts['user_id'];

				include $template_file;

				wp_enqueue_script( 'wallets-front' );

			} catch ( \Exception $e ) {
				ob_end_clean();

				/**
				 * Error message pattern for shortcodes.
				 *
				 * If something goes wrong while trying to render a shortcode,
				 * the error message is passed through this sprintf() pattern.
				 *
				 * See the translator notes below for the strings that are inserted into this pattern.
				 *
				 * @since 6.0.0 Introduced.
				 *
				 * @param string $error_message_pattern The error message pattern shown when a shortcode cannot be rendered. Makes four string substitutions (see below).
				 *
				 */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						(string) esc_attr( $template_slug ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		}
		add_shortcode( 'wallets_balance', __NAMESPACE__ . '\shortcode_wallets_balance' );

		/**
		 * The `[wallets_rates]` shortcode displays exchange rates for online currencies.
		 *
		 * Additional templates for this shortcode:
		 * - (none)
		 *
		 * The current user, or the specified user must have the following capabilities:
		 * - (none, public shortcode)
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[decimals]`              Exchange rates will be shown with this many decimal digitss.
		 *   - `[template]`              Specialized template part.
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_rates`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_rates( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'decimals' => 8, // how to show exchange rates
				'template' => null, // The specialized template part.
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$atts['decimals'] = max( 0, min( 16, absint( $atts['decimals'] ) ) );

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();
			try {
				do_not_cache_page();

				include $template_file;

				wp_enqueue_script( 'wallets-front' );

			} catch ( \Exception $e ) {
				ob_end_clean();

				/** This filter is documented in this file. See above. */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						(string) esc_attr( $template_slug ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		};

		add_shortcode( 'wallets_rates', __NAMESPACE__ . '\shortcode_wallets_rates' );

		/**
		 * The `[wallets_status]` shortcode displays the wallet status (online/offline) for the site's currencies.
		 *
		 * Additional templates for this shortcode:
		 * - (none)
		 *
		 * The current user, or the specified user must have the following capabilities:
		 * - (none, public shortcode)
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[template]`              Specialized template part.
		 *
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_status`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_status( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'template' => null, // The specialized template part.
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();
			try {
				do_not_cache_page();

				include $template_file;

				wp_enqueue_script( 'wallets-front' );

			} catch ( \Exception $e ) {
				ob_end_clean();

				/** This filter is documented in this file. See above. */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						(string) esc_attr( $template_slug ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		};
		add_shortcode( 'wallets_status', __NAMESPACE__ . '\shortcode_wallets_status' );

		/**
		 * The `[wallets_deposit]` shortcode displays a UI for the user to see their deposit addresses and create new ones.
		 *
		 * The deposit addresses for each currency are shown for the specified user.
		 *
		 * Depending on the currency, the deposit addresses may be accompanyied by extra information such as Payment ID, Memo, etc.
		 *
		 * The deposit addresses and any extra information are also rendered as QR Codes, to be scanned by a phone.
		 *
		 * The user can request to create a new deposit address for a currency, and optionally label that address.
		 *
		 * A user may have multiple deposit addresses per currency, up to a limit.
		 *
		 * Additional templates for this shortcode:
		 * - (none)
		 *
		 * The current user, or the specified user must have the following capabilities:
		 * - `has_wallets`
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[user_id]`               WordPress user ID for the user whose balances to display.
		 *   - `[user]`                  Can be either a `login`, `slug`, or `email` of the user whose balances to display.
		 *   - `[symbol]`                Ticker symbol of the currency to display, or to set as default in the UI.
		 *   - `[currency_id]`           `post_id` of the currency to display, or to set as default in the UI.
		 *   - `[coingecko_id]`          CoinGecko ID of the currency to display, or to set as default in the UI.
		 *   - `[template]`              Specialized template part.
		 *   - `[qrsize]`                Size, in css notation, of the qrcode. Can use units such as `px`, `pt`, etc.
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_deposit`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_deposit( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'user_id'      => get_current_user_id(), // The ID of the user whose data to show.
				'user'         => null, // The login name, slug, or email of the user whose data to show.
				'symbol'       => null, // The symbol of the currency whose data to show.
				'currency_id' => null, // The currency_id of the currency whose data to show.
				'coingecko_id' => null, // The coingecko_id of the currency whose data to show.
				'template'     => null, // The specialized template part.
				'qrsize'       => 'inherit',
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();
			try {
				do_not_cache_page();

				parse_atts_for_user( $atts );

				parse_atts_for_currency( $atts );

				// If the current user is not the user to display, we
				// are going to load the data statically, and disable the
				// reload button.
				$atts['static'] = get_current_user_id() != $atts['user_id'];

				include $template_file;

				wp_enqueue_script( 'sweetalert2' );
				wp_enqueue_script( 'jquery-qrcode' );
				wp_enqueue_script( 'wallets-front' );

			} catch ( \Exception $e ) {
				ob_end_clean();

				/** This filter is documented in this file. See above. */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						(string) esc_attr( $template_slug ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		};
		add_shortcode( 'wallets_deposit', __NAMESPACE__ . '\shortcode_wallets_deposit' );

		/**
		 * The `[wallets_total_balances]` shortcode displays total balances over all users for each coin.
		 *
		 * This shortcode is always rendered on the server-side.
		 *
		 * Additional templates for this shortcode:
		 * - (none)
		 *
		 * The current user, or the specified user must have the following capabilities:
		 * - (none, public shortcode)
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[show_zero_balances]`    Set to a truthy value to show zero balances by default on page load.
		 *   - `[template]`              Specialized template part.
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_total_balances`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_total_balances( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'show_zero_balances' => false, // For balance list, whether to show zero balances or not.
				'template'           => null, // The specialized template part.
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$defaults['show_zero_balances'] = (bool) $defaults['show_zero_balances'];

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();
			try {
				do_not_cache_page();

				include $template_file;

				wp_enqueue_script( 'wallets-front' );

			} catch ( \Exception $e ) {
				ob_end_clean();

				/** This filter is documented in this file. See above. */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						(string) esc_attr( $template_slug ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		};
		add_shortcode( 'wallets_total_balances', __NAMESPACE__ . '\shortcode_wallets_total_balances' );

		/**
		 * The `[wallets_account_value]` shortcode displays the total value of a user's account.
		 *
		 * Additional templates for this shortcode:
		 * - `[wallets_account_value template="textonly"]` - Displays the total account value as a single <span> that can be embedded in text.
		 *
		 * The current user, or the specified user must have the following capabilities:
		 * - `has_wallets`
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[user_id]`               WordPress user ID for the user whose balances to display.
		 *   - `[user]`                  Can be either a `login`, `slug`, or `email` of the user whose balances to display.
		 *   - `[template]`              Specialized template part.
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_account_value`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_account_value( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'user_id'            => get_current_user_id(), // The ID of the user whose data to show.
				'user'               => null, // The login name of the user whose data to show.
				'template'           => null, // The specialized template part.
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();

			try {
				do_not_cache_page();

				parse_atts_for_user( $atts );

				// If the current user is not the user to display, we
				// are going to load the data statically, and disable the
				// reload button.
				$atts['static'] = get_current_user_id() != $atts['user_id'];

				include $template_file;

				wp_enqueue_script( 'wallets-front' );

			} catch ( \Exception $e ) {
				ob_end_clean();

				/** This filter is documented in this file. See above. */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						(string) esc_attr( $template_slug ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		};
		add_shortcode( 'wallets_account_value', __NAMESPACE__ . '\shortcode_wallets_account_value' );

		/**
		 * The `[wallets_move]` shortcode displays a UI for transferring currencies to other users on the site. (off-chain transactions)
		 *
		 * Additional templates for this shortcode:
		 * - (none)
		 *
		 * The current user must have the following capabilities:
		 * - `has_wallets`
		 * - `send_funds_to_user`
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[symbol]`                Ticker symbol of the currency to display, or to set as default in the UI.
		 *   - `[currency_id]`           `post_id` of the currency to display, or to set as default in the UI.
		 *   - `[coingecko_id]`          CoinGecko ID of the currency to display, or to set as default in the UI.
		 *   - `[template]`              Specialized template part.
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_move`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_move( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'symbol'       => null, // The symbol of the currency whose data to show.
				'currency_id'  => null, // The currency_id of the currency whose data to show.
				'coingecko_id' => null, // The coingecko_id of the currency whose data to show.
				'template'     => null, // The specialized template part.
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();
			try {
				do_not_cache_page();

				if ( ! ds_current_user_can( 'has_wallets' ) ) {
					throw new \Exception(
						(string) __(
							'User does not have the has_wallets capability!',
							'wallets'
						)
					);
				}

				if ( ! ds_current_user_can( 'send_funds_to_user' ) ) {
					throw new \Exception(
						__(
							'User does not have the send_funds_to_user capability!',
							'wallets'
						)
					);
				}

				parse_atts_for_currency( $atts );

				include $template_file;

				wp_enqueue_script( 'sweetalert2' );
				wp_enqueue_script( 'wallets-front' );

			} catch ( \Exception $e ) {
				ob_end_clean();

				/** This filter is documented in this file. See above. */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						(String) esc_attr( $template_slug ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		};
		add_shortcode( 'wallets_move', __NAMESPACE__ . '\shortcode_wallets_move' );

		/**
		 * The `[wallets_withdraw]` shortcode displays a UI for submitting cryptocurrency withdrawals.
		 *
		 * Additional templates for this shortcode:
		 * - (none)
		 *
		 * The current user, or the specified user must have the following capabilities:
		 * - `has_wallets`
		 * - `withdraw_funds_from_wallet`
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[symbol]`                Ticker symbol of the currency to display, or to set as default in the UI.
		 *   - `[currency_id]`           `post_id` of the currency to display, or to set as default in the UI.
		 *   - `[coingecko_id]`          CoinGecko ID of the currency to display, or to set as default in the UI.
		 *   - `[template]`              Specialized template part.
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_withdraw`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_withdraw( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'symbol'       => null, // The symbol of the currency whose data to show.
				'currency_id'  => null, // The currency_id of the currency whose data to show.
				'coingecko_id' => null, // The coingecko_id of the currency whose data to show.
				'template'     => null, // The specialized template part.
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();
			try {
				do_not_cache_page();

				if ( ! ds_current_user_can( 'has_wallets' ) ) {
					throw new \Exception(
						(string) __(
							'User does not have the has_wallets capability!',
							'wallets'
							)
						);
				}

				if ( ! ds_current_user_can( 'withdraw_funds_from_wallet' ) ) {
					throw new \Exception(
						(string) __(
							'User does not have the withdraw_funds_from_wallet capability!',
							'wallets'
						)
					);
				}

				parse_atts_for_currency( $atts );

				include $template_file;

				wp_enqueue_script( 'sweetalert2' );
				wp_enqueue_script( 'wallets-front' );
				wp_enqueue_script( 'jsqrcode' );

			} catch ( \Exception $e ) {
				ob_end_clean();

				/** This filter is documented in this file. See above. */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						(string) esc_attr( $template_slug ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		};
		add_shortcode( 'wallets_withdraw', __NAMESPACE__ . '\shortcode_wallets_withdraw' );

		/**
		 * The `[wallets_fiat_withdraw]` shortcode displays a UI for submitting fiat bank withdrawal requests.
		 *
		 * For convenience, the withdrawal fields are populated with the last withdrawal request submitted by the same user.
		 *
		 * Additional templates for this shortcode:
		 * - (none)
		 *
		 * The current user, or the specified user must have the following capabilities:
		 * - `has_wallets`
		 * - `withdraw_funds_from_wallet`
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[user_id]`               WordPress user ID for the user whose balances to display.
		 *   - `[symbol]`                Ticker symbol of the currency to display, or to set as default in the UI.
		 *   - `[currency_id]`           `post_id` of the currency to display, or to set as default in the UI.
		 *   - `[user]`                  Can be either a `login`, `slug`, or `email` of the user whose balances to display.
		 *   - `[template]`              Specialized template part.
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_fiat_withdraw`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_fiat_withdraw( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'user_id'      => get_current_user_id(), // The ID of the user whose data to show.
				'user'         => null, // The login name of the user whose data to show.
				'symbol'       => null, // The symbol of the currency whose data to show.
				'currency_id'  => null, // The currency_id of the currency whose data to show.
				'template'     => null, // The specialized template part.
				'validation'   => '', // Set to "off" to disable all validation for this input.
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();
			try {
				do_not_cache_page();

				parse_atts_for_user( $atts );

				if ( ! ds_user_can( $atts['user_id'], 'withdraw_funds_from_wallet' ) ) {
					throw new \Exception(
						(string) __(
							'User with ID %d cannot initiate withdrawals!',
							'wallets'
						)
					);
				}

				// If the current user is not the user to display, we
				// are going to load the data statically, and disable the
				// reload button.
				$atts['static'] = get_current_user_id() != $atts['user_id'];

				parse_atts_for_currency( $atts );

				include $template_file;

				wp_enqueue_script( 'sweetalert2' );
				wp_enqueue_script( 'iban' );
				wp_enqueue_script( 'wallets-front' );

			} catch ( \Exception $e ) {
				ob_end_clean();

				/** This filter is documented in this file. See above. */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				/** This filter is documented in this file. See above. */
				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						(string) esc_attr( $template_slug ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		};
		add_shortcode( 'wallets_fiat_withdraw', __NAMESPACE__ . '\shortcode_wallets_fiat_withdraw' );

		/**
		 * The `[wallets_fiat_deposit]` shortcode displays a UI with the correct fiat bank deposit details for each currency.
		 *
		 * The bank deposit details must be set by the admin for each fiat currency:
		 *
		 * - Go to "Admin" &rarr; "Wallets" &rarr; and click "Add New".
		 * - In the title field, set a name for your wallet: "(Your wallet)".
		 * - Set the "Wallet adapter" to the `Bank_Fiat_Adapter`, check "Wallet Enabled", and click "Update".
		 * - Go to "Admin" &rarr; "Currencies" &rarr; "(Your fiat currency)" and click "Edit".
		 * - Set "Wallet" to "(your wallet)" and click "Update".
		 * - Go to "Admin" &rarr; "Wallets" &rarr; "(Your wallet)".
		 * - For each fiat currency connected to this wallet, set the bank deposit details that the user can use to deposit.
		 *
		 * Additional templates for this shortcode:
		 * - (none)
		 *
		 * The current user, or the specified user must have the following capabilities:
		 * - `has_wallets`
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[symbol]`                Ticker symbol of the currency to display, or to set as default in the UI.
		 *   - `[currency_id]`           `post_id` of the currency to display, or to set as default in the UI.
		 *   - `[coingecko_id]`          CoinGecko ID of the currency to display, or to set as default in the UI.
		 *   - `[template]`              Specialized template part.
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_deposit`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_fiat_deposit( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'symbol'       => null, // The symbol of the currency whose data to show.
				'currency_id'  => null, // The currency_id of the currency whose data to show.
				'coingecko_id' => null, // The coingecko_id of the currency whose data to show.
				'template'     => null, // The specialized template part.
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();

			try {
				do_not_cache_page();

				parse_atts_for_currency( $atts );

				include $template_file;

				wp_enqueue_script( 'wallets-front' );

			} catch ( \Exception $e ) {
				ob_end_clean();

				/** This filter is documented in this file. See above. */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				/** This filter is documented in this file. See above. */
				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
				sprintf(
					$error_message_pattern,
					(string) esc_attr( $template_slug ),
					esc_html( $atts['template'] ? $atts['template'] : 'default' ),
					esc_html( $template_file ),
					esc_html( $e->getMessage() )
				);
			}
			return ob_get_clean();
		};
		add_shortcode( 'wallets_fiat_deposit', __NAMESPACE__ . '\shortcode_wallets_fiat_deposit' );

		/**
		 * The `[wallets_transactions]` shortcode displays a table UI of a user's past and pending transactions.
		 *
		 * Additional templates for this shortcode:
		 * - `[wallets_transactions template="rows"]` - Displays the same information in list form rather than table form.
		 *
		 * The current user, or the specified user must have the following capabilities:
		 * - `has_wallets`
		 * - `list_wallet_transactions`
		 *
		 * @param string[] $atts The attributes passed to the shortcode.
		 *   - `[user_id]`               WordPress user ID for the user whose balances to display.
		 *   - `[user]`                  Can be either a `login`, `slug`, or `email` of the user whose balances to display.
		 *   - `[symbol]`                Ticker symbol of the currency to display, or to set as default in the UI.
		 *   - `[currency_id]`           `post_id` of the currency to display, or to set as default in the UI.
		 *   - `[coingecko_id]`          CoinGecko ID of the currency to display, or to set as default in the UI.
		 *   - `[columns]`               A comma-separated list of columns to display in the transactions table UI. See {@link namespaces/dswallets.html#constant_TRANSACTIONS_COLS column slugs}
		 *   - `[rowcount]`              How many rows to show per page. Default 10.
		 *   - `[categories]`            Optionally filter txs by this comma-separated list containing any of: `deposit`, `move`, `withdrawal`, `all`.
		 *   - `[tags]`                  Optionally filter txs by this comma-separated list of transaction tags (taxonomy: `wallets_tx_tags`)
		 *   - `[template]`              Specialized template part.
		 *
		 * @param string $content For nested shortcodes, the enclosed text. Should be empty for wallets shortcodes.
		 * @param string $tag The tag of the shortcode, in this case the string `wallets_transactions`.
		 * @return string The HTML output, including any templates.
		 */
		function shortcode_wallets_transactions( $atts, string $content, string $tag ): string {

			$template_slug = preg_replace( '/^wallets_/', '', $tag );

			$defaults = [
				'user_id'      => get_current_user_id(), // The ID of the user whose data to show.
				'user'         => null, // The login name of the user whose data to show.
				'symbol'       => null, // The symbol of the currency whose data to show.
				'currency_id'  => null, // The currency_id of the currency whose data to show.
				'coingecko_id' => null, // The coingecko_id of the currency whose data to show.
				'columns'      => TRANSACTIONS_COLS, // which columns to show (applies to table of default template only)
				'rowcount'     => 10, // how many rows to display per page by default
				'categories'   => 'all', // comma separated list containing any of: deposit, move, withdrawal, all
				'tags'         => '',    // term slugs from from wallets_tx_tags custom taxonomy, empty means all
				'template'     => null, // The specialized template part.
			];

			$atts = shortcode_atts(
				$defaults,
				$atts,
				"wallets_$template_slug"
			);

			$template_file = get_template_part( $template_slug, $atts['template'] );

			ob_start();

			try {
				do_not_cache_page();

				parse_atts_for_user( $atts );

				if ( ! ds_current_user_can( 'list_wallet_transactions' ) ) {
					throw new \Exception(
						(string) __(
							'User with ID %d does not have the list_wallet_transactions capability!',
							'wallets'
						)
					);
				}

				parse_atts_for_currency( $atts );

				// If the current user is not the user to display, we
				// are going to load the data statically, and disable the
				// reload button.
				$atts['static'] = get_current_user_id() != $atts['user_id'];

				if ( is_string( $atts['columns'] ) ) {
					$columns = explode( ',', $atts['columns'] );
					if ( ! $columns ) {
						$columns = [];
					}
					$columns = array_map( 'trim', $columns );
					$columns = array_filter( $columns );
					$columns = array_unique( $columns );
					$columns = array_intersect( $columns, TRANSACTIONS_COLS );
					if ( ! $columns ) {
						$columns = TRANSACTIONS_COLS;
					}
					$atts['columns'] = $columns;
				}

				$atts['rowcount'] = max( 1, absint( $atts['rowcount'] ) );

				$atts['categories'] = parse_categories( $atts['categories'] );

				$atts['tags'] = parse_tags( $atts['tags'] );

				include $template_file;

				wp_enqueue_script( 'wallets-front' );
				wp_enqueue_script( 'momentjs' );
				wp_enqueue_script( 'momentjs-locales' );


			} catch ( \Exception $e ) {
				ob_end_clean();

				/** This filter is documented in this file. See above. */
				$error_message_pattern = (string) apply_filters(
					'wallets_ui_text_shortcode_error',
					/* translators: %1$s is the shortcode tag, %2$s is the matched template name for the shortcode, %3$s is the full path to the template file, %4$s is the error message. */
					__(
						'Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s',
						'wallets-front'
					)
				);

				$error_message_pattern = "<div class=\"dashed-slug-wallets %1\$s error\">$error_message_pattern</div>";

				return
					sprintf(
						$error_message_pattern,
						(string) esc_attr( $template_slug ),
						esc_html( $atts['template'] ? $atts['template'] : 'default' ),
						esc_html( $template_file ),
						esc_html( $e->getMessage() )
					);
			}
			return ob_get_clean();
		};
		add_shortcode( 'wallets_transactions', __NAMESPACE__ . '\shortcode_wallets_transactions' );

	}
);
