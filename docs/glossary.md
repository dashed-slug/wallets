# Glossary

*Bitcoin and Altcoin Wallets* is made up of a lot of components that each have their own name.

They say that there's only two hard problems in CS: cache invalidation, and naming things. *Bitcoin and Altcoin Wallets* certainly has had problems with cache invalidation in the past; these have been adressed with various workarounds. As for naming things, every little thing in the plugin has a name, and I try to keep the nomenclature consistent.

Here's a glossary of the major technical terms used:

## ABA Routing number and Account number {#aba}

An American Bankers Association Routing number and an account number are the details needed to perform a bank transaction in the USA. When performing [fiat deposits](#fiat-deposits)/[withdrawals](#fiat-withdrawals), this is one of the options for "Bank addressing method".

To create an *[Address post type][post-type-address]* with such details, enter the following JSON data in the address's *Title*:

	{ "routingNumber": "123456789", "accountNumber": "1234567"}

Replace the numbers in the JSON with your actual *Routing number* and *Account number*.

## Activation code {#activation-code}

*[Premium dashed-slug members](#premium-membership)* receive an *Activation code*. This code allows the plugin's extensions to contact the update server on dashed-slug.net. The plugins will receive updates as long as your membership lasts. To enter the code to the plugin, go to: [Settings](#settings) &rarr; _Bitcoin and Altcoin Wallets_ &rarr; Updates &rarr; Activation code.

## Adapter {#adapter}

See [Wallet Adapter](#wallet-adapter).

## Adapter type {#adapter-type}

The PHP class name of a *[wallet adapter](#wallet-adapter)* object.

To see the various available classes, visit the reference on the [Wallets custom post type object][post-type-wallets].

## Address {#address}

*Addresses* can be blockchain addresses for *[cryptocurrencies](#cryptocurrency)*, or bank account addresses for *[Fiat currencies](#fiat-currency)*.

*Addresses* are stored on the DB as custom posts of type `wallets_address`.

To learn more about the corresponding custom post type, see *[Address post type][post-type-address]*.


## Address string {#address-string}

The string of a crypto address. This is the main string, excluding any *[extra info](#extra-info)*.

It is stored as the `wallets_address` post meta of a post of type `wallets_address`.

## Address tags {#address-tags}

You can organize your *[Addresses](#address)* using tags, with the `wallets_address_tags` custom taxonomy.

## Admins {#admins}

Admins in the context of the plugin, are users who have the `manage_wallets` [capability](#capabilities). This capability is assigned, when the plugin is first activated, to all users with `manage_options`. These are usually the administrators.

Administrators may receive certain types of emails about the site's operation.

> **DEVELOPERS**: To send an email to the administrators, the plugin's code uses the `DSWallets\wp_enqueue_mail_to_admins()` helper function. This takes a `$subject` and a `$message`, and enqueues an email for all admins, to be sent asynchronously on [cron](#cron).

## Amount pattern {#amount-pattern}

This is an [sprintf()][php-sprintf] pattern that is set on each *Currency*. It specifies how amounts are to be rendered for this currency.

For example, with Bitcoin you might use `BTC %01.8f` as your amount pattern.

## Archived address {#archived-address}

An *[Address](#address)* with the special tag slug: `archived`. Such an address will not be shown in the frontend. Deposit addresses marked `archived` will continue to be usable as deposit addresses.

## App extension {#app-extension}

An *[extension](#extension)* to the parent plugin, *[Bitcoin and Altcoin Wallets](#bitcoin-and-altcoin-wallets)*, that provides useful functionality. Contrast with *[Wallet adapter extension](#wallet-adapter-extension)*.

## Available Balance {#available-balance}

Part of a [user's balance](#user-balance)* may be locked in either *[pending](#pending)* *[transactions](#transaction)*, or in Exchange market orders which have not yet been filled. This balance cannot be withdrawn or traded until the transaction or order is executed/cancelled/failed.

The remaining balance that is not locked in this way, is free to be withdrawn or sent to other users or traded. This is the *Available balance*.

## Balance {#balance}

Ambiguous. Can refer to either *[User's balance](#user-balance)* or to *[Hot Wallet balance](#hot-wallet-balance)*.

## Bank Fiat adapter {#bank-fiat-adapter}

A special *[wallet adapter](#wallet-adapter)* that does not actually communicate with a wallet. Instead, it allows the admin to keep track of manual deposits and withdrawals via a bank account.

Previously this was a separate extension, but is now built-in to the plugin.

Admins can do the following to let users interact with this wallet adapter:

- Assign the `DSWallets\Bank_Fiat_Adapter` to a [Wallet](#wallet). This wallet will handle all fiat bank transactions.
- Assign some [Fiat currencies](#fiat-currency) to the wallet.
- Fill in banking deposit details as needed. Leave blank any deposit details for bank/currency pairs that will not be supported.
- Use the shortcodes `[wallets_fiat_deposit]` and `[wallets_fiat_withdrawal]` on a page. This will display the banking UI.

## Bitcoin and Altcoin Wallets {#bitcoin-and-altcoin-wallets}

The parent plugin, created by [dashed-slug.net](#dashed-slug) and hosted on wordpress.org.

A plugin that keeps track of cryptocurrency wallets, cryptocurrencies and fiat currencies, and addresses and transactions.

Features a modular architecture, where it can be [extended](#extension) via [Wallet adapters](#wallet-adapter) and [App extensions](#app-extension).

## Block explorer URI pattern for addresses {#block-explorer-uri-pattern-for-addresses}

Block explorer URI patterns are settings that an admin can set on a *[Currency](#currency)*.

When an [Admin](#admins) edits an *[Addresses](#address)*, it is possible to specify how this address can be linked to a block explorer, when rendered for the user. Block explorers are websites allowing visitors to browse transactions and addresses on a blockchain. The admin enters an `[sprintf][php-sprintf]` pattern for the link, where the `%s` placeholder gets substituted with the address string.

For example, the following is a block explorer URI pattern for displaying Bitcoin addresses on *chain.so*:

	https://chain.so/address/BTC/%s

Previously it was possible to change these links only using the `wallets_explorer_uri_add_SYMBOL` WordPress filter, where `SYMBOL` is the ticker symbol of the currency. In version `6.0.0`, an admin can enter the links directly in the *Currency* details. The filter is no longer needed, but is kept for compatibility. The filter can modify or override the values entered by the admin in the *Currency* details.

## Block explorer URI pattern for transactions {#block-explorer-uri-pattern-for-transactions}

Block explorer URI patterns are settings that an admin can set on a *[Currency](#currency)*.

When an [Admin](#admins) edits an *[Transaction](#transaction)*, it is possible to specify how this transaction can be linked to a block explorer, when rendered for the user. Block explorers are websites allowing visitors to browse transactions and addresses on a blockchain. The admin enters an `[sprintf][php-sprintf]` pattern for the link, where the `%s` placeholder gets substituted with the transaction's TXID.

For example, the following is a block explorer URI pattern for displaying Bitcoin transactions on *chain.so*:

	https://chain.so/tx/BTC/%s

Previously it was possible to change these links only using the `wallets_explorer_uri_add_SYMBOL` WordPress filter, where `SYMBOL` is the ticker symbol of the currency. In version `6.0.0`, an admin can enter the links directly in the *Currency* details. The filter is no longer needed, but is kept for compatibility. The filter can modify or override the values entered by the admin in the *Currency* details.

## Cancelled {#cancelled}

The *[status of a transaction](#transaction-status)* that was cancelled manually by an [admin](#admins).

The post of a cancelled transaction will have `post_status=draft`, and the value of the `wallets_error` post meta will be empty.

## Capabilities {#capabilities}

1. For the general concept of WordPress Capabilities, see [the wordpress.org Support page on Roles and Capabilities][wordpress-caps].

2. In the context of the *[Bitcoin and Altcoin Wallets](#bitcoin-and-altcoin-wallets)* plugin, the capabilities are those with a slug that begins with `wallets_`. They control access to various user wallet actions and to admin management. To learn more about the various *Capabilities*, visit the *[Capabilities Settings][capabilities-settings]*.

## Coin adapter {#coin-adapter}

In versions previous to `6.0.0`, coin adapters were what the plugin used to communicate with wallets. Since `6.0.0`, the concept of the coin adapter is decomposed into *Wallet Adapters* and *Currencies*. This newer specification is more flexible, because it decouples the details of a *[wallet](#wallet), from those of the [currency](#currency) or currencies it holds.

## CoinGecko ID {#coingecko-id}

Each cryptocurrency can receive exchange rates data from CoinGecko. To do this, you can specify the cryptocurrency's unique ID on CoinGecko. This allows the plugin to query the CoinGecko API for [exchange rates](#exchange-rates) for this currency.

To learn how you can set the CoinGecko ID on a currency easily, go to: _The Post Types_ &rarr; _Currencies_ &rarr; _Fields_ &rarr; _[Currency exchange rates][currency-exchange-rates]_.

## CoinGecko VS currencies {#coingecko-vs-currencies}

When the plugin requests [exchange rates](#exchange-rates) for cryptocurrencies from the CoinGecko API, it can retrieve the exchange rates against a specific number of well-known currencies. These are the *VS currencies*.

To select which *VS Currencies* to download rates for, go to: _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Exchange rates_ &rarr; _CoinGecko vs currencies_. Pick only the currencies that you believe your users will be familiar with.

## CoinPayments adapter {#coinpayments-adapter}

A wallet adapter that allows you to use wallets hosted by the third-party service [CoinPayments.net][coinpayments-net].

The wallet adapter can handle currencies for multiple wallets.

You do not have to create cryptocurrency entries for each wallet. When the wallet adapter connects to your CoinPayments account, it will check which currencies you have enabled, and it will create the currencies, and link them to the wallet.

For more information see the [CoinPayments adapter home page][coinpayments-adapter].

## Cold Storage {#cold-storage}

A wallet that holds a part of the website's funds and is offline. That is, there is no way for the system where WordPress runs to withdraw funds from the cold storage wallet even if the system is compromised. Only the site operator should be able to transfer funds to and from cold storage.

## Confirmation {#confirmation}

1. In the general context of cryptocurrencies, see [the Bitcoin wiki page for Confirmation][wiki-conf].

2. In the context of the *[Bitcoin and Altcoin Wallets](#bitcoin-and-altcoin-wallets)* plugin, a transaction requires confirmation if its `wallets_nonce` meta is set. The user who placed the transaction must click a link in their email to confirm the transaction. The link contains a nonce specific to the transaction. If the nonce is correct, it is removed from the transaction. The transaction then becomes eligible to be executed.

## Debit transaction {#debit-transaction}

A debiting *internal transfer*. Subtracts from the user's [balance](#user-balance).

Any *[internal transfer fees](#internal-transfer-fees)* are charged on the sender's balance via this transaction.

The amount transacted is stored as a negative value on the transaction post.

On the DB, the *[debit transaction](#debit-transaction)* is the parent post of the counterpart *[credit transaction](#credit-transaction)*.

A complete *internal transfer* between users will typically include two transaction counterparts:
- The *debit transaction*. Subtracts from the sender's balance.
- The *[credit transaction](#credit-transaction)*.

## Cron {#cron}

On every "heartbeat" of the plugin:
- a number of transactions are executed
- *wallet adapters* can perform their own actions such as detecting transactions or other checks
- other maintenance tasks run

Normally *cron* is triggered by the wp cron mechanism, which in turn can be triggered by a Linux cron scheduler if needed. However the plugin's cron is guaranteed to run eventually even in installations where the other mechanisms are absent.

## Cron job {#cron-job}

Any of a number of housekeeping tasks associated with the plugin. These are tasks that run periodically.
See also *[Cron tasks]{#cron-tasks}*.

## Cron tasks {#cron-tasks}

Any of a number of housekeeping tasks associated with the plugin. These are tasks that run periodically. See also *[Cron job]{#cron-job}*.

## Cryptocurrency {#cryptocurrency}

For the purposes of the plugin, any *[Currency](#currency)* that is not a *[Fiat currency](#fiat-currency)*.

## Currency {#currency}

*Currencies* can be *[cryptocurrencies](#cryptocurrency)*, or *[Fiat currencies](#fiat-currency)*.

*Currencies* are stored on the DB as custom posts of type `wallets_currency`.

To learn more about the corresponding custom post type, see: _The Post Types_ &rarr; _[Currency post type][post-type-currency]_.


## Currency icon {#currency-icon}

The logo or icon of a currency can be set by an administrator.

For best results, find a rectangular PNG with transparency, but any image will do.

As an [Admin](#admins), you can set the image as the "featured image", when editing a *[Currency](#currency)*.

The icon is displayed in the *[frontend UIs](#frontend-ui)*.

## Currency tags {#currency-tags}

You can organize your *[currencies](#currency)* using tags. These tags are part of the `wallets_currency_tags` custom taxonomy.

## Customizer {#customizer}

The [Customizer][customizer] is a WordPress feature that lets you easily control visual aspects of your theme and plugins.

The plugin adds a section in the Customizer titled *Bitcoin and Altcoin Wallets*.

Use the settings in this section to control visual features of the *[frontend UIs](#frontend-ui)*.

For more information see: _Frontend_ &rarr; _[Modifying styles with Customizer][frontend-customizer]_

## Dashboard widget {#dashboard}

The [WordPress Dashboard][wordpress-dashboard] panel is an area where admins can have a quick overview of the site's traffic.

*[Bitcoin and Altcoin Wallets](#bitcoin-and-altcoin-wallets)* adds a tabbed widget in the dashboard panel.

To learn more about the dashboard widget, go to: *[Dashboard][wordpress-dashboard]*.

## dashed-slug {#dashed-slug}

Software house that churns out cryptocurrency plugins for WordPress at an alarmingly sluggish rate. The dashed-slug is just a [slug](https://www.hostinger.com/tutorials/what-is-a-wordpress-slug/) with dashes, i.e. it's the best kind of slug.

## Credit transaction {#credit-transaction}

A crediting *[internal transfer](#internal-transfer)*. Adds to the recipient's [balance](#user-balance). The amount is stored as a positive value on the transaction post. There are no fees associated with this transaction. On the DB, it is marked as the child post of the counterpart *[debit transaction](#debit-transaction)*.

## Debug info {#debug-info}

*Debug info* is a collection of information about your system, that is displayed in the *[Dashboard widget](#dashboard)*. It is there to help with debugging.

## Deposit {#deposit}

A *transaction* on the DB ledger which shows that someone has sent funds from the cryptocurrency network, to a user *[deposit address](#deposit-address)*.

First, the transaction is noticed by the site's hot wallet. The wallet notifies the plugin about the new TXID, and the plugin queries the *[Wallet Adapter](#wallet-adapter)* for details on this TXID. If data is available, the plugin will create a deposit transaction for the user on the DB ledger, and the user's balance will increase. The user will also receive *Notifications* about the status of the deposit.

Since version `6.0.0`, arbitrary deposits can be created easily by an admin.

## Deposit Address {#deposit-address}

A cryptocurrency [address](#address) with which a user can perform a *[deposit](#deposit)* to their *[User balance](#user-balance)*, by sending coins/tokens to that address. Every user has one or more *deposit addresses* for each *[currency](#currency)*. This new address is generated by the *[wallet](#hot-wallet)*, via the *[wallet adapter](#wallet-adapter)*.

## Deposit code {#deposit-code}

When performing *[Fiat deposits](#fiat-deposits)*, a user has to perform a bank transfer, and attach a *Deposit code* as a reference code to the transfer. This code is unique to each user, and allows the plugin and its operator to credit the correct user upon entering the deposit to the *Fiat deposits* *[tool](#tools)*.

## Documentation {#documentation}

The plugin's documentation is composed of:
- This documentation, a collection of Markdown files
- [PHPDocumentor documentation][phpdoc], auto-generated from the plugin's source code. Available at http://wallets-phpdoc.dashed-slug.net/ .

## Done {#done}

The *[status of a transaction](#transaction-status)* that has been performed successfully.

The post of a done transaction will have `post_status=publish`.

## Εmail notification {#email-notification}

A *[notification](#notification)* is a message that informs the user about a change in the *[status](#transaction-status)* of a *[Transaction](#transaction)*.

Notifications are typically sent over email. The content is based on that of [Email templates](#email-templates).

Developers can intercept *notifications* and deliver them to users via other means besides email. For more information on how to do this, capture the `wallets_email_notify` action, which takes as argument the [transaction](#transaction) object:

	add_action(
		'wallets_email_notify',
		function( Transaction $tx ) {
			// TODO use the transaction data in $tx to notify the user
		}
	);


## Email queue {#email-queue}

Whenever the plugin emails users, the outgoing email data is placed on a queue, and is sent asynchronously in batches, using WordPress `wp_mail()`.

The queue is an array stored in a WordPress option, `wallets_email_queue`.

On a Linux system with [wp-cli][wp-cli] installed, you can monitor the outgoing email queue with:

	watch wp option get wallets_email_queue

## Email templates {#email-templates}

The template files used for rendering [email notifications](#email-notification) are:

	wp-content/plugins/wallets/templates/email-*.php

Do not edit the files in place. To see how to override the templates, see: _Frontend_ &rarr; _Modifying the UI appearance_ &rarr; _[Editing the template files][editing-templates]_.


## Exchange rates {#exchange-rates}

Exchange rates are data stored on the *Currencies*. The exchange rates of a *[Currency](#currency) can be defined against *[VS Currencies](#coingecko-vs-currencies)*.

For more information about setting *Exchange rates* to *Currencies*, refer to: _The Post Types_ &rarr; _Currencies_ &rarr; _Fields_ &rarr; _[Currency's exchange rates][currency-exchange-rates]_.

## Extension {#extension}

A plugin that extends *[Bitcoin and Altcoin Wallets](#bitcoin-and-altcoin-wallets)*. See *[App extension](#app-extension)* and *[Wallet adapter extension](#wallet-adapter-extension)*.

## Extra field {#extra-field}

*[Addresses](#address)* contain a primary Address string, which is usually the entire address. However, with some currencies and blockchain technologies, an address can be supplemented with additional information about the transaction's destination. Examples include the *Monero "Payment ID"* and the *Ripple (XRP) "Destination Tag"*. This second field is collectively called the *Extra field*. The actual name of the field is determined by the *Wallet adapter*, not the *Currency* entry.

## Failed {#failed}

The *[status of a transaction](#transaction-status)* that did not succeed, usually due to some error.

The post of a failed transaction will have `post_status=draft`, and the value of the `wallets_error` post meta will be non-empty. This `wallets_error` value is the error message associated with the failure.

## Fees {#fees}

Fees are subtracted from *[User balances](#user-balance)* and remain on the site's *[Hot wallet balance](#hot-wallet-balance)*.

In the case of [crypto](#cryptocurrency) [withdrawals](#withdrawal), the fees are first used to pay for the blockchain's network fees; any remaining fees stay with your site's wallet.

In the case of internal transfers between users, the entirety of the paid fees is subtracted from the sender's balance and remains with your wallet, since there is no blockchain transaction.

## Fiat {#fiat}

For a definition of *Fiat* money see: [Investopedia on Fiat Money][investopedia-fiat-money].

## Fiat currency {#fiat-currency}

A *[Currency](#currency)* that represents *[fiat money](#fiat)*.

Fiat currencies can be auto-generated from *[fixer.io][fixer-io]*. They get assigned the `fiat` and `fixer` tags in the `wallets_currency_tags` custom taxonomy.

The plugin will create *[Currencies](#currency)* for all known *Fiat currencies*, from fixer.io. The plugin will keep the exchange rates of these currencies updated. For details, see [FixerIO_Task][fixerio-task].

## Fiat Deposits {#fiat-deposits}

Users can perform bank wire transfers to deposit *[Fiat Currencies](#fiat-currency)* using the wallets shortcode: `[wallets_fiat_deposit]`.

For details on how this works, see: _Tools_ &rarr; _[Fiat Deposits][fiat-deposits]_.

## Fiat Withdrawals {#fiat-withdrawals}

Users can request bank wire transfers to withdraw *[Fiat Currencies](#fiat-currency)* using the wallets shortcode: `[wallets_fiat_deposit]`.

For details on how this works, see: _Tools_ &rarr; _[Fiat Withdrawals][fiat-withdrawals]_.

## Fixed exchange rates {#fixed-exchange-rates}

Normally the [exchange rates](#exchange-rates) of a currency will be auto-updated by [cron tasks](#cron-tasks):

- If it is a *[Cruptocurrency](#cryptocurrency)*, exchange rates are updated from *CoinGecko*.

- If it is a *[Fiat Currency](#fiat-currency)*, exchange rates are updated from *[fixer.io][fixer-io]*, as long as you have provided an API key.

You also have the option to provide exchange rates statically:

When editing a *Currency*, you can provide static values for the exchnage rates against any enabled *[VS Currencies](#vs-currencies)*. It is your responsibility to update these values.

## Frontend UI {#frontend-ui}

The frontend UI is composed of a number of *[Wallets Shortcodes](#wallets-shortcodes)*. These UIs let users deposit/withdraw/transact/etc.

The frontend uses the plugin's [WP-REST API](#wp-rest-api) to perform actions on behalf of the user, and to display other data.

For details on the Frontend, including how to modify it, see: *[Frontend][frontend]*

## General Capabilities {#general-capabilities}

The plugin has two groups of *[Capabilities](#capabilities)*, *General Capabilities* and *[Post-Type Capabilities](#post-type-capabilities)*

The General Capabilities are:

- `manage_wallets`
- `has_wallets`
- `send_funds_to_user`
- `withdraw_funds_from_wallet`
- `list_wallet_transactions`
- `view_wallets_profile`

All of these Capabilities can be edited in the *[Capabilities Settings][capabilities-settings]*.

## Glossary {#glossary}

You're looking at it!

## Hard daily withdrawal amount {#hard-daily-withdrawal-amount}

This is a *[Currency](#currencies)* setting. The maximum amount of this currency that a user can withdraw in one day. A value of 0 means no limit.

You can set this value for all users, or per *User Role*.

## Hot Wallet Balance {#hot-wallet-balance}

The total amount of coins available to the site for withdrawals. This does not need to be the same as the total sum of *user balances*.

## HTTP settings {#http-settings}

The plugin communicates with third-party APIs for various purposes (CoinGecko, [fixer.io][fixer-io]), and with wallets via *[Wallet Adapters](#wallet-adapters)*.

You can control this type of communication using the [HTTP settings][http-settings] tab, which you can find in the plugin's *[Settings](#settings)*.

## IFSC and Account number {#ifsc-and-account-number}

An *Indian Financial System Code* and an *Account number* are the details needed to perform a bank transaction in India. When performing fiat deposits/withdrawals, this is one of the options for bank addressing method.

To create an *[Address post type][post-type-address]* with such details, enter the following JSON data in the address's Title:

	{ "ifsc": "123456789", "indianAccNumber": "1234567"}

Replace the numbers in the JSON with your actual *IFSC* and *Account number*.

## Internal fee {#internal-fee}

A *[fee](#fees)* charged on an *[Internal transfer](#internal-transfer)*.

## Internal transfer {#internal-transfer}

A *[Transaction](#transaction)* between two users in the same WordPress installation, either in the same blog or accross blogs in a multisite install.

The internal transfer fees are set by the administrator. The transaction is only stored in the local DB ledger. It is not broadcast to the network. You could set the internal transfer fees to zero, but be careful &mdash; DB space costs money!

Users can initiate internal transfers using the `[wallets_move]` shortcode.

*App extensions* such as the *WooCommerce Payment Gateway extension* use internal transfers to perform transactions.


## JSON-API {#json-api}

A recently deprecated, HTTP-based API that provided an interface between the frontend JavaScript code and the plugin's backend. The JSON API exposes information provided by the PHP API to logged in users. In versions `6.0.0` and later, it is emulated as *Legacy JSON-API" for legacy applications.

## JSON-API v3 {#json-api-v3}

The last version of the now deprecated JSON-API. This is the one that's emulated by the *Legacy JSON-API* feature in versions `6.0.0` and later.

## Text domains {#text-domains}

For a general overview of *Text domains*, see the [Internationalization handbook][wp-i18n].

For details on the text domains used by the plugin, see: _Localization_ &rarr; _[Text domains][text-domains]_.

## Legacy JSON-API {#legacy-json-api}

The *[Bitcoin and Altcoin Wallets](#bitcoin-and-altcoin-wallets)* WordPress plugin version `6.0.0` and later features a *WP-REST API*. The frontend UIs use the WP-REST API to load the wallet data that is displayed to the end user.

Plugin versions before `6.0.0` used the now deprecated *[JSON-API](#json-api)*. The *Legacy JSON-API* emulates this previous API as closely as possible to preserve compatibility with existing code.

Do not build new code on deprecated features, as these may be removed in a future version. The *[WP-REST API](#wp-rest-api)* is the currently recommended programmatic interface to the plugin.

To learn more about the Legacy API, see: _Developer reference_ &rarr; _Wallet APIs_ &rarr; _[Legacy PHP-API][legacy-php-api]_.

## Legacy PHP-API {#legacy-php-api}

Versions of the [Bitcoin and Altcoin Wallets WordPress plugin][wallets] before `6.0.0` featured a so-called [PHP-API][php-api]. This API allowed manipulating transactions via PHP code.

With version `6.0.0`, all operations can be done by directly manipulating the [custom post type objects](#oo-php-api). The *Legacy PHP API* is introduced for compatibility. It is a rebuild of the *PHP-API* to work with the new objects, rather than the old custom MySQL tables. It aims to emulate the old API as closely as possible. However, there are some small differences with the original, because of the shift from *coin adapters* to *wallet adapters*, and because of other changes. Smoke test your legacy code, or upgrade to using the [OO PHP-API](#oo-php-api)!

For documentation, see [PHPDocumentor on Post Types](#legacy-php-api-docs).

## Localization {#localization}

The parent plugin can now be localized via https://translate.wordpress.org since version `6.0.0`.

Localizing an extension to the plugin involves translating the plugin's `.pot` files into your Language's `.po` files.

For extensions, you can translate the `wallets-extension` *[text domain](#text-domain)* for strings in the Admin interface, or the `wallets-extension-front` *[text domain](#text-domain)* for strings displayed in the *[Frontend](#frontend)*, where "`wallets-extension`" is the unique slug for the extension (e.g. `wallets-exchange`, `wallets-faucet`, etc).

For more information, see: *[Localization][localization]*.

## Logging {#logging}

If you add `define( 'WP_DEBUG', true );` in your `wp-config.php`, WordPress will write a log file, `debug.log`.

The plugin will write to this log from time to time, mostly whenever an error is encountered, but also to mark other events.

See also *[Verbose logging](#verbose-logging)*.

## Migration {#migration}

Moving from *Bitcoin and Altcoin Wallets* versions `5.x` to `6.0.0` requires a data migration process.

Data that was previously stored on the custom SQL tables `wp_wallets_txs` and `wp_wallets_adds` must now be copied into Custom Post Types.

## Migration Cron job {#migration-cron-job}

a *[Cron job](#cron-job) that performs *[Migration](#migration)*.

## Migration Tool

A *[Tool](#tools)* that helps you control the *[Migration Cron job](#migration-cron-job)* that lets you perform *[Migration](#migration)*.


## Minimum withdrawal amount {#minimum-withdrawal-amount}

This is a *[Currency](#currencies)* setting. It is the minimal amount, for each currency, that users can withdraw in one transaction for that currency. Set this to be something larger than the network fees.

## Move {#move}

See *[Internal transfer]{internal-transfer}*. The term *move* is used mostly throughout the sourcecode for brevity.

One of the three types of *[Transaction](#transaction) Category/Type the other being, `deposit` and `withdrawal`.

## Multi-adapter {#multi-adapter}

A *wallet adapter* that provides access to more than one *Currencies*. Example is the *CoinPayments adapter*.

## Multisite support {#multisite-support}

The plugin can operate in two modes:

When network activated, user wallets exist across all sites on the network.

When activated on single site installations, or when activated independently on single sites of a network, user wallets exist only on the site where the plugin was activated. If the user visits other sites on the network that also have the plugin activated, the user will have different wallets there.

## Notification {#notification}

An email message sent to a user to inform them of some event that affects their balance in some way, such as a *deposit*, *withdrawal*, or *internal transfer*. The message reports whether the transaction succeeded or failed, and includes an error message in case of failure.

## OO-PHP-API {#oo-php-api}

With version `6.0.0`, all operations can be done by directly manipulating the [custom post type objects](#post-types), thus the old *[PHP-API](#php-api)* is no longer needed.

To learn more about the OO API, see: _Developer reference_ &rarr; _Wallet APIs_ &rarr; _[](#php-api)_.


## Pending {#pending}

The *[status of a transaction](#transaction-status)* that is ready to execute. It may also be a transaction that is waiting for user *[Confirmation](#confirmation)*.

The post of a pending transaction will have `post_status=pending`.

## PHP-API {#php-api}

Collection of WordPress actions and filters that can be used to interact with the wallets via PHP code. The PHP API is documented using [phpDoc][phpdoc]. The *JSON API* utilizes this PHP API.

In versions `6.0.0` or later, the PHP-API is emulated as *[Legacy PHP-API](#legacy-php-api)* to maintain compatibility with legacy code.

## Polling mechanism {#polling-mechanism}

Most of the *[Frontend UIs](#frontend-ui)* can be refreshed with a click by the user. The UIs also auto-refresh whenever the tab gains focus, and periodically on a timer. The polling mechanism is this periodic reloading of frontend data, so that displayed data always remains recent. You can control the timer interval with the `wallets_polling_interval` *[Setting](#settings)*.

## Post-Type Capabilities {#post-type-capabilities}

The plugin has two groups of *[Capabilities](#capabilities)*, *General Capabilities* and *[Post-Type Capabilities](#post-type-capabilities)*

The Post Type capabilities are defined by WordPress for all custom post types:

- `delete_others_wallets_wallets`
- `delete_wallets_wallets`
- `delete_private_wallets_wallets`
- `delete_published_wallets_wallets`
- `edit_others_wallets_wallets`
- `edit_wallets_wallets`
- `edit_private_wallets_wallets`
- `edit_published_wallets_wallets`
- `publish_wallets_wallets`
- `read_private_wallets_wallets`
- `delete_others_wallets_currencies`

- `delete_wallets_currencies`
- `delete_private_wallets_currencies`
- `delete_published_wallets_currencies`
- `edit_others_wallets_currencies`
- `edit_wallets_currencies`
- `edit_private_wallets_currencies`
- `edit_published_wallets_currencies`
- `publish_wallets_currencies`
- `read_private_wallets_currencies`

- `delete_others_wallets_txs`
- `delete_wallets_txs`
- `delete_private_wallets_txs`
- `delete_published_wallets_txs`
- `edit_others_wallets_txs`
- `edit_wallets_txs`
- `edit_private_wallets_txs`
- `edit_published_wallets_txs`
- `publish_wallets_txs`
- `read_private_wallets_txs`

- `delete_others_wallets_addresses`
- `delete_wallets_addresses`
- `delete_private_wallets_addresses`
- `delete_published_wallets_addresses`
- `edit_others_wallets_addresses`
- `edit_wallets_addresses`
- `edit_private_wallets_addresses`
- `edit_published_wallets_addresses`
- `publish_wallets_addresses`
- `read_private_wallets_addresses`


All of these Capabilities can be edited in the *[Capabilities Settings][capabilities-settings]*.

## Premium dashed-slug Membership {#premium-membership}

As long as you are a *[Premium dashed-slug member](#premium-membership)*, you have access to download new updates to the plugin's [extensions].

[Wallet adapter extensions](#wallet-adapter-extension) are available for free to all subscribers.

Subscribers who pay for *Premium Membership* can also download the *[App extension](#app-extensions)*.

Finally, *Premium Members* get an *[Activation code](#activation-code)*. This lets their WordPress download updates to the apps automatically, from the dashed-slug update server.

## Profile {#profile}

The WordPress Profile screen in the admin interface has a *[Bitcoin and Altcoin Wallets](#bitcoin-and-altcoin-wallets)* section.

This section is visible to admins and users with the `view_wallets_profile` *[General capability](#general-capabilities)*.

It has the following information:

- A personal user API key for the deprecated, [legacy JSON-API v3](#legacy-json-api).
- Link to the user's *[Addresses](#address)*.
- Link to the user's *[Transactions](#transaction)*.
- A breakdown of User holdings per Currency.

## Save Changes {#save-changes}

When editing any of the plugin's *[Settings](#settings)*, never forget to hit the _Save Changes_ button at the bottom of the admin screen.

## Settings {#settings}

The plugin's settings.

For more information, see: *[Settings reference][settings]*.

## Shortcodes {#shortcodes}

See: [Wallets Shortcodes](#wallets-shortcodes).

## Status {#status}

The current state of a *[Transaction](#transaction)*.

See *[Transaction status](#transaction-status)*.

## Sum of User Balances {#sum-of-user-balances}

See *[Total user balances](#total-user-balances)*.

## SWIFT-BIC and IBAN {#swift-bic-and-iban}

An SWIFT-BIC number and an IBAN number are the details needed to perform a bank transaction in Europe. When performing fiat deposits/withdrawals, this is one of the options for bank addressing method.

To create an *[Address post type][post-type-address]* with such details, enter the following JSON data in the address's Title:

	{ "swiftBic": "123456789", "iban": "1234567"}

Replace the numbers in the JSON with the actual *SWIFT/BIC* and *Account number* of the wire transfer's destination.

## SWIFT-BIC and Account Number {#swift-bic-and-accnum}

An SWIFT-BIC number and an account number are the details needed to perform a bank transaction in Africa. When performing fiat deposits/withdrawals, this is one of the options for bank addressing method.

To create an *[Address post type][post-type-address]* with such details, enter the following JSON data in the address's Title:

	{ "swiftBic": "123456789", "accountNumber": "1234567"}

Replace the numbers in the JSON with the actual *SWIFT/BIC* and *Account number* of the wire transfer's destination.

## Symbol {#symbol}

See *[Ticker symbol](#ticker-symbol)*.

## Templates {#templates}

The *UI Templates* are PHP files containing the *Frontend UIs*. Each *[Wallets Shortcode](#wallets-shortcodes)* corresponds to at least one, or possibly more, *Templates*.

A template contains the HTML markup, CSS styling, and JavaScript code needed to render the Frontend UI. The UIs use the `knockout.js` library which is loaded by the plugin when at least one template is rendered.

To learn more about how to modify, and override, UI templates, see: _Developer Reference_ &rarr; _Modifying the UI appearance_ &rarr; _[Editing the template files][editing-templates]_.


## Ticker symbol {#ticker-symbol}

A short, usually three-letter string of letters that identifies a currency. e.g. `BTC` for Bitcoin.

Unfortunately, ticker symbols are NOT unique across the cryptoverse. Ticker symbol clashes between different projects are common. Since `6.0.0` the plugin no longer relies on ticker symbols being unique. You can now declare multiple currencies with the same ticker symbol, but with different post_id, different attributes, etc.

## Tools {#tools}

The plugin adds the following tools under the WordPress _Tools_ menu:

- Cold storage tool &mdash; Lets admins move funds to and from an external wallet for extra security. For more information see: _Tools_ &rarr; _[Cold Storage][cold-storage]_.
- Fiat deposits tool &mdash; Lets admins process incoming wire transfers from user bank accounts. For more information see: _Tools_ &rarr; _[Fiat Deposits][fiat-deposits]_.
- Fiat deposits tool &mdash; Lets admins process withdrawal requests from user bank accounts via wire transfer. For more information see: _Tools_ &rarr; _[Fiat Withdrawals][fiat-withdrawals]_.

## Total user balances {#total-user-balances}

The sum of all user balances for a particular currency.

This amount is relevant, because the admin or admins may want to keep a percentage of that amount on the hot wallet, and the remaining amount on [Cold storage](#cold-storage).

## Transaction {#transaction}

A *[deposit](#deposit)*, *[withdrawal](#withdrawal)*, or *[internal transfer](#internal-transfer)*. Transactions affects user balances, and may carry [fees](#transaction-fees).

Transactions are stored on the DB as custom posts of type `wallets_tx`.

To learn more about the corresponding custom post type, see *[Transaction post type][post-type-tx]*.


## Transaction fees {#transaction-fees}

Your site can earn fees when users perform transactions on your site. Fees are not actually transfered, instead they are simply subtracted from *[user balances](#user-balance)*. Any portion of the *[Hot wallet](#hot-wallet)* balance that is not owed to *user balances* is effectively belonging to the site's operators. If enough fees accumulate, the best way to withdraw them to an external wallet is to perform a *[Cold Storage](#cold-storage)* transfer.

These are the types fees paid by the users for performing transactions

- *[Internal transfer](#internal-transfer)* fees &mdash; For transactions between users on your site. The fee, if any, is charged to the sender's balance.
- *[Withdrawal](#withdrawal)* fees &mdash; For performing withdrawals to blockchain addresses. The fee charged to the user should be set by an admin, so that it at least covers the miners' fee. Any part of the fee not actually paid on the network remains with the site's *[hot wallet](#hot-wallet)*.

## Transaction status {#transaction-status}

The current status of a transaction indicates whether the transaction has been executed, or whether it is going to be executed, and if not, why not!

Is one of:
- *[Pending](#pending)*
- *[Done](#done)*
- *[Failed](#failed)*
- *[Cancelled](#cancelled)*


## Transaction tags {#transaction-tags}

You can organize your *[Transactions](#transaction)* using tags, with the `wallets_txs_tags` custom taxonomy.

## Transients {#transients}

Normally, theme and plugin code uses the [WordPress Transients API][wp-transients] to cache data internally.

Sometimes, if the server's cache is misconfigured, *Transients* that are supposed to expire, do not expire.

For this reason, the plugin's transients use some function wrappers to the Transients API. This ensure that the plugin's Transients expire no later than they should.

For more information, see the `wallets_transients_broken` *[Setting](#settings)*.

## Unavailable balance {#unavailable-balance}

The part of a user's balance that is locked in either pending *transactions* or in Exchange market orders that have not yet been filled. This balance cannot be withdrawn or traded until the transaction or order is executed/cancelled/failed. Internally, *app extensions* can use the `wallets_api_unavailable_balance` filter of the *PHP-API* to add to this unavailable balance.

## User available balance {#user-available-balance}

How much of a user's balance can be used to send in new transactions. Equals to the *[user balance](#user-balance)*, minus *[unavailable balance](#unavailable-balance)*.

> *[User available balance](#user-available-balance)* = *[user balance](#user-balance)* &minus; *[unavailable balance](#unavailable-balance)*

## User balance {#user-balance}

The total sum of all *[Done](#done)* *[transactions](#transaction)* that a user has performed. *Deposits* and received *credit moves* carry positive (crediting) amounts, while *debit moves* and *withdrawals* carry negative (debiting) amounts.

## Verbose Logging {#verbose-logging}

Enable this [setting](#settings) to write detailed logs to `wp-content/wp-debug.log`. This option is only available if you add `define( 'WP_DEBUG', true );` in your `wp-config.php`.

This is useful for debugging. If you are running your site with debug logging enabled, the plugin will write some limited information to the logs from time to time. Turning this setting on will cause the plugin to write to the debug log very detailed information on:

- when the [cron tasks](#cron-tasks) are running
- how long they run for
- their status, and
- how much memory each task used

## Verified by user {#verified-by-user}

A type of transaction *[confirmation](#confirmation)*. For *[withdrawals](#withdrawal)* and/or *[internal transfers](#internal-transfer)*, the user that originated the transaction can be required to provide email *[confirmation](#confirmation)*. The user clicks on a link in the email. The link contains a nonce and the plugin's *[cron tasks](#cron-tasks)* can now process the transaction.

## VS Currencies {#vs-currencies}

See *[CoinGecko VS currencies](#coingecko-vs-currencies)*.

## Wallet {#wallet}

Wallets are defined by a *[Wallet Adapter](#wallet-adapter)* plus the adapter's connection settings.

Wallets are represented on the DB as custom posts of type `wallets_wallet`.

To learn more about the corresponding custom post type, see *[Wallet post type][post-type-wallet]*.


## Wallet Adapter {#wallet-adapter}

Something like a device driver, but for wallets. It's an abstraction that communicates with a cryptocurrency wallet to provide deposit and withdraw support for a currency to the plugin.

## Wallet Adapter Settings {#wallet-adapter-settings}

*Wallet adapters* define settings that are specific to each adapter. These are connection settings that tell the adapter how to connect to your wallet.

An admin must provide these settings on the *Wallet* editor. Refer to your Wallet Adapter's documentation for details.

## Wallet adapter extension {#wallet-adapter-extension}

An *extension* to the plugin that provides one or more *Wallet Adapters*.

## Wallets shortcodes {#wallets-shortcodes}

WordPress uses [Shortcodes][shortcodes] to allow editors to insert UIs and other features inline with a page's text.

The *Wallets Shortcodes* are shortcodes that display a [Frontend UI](#frontend-ui).

To learn more about the shortcodes, see: _Frontend_ &rarr; _[Wallets Shortcodes][frontend-wallets-shortcodes]_.

## Withdrawal {#withdrawal}

A *[transaction](#transaction)* whereby a user withdraws money from their account on the WordPress site, to another address on the blockchain network. This is one of the three types of *Transactions*, the others being `deposit` and `move`.

If the destination address is a known deposit address for some other user on the site, an *[internal transfer](#internal-transfer)* is done instead. This saves on network fees.

## Withdrawal Address {#withdrawal-address}

A cryptocurrency [address](#address) with which a user can perform a *[withdrawal](#withdrawal)* to. Withdrawal means that the user sends coins/tokens to that address, from their *[User balance](#user-balance)*.

Withdrawal addresses are usually created from user input into the `[wallets_withdraw]` [Frontend UI](#frontend-ui) (or the `[wallets_fiat_withdraw]` [Frontend UI](#frontend-ui)).

## Withdrawal fees {#withdrawal-fees}

The fee that is subtracted from a user's balance, after withdrawing funds to a blockchain address.

The amount specified by the admin should be larger than any network fees that the wallet will pay for a typical transaction. The network fees are paid from these withdrawal fees.

## WP-REST API {#wp-rest-api}

The *[Bitcoin and Altcoin Wallets](#bitcoin-and-altcoin-wallets)* WordPress plugin version `6.0.0` and later features a *WP-REST API*. The frontend UIs use the WP-REST API to load the wallet data that is displayed to the end user.

To learn more about the API, see: _Developer Reference_ &rarr; _Wallet APIs_ &rarr; _[WP-REST API][wp-rest-api]_.



[settings]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=settings
[capabilities-settings]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=settings#caps
[http-settings]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=settings#http
[wordpress-caps]: https://wordpress.org/support/article/roles-and-capabilities/ "Roles and Capabilities"
[phpdoc]: https://wallets-phpdoc.dashed-slug.net/
[post-type-address]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#addresses
[post-type-currency]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#currencies
[post-type-tx]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#tx
[post-type-wallets]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#wallets
[wiki-conf]: https://en.bitcoin.it/wiki/Confirmation
[php-sprintf]: https://www.php.net/manual/en/function.sprintf.php
[currency-exchange-rates]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#currency-exchange-rates
[coinpayments-net]: https://www.coinpayments.net/
[coinpayments-adapter]: https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/coinpayments-adapter-extension/
[customizer]: https://codex.wordpress.org/Theme_Customization_API "Codex / Theme Customization API"
[frontend]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=frontend
[frontend-customizer]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=frontend#customizer
[frontend-wallets-shortcodes]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=frontend#wallets-shortcodes
[wordpress-dashboard]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=dashboard
[editing-templates]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=frontend#editing-templates
[wp-cli]: https://wp-cli.org/
[fixer-io]: https://fixer.io/?fpr=dashed-slug "Fixer.io"
[fixerio-task]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=settings#fiat
[fiat-deposits]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=tools#fiat-deposits
[fiat-withdrawals]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=tools#fiat-withdrawals
[investopedia-fiat-money]: https://www.investopedia.com/terms/f/fiatmoney.asp "Investopedia Fiat Money"
[legacy-php-api]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=developer#php-api
[wp-i18n]: https://developer.wordpress.org/themes/functionality/internationalization/#text-domain
[text-domains]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=l10n#text-domains
[localization]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=l10n
[wp-rest-api]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=developer#wr-rest-api
[wp-transients]: https://developer.wordpress.org/apis/handbook/transients/
[cold-storage]: http://www.turbox.lan:81/wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=tools#cold-storage
