<?php

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

/**
 * Helpers for multisite support.
 *
 * If the plugin is on a multisite installation AND is network-activated, then all data is shared across sites (aka blogs).
 * This is how it's done:
 *
 * Options and transients are stored on the main site (usually site_id = 1).
 *
 * Custom post types (Wallets, Currencies, Transactions and Addresses) are stored on the main site (usually site_id = 1).
 *
 * Cron jobs only run on the main site (usually site_id = 1).
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */

/**
 * Whether the wallets plugin is network-active on a multisite installation.
 *
 * @return bool True iff net active on MS.
 */
function is_net_active(): bool {
	if ( ! is_multisite() ) {
		return false;
	}
	return is_plugin_active_for_network( 'wallets/wallets.php' );
}

/**
 * Switch to main site/blog to do DB stuff.
 *
 * This is useful for any function that does DB stuff, and ensures that data is synced between blogs.
 *
 * @return bool Whether the blog was switched.
 */
function maybe_switch_blog(): bool {

	if ( is_net_active() ) {
		switch_to_blog( get_main_site_id() );
		return true;
	}
	return false;
}

/**
 * Switch back from blog 1 to the current blog after doing DB stuff.
 *
 * This is useful for any function that does DB stuff, and ensures that data is synced between blogs.
 *
 * @return bool Whether the blog was switched back.
 */
function maybe_restore_blog(): bool {
	if ( is_net_active() ) {
		restore_current_blog();
		return true;
	}
	return false;
}

/**
 * Creates a new option/value pair related to the plugin.
 *
 * This delegates to the WordPress function add_option. But if the plugin is network-active on a multisite installation,
 * the option is created on the network's main blog. This ensures that settings are global over all blogs.
 *
 * @since 6.0.0 Moved into helpers
 * @since 2.4.0 Added
 * @link https://developer.wordpress.org/reference/functions/add_option/
 * @param string $option The option name.
 * @param mixed $value The option value.
 * @return bool The result of the wrapped function.
 */
function add_ds_option( $option, $value ) {
	maybe_switch_blog();
	$result = add_option( $option, $value );
	maybe_restore_blog();
	return $result;
};

/**
 * Updates an existing option/value pair related to the plugin, or creates the option.
 *
 * This delegates to the WordPress function update_option. But if the plugin is network-active on a multisite installation,
 * the option is updated on the network's main blog. This ensures that settings are global over all blogs.
 *
 * @since 6.0.0 Moved into helpers
 * @since 2.4.0 Added
 * @link https://developer.wordpress.org/reference/functions/update_option/
 * @param string $option The option name.
 * @param mixed $value The option value.
 * @return bool The result of the wrapped function.
 */
function update_ds_option( $option, $value ) {
	maybe_switch_blog();
	$result = update_option( $option, $value );
	maybe_restore_blog();
	return $result;
};

/**
 * Retrieves an option's value.
 *
 * This delegates to the WordPress function get_option. But if the plugin is network-active on a multisite installation,
 * the option is retrieved from the network's main blog. This ensures that settings are global over all blogs.
 *
 * @since 6.0.0 Moved into helpers
 * @since 2.4.0 Added
 * @link https://developer.wordpress.org/reference/functions/get_option/
 * @param string $option The option name.
 * @return mixed The result of the wrapped function.
 */
function get_ds_option( $option, $default = false ) {
	maybe_switch_blog();
	$result = get_option( $option, $default );
	maybe_restore_blog();
	return $result;
};

/**
 * Deletes an option's value.
 *
 * This delegates to the WordPress function delete_option. But if the plugin is network-active on a multisite installation,
 * the option is deleted from the network's main blog. This ensures that settings are global over all blogs.
 *
 * @since 6.0.0 Moved into helpers
 * @since 2.4.0 Added
 * @link https://developer.wordpress.org/reference/functions/delete_option/
 * @param string $option The option name.
 * @return bool The result of the wrapped function.
 */
function delete_ds_option( $option ) {
	maybe_switch_blog();
	$result = delete_option( $option );
	maybe_restore_blog();
	return $result;
};

/**
 * Sets a transient value to be used as cached data.
 *
 * This delegates to the WordPress function set_transient. But if the plugin is network-active on a multisite installation,
 * the transient is saved on the network's main blog. This ensures that cached data are global over all blogs.
 *
 * @since 6.0.0 Saves transient as option with encapsulated expiration time, if transients are broken
 * @since 6.0.0 Moved into helpers
 * @since 2.11.1 Added
 * @link https://codex.wordpress.org/Function_Reference/set_site_transient
 * @link https://codex.wordpress.org/Function_Reference/set_transient
 * @param string $transient The transient name.
 * @param mixed $value The transient value.
 * @param int $expiration Time until expiration in seconds from now, or 0 for never expires.
 * @return bool The result of the wrapped function.
 */
function set_ds_transient( $transient, $value, $expiration = 0 ) {
	maybe_switch_blog();

	$transients_broken = get_ds_option( 'wallets_transients_broken' );

	if ( $transients_broken ) {

		if ( $expiration ) {
			$result = update_option(
				"wt_$transient",
				[
					't' => time() + $expiration,
					'v' => $value,
				]
			);
		} else {
			$result = update_option(
				"wt_$transient",
				[
					'v' => $value,
				]
			);
		}

	} else {
		$result = set_transient( $transient, $value, $expiration );
	}

	maybe_restore_blog();

	return $result;
};

/**
 * Deletes a transient value.
 *
 * This delegates to the WordPress function delete_transient. But if the plugin is network-active on a multisite installation,
 * the transient is deleted from the network's main blog. This ensures that cached data are global over all blogs.
 *
 * @since 6.0.0 Deletes transient from options if transients are broken.
 * @since 6.0.0 Moved into helpers
 * @since 2.11.1 Added
 * @link https://codex.wordpress.org/Function_Reference/delete_site_transient
 * @link https://codex.wordpress.org/Function_Reference/delete_transient
 * @param string $transient The transient name.
 * @return bool True if successful, false otherwise.
 */
function delete_ds_transient( $transient ) {
	maybe_switch_blog();

	$transients_broken = get_ds_option( 'wallets_transients_broken' );

	if ( $transients_broken ) {
		$result = delete_option( "wt_$transient" );
	} else {
		$result = delete_transient( $transient );
	}

	maybe_restore_blog();
	return $result;
};

/**
 * Retrieves cached data that was previously stored in a transient.
 *
 * This delegates to the WordPress function get_transient. But if the plugin is network-active on a multisite installation,
 * the transient is retrieved from the network's main blog. This ensures that cached data are global over all blogs.
 *
 * @since 6.0.0 Retrieves transient from options if transients are broken.
 * @since 6.0.0 Moved into helpers.
 * @since 3.9.2 Will always return false if the option "wallets_transients_broken" is set.
 * @since 2.11.1 Added
 * @link https://codex.wordpress.org/Function_Reference/get_site_transient
 * @link https://codex.wordpress.org/Function_Reference/get_transient
 * @param string $transient The transient name.
 * @param mixed $default The default value to return if transient was not found.
 * @return mixed The result of the wrapped function.
 */
function get_ds_transient( $transient, $default = false ) {
	maybe_switch_blog();

	$transients_broken = get_option( 'wallets_transients_broken' );
	$result = false;

	if ( $transients_broken ) {

		$wrapped_result = get_option( "wt_$transient" );

		if ( $wrapped_result && isset( $wrapped_result['v'] ) ) {

			if ( isset( $wrapped_result['t'] ) && is_numeric( $wrapped_result['t'] ) ) {

				if ( time() <= $wrapped_result['t'] ) {
					$result = $wrapped_result['v'];
				} else {
					delete_option( "wt_$transient" );
				}
			} else {
				$result = $wrapped_result['v'];
			}
		}
	} else {
		$v = get_transient( $transient );
		$result = $v ? $v : $default;
	}

	maybe_restore_blog();

	return false === $result ? $default : $result;
};


function ds_user_can( $user, string $capability, ...$args ) {
	maybe_switch_blog();

	$result = user_can( $user, $capability, ...$args );

	maybe_restore_blog();

	return $result;
}

function ds_current_user_can( string $capability, ...$args ) {
	maybe_switch_blog();

	$result = current_user_can( $capability, ...$args );

	maybe_restore_blog();

	return $result;
}


/*
 * This detects situations with misconfigured server-side caches
 * where transients are not expiring. We set a transient to expire
 * after one minute, storing inside it the current timestamp.
 * If after one minute and ten seconds it has not expired,
 * we set the wallets_transients_broken option so that
 * other transient helpers can override the built-in mechanism.
 */
add_action(
	'init',
	function() {
		$test_value = get_transient( 'wallets_transients_test' );
		if ( $test_value && is_numeric( $test_value ) ) {
			if ( time() > $test_value + 70 ) {
				update_ds_option( 'wallets_transients_broken', 'on' );
				delete_transient( 'wallets_transients_test' );
			}
		} else {
			set_transient(
				'wallets_transients_test',
				time(),
				MINUTE_IN_SECONDS
			);
		}
	}
);
