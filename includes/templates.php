<?php

/**
 * Loads templates in a way that is overridable from themes and child themes. Affects shortcodes and widgets.
 *
 * @since 5.0.0
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Template_Loader' ) ) {
	class Dashed_Slug_Wallets_Template_Loader {

		public function __construct() {
			add_filter( 'wallets_views_dir', array( __CLASS__, 'wallets_views_dir_deprecated' ) );

		}

		public static function wallets_views_dir_deprecated( $dir ) {
			_doing_it_wrong(
				__FUNCTION__,
				'The wallets_views_dir and its associated filters and templating mechanism ' .
				'have been deprecated in favor of the themeable "templates" directory. See the docs for more.',
				'5.0.0'
			);
			return $dir;
		}

		private static $plugin_templates_directory;

		public static function get_plugin_templates_directory() {
			return DSWALLETS_PATH . '/templates';
		}
		/**
		 * Retrieves a template part.
		 *
		 * Adapted from bbPress
		 *
		 * @param string $slug The generic template part.
		 * @param string $name The specialized template part.
		 * @param boolean $load Whether to include the template.
		 * @return string|false Full path to the template file, or false if not found.
		 *
		 * @link https://pippinsplugins.com/template-file-loaders-plugins/
		 *
		 */
		public static function get_template_part( $slug, $name = null, $load = false ) {
			do_action( "get_template_part_$slug", $slug, $name );

			$templates = array();
			if ( $name ) {
				$templates[] = "$slug-$name.php";
			}
			$templates[] = "$slug.php";

			/**
			 * Allows template parts to be filtered.
			 *
			 *
			 * @since 5.0.0
			 */
			$templates = apply_filters( 'wallets_get_template_part', $templates, $slug, $name );

			$template_file = self::locate_template( $templates, $load );
			if ( file_exists( $template_file ) ) {
				if ( is_readable( $template_file ) ) {
					return $template_file;
				} else throw new Exception( "File $template_file is not readable!" );
			} throw new Exception( "File $template_file was not found!" );
		}

		private static function locate_template( $template_names, $load = false ) {
			$located = false;

			foreach ( (array) $template_names as $template_name ) {

				if ( empty( $template_name ) ) {
					continue;
				}

				$template_name = untrailingslashit( $template_name, '/' );

				// Check child theme first
				if ( file_exists( trailingslashit( get_stylesheet_directory() ) . 'templates/wallets/' . $template_name ) ) {
					$located = trailingslashit( get_stylesheet_directory() ) . 'templates/wallets/' . $template_name;
					break;

				// Check parent theme next
				} elseif ( file_exists( trailingslashit( get_template_directory() ) . 'templates/wallets/' . $template_name ) ) {
					$located = trailingslashit( get_template_directory() ) . 'templates/wallets/' . $template_name;
					break;

				// Check theme compatibility last
				} elseif ( file_exists( trailingslashit( self::get_plugin_templates_directory() ) . $template_name ) ) {
					$located = trailingslashit( self::get_plugin_templates_directory() ) . $template_name;
					break;
				}
			}

			if ( ( true == $load ) && $located ) {
				if ( ! is_readable( $located ) ) {
					throw new Exception( "File $located is not readable!" );
				}

				load_template( $located, false );
			}

			return $located;
		}
	}
}

new Dashed_Slug_Wallets_Template_Loader;