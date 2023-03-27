<?php

/**
 * The fiat currency adapter abstract class.
 *
 * Subclasses can implement various modes of deposit/withdrawal.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

/**
 * The fiat currency adapter is a wallet adapter for defining fiat currencies.
 *
 * This adapter does not communicate with any wallets.
 *
 * @since 6.0.0 Introduced.
 */
abstract class Fiat_Adapter extends Wallet_Adapter {

}
