<?php

/**
 * Functions used for parsing user values for some shortcode attributes.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

/**
 * Parses a string of transaction category/type slugs.
 *
 * Transactions can have any one of the category/type slugs:
 *
 * - `deposit`
 * - `withdrawal`
 * - `move`
 *
 * Additionally, `all` is used to refer to all three categories/types.
 *
 * Note that these are not real taxonomies, just a post_meta on the posts of `wallets_tx` type.
 *
 * @param string $category_slugs Comma-separated list of category/type slugs.
 * @return array Parsed list of category/type slugs.
 */
function parse_categories( string $category_slugs ): array {
	$return_cats = [];

	foreach ( preg_split( '/\s*,\s*/', trim( $category_slugs ) ) as $category_slug ) {

		switch ( $category_slug ) {
			case 'deposit':
			case 'withdrawal':
			case 'move':
				$return_cats[] = $category_slug;
				break;

			case 'withdraw':
				$return_cats[] = 'withdrawal';
				break;

			case 'all':
				return [ 'all' ];
				break;

			case '':
				break;

			default:
				error_log( "Invalid category '$category_slug' encountered in shortcode. Ignoring." );
				break;
		}
	}

	if ( ! $return_cats ) {
		$return_cats = [ 'all' ];
	}

	if ( in_array( 'deposit', $return_cats ) && in_array( 'withdrawal', $return_cats ) && in_array( 'move', $return_cats ) ) {
		$return_cats = [ 'all' ];
	}

	return array_unique( $return_cats );
}

function parse_tags( string $tags = '' ): array {
	if ( ! $tags ) {
		$tags = [];
	} else {
		$tags = explode( ',', $tags );
		$tags = array_map( 'trim', $tags );
		$tags = array_filter( $tags );
		$tags = array_unique( $tags );
	}

	return $tags;
}

/**
 * Parses shortcode attributes to determine the currency specified, if any.
 *
 * Looks for these attributes in sequence: `currency_id`, `coingecko_id`, `symbol`.
 *
 * @param array $atts The shortcode attruibutes.
 */
function parse_atts_for_currency( array &$atts ): void {

	if ( isset( $atts['currency_id'] ) && is_numeric( $atts['currency_id'] ) ) {
		$atts['currency'] = Currency::load( $atts['currency_id'] );
	}

	elseif ( isset( $atts['coingecko_id'] ) ) {
		$atts['currency'] = get_first_currency_by_coingecko_id( $atts['coingecko_id'] );
	}

	elseif ( isset( $atts['symbol'] ) ) {
		$atts['currency'] = get_first_currency_by_symbol( $atts['symbol'] );
	}

	if ( isset( $atts['currency'] ) && $atts['currency'] instanceof Currency ) {

		$atts['currency_id'] = $atts['currency']->post_id;
		$atts['symbol']      = $atts['currency']->symbol;

	}
}

/**
 * Parses shortcode attributes to determine the user specified, if any.
 *
 * First checks for the user attribute. If it matches a login, email, or slug for a user,
 * it sets the `\WP_User` object in `$atts['user_data']`
 *
 * Alternatively, if the user_id is specified, then the user with that ID is loaded into `user_data`.
 *
 * @param array $atts The shortcode attributes.
 * @throws \Exception If user was not found, or does not have capability `has_wallets`.
 */
function parse_atts_for_user( array &$atts ): void {

	if ( isset( $atts['user'] ) ) {

		foreach( [ 'login', 'slug', 'email' ] as $field ) {
			$atts['user_data'] = get_user_by( $field, $atts['user'] );

			if ( $atts['user_data'] ) {
				$atts['user_id'] = $atts['user_data']->ID;
				break;
			}
		}

		if ( ! ( isset( $atts['user_data'] ) && ( $atts['user_data'] instanceof \WP_User ) ) ) {
			throw new \Exception(
				__(
					'A valid user must be specified using one of the following attributes: user_id, user',
					'wallets'
				)
			);
		}

	} elseif ( isset( $atts['user_id'] ) && $atts['user_id'] ) {
		$atts['user_data'] = get_user_by( 'ID', (int) $atts['user_id'] );

		if ( ! ( isset( $atts['user_data'] ) && ( $atts['user_data'] instanceof \WP_User ) ) ) {
			throw new \Exception(
				__(
					'A valid user must be specified using one of the following attributes: user_id, user',
					'wallets'
				)
			);
		}
	}

	if ( ! ds_user_can( $atts['user_id'], 'has_wallets' ) ) {
		throw new \Exception(
			sprintf(
				'User with ID %d does not have wallets!',
				$atts['user_id']
			)
		);
	}
}

/**
 * Retrieves a template part for this plugin or its extensions.
 *
 * Adapted from bbPress
 *
 * @param string $slug The generic template part.
 * @param ?string $name The specialized template part.
 * @param boolean $load Whether to include the template.
 * @oaram string $plugin_slug The slug of the plugin to load a template for (default: wallets)
 *
 * @return string Full path to the template file.
 * @throws \Exception If the template was not found.
 *
 * @link https://pippinsplugins.com/template-file-loaders-plugins/
 *
 */
function get_template_part( string $slug, ?string $name = null, bool $load = false, string $plugin_slug = 'wallets' ): string {
	do_action( "get_template_part_$slug", $slug, $name );

	$templates = [];
	if ( $name ) {
		$templates[] = "$slug-$name.php";
	}
	$templates[] = "$slug.php";

	/**
	 * Allows template parts to be filtered.
	 *
	 * When frontend UIs are rendered, such as when using the wallet shortcodes,
	 * this loader is invoked. The plugin comes with its own HTML template files.
	 * However, you can copy these templates under your theme or child theme and modify them.
	 *
	 * For example, if you use the shortcode `[wallets_balance template="list"]`,
	 * the slug is "balance", the name is "
	 * For more information about this, see the 5.0.0 release notes or the "Frontend" section
	 * of the accompanying PDF documentation.
	 *
	 * @param string $slug        The name of the template, e.g. `balance` for
	 *                            the `[wallets_balance]` shortcode.
	 * @param string $name        The specialized name of the template variation,
	 *                            e.g. `list` for the `[wallets_balance template="list"]` shortcode.
	 * @param string $plugin_slug The slug of the requesting plugin. For this plugin, this is `wallets`.
	 *
	 * @link https://www.dashed-slug.net/wallets-5-0-0/ Release notes for Bitcoin and Altcoin Wallets `5.0.0`.
	 * @since 5.0.0
	 */
	$templates = (array) apply_filters( 'wallets_get_template_part', $templates, $slug, $name, $plugin_slug );

	$template_file = locate_template( $templates, $load, $plugin_slug );
	if ( $template_file && file_exists( $template_file ) ) {
		if ( is_readable( $template_file ) ) {
			return $template_file;
		} else throw new \Exception( "File $template_file is not readable!" );
	} throw new \Exception( "File $template_file was not found!" );
 }

 function locate_template( array $template_names, bool $load = false, string $plugin_slug = 'wallets' ): ?string {
	$located = null;

	foreach ( (array) $template_names as $template_name ) {

		if ( empty( $template_name ) ) {
			continue;
		}

		$template_name = untrailingslashit( $template_name );

		// Check child theme first
		$candidate_child  = trailingslashit( get_stylesheet_directory() ) . "templates/$plugin_slug/$template_name";
		$candidate_parent = trailingslashit( get_template_directory() ) . "templates/$plugin_slug/$template_name";
		$candidate_plugin = WP_PLUGIN_DIR . "/$plugin_slug/templates/$template_name";

		if ( file_exists( $candidate_child ) ) {
			$located = $candidate_child;
			break;

			// Check parent theme next
		} elseif ( file_exists( $candidate_parent ) ) {
			$located = $candidate_parent;
			break;

			// Check theme compatibility last
		} elseif ( file_exists( $candidate_plugin ) ) {
			$located = $candidate_plugin;
			break;
		}
	}

	if ( $located && ! is_readable( $located ) ) {
		throw new \Exception( "File $located is not readable!" );
	}

	if ( $located && $load ) {
		load_template( $located, false );
	}

	return $located;
 }
