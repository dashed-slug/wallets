=== Bitcoin and Altcoin Wallets ===
Contributors: dashedslug
Donate link: https://flattr.com/profile/dashed-slug
Tags: wallet, bitcoin, cryptocurrency, altcoin, coin, money, e-money, e-cash, deposit, withdraw, account, API
Requires at least: 3.8
Tested up to: 4.7.3
Stable tag: 2.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Turn your blog into a bank: Let your users deposit, withdraw, and transfer bitcoins and altcoins on your site.

== Description ==

https://www.youtube.com/watch?v=_dbkKHhEzRQ

= At a glance =

[Bitcoin and Altcoin Wallets](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin)
is a FREE WordPress plugin by [dashed-slug](https://dashed-slug.net).

It enables financial transactions on your site via Bitcoins and other cryptocurrencies.

= Bitcoin and Altcoin Wallets FREE plugin overview =

This is the *core plugin* that takes care of *basic accounting functionality*:

- **Accounting for your users.** Data is held on tables in your MySQL database.
- **A financial transaction [PHP API](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/php-api/)**:
  Calls that let the logged in user handle their cryptocurrencies.
- **A [JSON API](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/json-api/)**: JSON requests of the above, for logged in users.
- **[Simple shortcodes](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/shortcodes/)**:
  These let you display frontend UI elements to let logged-in users perform the following common tasks:
  - deposit from the blockchain,
  - withdraw to an external blockchain address,
  - transfer funds to other users (on-site transactions that bypass the blockchain),
  - view a history of past transactions
- **Widgets** the same UI elements available via shortcodes can also be used as widgets in your theme.
- Configure who has a wallet and who does what using WordPress capabilities.
- Configure e-mail notifications for users.
- **Backup and restore transactions**: An **import/export** functionality to backup transactions to and from
  [CSV](https://en.wikipedia.org/wiki/Comma-separated_values) files.
- **Extensible architecture**
  - Easily install coin adapter plugins to use other cryprocurrencies besides Bitcoin.
  - Easily install extension plugins that talk to the PHP API to provide additional functionality such as payment gateways.

= Premium plugin extensions available today =

Registered [dashed-slug](https://www.dashed-slug.net) members enjoy unlimited access to all the premium extensions to
this plugin (as well as extensions to the [SVG Logo and Text Effects](https://wordpress.org/plugins/slate/) FREE WordPress plugin).

Here are all the currently available extensions to the Bitcoin and Altcoin Wallets FREE WordPress plugin:

- [WooCommerce Cryptocurrency Payment Gateway extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/woocommerce-cryptocurrency-payment-gateway-extension/)
- [Feathercoin adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/feathercoin-adapter-extension/)
- [Litecoin Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/litecoin-adapter-extension/)
- [block.io Cloud Wallet Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/block-io-cloud-wallet-adapter-extension/)
- [Events Manager Cryptocurrency Payment Gateway extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/events-manager-cryptocurrency-payment-gateway-extension/)

= And here's a preview of what is to come: =

- **[More payment gateways](https://en.wikipedia.org/wiki/Payment_gateway)** for spending balances on common e-commerce plugins.
- A plugin that will let **users reward (tip) authors** for high-quality content.
- Plugins that **[reward user engagement](https://en.wikipedia.org/wiki/Gamification)**.
- An **[ad exchange](https://en.wikipedia.org/wiki/Ad_exchange)** plugin to enable administrators to sell ad spaces and
  to let advertisers bid for these ad spaces.
- **[Faucets](https://en.wikipedia.org/wiki/Bitcoin_faucet)**
- **Currency exchanges**
- etc.

**The dashed-slug.net development is driven by your feedback. Send in your feature requests today.**

= follow the slime =

The dashed-slug is a social slug:

- Facebook: [https://www.facebook.com/dashedslug](https://www.facebook.com/dashedslug)
- Google+: [https://plus.google.com/103549774963556626441](https://plus.google.com/103549774963556626441)
- RSS feed: [https://www.dashed-slug.net/category/news/feed](https://www.dashed-slug.net/category/news/feed)
- Youtube channel: [https://www.youtube.com/channel/UCZ1XhSSWnzvB2B_-Cy1tTjA](https://www.youtube.com/channel/UCZ1XhSSWnzvB2B_-Cy1tTjA)

== Installation ==

= Overview =

*The installation for the plugin itself is the same as for any WordPress plugin.
Additionally, **you will have to install and maintain a Bitcoin daemon on your server**.
This will typically require SSH access and some basic knowledge of UNIX/Linux.*

= Instructions =

To Install the plugin and connect it to a Bitcoin full node using the built-in Bitcoin adapter:

1. Make sure that you have **the latest WordPress version** installed,
   and that you are running on **at least PHP 5.6.**
   Even though the plugin has been tested on WordPress 3.8 and PHP 5.3,
   for security reasons you are **strongly** recommended to use the latest version of WordPress and a supported version of PHP.
   [Check to see here](http://php.net/supported-versions.php) if your PHP version is currently supported for security issues.
   As of 2017, anything below 5.6 has reached its end-of-life and is no longer supported.

2. **Install and activate the Wallets plugin.** For general information on installing WordPress plugins, you can consult the
   [relevant WordPress documentation](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

3. **Install a Bitcoin full node** on your server. Detailed instructions
   [are available here](https://bitcoin.org/en/full-node). Read and follow the instructions carefully.

   *Take note of the
   [memory, disk, and bandwidth requirements](https://bitcoin.org/en/full-node#minimum-requirements)
   and check against the resources available on your server.* If you find that running a full node is too heavy on
   your server's resources, please see the FAQ section below for alternative options.


4. **Configure the bitcoin adapter on your WordPress installation.**
   Navigate to *Wallets* &rarr; *Bitcoin (BTC)* in your WordPress admin area.

   At a minimum you need to enable the adapter and enter the
   location and credentials to your *Bitcoin daemon RPC API*.

   You will need to set the following: `IP`, `Port`, `User`, `Password`, `Path`.

5. **Configure the bitcoin daemon on your server.**
   You will need to edit your `~/.bitcoin/bitcoin.conf` file and make the configuration match what you entered above.
   The plugin will give you the exact configuration arguments that you need to start the daemon with.

   For more information on the bitcoin daemon configuration,
   consult [the relevant wiki page](https://en.bitcoin.it/wiki/Running_Bitcoin).

6. **Check that the adapter works.**
   Navigate to the *Wallets* menu in the admin area.
   If the Bitcoin *Adapter Status* reads *Responding*, then you're good to go.

   **Note that for a new `bitcoind` installation, you might have to wait until the entire blockchain downloads first.**
   This can take a few hours. Again, skip to the FAQ section for other alternatives.


= Disclaimer =

**By using this free plugin you assume all responsibility for handling the account balances for all your users.**
Under no circumstances is **dashed-slug.net** or any of its affiliates responsible for any damages incurred by the use of this plugin.

Every effort has been made to harden the security of this plugin, but its safe operation depends on your site being secure overall.
You, the site administrator, must take all necessary precautions to secure your WordPress installation before you connect it to any live wallets.

You are strongly recommended to take the following actions (at a minimum):

- [educate yourself about hardening WordPress security](https://codex.wordpress.org/Hardening_WordPress)
- [install a security plugin such as Wordfence](https://infinitewp.com/addons/wordfence/?ref=260 "This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.")
- **Enable SSL on your site** if you have not already done so. You should be already doing this for a number of reasons that will not be listed here.

By continuing to use the Bitcoin and Altcoin Wallets plugin, you denote that you have read and agreed to the above disclaimer.

= Further reading =

- https://codex.wordpress.org/Managing_Plugins#Installing_Plugins
- https://bitcoin.org/en/full-node
- https://en.bitcoin.it/wiki/Running_Bitcoin


== Frequently Asked Questions ==

= Is it secure? =

The Bitcoin and Altcoin Wallets plugin is only as secure as your WordPress installation.
Regardless of whether you choose to install this plugin,
you should have already taken steps to secure your WordPress installation.
At a minimum you should do the following:

1. Install a security plugin such as [Wordfence](https://infinitewp.com/addons/wordfence/?ref=260 "This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.").
2. Read the Codex resources on [Hardening WordPress](https://codex.wordpress.org/Hardening_WordPress).

= Do I really need to run a full node? bitcoind is too resource-hungry for my server. =

Running a full node requires the full blockchain to be downloaded. This can take tens of GigaBytes on your server.
You currently have the following alternative options:
1. Install and configure the [bittiraha-walletd](https://github.com/prasos/bittiraha-walletd) wallet. Point your RPC API settings to that wallet.
2. Become a premium dashed-slug.net member and use the
    [block.io Cloud Wallet Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/block-io-cloud-wallet-adapter-extension/)

From version 1.1.0 onward, this plugin is compatible with the [bittiraha-walletd](https://github.com/prasos/bittiraha-walletd) wallet.
From the project's description on GitHub:

> Lightweight Bitcoin RPC compatible HD wallet
> This project is meant as a drop-in replacement for bitcoind for use in lightweight servers.

This is a wallet based on `bitcoinj` and does not store the blockchain locally.

**block.io** is a "wallet as a service" online wallet that is very easy to configure with this plugin.
Essentially you just need to copy the API keys into your WordPress admin screens.

= How can I integrate the Bitcoin and Altcoin Wallets plugin with my site's frontend? =

A number of UI elements are made available for the front-end.

Just use the provided widgets in your theme, or insert the following shortcodes in a post or page:

- deposit funds: `wallets_deposit`,
- withdraw funds: `wallets_withdraw`,
- transfer funds to other users: `wallets_move`,
- view their balance: `wallets_balance`,
- view past transactions: `wallets_transactions`.

These shortcodes render [knockout.js](http://knockoutjs.com/)-enabled forms.
The forms only show to users who have the necessary capabilities assigned.

= I don't like the built-in forms. Can I provide my own? =

First of all, the forms can be styled with CSS. They have convenient HTML classes that you can use.

If you wish to create forms with completely different markup, you can provide your own views for these shortcodes.
Use the `wallets_views_dir` filter to override the directory where the views are stored
(the default is `wallets/includes/views`). Most people will not need to do this.

= I want to do transactions from JavaScript. I don't want to use the provided shortcodes and their associated forms. =

The provided built-in forms talk to a JSON API that is available to logged in users.
If you choose to build your own front-end UI, you can point your AJAX calls directly to the [JSON API](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/json-api/).

= I want to do transactions from the PHP code of my theme or plugin. =

You can use the [PHP API](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/php-api/) directly.

[Refer to the documentation for details.](http://wallets-phpdoc.dashed-slug.net/classes/Dashed_Slug_Wallets.html)

= How are fees calculated? =

As a site administrator you set two types of fees for each coin adapter that you enable:

- **transfer fees** &mdash; These are the fees a user pays when they send funds to other users.

  These types of transactions
  do not go on the blockchain, so any fees you set here are subtracted from the sender's account in addition to the sent amount.

- **withdrawal fees** &mdash; This is the amount that is subtracted from a user's account in addition to the amount that
  they send to another address on the blockchain.

  This is NOT the network fee, and you are advised to set the withdrawal fee
  to an amount that will cover the network fee of a typical transaction, possibly with some slack that will generate a modest profit on your site.
  To control network fees when running a Bitcoin full node, use the wallet settings in `bitcoin.conf`: `paytxfee`, `mintxfee`, `maxtxfee`, etc.
  [Refer to the documentation for details.](https://en.bitcoin.it/wiki/Running_Bitcoin)

= How can I get support or submit feedback? =

Please use the [support forum on WordPress.org](https://wordpress.org/support/plugin/wallets)
for all issues and inquiries regarding the plugin.

For all other communication, please contact [info@dashed-slug.net](mailto:info@dashed-slug.net).

== Screenshots ==

1. **Adapters list** - Go to the Wallets menu to see a list of installed coins and some stats about them.
2. **Bitcoin Adapter settings** - The settings for talking to your bitcoin daemon. If you install other adapters, there will be similar screens to talk to the respective daemons.
3. **Frontend - deposit** - The \[wallets_deposit\] shortcode displays a UI element that lets your users know which address they can send coins to if they wish to deposit to their account.
4. **Frontend - move** - The \[wallets_move\] shortcode displays a UI element that lets your users transfer coins to other users on the site.
5. **Frontend - withdraw** - The \[wallets_withdraw\] shortcode displays a UI element that lets your users withdraw coins from their account to an external address.
6. **Frontend - balance** - The \[wallets_balance\] shortcode displays your users' account balances.
7. **Frontend - transactions** - The \[wallets_transactions\] shortcode displays an AJAX-powered table of past transactions affecting the accounts of your users.

== Changelog ==

= 2.1.2 =
- Fix: Errors were not being reported on frontend. (JSON API now always returns status 200 OK even if carrying an error message.)

= 2.1.1 =
- Add: The capabilities matrix is now hookable by extensions
- Add: Internal transfers can now have unlimited descriptive tags assigned
- Fix: The `get_users_info` JSON API now retrieves only users who have capability `has_wallets`

= 2.1.0 =
- Add: Capabilities feature lets you assign capabilities to user roles
- Add: E-mail notifications are now admin-configurable
- Add: Frontend Widgets
- Change: Settings tab is now cron tab
- Change: Better code organisation

= 2.0.2 =
- Add: Link to homepage and settings page from plugin list
- Fix: When altering cron duration from admin screens cron job is correctly rescheduled
- Fix: Cron job is now unscheduled on plugin deactivation
- Fix: Uninstall script now correctly unschedules cron job
- Fix: Safer user ID detection (does not depend on `wp_load` action)
- Fix: Using `sprintf` format from adapter in error messages
- Fix: Typo in error message when insufficient balance for withdraw/move
- Improve: Better code organisation for admin screens
- Improve: Safer inserting of new addresses in `wallets_address` action

= 2.0.1 =
- Fix: Dates in the [wallets_transactions] UI were not showing correctly in Internet Explorer
- Improve: Refactored the withdrawal API for compatibility with changes to block.io adapter 1.0.2

= 2.0.0 =
- Add: Generalised `wp_cron` mechanism lets coin adapters perform any periodic checks via an optional `cron()` method.
- Improve: Various improvements to the coin adapter API. `list_transactions` was removed in favor of the generic `cron()` method.
- Add: The `bitcoind` and other RPC API coin adapters do not depend on the notification API to discover deposits.
- Add: Better admin UI explaining how fees operate.
- Add: Adapters can now optionally notify the plugin of user-address mappings with the `wallets_address` action
- Add: The plugin now warns the admin about using SSL.
- Fix: The `bitcoind` built-in adapter now works smoother with the `bittiraha` lightweight wallet.
- Fix: Improved user `get_balance()` in terms of performance and robustness.
- Fix: Bitcoin RPC API adapter only binds to notification API set to enabled.
- Fix: Catching an exception when notified about transaction to unknown address.
- Fix: When transaction tables are locked to perform atomic transactions, the `wp_options` table is available to coin adapters.

= 1.2.0 =
- Add: Multiple coin adapters per extension and per coin (see release notes)
- Add: Fail-safe mechanism that periodically checks for deposits that have not been recorded.
- Add: New setting panel in admin for settings that are not specific to any coin adapters.
- Fix: Exceptions thrown during failed deposit notifications are now caught.

= 1.1.0 =
- Add: Compatibility with the `prasos/bittiraha-walletd` lightweight wallet (see FAQ).
- Fix: Users who are not logged in are not nagged with an alert box. Shortcode UIs now display "Must be logged in" message instead.
- Simplified the adapters list. There will be an entire admin panel about the removed information in a future version.
- Add: Adapters list now gives meaningful errors for unresponsive coin adapters.

= 1.0.6 =
- Made compatible with PHP versions earlier than 5.5
- Added warning in readme about running on PHP versions that have reached end-of-life

= 1.0.5 =
- Deactivate button not shown for built in Bitcoin adapter
- Added video tutorial to readme

= 1.0.4 =
- Recommends the configurations needed in your `bitcoin.conf`
- Does not recommend command line arguments to `bitcoind` any more
- Updated install instructions in `readme.txt`

= 1.0.3 =
- Fixed issue where deactivating any plugin would fail due to nonce error

= 1.0.2 =
- Clearer disclaimer
- Fixed a broken link

= 1.0.1 =
- Fixed some string escaping issues

= 1.0.0 =
- Accounting
- bitcoind connectivity
- PHP API
- JSON API
- Front-end shortcodes
- CSV Import/Export

== Upgrade Notice ==

Version 2.1.1 is a minor patch release. If you have already installed 2.1.0 you can upgrade but there is no rush.

== Donating ==

This is a free plugin.

The dashed-slug is a [heroic one-man effort](https://www.dashed-slug.net/dashed-slug/team/) against seemingly insurmountable coding complexities :-)

Showing your support helps the dashed-slug purchase the necessary coffee for designing, developing, testing, managing and supporting these and more quality WordPress plugins.

These are all the ways you can show your support, if you so choose:

1. **Become a registered [dashed-slug.net](https://www.dashed-slug.net) member**, and enjoy unlimited access to all the premium plugin extensions available, and priority support with any issues.
2. **Use the affiliate links** that are sprinkled throughout the site and plugins. WordPress is a big software ecosystem.
    The dashed-slug.net plugins play nicely together with the following awesome products and services:
    - [Visual Composer](http://www.codecanyon.net/item/visual-composer-page-builder-for-wordpress/242431?ref=dashed-slug "This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.")
    - [Wordfence](https://infinitewp.com/addons/wordfence/?ref=260 "This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.")
    - [block.io](http://www.block.io/#_l_193__dashed-slug "This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.")
3. **Spread the word** to your friends.
4. If you wish, you may **donate** any amount [via flattr](https://flattr.com/profile/dashed-slug)
    or [via Bitcoin](bitcoin:1DaShEDyeAwEc4snWq14hz5EBQXeHrVBxy?dashed-slug?message=donation)

