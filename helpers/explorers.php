<?php

/**
 * Block explorer URI pattern defaults.
 *
 * Some defaults are provided for some well-known coins.
 * The defaults are provided via the filters:
 * - `wallets_explorer_uri_add_SYMBOL`
 * - `wallets_explorer_uri_tx_SYMBOL`
 *
 * ...where SYMBOL is the ticker symbol for the currency.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

/**
 * We attach a "from" GET parameter with this value to all default block explorer URIs.
 *
 * If you need to override this, hook to the following filters and pass your own referral id.
 *
 * @internal
 * @link https://blockchair.com/partnerships/howto
 */
const BLOCKCHAIR_REFERRAL_SLUG = 'dashed-slug-wallets';


add_filter( 'wallets_explorer_uri_add_BTC', function( $address ) {
	return $address ? $address : 'https://blockchair.com/bitcoin/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_BTC', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/bitcoin/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_BCH', function( $address ) {
	return $address ? $address : 'https://blockchair.com/bitcoin-cash/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_BCH', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/bitcoin-cash/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_ETH', function( $address ) {
	return $address ? $address : 'https://blockchair.com/ethereum/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_ETH', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/ethereum/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_LTC', function( $address ) {
	return $address ? $address : 'https://blockchair.com/litecoin/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_LTC', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/litecoin/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_BSV', function( $address ) {
	return $address ? $address : 'https://blockchair.com/bitcoin-sv/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_BSV', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/bitcoin-sv/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_DOGE', function( $address ) {
	return $address ? $address : 'https://blockchair.com/dogecoin/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_DOGE', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/dogecoin/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_DASH', function( $address ) {
	return $address ? $address : 'https://blockchair.com/dash/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_DASH', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/dash/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_XRP', function( $address ) {
	return $address ? $address : 'https://blockchair.com/ripple/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_XRP', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/ripple/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_GRS', function( $address ) {
	return $address ? $address : 'https://blockchair.com/groestlcoin/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_GRS', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/groestlcoin/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_XLM', function( $address ) {
	return $address ? $address : 'https://blockchair.com/stellar/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_XLM', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/stellar/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_EOS', function( $address ) {
	return $address ? $address : 'https://blockchair.com/eos/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_EOS', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/eos/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_ADA', function( $address ) {
	return $address ? $address : 'https://blockchair.com/cardano/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_ADA', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/cardano/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_XTZ', function( $address ) {
	return $address ? $address : 'https://blockchair.com/tezos/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_XTZ', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/tezos/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_add_ZEC', function( $address ) {
	return $address ? $address : 'https://blockchair.com/zcash/address/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

add_filter( 'wallets_explorer_uri_tx_ZEC', function( $txid ) {
	return $txid ? $txid : 'https://blockchair.com/zcash/transaction/%s?from=' . BLOCKCHAIR_REFERRAL_SLUG;
} );

