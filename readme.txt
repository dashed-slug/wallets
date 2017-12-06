=== Bitcoin and Altcoin Wallets ===
Contributors: dashedslug
Donate link: https://flattr.com/profile/dashed-slug
Tags: wallet, bitcoin, cryptocurrency, altcoin, coin, money, e-money, e-cash, deposit, withdraw, account, API
Requires at least: 4.0
Tested up to: 4.9
Stable tag: 2.10.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Turn your blog into a bank: Let your users deposit, withdraw, and transfer bitcoins and altcoins on your site.

== Description ==

https://www.youtube.com/watch?v=_dbkKHhEzRQ

### Turn your blog into a bank: Let your users deposit, withdraw, and transfer bitcoins and altcoins on your site.

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
- Configure e-mail confirmations for transactions and optionally confirm transactions via the admin interface.
- **Backup and restore transactions**: An **import/export** functionality to backup transactions to and from
  [CSV](https://en.wikipedia.org/wiki/Comma-separated_values) files.
- **Extensible architecture**
  - Easily install coin adapter plugins to use other cryprocurrencies besides Bitcoin.
  - Easily install extension plugins that talk to the PHP API to provide additional functionality such as payment gateways.

= Free coin adapter extensions =

 You can extend this plugin to work with other coins if you install coin adapters. Coin adapters are available for free to all
 subscribers at [dashed-slug](https://www.dashed-slug.net) (you do not have to pay for membership).

- [block.io Cloud Wallet Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/block-io-cloud-wallet-adapter-extension/)
- [CoinPayments Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/coinpayments-adapter-extension/)
- [Feathercoin adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/feathercoin-adapter-extension/)
- [Litecoin Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/litecoin-adapter-extension/)
- [Gridcoin Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/gridcoin-adapter-extension/)

...plus more!

= Premium plugin extensions available today =

Premium [dashed-slug](https://www.dashed-slug.net) members enjoy unlimited access to all the premium extensions to
this plugin (as well as extensions to the [SVG Logo and Text Effects](https://wordpress.org/plugins/slate/) FREE WordPress plugin).

Here are all the currently available premium app extensions to the Bitcoin and Altcoin Wallets FREE WordPress plugin:

- [Author Payroll extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/author-payroll-extension/)
- [Tip the Author extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/tip-the-author-extension/)
- [WooCommerce Cryptocurrency Payment Gateway extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/woocommerce-cryptocurrency-payment-gateway-extension/)
- [Events Manager Cryptocurrency Payment Gateway extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/events-manager-cryptocurrency-payment-gateway-extension/)
- [Faucet extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/faucet-extension/)

Premium members also get auto-updates for any installed extensions. [Instructions for how to set up auto-updates are here](https://www.dashed-slug.net/dashed-slug/extension-updates-activation/).

= And here's a preview of what is to come: =

- A way to exchange between various currencies using the CoinPayments API.
- An interest-paying plugin for investment-type sites.
- Plugins that **[reward user engagement](https://en.wikipedia.org/wiki/Gamification)**.
- An **[ad exchange](https://en.wikipedia.org/wiki/Ad_exchange)** plugin to enable administrators to sell ad spaces and
  to let advertisers bid for these ad spaces.
- etc.

**The dashed-slug.net development is driven by your feedback. Send in your feature requests today.**

= follow the slime =

The dashed-slug is a social slug:

- Facebook: [https://www.facebook.com/dashedslug](https://www.facebook.com/dashedslug)
- Google+: [https://plus.google.com/103549774963556626441](https://plus.google.com/103549774963556626441)
- RSS feed: [https://www.dashed-slug.net/category/news/feed](https://www.dashed-slug.net/category/news/feed)
- SteemIt: [https://steemit.com/@dashed-slug.net](https://steemit.com/@dashed-slug.net)
- Youtube channel: [https://www.youtube.com/channel/UCZ1XhSSWnzvB2B_-Cy1tTjA](https://www.youtube.com/channel/UCZ1XhSSWnzvB2B_-Cy1tTjA)
- GitHub: [https://github.com/dashed-slug](https://github.com/dashed-slug)

== Installation ==

= Overview =

*The installation for the plugin itself is the same as for any WordPress plugin.
Additionally, **you will have to install and maintain a Bitcoin daemon on your server**.
This will typically require SSH access and some basic knowledge of UNIX/Linux.*

= Instructions =

To Install the plugin and connect it to a Bitcoin full node using the built-in Bitcoin adapter:

1. Make sure that you have **the latest WordPress version** installed,
   and that you are running on **at least PHP 5.6.**
   Even though the plugin has been tested on WordPress 4.0 and PHP 5.3,
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
   your server's resources, or if you do not have the technical expertise to work with Linux and the command line,
   or if you only have access to shared hosting, please see the FAQ section below for alternative options.

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

**By using this free plugin you accept all responsibility for handling the account balances for all your users.**
Under no circumstances is **dashed-slug.net** or any of its affiliates responsible for any damages incurred by the use of this plugin.

Every effort has been made to harden the security of this plugin, but its safe operation depends on your site being secure overall.
You, the site administrator, must take all necessary precautions to secure your WordPress installation before you connect it to any live wallets.

You are strongly advised to take the following actions (at a minimum):

- [educate yourself about hardening WordPress security](https://codex.wordpress.org/Hardening_WordPress)
- [install a security plugin such as Wordfence](https://infinitewp.com/addons/wordfence/)
- **Enable SSL on your site** if you have not already done so.

By continuing to use the Bitcoin and Altcoin Wallets plugin, you indicate that you have understood and agreed to this disclaimer.

= Further reading =

- https://codex.wordpress.org/Managing_Plugins#Installing_Plugins
- https://bitcoin.org/en/full-node
- https://en.bitcoin.it/wiki/Running_Bitcoin


== Frequently Asked Questions ==

= Which coins are currently available? =

The list of wallets that you can connect to directly is constantly growing.

To check what’s currently available go to https://www.dashed-slug.net and check the menu under *Wallets* &rarr; *Coin Adapter Extensions* and see the available RPC adapters.

Also, if you are OK with using a web wallet service, then you can install the [CoinPayments adapter](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/coinpayments-adapter-extension/). You then automatically get [all of the coins that platform supports](https://www.coinpayments.net/supported-coins/).

= Is it secure? =

The [Bitcoin and Altcoin Wallets](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/) plugin is only as secure as your WordPress installation. Regardless of whether you choose to install this plugin, you should have already taken steps to secure your WordPress installation. At a minimum you should do the following:

- Install a security plugin such as [Wordfence](https://infinitewp.com/addons/wordfence/).
- Read the Codex resources on [Hardening WordPress](https://codex.wordpress.org/Hardening_WordPress).
- If you are connecting to an RPC API on a different machine than that of your WordPress server over an untrusted network,
  make sure to tunnel your connection via `ssh` or `stunnel`. [See here](https://en.bitcoin.it/wiki/Enabling_SSL_on_original_client_daemon).

= Do I really need to run a full node? bitcoind is too resource-hungry for my server. =

Running a full node requires you to set up the daemon on a VPS or other machine that you own and administer. Normally the full blockchain needs to be downloaded, so you need to make sure that your server can handle the disk and network requirements.

**Cloud wallets**

Instead, you can choose to install one of the available coin adapters that are backed by cloud wallet services. These currently are:

- The [CoinPayments Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/coinpayments-adapter-extension/)
- The [block.io Cloud Wallet Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/block-io-cloud-wallet-adapter-extension/)

Study the services and their terms of service including what fees they charge before choosing to use them.

**bittiraha**

From version 1.1.0 onward, this plugin is compatible with the [bittiraha-walletd](https://github.com/prasos/bittiraha-walletd) wallet. From the project's description on GitHub:

> Lightweight Bitcoin RPC compatible HD wallet
> This project is meant as a drop-in replacement for bitcoind for use in lightweight servers.

This is a wallet based on `bitcoinj` and does not store the blockchain locally. You will have to install this on a VPS or other server via the shell.

A downside is that the `walletnotify` mechanism and the `listtransactions` command are not implemented. **This means that there is no easy way for the plugin to be notified of deposits.** Deposits will not be recorded in the transactions table. Users will not be emailed when they perform deposits and they will not be able to see their deposits in the `[wallets_transactions]` UI. Deposits will correctly affect users' balances. You have been warned.

**Electrum**

Alternatively, you can install and configure an [Electrum wallet](https://electrum.org) and the [Electrum Bitcoin RPC Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/electrum-bitcoin-rpc-adapter-extension/). **Don't forget to disable the built-in Bitcoin core adapter.**

= Can you install/configure the plugin for me? / I am having trouble with the bitcoin.conf file =

I am available to answer any specific questions if you attempt to install the plugin and you face some problem. Unfortunately I do not undertake installation and configuration of the plugin.

Keep in mind that no software is set-and-forget. Once you install software, it then needs to be maintained. If you find that you are having trouble installing the plugin or connecting it to a wallet, even with help, this is a good indication that you should not be running a wallet with people's money on it.

Remember that you have two options: stand-alone wallets or web wallets. Running a web wallet is considerably easier than a stand-alone wallet, as it does not require system administration skills. As a general rule, if you have trouble using Linux from the command line, you will be better off installing a web wallet.

= I cannot connect to a wallet running on my home computer =

Unless your home internet connection has a static IP and you have opened the correct ports on your router/firewall you will not be able to use your home computer as a wallet backend. In fact, this is not a very good idea. Ideally you need a dedicated server to run a wallet with the availability required by a site that serves multiple users. Virtual private servers (VPSs) should be OK as the wallets do not max-out CPU usage under normal operation, after the blockchain finishes syncing. Shared/managed hosting plans are not appropriate for running wallet daemons. If you have a shared/managed hosting plan (i.e. no ssh access), you are stuck with using web wallets.

Check with your hosting plan for disk space and memory requirements against the requirements of the wallet you wish to run. For Bitcoin core, click [here](https://bitcoin.org/en/bitcoin-core/features/requirements).

= How can I integrate the plugin with my site? =

Just insert the [shortcodes](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/wallet-shortcodes/) anywhere to create forms to let a logged in user:

- **deposit funds:** `[wallets_deposit]`
- **withdraw funds:** `[wallets_withdraw]`
- **transfer funds to other users:** `[wallets_move]`
- **view their balance:** `[wallets_balance]`
- **view past transactions:** `[wallets_transactions]`

These shortcodes render [knockout.js](http://knockoutjs.com/)-enabled forms. Read the shortcodes documentation for more details.

You can enter the same UI elements into your theme's widget area.
Simply go to *Appearance* &rarr; *Widgets* and use the provided front-end widgets.

You can also use a special menu item to display the user balances as part of a nav menu:

1. Go to *Appearance* &rarr; *Menus*.
2. At the top right side of the screen, click *Screen Options*.
3. Under *Boxes*, make sure that *Bitcoin and Altcoin Wallets balances* is selected.
4. Now you are free to enter the balances menu item into your menus.

= I don’t like the built-in forms. Can I provide my own? =

First of all, the forms can be styled with CSS. They have convenient HTML classes that you can use.

If you wish to create forms with completely different markup, you can provide your own views for these shortcodes.
Use the `wallets_views_dir` filter to override the directory where the views are stored (the default is `wallets/includes/views`).
Most people will not need to do this.

Read the [shortcodes documentation](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/wallet-shortcodes/) for more details.

= I want to do transactions from JavaScript. I don’t want to use the provided shortcodes and their associated forms. =

The provided built-in forms talk to a JSON API that is available to logged in users.
If you choose to build your own front-end UI, you can do your AJAX calls directly to the JSON API.

Refer to the [JSON API documentation](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/json-api/) for details.

= I want to do transactions from the PHP code of my theme or plugin. =

You can use the PHP API directly.

Refer to the [PHP API documentation](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/php-api/) for details.

= I want to replace an adapter with another one. =

You can only have one coin adapter enabled per each coin. The plugin will warn you about this.

Suppose you wish to replace the internal *Bitcoin core adapter* with the *block.io BTC adapter*. You would have to:

1. Wait for pending deposits and withdrawals to execute. They should be in *'done'* state.
2. Disable your original coin adapter.
3. Delete the deposit addresses from your database with:

	`DELETE FROM wp_wallets_adds WHERE symbol = 'BTC';`

4. Activate and configure the new adapter.

= Can you add XYZ coin for me? =

Yes and no. I have received a large amount of requests from small coins and cannot cater for them all. I do try to implement some of the requests I receive.

If your coin's wallet has a standard RPC API that is a direct fork of Bitcoin core, then you should be able to modify the [Litecoin adapter](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/litecoin-adapter-extension/) to suite your needs. Feel free to republish it after modifying the code. There are some instructions on how to do this [here](https://www.dashed-slug.net/developers-coin-adapters-api/).

If your coin is an ERC-20 token then there is no support for that at the moment. Ideally this is something that will be implemented in the future.

= Are you available for custom development work? =

Unfortunately I do not undertake custom projects. If you have an idea about a cool extension then please let me know about it. If it is a good fit for the project, it will be added to the backlog. When implemented, It will be available either to all users for free, or for dashed-slug premium members.

= Can you add fiat currency deposits? =

I do not have plans to add fiat currency deposits. That is not to say that someone cannot develop an extension to do this.

= Can you build an exchange on top of the plugin? =

Yes this is in the future plans.

= I want to pay for premium membership but cannot or do not want to pay via PayPal. =

I am currently in the process of building a plugin extension that will allow you to pay for membership via cryptocurrencies.
In the meantime, you may contact me directly at [info@dashed-slug.net](mailto:info@dashed-slug.net) if you wish to send a Bitcoin payment and I will activate your membership manually.

= How can I get support or submit feedback? =

Please use the [support forum on WordPress.org](https://wordpress.org/support/plugin/wallets) for all issues and inquiries regarding the plugin.

To get support on the provided extensions, subscribe to dashed-slug and go to the [support forums](https://www.dashed-slug.net/support/).

For all other communication, please contact [info@dashed-slug.net](mailto:info@dashed-slug.net).




== Screenshots ==

1. **Adapters list** - Go to the Wallets menu to see a list of installed coin adapters and their status.
2. **Capabilities matrix** - Easily control who can do what by assigning WordPress capabilities to your user roles.
3. **Confirmation settings** - Decide whether internal and external transfers need to be verified by the user over email, an administrator via the admin panel, or both.
4.  **Cron** - Control the recurring background tasks to finetune the plugin's performance.
5. **E-mails** - Use a simple templating format to edit the email notifications that users receive about their transactions.
6. **QR-Codes** - Turn on or off the display of QR-codes for deposit addresses.
7. **Bitcoin adapter settings** - Settings for communicating with the Bitcoin RPC API. If you install other coin adapters they will have similar panels with settings.
8. **Frontend - deposit** - The \[wallets_deposit\] shortcode displays a UI element that lets your users know which address they can send coins to if they wish to deposit to their account.
9. **Frontend - move** - The \[wallets_move\] shortcode displays a UI element that lets your users transfer coins to other users on the site.
10. **Frontend - withdraw** - The \[wallets_withdraw\] shortcode displays a UI element that lets your users withdraw coins from their account to an external address.
11. **Frontend - balance** - The \[wallets_balance\] shortcode displays your users' account balances.
12. **Frontend - transactions** - The \[wallets_transactions\] shortcode displays an AJAX-powered table of past transactions affecting the accounts of your users.


== Changelog ==

= 2.10.5 =
- Fix: Plugin now works even if theme causes the frontend `wp` JavaScript object to not exist.
- Fix: String localization is now working.
- Add: String localization now split into frontend and backend. See documentation for details.
- Add: Greek language translations for frontend.

= 2.10.4 =
- Fix: Setting capabilities in network-activated multisite installs now modifies capabilities accross the network.
- Add: Plugin warns user if needed PHP extensions are not installed.
- Add: Admins can now view their own deposit addresses and balances in their user profile screen.
- Improve: Bumped included `bs58check` library from 2.0.2 to 2.1.0.

= 2.10.3 =
- Add: Admins with `manage_wallets` can now view deposit addresses and balances of users in the user profile screen.
- Improve: Better cache control in the JSON API.
- Fix: Bug with the `get_transactions` API where data was not returned when using the friendly URI form instead of GET parameters.
- Fix: Warnings, errors and other notices that are relevant to wallet managers are now only shown to users with `manage_wallets`.
- Fix: Invalid `get_transactions` JSON API request with NaN argument while the frontend UI initializes.
- Add: Instructions for downloading the documentation added in the about section.

= 2.10.2 =
- Add: Exchange rates are now available over tor. Only enable this if running WordPress on an Onion hidden service.
- Add: Exchange rates can be turned off if not needed to improve performance.
- Add: User is warned if the DB table indices are corrupted.
- Improve: Adding/updating transaction rows does not depend on the DB constraints.
- Improve: Exchange rates are decompressed using PHP curl, not via the compress.zlib filter.
- Fix: Misleading textual description of the IP setting in RPC adapters.
- Fix: Small bug with error reporting in JSON adapters.

= 2.10.1 =
- Fix: More DB columns changed to ASCII. Saves space, plus fixes "Specified key was too long" on some databases and server locales.
- Improve: Frontend observables `withdraw_fee` and `move_fee` changed to camelCase to match other observables.
- Add: Debug log markers at uninstall script boundaries. Should aid in troubleshooting.

= 2.10.0 =
- Improve: Better knockout bindings in frontend. Bindings applied to UI elements only, not entire page. Allows for playing nice with other knockout code.
- Add: The wallets viewmodel is now available for inheritance under the global wp object. Allows for extensions that modify the UI.
- Add: Tradesatoshi added to list of available exchange rate providers.
- Fix: Issue where database tables were not created on new installs.
- Fix: Race condition between uninstall script and cron job that caused unaccepted transactions to transition into pending state.
- Improve: Bumped the included knockout distribution to latest version, 3.5.0-pre.

= 2.9.0 =
- Add: Notifications can now be sent either as emails or as BuddyPress private messages (or both).
- Fix: When upgrading database sche,a, suppress logging of some errors  that are to be expected.

= 2.8.2 =
- Fix: Bug introduced in 2.8.1 where deposits could be duplicated in some situations.

= 2.8.1 =
- Add: Changes throughout the plugin for currencies that use additional information in transactions besides a destination address (e.g. Monero, Ripple, etc).
- Fix: Some issues with language domains in translated strings.
- Fix: QR code only shown for currencies where deposit makes sense.
- Add: NovaExchange will be shown as unavailable as an exchange rate provider after 2018-02-28.

= 2.8.0 =
- Add: Admins can cancel internal transactions.
- Add: Admins can retry cancelled internal transactions.
- Improve: Exchange rates are now not slowing down the system. Better caching mechanism. Runs on PHP shutdown.
- Add: YoBit and Cryptopia exchanges added as exchange rate sources.
- Add: Exchange rate sources are now pluggable (see PDF documentation).
- Add: Dashboard debug info now includes commit hash and plugin version.
- Fix: Bug with failsafe mechanism for systems where WP Cron is not running, introduced in 2.7.4
- Improve: When not connected, the internal Bitcoin core plugin now suggests a salted password for the bitcoin.conf file, using the rpcauth= argument.

= 2.7.4 =
- Add: Failsafe mechanism for systems where WP Cron is not running.
- Add: Panel with useful system debug info in Dashboard area. Users can copy and paste it when requesting support.
- Add: Show warning about old WordPress or PHP versions.

= 2.7.3 =
- Fix: Incompatibility with PHP 5.3 introduced in 2.7.2.
- Improve: More efficient pulling of bittrex exchange rates.

= 2.7.2 =
- Add: Exchange rates API now uses a choice of Bittrex, Poloniex or Novaexchange APIs.
- Add: Blockchain.info donation button in about section.
- Add: SteamIt social link in about section.

= 2.7.1 =
- Fix: Bug where wrong coin address was displayed in cold storage section.
- Add: Cold storage section now links to wiki page.
- Add: All extensions now listed in About section.

= 2.7.0 =
- Add: Cold storage section, allowing easy addition and withdrawal of funds to and from external wallets.
- Improve: Uninstalling and re-installing the plugin now fixes the SQL table schemas if they are missing or damaged.

= 2.6.3 =
- Add: When coin adapters report a new status for an existing transaction, the plugin can now update the status of the transaction.

= 2.6.2 =
- Fix: SQL formatting issue.
- Add: Text descriptions for adapter HTTP settings.
- Add: JSON coin adapter base class now does more verbose error reporting on API communication errors.
- Improve: Actions in transactions list admin screen are now buttons.

= 2.6.1 =
- Fix: Query formatting issue.

= 2.6.0 =
- Fix: Added back Knockout.js that was missing due to changes in 2.5.4 (oops!)
- Add: Functions for pulling exchange rates are now in wallets core, available for all extensions.

= 2.5.4 =
- Fix: `do_move()` checks the balance of sender, not current user.
- Fix: Menu item now shows balance(s) of current user, not total wallet balance(s).
- Improve: Knockout.js assets are now local, not served from CDN.
- Add: FAQ section about supported coins.

= 2.5.3 =
- Fix: Issues with frontend JavaScript code that would prevent popups from being displayed.
- Fix: Issue where in some situations cached assets (JS, CSS) from older plugin versions were being used.
- Improve: Better markup for balances menu item.
- Add: Many common questions added to the FAQ section.

= 2.5.2 =
- Fix: Compatibility issue with PHP < 5.5
- Fix: More correct markup in balances nav menu item.

= 2.5.1 =
- Fix: Minor JavaScript issue that prevented the frontend from working correctly with some coin adapters.

= 2.5.0 =
- Add: Balance information can now be inserted into WordPress menus. See *Appearance* &rarr; *Menus*.
- Add: Pluggable validation mechanism for withdrawal addresses. Bitcoin addresses validated against `bs58check`.
- Add: Fees to be paid are now updated dynamically as soon as a user types in an amount.
- Improve: Massive refactoring in the knockout.js code.
- Fix: get_balance memoization now works correctly for multiple invocations of different users.

= 2.4.6 =
- Fix: Bug in balance checks.

= 2.4.5 =
- Improve: Paid fees are now deducted from the amount that users enter in the withdrawal and internal transfer UIs.
- Add: Fees now have a fixed component and a component that is proportional to the transacted amount.
- Add: Coin adapter settings now display descriptions.

= 2.4.4 =
- Improve: Adapters now live in their own special panel.
- Add: About page with social actions and latest news.
- Add: Doublecheck to see if WordPress cron is executing and inform user if not.

= 2.4.3 =
- Improve: Adapter list now shows both funds in wallets and funds in user accounts
- Improve: In adapters list, coin name, coin icon and coin symbol are now merged into one "Coin" column
- Add: Usernames in transaction list are links to user profiles
- Add: Link to support forum from plugin list
- Add: Added mention of Electrum coin adapter in FAQ section

= 2.4.2 =
- Improve: `get_new_address()` deprecated in favor of `get_deposit_address()`.
- Add: `do_move()` can now do a funds transfer from users other than the current one.
- Fix: Bug where a DB transaction started after a funds transfer is now fixed.

= 2.4.1 =
- Fix: When performing actions in transactions admin panel, redirect to that same panel without the action arguments (allows page refresh).
- Add: PHPdoc for new helper functions introduced in 2.4.0
- Add: Text warning about security best practices regarding RPC API communications over untrusted networks.


= 2.4.0 =
- Add: On multisite installs, the plugin can be *network-activated*.
- Add: Feature extensions (WooCommerce, EventsManager, Tip the Author, etc) can now place withdrawals or transfers that do not require confirmations.
- Fix: Broken "Settings" link in plugins list replaced with a working "Wallets" link.

= 2.3.6 =
- Add: When a user requests to withdraw to a known deposit address of another user, an internal move transaction is performed instead.
- Improve: Frontend transactions in `[wallets_transactions]` are sorted by descending created time.
- Improve: Admin transactions list defaults to sorted by descending created time.
- Add: If a coin adapter does not override the sprintf format for amounts, the format now includes the coin's symbol letters.
- Fix: Uncaught exception when user-unapproving a transaction in admin when it corresponds to a currently disabled adapter.
- Fix: Uncaught exception when performing `wallets_transaction` action on a currently disabled adapter.
- Fix: Suppress a logs warning in `Dashed_Slug_Wallets_Coin_Adapter::server_ip()`.

= 2.3.5 =
- Fix: Withdrawals to addresses that are also deposit addresses on the same system are no longer allowed.
- Fix: Email notifications for successful withdrawals now correctly report the transaction ID.
- Fix: Email notifications for failed withdrawals do not report a transaction ID since it does not exist.

= 2.3.4 =
- Improve: Confirmation links can be clicked even if user not logged in.
- Add: When a transaction is user unaccepted via admin, a new confirmation email is sent.
- Fix: Unused code cleanup

= 2.3.3 =
- Fix: Deposit notifications restored after being disabled in 2.3.2
- Fix: Only send confirmation emails if DB insert succeeds

= 2.3.2 =
- Fix: Issue introduced in 2.3.0 where pending (not executed) withdrawals to the same address would fail.
- Fix: Unhandled exception when sending a notification email while the corresponding adapter is disabled.
- Change: CSV import feature only imports transactions with "done" status to maintain DB consistency.

= 2.3.1 =
- Fix: Issue where on some systems MySQL tables were not being updated correctly, resulting in user balances appearing as 0.

= 2.3.0 =
- Add: Administrator panel to show all transactions in the system.
- Change: The `.csv` import functionality is now moved to the transactions admin panel.
- Change: Transaction requests are now decoupled from transaction executions. They are executed by cron jobs in batches of configurable size and frequency.
- Add: Transactions can require confirmation by an administrator with `manage_wallets`.
- Add: Transactions can require the user to click on a link sent by email.
- Add: Failed transactions are retried a configurable number of times.
- Add: Transaction retries can be reset by an administrator with `manage_wallets`.
- Add: Users can now be notified by email if their transaction fails.
- Add: Frontend transactions lists (wallets_transactions UI) now show the TXID.
- Add: Frontend transaction lists (wallets_transactions UI) are now color coded based on transaction state.
- Fix: The minimum number of confirmations reported by get_minconf() was always `1` instead of the user-supplied value.
- Change: Performance improvement in the code that calculates balances for users (function `get_balance()`).
- Change: Internal transfers that cause two row inserts are now surrounded by a DB lock and atomic transaction to ensure consistency even in case of an unexpected error.

= 2.2.5 =
- Fix: Administrator capabilities were erroneously being erased in 2.2.4 when editing other role capabilities

= 2.2.4 =
- Add: User is warned if DISABLE_WP_CRON is set.
- Fix: Administrator is now unable to remove capabilities from self for safety.
- Fix: Fees fields were being cleared when the clear button was pressed or after a successful transaction.
- Fix: Suppress duplicate warnings in logs when inserting existing user address
- Fix: Moment.js third-party lib was being reminified.

= 2.2.3 =
- Add: Multisite (aka network) installs now supported
- Improve: If user does not have wallets capability the frontend is not burdened with wallets scripts or styles
- Fix: Transactions table has horizontal scrolls (especially useful in the transactions widget)
- Fix: Added empty `index.php` files in all directories for added security.

= 2.2.2 =
- Fix: Do not popup error to users who are not logged in

= 2.2.1 =
- Add: Deposit addresses now also shown as QR-Codes
- Add: After import show both successful and unsuccessful transaction counts
- Fix: Users now are not allowed to transfer funds to self
- Fix: E-mail notifications withdrawals would show timestamps, now show human-readable date/time

= 2.2.0 =
- Change: Improved coin adapters API. All current adapters need update to the 2.2.0 API.
- Add: Accompanying PDF documentation now provides instructions for creating a coin adapter (for developers).
- Fix: Improved front-end error reporting in some cases.
- Fix: Plugin would not activate on MySQL DBs with collation utf8mb4*
- Improve: If the PHP cURL module is not installed, any RPC adapters are automatically disabled and the user is warned.

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

Version 2.10.5 introduces localization.

== Donating ==

This is a free plugin.

The dashed-slug is a [heroic one-man effort](https://www.dashed-slug.net/dashed-slug/team/) against seemingly insurmountable coding complexities :-)

Showing your support helps the dashed-slug purchase the necessary coffee for designing, developing, testing, managing and supporting these and more quality WordPress plugins.

These are all the ways you can show your support, if you so choose:

1. **Become a registered [dashed-slug.net](https://www.dashed-slug.net) member**, and enjoy unlimited access to all the premium plugin extensions available, and priority support with any issues.
2. **Report bugs and suggest features.** Please try to use the appropriate support forum depending on whether your issue is with the main plugin or its extensions.
3. **Spread the word** to your friends.
4. **If you wish, you may donate** any amount [via flattr](https://flattr.com/profile/dashed-slug) or via Bitcoin at `1DaShEDyeAwEc4snWq14hz5EBQXeHrVBxy`.

