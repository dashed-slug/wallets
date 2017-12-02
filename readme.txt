=== Bitcoin and Altcoin Wallets ===
Contributors: dashedslug
Donate link: https://flattr.com/profile/dashed-slug
Tags: wallet, bitcoin, cryptocurrency, altcoin, coin, money, e-money, e-cash, deposit, withdraw, account, API
Requires at least: 3.8
Tested up to: 4.7.2
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Turn your blog into a bank: Let your users deposit, withdraw, and transfer bitcoins and altcoins on your site.

== Description ==

https://www.youtube.com/watch?v=_dbkKHhEzRQ

= At a glance =

[Bitcoin and Altcoin Wallets](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin)
is a FREE WordPress plugin by [dashed-slug](https://dashed-slug.net).

It enables financial transactions on your site via Bitcoins and other cryptocurrencies.

= What is available today =

1. A fully functional **cryptocurrencies API stack**, that enables communication to wallets via:
   1. **[PHP calls](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/php-api/)** from your themes and plugins,
   2. a **[JSON API](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/json-api/)**, accessible to logged in users,
   3. the **[frontend UI](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/shortcodes/)**, available via a simple set of shortcodes.
2. A built-in **Bitcoin adapter** that redirects requests to a bitcoin daemon.
3. **Transaction and accounting data** is held on special tables in your MySQL database.
   *The Bitcoin core accounting API is not used since [it is being deprecated](https://github.com/bitcoin/bitcoin/issues/3816).*
4. An **import/export** functionality to backup transactions to and from [CSV](https://en.wikipedia.org/wiki/Comma-separated_values) files.

= What is on the roadmap =

This plugin is already useful today, but will be extended over the coming months:

- short-term, immediate goal:
  - To enable you, the site's administrator to achieve **[CCSS](https://cryptoconsortium.org/standards/CCSS) compliance**.
    CCSS is a security standard that you should aspire to if you're storing other people's cryptocurrencies,
    whether you choose to be audited and certified or not.
  - More **coin adapters** for integrating various cryptocurrencies.
    Currently there is a [Litecoin](https://litecoin.org) adapter that you can install separately.

- long-term goals:
  - **[Payment gateways](https://en.wikipedia.org/wiki/Payment_gateway)** for spending balances on common e-commerce plugins.
  - Plugins that **[reward user engagement](https://en.wikipedia.org/wiki/Gamification)** with microtransactions.
  - **[Ad exchange](https://en.wikipedia.org/wiki/Ad_exchange)** plugins to enable administrators to sell ad spaces and
    to let advertisers bid for these ad spaces.
  - **[Faucets](https://en.wikipedia.org/wiki/Bitcoin_faucet)**
  - **Currency exchanges**
  - etc.

If you are a developer you may decide to contribute towards this effort by building one or more of these components yourself.
Ultimately the Wallets cryptocurrency stack should be a community effort.

= Wallets plugin overview =

This is the *core plugin* that takes care of *basic accounting functionality*:

- **A financial PHP API**: Calls that let the logged in user handle their cryptocurrencies.
- **A JSON API**: JSON requests of the above.
- **Simple shortcodes**: These let you display frontend forms for common tasks.
  - deposit,
  - withdraw,
  - transfer funds,
  - view past transactions
- **Accounting for your users.** Data is held in a table in your MySQL database.
- **Backup and restore transactions**: A robust mechanism to backup transactions to external `.csv` files.
- **Extensible architecture**
  - Easily install coin adapter plugins to use other cryprocurrencies besides Bitcoin.
  - Easily install extension plugins that talk to an accounting API to provide additional functionality.

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

What follows is step-by-step instructions:

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
   your server's resources, please see the FAQ section below.


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
   This can take a few hours.


= Disclaimer =

**By using this free plugin you assume all responsibility for handling the account balances for all your users.**
Under no circumstances is **dashed-slug.net** or any of its affiliates responsible for any damages incurred by the use of this plugin.

Every effort has been made to harden the security of this plugin, but its safe operation depends on your site being secure overall.
You, the site administrator, must take all necessary precautions to secure your WordPress installation before you connect it to any live wallets.

You are strongly recommended to take the following actions (at a minimum):

- [educate yourself about hardening WordPress security](https://codex.wordpress.org/Hardening_WordPress)
- [install a security plugin such as Wordfence](https://infinitewp.com/addons/wordfence/?ref=260 "This affiliate link supports the development of dashed-slug.net plugins. Thanks for clicking.")

By continuing to use the Bitcoin and Altcoin Wallets plugin, you agree that you have read and understood this disclaimer.

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

Running a full node requires the full blockchain to be downloaded.

From version 1.1.0 onward, this plugin is compatible with the [bittiraha-walletd](https://github.com/prasos/bittiraha-walletd) wallet.
From the project's description on GitHub:

> Lightweight Bitcoin RPC compatible HD wallet
> This project is meant as a drop-in replacement for bitcoind for use in lightweight servers.

This is a wallet based on `bitcoinj` and does not store the blockchain locally.

A downside is that the `walletnotify` mechanism and the `listtransactions` command are not implemented.
**This means that there is no easy way for the plugin to be notified of deposits.**
Deposits will not be recorded in the transactions table.
Users will not be emailed when they perform deposits and they will not be able to see their deposits
in the `[wallets_transactions]` UI. Deposits will correctly affect users' balances.
You have been warned.

Soon there will be a coin adapter based on the online `blockchain.info` service.
This will be another option for running a Bitcoin wallet without the overhead of a local copy of the blockchain.
Stay tuned.

= How can I integrate it with my site? =

Just insert the shortcodes anywhere to create forms to let a logged in user:

- deposit funds: `wallets_deposit`,
- withdraw funds: `wallets_withdraw`,
- transfer funds to other users: `wallets_move`,
- view their balance: `wallets_balance`,
- view past transactions: `wallets_transactions`.

These shortcodes render [knockout.js](http://knockoutjs.com/)-enabled forms.

= I don't like the built-in forms. Can I provide my own? =

First of all, the forms can be styled with CSS. They have convenient HTML classes that you can use.

If you wish to create forms with completely different markup, you can provide your own views for these shortcodes.
Use the `wallets_views_dir` filter to override the directory where the views are stored
(the default is `wallets/includes/views`). Most people will not need to do this.

= I want to do transactions from JavaScript. I don't want to use the provided shortcodes and their associated forms. =

The provided built-in forms talk to a JSON API that is available to logged in users.
If you choose to build your own front-end UI, you can do your AJAX calls directly to the JSON API.

Refer to the documentation for details.

= I want to do transactions from the PHP code of my theme or plugin. =

You can use the PHP API directly.

[Refer to the documentation for details.](http://wallets-phpdoc.dashed-slug.net/classes/Dashed_Slug_Wallets.html)

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

= 1.2.0 =
* Add: Multiple coin adapters per extension and per coin (see release notes)
* Add: Fail-safe mechanism that periodically checks for deposits that have not been recorded.
* Add: New setting panel in admin for settings that are not specific to any coin adapters.
* Fix: Exceptions thrown during failed deposit notifications are now caught.

= 1.1.0 =
* Add: Compatibility with the `prasos/bittiraha-walletd` lightweight wallet (see FAQ).
* Fix: Users who are not logged in are not nagged with an alert box. Shortcode UIs now display "Must be logged in" message instead.
* Simplified the adapters list. There will be an entire admin panel about the removed information in a future version.
* Add: Adapters list now gives meaningful errors for unresponsive coin adapters.

= 1.0.6 =
* Made compatible with PHP versions earlier than 5.5
* Added warning in readme about running on PHP versions that have reached end-of-life

= 1.0.5 =
* Deactivate button not shown for built in Bitcoin adapter
* Added video tutorial to readme

= 1.0.4 =
* Recommends the configurations needed in your `bitcoin.conf`
* Does not recommend command line arguments to `bitcoind` any more
* Updated install instructions in `readme.txt`

= 1.0.3 =
* Fixed issue where deactivating any plugin would fail due to nonce error

= 1.0.2 =
* Clearer disclaimer
* Fixed a broken link

= 1.0.1 =
* Fixed some string escaping issues

= 1.0.0 =
* Accounting
* bitcoind connectivity
* PHP API
* JSON API
* Front-end shortcodes
* CSV Import/Export

== Upgrade Notice ==

Up to now all available coin adapters had connected to the RPC API of a wallet daemon.
You will soon have other options including connection to cloud wallets such as **block.io** or **blockchain.info**.

1.2.0 introduces architectural changes necessary so you can choose between alternative coin adapter implementations for any one coin.
You can now turn adapters on or off from their settings page. One plugin extension can now offer multiple coin adapters.

This release breaks API compatibility with existing altcoin adapters. Your already stored transactions are safe,
but if you have purchased access to the altcoin adapters available on dashed-slug.net,
please update the adapters to their latest versions:

* `wallets-feathercoin` 1.1.0
* `wallets-litecoin` 1.1.0


== Donating ==

This is a free plugin.

If you wish, you may show your support by donating.

Donating helps the dashed-slug to design, develop, test, release and support more quality WordPress plugins.

= via flattr =

[flattr the dashed-slug](https://flattr.com/profile/dashed-slug)

= via bitcoin =

[send bitcoins to 1DaShEDyeAwEc4snWq14hz5EBQXeHrVBxy](bitcoin:1DaShEDyeAwEc4snWq14hz5EBQXeHrVBxy?dashed-slug?message=donation)
