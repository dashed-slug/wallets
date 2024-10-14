=== Bitcoin and Altcoin Wallets ===
Contributors: dashedslug
Donate link: https://flattr.com/profile/dashed-slug
Tags: wallet, bitcoin, cryptocurrency, altcoin, custodial
Requires at least: 6.0
Tested up to: 6.6.2
Requires PHP: 7.0
Stable tag: 6.3.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Custodial cryptocurrency wallets.

== Description ==

### Custodial cryptocurrency wallets.

= At a glance =

[Bitcoin and Altcoin Wallets](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin) is a FREE WordPress plugin by [dashed-slug](https://dashed-slug.net).

Your users can deposit, withdraw and transfer Bitcoins and other cryptocurrencies on your site.

= Free wallet adapter extensions =

 You can extend this plugin to work with other coins if you install wallet adapters.

 There is a built-in wallet adapter that lets you connect with Bitcoin core and similar wallets, such as: Dogecoin core, Bitcoin ABC (Bitcoin Cash wallet), Litecoin core, etc. Any wallet that uses the Bitcoin core RPC API is compatible.

 The following wallet adapters are available for free to all [dashed-slug subscribers](https://www.dashed-slug.net/dashed-slug/subscribe/).

- [lnd and tapd Wallet Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/lnd-wallet-adapter-extension/) - Connect to an lnd node, and perform transactions on the Bitcoin Lightning network. Also connect to a tapd node to mint and transact Taproot Assets.
- [CoinPayments Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/coinpayments-adapter-extension/) - Third-party wallet for many cryptocurrencies. Saves you from the hassle of hosting wallets on servers. But you don't control the private keys.
- [Monero Coin Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/monero-coin-adapter-extension/) - Full node wallet adapter for Monero and its forks.
- [TurtleCoin Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/turtlecoin-adapter-extension/) - Full node wallet adapter for TurtleCoin and its forks.

You do not have to pay for membership to get the wallet adapters.


= Premium app extensions =

Premium [dashed-slug](https://www.dashed-slug.net) members get unlimited access to download all the premium extensions:

- [Exchange extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/exchange-extension/) - Allows your users to enter market orders and exchange cryptocurrencies.
- [Airdrop extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/airdrop-extension/) - Distribute coins or pay interest to your users by performing airdrops and recurring airdrops.
- [Faucet extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/faucet-extension/) - Reward your users for solving CAPTCHAs.
- [Paywall extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/paywall-extension/) - Let users pay for subscription to various user roles, and use shortcodes to control what content they see, based on these roles.
- [Tip the Author extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/tip-the-author-extension/) - Allows users with cryptocurrency wallets to tip content authors.
- [WooCommerce Cryptocurrency Payment Gateway extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/woocommerce-cryptocurrency-payment-gateway-extension/) - Let logged in users pay at WooCommerce checkout from their cryptocurrency wallet.

Premium members get auto-updates for any installed extensions. See [how to set up auto-updates](https://www.dashed-slug.net/dashed-slug/extension-updates-activation).


= follow the slime =

Find the dashed-slug on the web:

- Twitter: [https://twitter.com/DashedSlug](https://twitter.com/DashedSlug)
- RSS feed: [https://www.dashed-slug.net/category/news/feed](https://www.dashed-slug.net/category/news/feed)
- Youtube channel: [https://www.youtube.com/dashedslugnet](https://www.youtube.com/dashedslugnet)
- GitHub: [https://github.com/dashed-slug](https://github.com/dashed-slug)

== Installation ==

To get started, install the plugin and follow the on-screen installation wizzard.

You can also consult the documentation. In the WordPress admin screens, see _Wallets Admin Docs_ &rarr; _wallets_ &rarr; _Installation instructions_.

= Disclaimer =

**By using this free plugin, you accept all responsibility for storing and handling user funds.**

Under no circumstances is **dashed-slug.net** or any of its affiliates responsible for any damages incurred by the use of this plugin.

By continuing to use the Bitcoin and Altcoin Wallets plugin, you indicate that you have understood and agreed to this disclaimer.

== Frequently Asked Questions ==





= Where is the plugin's documentation? =

Since version `6.0.0`, the plugin displays its own documentation in the admin screens. Just go to the Wallets Admin Docs menu, where you'll find the documentation for the plugin, and for any plugin extensions you have installed.

Developers can study the PHPdocumentor pages at: https://wallets-phpdoc.dashed-slug.net/

= How secure is it? =

When users issue transactions, these can require verification via an email link by the user. Additionally, you can require that an admin also verifies each transaction. (See "Confirmations" in the documentation).

Of course, the plugin is only as secure as your WordPress installation is.

You should take extra time to secure your WordPress installation, because it will have access to your hot wallets. At a minimum you should do the following:

- Install a security plugin such as [Wordfence](https://infinitewp.com/addons/wordfence/).
- Read the Codex resources on [Hardening WordPress](https://codex.wordpress.org/Hardening_WordPress).
- If you are connecting to an RPC API on a different machine than that of your WordPress server over an untrusted network, tunnel your connection via `ssh` or `stunnel`. [See here](https://en.bitcoin.it/wiki/Enabling_SSL_on_original_client_daemon).

Some more ideas:
- Add a user auditing tool such as [Simple History](https://wordpress.org/plugins/simple-history/).
- Add a CAPTCHA plugin to your login pages.
- Only keep up to a small percentage of the funds in your hot wallet (See *Cold Storage* in the documentation).

= I am installing a bitcoin full node on my server. How can I run it as a service so that it is always running? =

This will depend on the Linux distribution on your server.

To setup bitcoin core as a service on [systemd](https://en.wikipedia.org/wiki/Systemd) I used [this guide](https://medium.com/@benmorel/creating-a-linux-service-with-systemd-611b5c8b91d6).

Here is my `/etc/systemd/system/bitcoin.service` file:

	[Unit]
	Description=Bitcoin wallet service
	After=network.target
	StartLimitBurst=5
	StartLimitIntervalSec=10

	[Service]
	Type=simple
	Restart=always
	RestartSec=1
	User=alexg
	ExecStart=/usr/local/bin/bitcoind

	[Install]
	WantedBy=multi-user.target

You will need to edit the user name to match yours, and possibly the path to your bitcoind binary.

Follow the article to set the service to run automatically on system restart. You should never have to enter the bitcoin command directly in the shell. Always let the system start it automatically as a service.

After that, you must check with your hosting provider (the provider who supplies the server for your bitcoin daemon) to see if there are any firewalls, blocking incoming communication with TCP port `8332`. Also check any local firewalls that you may be running, such as `ufw`.



= Can I control which users have a wallet? =

Yes, simply assign the `has_wallets` capability to the appropriate users or user roles. You should also assign more capabilities, such as `list_wallet_transactions`, `send_funds_to_user`, and `withdraw_funds_from_wallet`.

You can control the capabilities per user role by navigating to: _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Capabilities_.

= Can I use the plugin to create an investment / interest paying site? =

Yes, you can use the premium *Airdrop extension* to perform _recurring airdrops_. These can effectively be paid out in the form of an interest on the user's wallet.

= Can I use the plugin to create a WooCommerce store that accepts cryptocurrencies? =

Yes, you can use the premium *WooCommerce Cryptocurrency Payment Gateway extension*. With it, users can use their on-site balance to checkout their shopping carts.

= Can I use the plugin to create a crypto faucet? =

Yes, you can use the premium *Faucet extension* to let users earn crypto by solving CAPTCHAs.

= Can I use the plugin to create a crypto exchange? =

Yes, you can use the premium *Exchange extension* to create market pairs. However the markets are local only, which means that no liquidity is imported from other exchanges. Read the disclaimers.

= Can I use the plugin to do a token sale? =

No, this plugin is not suitable for token sales.

However, you could, of course, setup markets using the *Exchange extension*, and set a large limit sell order with your admin account. This will allow users to buy your shillcoin. You can even disable buying or selling separately.

= Can I use the plugin to accept tips for articles? =

Yes, you can use the premium *Tip the author extension*. This lets you attach a tipping UI to posts/articles. You can control where the tipping UI is shown, by post type, category, tags, or even author. Only authors with the `receive_tip` capability can receive tips, and only users with the `tip_the_author` capability can send tips.

= Can I use the plugin to create a paywall? =

Not yet. However, a premium extension to let you do just that is currently in development.



= How can I change the plugin's code? =

The plugin and its extensions are yours to edit. You are free to hack them as much as you like. However, you are generally discouraged from doing so, for the following reasons:

- I cannot provide support to modified versions of the plugin. Editing the code can have unintended consequences.

- If you do any modifications to the code, any subsequent update will overwrite your changes. Therefore, it is [not recommended](https://iandunn.name/2014/01/10/the-right-way-to-customize-a-wordpress-plugin/) to simply fire away your favorite editor and hack away themes or plugins.

Whenever possible, use an existing hook ([action](https://developer.wordpress.org/reference/functions/add_action/) or [filter](https://developer.wordpress.org/reference/functions/add_filter/)) to modify the behavior of the plugin. Then, add your code to a child theme, or in separate plugin file. Any PHP file with the [right headers](https://codex.wordpress.org/File_Header) is a valid plugin file.

If you can’t find a hook that allows you to do the modifications you need, contact me to discuss about your need. I may be able to add a hook to the next patch of the plugin.

= Why is my CSS not being applied to the UI elements? =

This is usually due to the plugin's CSS rules hiding your own rules. Don't just spam `!important`, instead take the time to study [CSS rule specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Specificity). If you are unsure how to apply a particular rule on the plugin's UIs, you can contact me.

= How can I change something in the UI elements? =

There are several ways to do this, depending on what type of change you want to apply. Please check the documentation under _Frontend and Shortcodes_ &rarr; _Modifying the UI appearance_.

= How can I perform transactions from my PHP code? =

There are two ways to do this:

1. You can create `DSWallets\Transaction` objects, populate all the fields, then save the obects to the DB. For details, check the documentation under: _Developer reference_ &rarr; _Working with custom post type objects_, and the PHPDocumentor page for the Transaction class for example code.

2. You can use the Legacy PHP-API. This is compatible with previous versions of the plugin.

= How can I perform transactions from the JavaScript frontend? =

There are two ways to do this:

1. You can use the *WP-REST API*. Consult the documentation under: _Developer reference_ &rarr; _Wallet APIs_ &rarr; _WP-REST-API_.

= How can I change the wallet backing a particular currency? =

It is possible that, for a particular cryptocurrency you may want to replace the wallet backing it with another wallet. For example, you may be offering Bitcoin via the CoinPayments service, and want to start using a Bitcoin core full node wallet. Or you may be using Bitcoin core, and you want to move to a new `wallet.dat` file.

This has become a lot easier with versions `6.0.0` and later, because *Currencies* and *Wallets* are now decoupled:

1. Create the new *Wallet* with the built-in Bitcoin adapter for full nodes. Connect to your new full node wallet.
2. Edit your *Currency*, in this case, _Bitcoin_. Set the _Wallet_ to your new full node wallet entry, and _Update_ the Currency.
3. Transfer the hot wallet balance from one wallet to the other. Transfer all the funds to an address generated by new *Hot Wallet*. The address must NOT be a deposit address assigned to a user. For example, you can use the deposit address shown in the *Cold Storage* tool for your new wallet.
4. Delete all the old deposit addresses for that currency. This will force the plugin to generate new deposit addresses from the newly connected wallet.
5. Inform your users that they must no longer use the old deposit addresses, if they have them saved somewhere.

If unsure about this process, please contact me.


= How does the plugin work in multisite installations? =

How it works depends on whether the plugin (and its extensions) are network-activated or not. In network-activated setups, users have one balance ber currency, across all sites the network. If the plugin is NOT network-activated, users have a different balance on each site on the network, and each site can have different currencies and wallets.

Note that the plugin and its extensions MUST either all be network activated, OR all must be activated on individual blogs. Do not mix-and-match network-activated and non-network-activated wallets plugins.

Consult the documentation section _Multisite_ for more information.


= How to handle a hack/cyberattack? =

While the latest WordPress version is often secure, the same cannot be said about all the WordPress plugins out there. Every day new security vulnerabilities are found involving WordPress plugins. Since WordPress is such a popular software platform, it gets a lot of attention from hackers.

Take an immediate backup of the site, and the server it runs on, if possible. This will preserve any traces that the hackers may have left behind. Funds theft is a crime and you can report it to the police, just like any other hack.

It's best if you are prepared beforehand: Keep the software on the site updated regularly. Take the time to harden your server and WordPress installation. Try to use only reputable plugins on your site and keep them updated.  Use a security plugin.

Finally, only keep a small percentage of the user balances on the hot wallet, utilizing the _Cold storage_ feature to transfer the remaining funds to offline wallets. That way, in case WordPress is compromised, you don't lose all your users' funds! Please take wallet management seriously. There is no software that will automatically do opsec for you. Have a plan on how to act in case of theft.

If you think you have discovered a security vulnerability in this plugin, please let me know over email (not on a public forum).




= How can I become a premium member and get access to the app extensions? =

Paying members can download the available *App extensions* and can download updates to those extensions.

Study the available [Membership plans](https://www.dashed-slug.net/dashed-slug/membership-plans/)


= What payment methods are available =

The site accepts Bitcoin and Ethereum. Please [deposit the correct amount to your account, then choose a subscription](https://www.dashed-slug.net/dashed-slug/membership-plans/).

[Since 1 November 2022](https://www.dashed-slug.net/important-changes-to-membership/),
membership is implemented using the plugin itself. The downloads are protected behind a paywall
using the [Paywall extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/paywall-extension/).

Previously the site accepted PayPal recurring payments. If you have already signed up using PayPal, you can continue to use it to pay for membership. New PayPal accounts are no longer available.

If you wish to pay via a different method, [contact me by email](https://www.dashed-slug.net/contact/).

You can send a PayPal payment to my business email address and let me know. I will then activate your membership manually, within 1 business day.

= I cannot download the premium plugins after paying for membership. =

If you have paid for an EU business plan, you must provide a valid VATIN. Please enter the VATIN without the country code prefix, and enter the correct _City_ and _Country_ for your business, in your profile details.

If you have paid for a regular plan, and for some reason you still cannot download the premium plugins, please [contact me by email](https://www.dashed-slug.net/contact/).

= How can I cancel my membership? =

If you have signed up with a PayPal recurring payment, you can go to your [PayPal dashboard](https://www.paypal.com/cgi-bin/webscr?cmd=_manage-paylist) to cancel the recurring payment.

Additionally, if you wish, you may delete your account from the profile screen on the dashed-slug website. Deleting your account does not automatically cancel your PayPal subscription. Simply visit [your profile](https://www.dashed-slug.net/membership-login/membership-profile/)

If you have paid via cryptocurrencies, there is no need to cancel. You can delete your account if you want, by visiting [your profile](https://www.dashed-slug.net/membership-login/membership-profile/). There is usually no need to do so.

= I am not happy with the plugin. Can I ask for a refund? =

You can ask for a refund of any payment within 30 days from the day of payment, no questions asked. Please contact me by email.





= Are you available for custom development work? =

Unfortunately I am not available for custom development work.


= Can you install the plugin for me? =
I do not undertake installations. I remain available to assist and answer any questions about the installation and configuration process.

> Regarding plugin installations, please consider this: Unless you know how the plugin works, you will not be able to provide support to your users, or fix issues when these arise. If you are not a developer, you should probably hire a developer to perform the installation and maintenance.


= Can you add XYZ feature? =

You can always suggest a feature to me. If it makes sense and I have the time, I might implement it. I do not make promises on this, therefore I do not accept payment for features.


= I am encountering some problem or I have another question. =

First check the *Troubleshooting* section of the documentation. The answer to your question may be listed there.

If you cannot find the answer to your question, please consult the documentation under _Contact Support_.


= How can I reach you over IM? =

I speak daily with many people, while I also do the development, testing, management, marketing, and everything else.

For this reason, I am NOT reachable over chat apps.

Please state your request on the forums or over email, and I will respond within 24 hours, Monday to Friday. If you are encountering an error, please show me the error message in a screenshot. Try to explain what you did so far and how you arrived at the error.


== Screenshots ==

1. **[wallets_deposit]** - Users can view their deposit addresses, create new addresses, and associate them with a label.
2. **[wallets_move]** - Users can transfer funds to other users on the system, off-chain. Whether they pay fees to your site is up to you.
3. **[wallets_withdraw]** - Users can request to withdraw funds to an external wallet.
4. **[wallets_balance]** - Users can view their balances, either one-by-one, or as a list. Equivalent amounts can be shown in USD, EUR, BTC or other currencies that you choose.
5. **[wallets_transactions]** - Users can view paginated details on their past transactions. You can choose which columns are rendered and in what order.
6. **Wallets post type** - Easily manage your various wallet backends via the Wallet post type.
7. **Wallet post type** - The wallet post type encapsulates all connection settings with your wallet's API and displays wallet status.
8. **Currency post type** - Associate the currencies you want to offer with your wallets, using the Currecy post type. Easily edit settings related to each currency. Group currencies together using a special Currency Tags taxonomy.
9. **Address post type** - Keep track of deposit and withdrawal addresses. Easily edit address details, or list transactions associated with addresses. Group addresses together using a special Address Tags taxonomy.
10. **Transaction post type** - Easily edit transaction details or create new transactions. Group transactions together using a special Transaction Tags taxonomy.
11. **Capabilities settings** - Since version 6.0.0, all settings are neatly organized into tabs, both for the plugin and for its premium extensions.

== Changelog ==

= 6.3.2 =
- Fix: Security issue where a logged in user with access to the admin screens could perform XSS attacks is now fixed.
- Fix: Deprecation warnings in PHP8.4 about implicitly nullable types are now squashed.
- Improve: Along with easycron, the plugin also now suggests cron-job.org which is a free alternative.

= 6.3.1 =
- Fix: Issue regarding editing addresses/transactions via the admin screens, introduced in 6.2.6, is now fixed.

= 6.3.0 =
- Add: It is now possible to submit bank transfer requests with a combination of SWIFT/BIC and Account Number. This will be useful for African countries.
- Improve: Saving transactions and addresses to the DB is now much faster. This should improve the UI experience when submitting transactions.
- Fix: When submitting a bank transfer request, it is no longer possible to click the submit button again while another request is underway.

= 6.2.7 =
- Fix: Issue with withdrawal fees not loaded, introduced in 6.2.6 is now fixed.
- Fix: Issue with transaction emails not being sent in some environments, introduced in 6.2.6, is possibly now fixed (CNR).

= 6.2.6 =
- Change: When the plugin loads batches of Wallets, Currencies, Transactions, or Addresses, it now does it faster and with less SQL queries to the DB to improve performance.
- Add: New built-in object cache to further improve DB performance. Can be turned off in general settings.
-

= 6.2.5 =
- Fix: Issue preventing the plugins from running on Windows (XAMPP).
- Fix: Deprecation warning in WP-REST API currency endpoints.
- Fix: Plugin header now correctly indicates that the plugin requires PHP 7.0 or later.
- Fix: `DSWallets\Transaction` objects now correctly load the timestamp field from the DB.

= 6.2.4 =
- Fix: Issue with Fiat Withdrawals not checking for the `has_wallets` capability correctly is now fixed.
- Add: Extensible way to exclude some currency tags from `[wallets_deposit]` and `[wallets_withdraw]` shortcodes, for the upcoming Taproot Assets adapter.

= 6.2.3 =
- Fix: Clicking the re-scrape button in Bitcoin-like wallet adapters now refreshes the page again.

= 6.2.2 =
- Fix: Issue introduced in `6.2.0` where wallets were prevented from being enabled/disabled, now fixed.
- Fix: Minor warning in logs when saving bitcoin wallet adapters.
- Add: The debug tab in the Dashboard panel now shows the database version.
- Add: Mention of rpcbind in accompanying installation instructions.

= 6.2.1 =
- Fix: Issue introduced in `6.2.0` where the wallet admin screen would crash on new installations is now fixed.
- Add: Generic error handling for when the metabox of a wallet adapter crashes.
- Add: New REST API endpoint for retrieving an Address by its post ID. See the REST API documentation for details.
- Change: The wording for the description for the "Contract Address" field under Addresses is now changed to include Taproot Asset IDs for the upcoming tapd Wallet adapter.

= 6.2.0 =
- Add: New feature for Bitcoin Core (and similar) wallet adapters: Admin can now restart scraping from a specific block height.
- Fix: If another theme or plugin loads an old version of Parsedown, causing ParsedownExtra to fail to load, the documentation viewer falls back gracefully to whatever Parsedown is currently loaded.
- Change: The "Contract address" field for "Currency" entries can now accept Asset ID hex strings. This is necessary for the upcoming Taproot Assets Wallet Adapter.
- Improve: If cron jobs are not running, the warning message in the admin screens now links to the relevant documentation.

= 6.1.10 =
- Fix: Fixed issue where the PHP curl library would not return meaningful error messages to the admin user in case of connection failures.
- Add: When actions are initiated via the frontend UIs by clicking a button, the button is now animated until the action completes. Additionally, the actions are performed asynchronously for a better user experience.
- Remove: Duplicate display of Confirmation link in withdrawal transactions is now removed. The Confirmation link is shown once at the top left part of the screen.
- Remove: The "post slug" metaboxes are not useful and have been removed from the following CPTs: Wallets, Currencies, Addresses, Transactions.
- Remove: The plugin's CPTs are no longer available to be added to frontend menus, since the posts themselves are only useful in the backend.
- Add: The PHP templates now come with a warning about not editing the templates in-place. The warning links to the documentation where the admin can learn more about this.
- Add: Troubleshooting section in documentation now has more information on how to restore the `manage_wallets` capability to the admin user.

= 6.1.9 =
- Fix: Silly syntax errors introduced right before summer vacation.

= 6.1.8 =
- Add: New setting allows for deposits to be ignored if they have a timestamp earlier than a set cutoff value.
- Add: When initiating an addresses and balances-only migration, the deposit cutoff value is set to the current timestamp.

= 6.1.7 =
- Fix: Issue introduced in 6.1.6 which prevented admin transaction editing is now fixed.
- Fix: If you have setup walletnotify for Bitcoin core and similar wallets, the notification API is now disabled while migration is underway.
- Change: The `walletnotify` and `blocknotify` WP-REST API endpoints associated with Bitcoin core and similar wallets now returns uniform error messages. The HTTP status is not always 200, but reflects the error that was encountered. The value of the returned `status` field is no longer success/error but is the HTTP response code, which is 200 when the call was successful.

= 6.1.6 =
- Change: In the plugin's general settings, the maximum deposit address limit is now applied per user and per currency. Was previously applied per user only over all currencies.
- Fix: When listing transactions in the admin screens, if the associated currency has block explorer URIs, these are now used to link addresses and TXIDs to a block explorer.
- Add: New filter `wallets_tags_exclude_min_withdraw` allows a currency's minimum withdrawal amount to not apply to transactions with the specified tags. This will allow the upcoming lnd extension to bypass this restriction for Layer2 transactions.
- Improve: Transactions that are not modified will no longer be re-written to the DB. This improves performance, plus it allows the last modified timestamp to remain intact in case of no actual modification of transaction data.
- Fix: The new mechanism for detecting whether a camera is available for scanning QR-Codes, introduced in `6.1.4`, was buggy and is now fixed.

= 6.1.5 =
- Fix: Usage of words "debit" and "credit" is now correct according to how the terms are used in everyday language, as opposed to their usage in accounting.

= 6.1.4 =
- Fix: Shortcode `[wallets_fiat_withdraw]` does not submit withdrawal requests with invalid amount.
- Add: Shortcode `[wallets_fiat_withdraw]` now accepts atttributes: `currency_id`, `symbol` to specify the default currency.
- Add: New WP-REST API endpoint `/users/U/currencies/C` for retrieving a single currency `C` and the balances of user `U` for that currency.
- Improve: Added a new withdrawal check: It is now impossible for a withdrawal to be executed as long as it has a TXID assigned. If you need to repeat execution, you must clear the TXID.
- Improve: The `[wallets_withdraw]` UI now uses a `getUserMedia()` call to determine if a camera is available to scan QR-Codes. Previous method using CSS media queries was not optimal.
- Improve: The included copy of `moment.js` is now upgraded to version `2.29.4`.

= 6.1.3 =
- Fix: Setting the `currency_id` attribute on the `[wallets_deposit]` shortcode now works again.
- Fix: Listing the addresses of a specific user now works again.
- Fix: Listing the addresses of a specific type (deposits, withdrawals) now works again.
- Improve: The transactions editor now loads faster on sites with many thousands of users. The user dropdown is replaced with an text input where you type a `user_login` and it autocompletes with AJAX.
- Improve: It is now possible to search addresses by keyword, and the keyword is matched against the address title AND the address string.
- Add: Addresses with the `archived` tag will not be shown in the REST API and frontend. This will be useful for the Lightning adapter later, among other things.

= 6.1.2 =
- Fix: Issue with editing transactions in admin when system holds too many transactions is now fixed.

= 6.1.1 =
- Fix: Issue with transactions search in admin is now fixed.
- Fix: When editing wallet adapter settings, it is now possible to uncheck boxes for boolean values.

= 6.1.0 =
- Add: New option "Admin must approve withdrawals". When on, pending withdrawals must be approved by an admin in the Transactions list screen using the "Approve" batch action.
- Add: The frontend "VS amounts", i.e. the equivalent amounts expressed in "VS currencies", are now shown with a number of decimals that is configurable by the admin.
- Add: Admin can now search transactions by TXID.
- Fix: Admin can now search for cancelled or failed transactions.
- Fix: When filtering posts, all enabled filters are now shown in bold and with ARIA accessibility attributes.
- Add: For pending withdrawals, admin can view the results of withdrawal pre-checks.
- Fix: Daily withdrawal counters for currencies are now correctly enforced at all times.
- Add: Daily withdrawal counters are now visible in the profile screen of each user.
- Add: For pending withdrawals, developer can add withdrawal pre-checks with action `wallets_withdrawal_pre_check` and with filter `wallets_withdrawals_pre_check`.
- Fix: When showing `[wallets_account_value]` shortcode without any enabled "VS currencies", the account value is now shown as '?' and not 'Undefined ?'.
- Fix: Documentation updated to reflect changes in premium plugin payment method and added mention to new Paywall extension.
- Fix: Improved logging for wallet adapters cron task.
- Fix: Added guard clause in currency icons cron task to protect against missing returned data from remote service.


= 6.0.0 =
- Change: Sourcecode rewritten from scratch and modernized for PHP7.x compatibility: Namespaces, callbacks, etc.
- Change: Coin adapters removed and replaced with wallet adapters. Wallet adapters are a more versatile abstraction of external wallets.
- Change: All amounts stored on the DB are now stored as integers. This avoids FP/rounding errors, but requires the admin to specify the number of decimals on a Currency explicitly.
- Add: Wallets now have their own CPT and editor screen. Connection information for wallet APIs is now decoupled from currency details.
- Add: Currencies now have their own CPT and editor screen. Currency details are now decoupled from wallet connection information details.
- Change: Currencies are no longer identified by their ticker symbol, since these can clash. Currencies are now uniquely identified by their post ID.
- Change: Transactions now have their own CPT and editor screen. Transactions are no longer stored in custom SQL tables, but are stored as posts.
- Add: If a transaction is saved without a comment, a basic comment is entered as a title.
- Change: Addresses now have their own CPT and editor screen. Addresses are no longer stored in custom SQL tables, but are stored as posts.
- Add: When creating a new address, if admin sets the address string empty, a new address string is created from the wallet.
- Add: Currencies, Transactions and Addresses can be organized via tags. These are implemented as WordPress custom taxonomies.
- Add: When listing Wallets, these can be filtered by Wallet Adapter type.
- Add: When listing Currencies, these can be filtered by associated Wallet or Currency tag.
- Add: When listing Transactions, these can be filtered by Currency, User, Type (deposit/withdaw/move), Status (pending, done, cancelled, failed), or Transaction tag.
- Add: When listing Addresses, these can be filtered by Currency, User, Type (deposit/withdaw/move), Status (pending, done, cancelled, failed), or Transaction tag.
- Add: When editing Wallets, Currencies, Transactions, or Addresses, there are now metaboxes that let an admin easily jump between associated CPTs. For example, can click a link to go from a Currency to the Transactions of that Currency.
- Add: A new "Migration" tool helps users transfer balances from the old custom SQL tables to the new format. Please study the documentation for details.
- Add: A new Object-Oriented way to create and manipulate Wallets, Currencies, Addresses and Transactions. Lots of example code snippets included in the docs and PHPdocumentor files.
- Add: A documented library of helper functions can be used by PHP developers to interface with the plugin. This lives in the `DSWallets` namespace.
- Change: The old PHP-API is renamed as "Legacy PHP-API", but not removed. Only the `wallets_api_adapters` filter is removed, since there are no more Coin Adapters.
- Add: A new cron job scheduler that runs jobs based on priorities. Can be triggerred externally to improve site performance.
- Add: New email queue system based on cron, handles large volumes of outgoing emails by sending them in batches.
- Add: New cron job that iterates over defined currencies and pulls icons/logos from CoinGecko when possible. Adds images to the WordPress media collection, and sets the logo as Currency featured image. Admin can override the featured image.
- Change: All the plugin's settings are now placed under the *Settings* menu, and organized in tabs. Extensions, when installed, add tabs to the settings screen.
- Change: Capability settings are now in a settings tab. The existing capabilities are retained. It is also possible to edit the capabilities related to the new CPTs (Wallets, Currencies, Addresses, Transactions).
- Add: Fiat currencies are now created automatically by a cron job when you enter a fixer.io API key. Fiat currencies can be associated to the built-in wallet adapter that handles manual Bank transfers. The Fiat Coin Adapter extension is removed.
- Change: It is now possible to define any number of Bitcoin-like wallets and currencies without any plugin extensions. The Full Node MultiCoin Adapter extension is removed.
- Change: The UIs are now self-contained. All the HTML markup, knockout.js attributes, JavaScript code, and CSS rules are encapsulated within the template files, and are overridable.
- Change: The UIs are now rendered independently. This improves frontend performance. If one UI crashes, the remaining UIs continue to work.
- Remove: There are no more static templates. All templates are dynamic. This reduces confusion about how templates work.
- Add: Currencies can be used by users even if not associated with a Wallet, or if the wallet API is unreachable. If wallet API is unreachable, only deposits and withdrawals will not be available, but on-site transactions are still possible.
- Add: New shortcode `[wallets_status]` informes users if a wallet is online.
- Change: The exchange rates mechanism now relies on CoinGecko. If the admin provides the CoinGecko ID for a Currency, the exchange rates are retrieved automatically via cron.
- Improve: In the Currency editor, if a currency is not associated with a CoinGecko ID, it is still possible for an admin to setup a fixed exchange rate for a currency.
- Improve: The admin can choose against which well-known currencies the exchange rates are stored (these are the "VS currencies" and correspond to CoinGecko data).
- Improve: The frontend UIs, when they display equivalent amounts in other currencies, no longer display amounts in one fixed site currency. User can click to rotate between displaying the equivalent amounts in all available "VS currencies".
- Add: Daily withdrawal limits per Currency. Can be set for all users, or per user role.
- Add: More information in the plugin's widget in the dashboard screen.
- Add: A new WP-REST API which utilizes the RESTful API mechanism built into WordPress replaces the old JSON-API.
- Change: The old JSON-API is now marked as deprecated and is disabled by default. Can be enabled in settings for backwards compatibility.
- Add: A new system that displays documentation in the admin screens. Some screens feature direct links to the relevant documentation. As extensions to the plugin are installed, their documentation books are added to the system.
- Change: The user profile screen has been improved. Lists all balances held by a user and links to the user's lists of Addresses and Transactions.
- Change: The cold storage UI screens have been improved.
- Remove: The frontend Widgets have been removed. Please use the shortcodes instead.

== Upgrade Notice ==

Version `6.3.2` is a security patch. Please upgrade ASAP.

== Donating ==

This is a free plugin built by the [dashed-slug team](https://www.dashed-slug.net/dashed-slug/team)!

- Bitcoin: `1DaShEDyeAwEc4snWq14hz5EBQXeHrVBxy`
- Litecoin: `LdaShEdER2UuhMPvv33ttDPu89mVgu4Arf`
- Dogecoin: `DASHEDj9RrTzQoJvP3WC48cFzUerKcYxHc`
