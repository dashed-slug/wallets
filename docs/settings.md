# Settings reference

This section showcases all settings for this plugin.

Since version `6.0.0`, settings are shown in _Settings_ &rarr; _Bitcoin and Altcoin Wallets_.

The settings are organized into tabs, and App extensions to the plugin can add additional tabs here.

> **TIP:** When you are done editing the settings in a tab, always hit the "Save Changes" button, found near the bottom of the page.


## General settings {#general}


### Max deposit address count per currency

|     |     |
| --- | --- |
| *Option* | `wallets_addresses_max_count` |
| *Default* | `10` |
| *Description* | *Restricts the amount of deposit addresses that a user can create per currency via the WP REST API (frontend).* |

Effectively this controls up to how many deposit addresses a user can have. A user would typically create a deposit address via the `[wallets_deposit]` shortcode. The user clicks the "Get new address" button, which triggers a WP-REST API POST request to `dswallets/v1/users/(user_id)/currencies/(currency_id)/addresses`. If the deposit addresses of the user have reached this max count, the request returns with the HTTP 420 Enhance Your Calm status code and no address is created.

### Disable built-in object cache (debug)

|     |     |
| --- | --- |
| *Option* | `wallets_disable_cache` |
| *Default* | `''` (off) |
| *Description* | *The plugin speeds up DB reads of its Wallets, Currencies, Transactions and Addresses into its built-in object cache. If this uses up too much memory and causes the plugin to crash, you can disable the cache here and see if it helps. Otherwise, it's best to leave it on.* |

This is a built-in cache for the plugin's CPTs: Wallets, Currencies, Transactions and Addresses. Since `6.0.0`, these are loaded as objects. Since `6.2.6`, these are loaded in batches where possible, to minimize SQL queries to the DB. They are also cached. This won't be a problem unless if the plugin loads too many objects in memory per request. This shouldn't be the case, since all cron jobs process data in small batches. But if you notice the plugin crashing due to insufficient memory, AND you are unable to increase the memory available to WordPress, then disabling this cache MAY help.

### Transients broken (debug)

|     |     |
| --- | --- |
| *Option* | `wallets_transients_broken` |
| *Default* | `''` (off) |
| *Description* | *Forces all transients for this plugin to be recomputed every time rather than loaded from server cache. Helps debug issues with server caches.* |

Sometimes a server-side caching plugin is misconfigured. Maybe it cannot connect to a keystore server, or something else is wrong. This causes transients which are normally set to expire, to not expire, ever! This means that any data cached by plugins in transients cannot refresh.

The plugin circumvents this via a clever hack if this option is set to `on`.

A test transient is set to expire in one minute, with the current timestamp. If the transient is found one minute after it has been created according to its timestamp, this means that it did not expire when it should, and this option is set to `on`. Otherwise you can keep it off.

### Deposit cutoff timestamp

|     |     |
| --- | --- |
| *Option* | `wallets_deposit_cutoff` |
| *Default* | `0` |
| *Description* | *The plugin will reject any deposits with timestamps before this cutoff. This is set automatically by the migration tool when initiating a new balances-only migration. The cutoff ensures that no deposits before a balance migration are repeated if the plugin receives notifications for them. Do not change this value unless you know what you are doing.* |

This setting allows the plugin to reject any deposits with a timestamp earlier than a cutoff.

This is useful for systems where balances migration has been performed from versions previous to `6.0.0`.

Because on such systems, early deposits have not been migrated, if such deposits are notified again on the plugin, then
the deposits will be re-entered into the plugin's ledger, resulting in double counting of old deposits.

This can happen for example if a Bitcoin core node is re-synced, and therefore it repeats walletnotify curl calls.

When an admin initiates a *Balances-only migration*, this setting is set to the current timestamp. Thus, all deposits with
a timestamp before the start of the migration will never be counted twice.

An admin can manually change this value, however this is not recommended.

A value of `0` means that all deposits are accepted.

## Exchange rates {#rates}


### CoinGecko vs Currencies

|     |     |
| --- | --- |
| *Option* | `wallets_rates_vs` |
| *Default* | `'btc', 'usd'` |
| *Description* | *The plugin will look up exchange rates for your currencies against these "vs currencies" on CoinGecko. If unsure, check "BTC" and "USD".* |

The "vs currencies" are a set of well-known currencies, against which all other currencies may have their exchange rate measured. These special currencies used by CoinGecko are:

'`btc`', '`eth`', '`ltc`', '`bch`', '`bnb`', '`eos`', '`xrp`', '`xlm`', '`link`', '`dot`', '`yfi`', '`usd`', '`aed`', '`ars`', '`aud`', '`bdt`', '`bhd`', '`bmd`', '`brl`', '`cad`', '`chf`', '`clp`', '`cny`', '`czk`', '`dkk`', '`eur`', '`gbp`', '`hkd`', '`huf`', '`idr`', '`ils`', '`inr`', '`jpy`', '`krw`', '`kwd`', '`lkr`', '`mmk`', '`mxn`', '`myr`', '`ngn`', '`nok`', '`nzd`', '`php`', '`pkr`', '`pln`', '`rub`', '`sar`', '`sek`', '`sgd`', '`thb`', '`try`', '`twd`', '`uah`', '`vef`', '`vnd`', '`zar`', '`xdr`', '`xag`', '`xau`', '`bits`', '`sats`'

Choose against which "vs currencies" you want the plugin to record exchange rates for all the currencies that are defined (i.e. for all cryptocurrency posts of type `wallets_currency`).

> When you later add currencies to your plugin, you can specify a CoinGecko ID to which your currency corresponds. For example, Bitcoin has the CoinGecko ID `bitcoin`, and Ethereum has the ID `ethereum`.

> Simply go to the Currency, and set the CoinGecko ID. The plugin will retrieve the exchange rate of your currency against any "vs currencies" that you have selected here.


## Fiat currencies {#fiat}

To define fiat currencies in the plugin, please sign up for an API key at: https://fixer.io

Enter the API key here, and hit "Save Changes".

The plugin will soon create *[Currencies][glossary-currency]* for all the fiat currencies known by fixer.io.

The plugin will continue to access fixer.io repeatedly and keep the exchange rates between these fiat currencies updated.

The free subscription plan is sufficient. The plugin will not query the service more than once per 8 hours, and this amounts to less than 100 API calls per month. (The free plan gives you 100 calls per month.)


### fixer.io API key

|     |     |
| --- | --- |
| *Option* | `wallets_fiat_fixerio_key` |
| *Default* | `''` (off) |
| *Description* | *Fiat currencies are defined using the third-party service fixer.io. The service will provide the plugin with fiat currency information and exchange rates of these currencies.* |

You should NOT enter the fiat currencies manually to the plugin.

Instead, let it retrieve the fiat currencies automatically from fixer.io.

Sign up for a free [fixer.io][fixer-io] account, and get your API key. Enter the API key here and hit the "Save Changes" button. After a few cron runs, all the fiat currencies will be created. Once all fiat currencies are created, their exchange rates will begin updating, once per hour.


## Frontend UI settings {#frontend}

Settings that affect the frontend (shortcode UIs).

> **TIP**: Most of the settings that directly affect the frontend are found in the *[Customizer][glossary-customizer]*.


### Polling interval

|     |     |
| --- | --- |
| *Option* | `wallets_polling_interval` |
| *Default* | `30` (seconds) |
| *Description* | *How often the frontend UIs are polling the WP REST API of this plugin to refresh the data shown. If you do not want the frontend to poll your server, choose "never".* |

The frontend UIs refresh their data from the server at regular intervals. This calls the plugin's WP-REST API periodically.

The default is for the templates to refresh every 30 seconds with new data from the server.

If your server cannot handle your user load, increase this duration. Increasing the duration will result in fewer API calls from user browsers.

To disable polling altogether, set this setting to `never`. The UIs will only load data once on page load and will not refresh unless the user explicitly clicks on the reload button.


### Legacy JSON-API v3 (deprecated)

|     |     |
| --- | --- |
| *Option* | `wallets_legacy_json_api` |
| *Default* | `''` (off) |
| *Description* | *The old JSON-API has been superceded by the WP-REST API. If you need backwards compatibility with the JSON-API, enable this setting. The JSON-API may be removed in a future version of the plugin.* |

The legacy JSON-API is provided for backwards compatibility with existing code, but is disabled by default. If you need it, enable it here.

For information about the now deprecated, Legacy JSON-API, see the [developer reference][json-api]


## Notifications {#notify}

Users are notified about their transactions via email. These settings control email notifications.


### Outgoing e-mails batch size

|     |     |
| --- | --- |
| *Option* | `wallets_emails_max_batch_size` |
| *Default* | `4` |
| *Description* | *How many emails to send from the email queue on each cron run.* |

When the plugin sends notifications via emails, it first adds the emails to an outgoing queue. This queue is stored in the `wallets_email_queue` WordPress option. On every cron run, this many emails are sent using the WordPress `wp_mail()` function.

Sending emails normally requires a functioning PHP `mail()` on the server. However, you can setup WordPress to use an external SMTP server, such as that of Gmail. To do this, you can use the [WP Mail SMTP][wp-mail-smtp] plugin.


### Move confirm links

|     |     |
| --- | --- |
| *Option* | `wallets_confirm_move_user_enabled` |
| *Default* | `''` (off) |
| *Description* | *Whether to require a confimation link for internal transfers (moves). The link is sent by email to the user and they must click on it for the transaction to proceed.* |

Internal transfers (moves) are normally initiated using the UI associated with the `[wallets_move]` shortcode.

If this option is set, the sender will receive an email with a special link to confirm that this transaction is to proceed. This ensures that the owner of the user's email account is the one who initiated the transaction.

If the setting is off, the transaction proceeds to execute on the next cron, without any further user input.


### Withdraw confirm links

|     |     |
| --- | --- |
| *Option* | `wallets_confirm_withdraw_user_enabled` |
| *Default* | `''` (off) |
| *Description* | *Whether to require a confimation link for withdrawals to external addresses. The link is sent by email to the user and they must click on it for the transaction to proceed.* |

Withdrawals are normally initiated using the UI associated with the `[wallets_withdraw]` shortcode.

If this option is set, the sender will receive an email with a special link to confirm that this transaction is to proceed. This ensures that the owner of the user's email account is the one who initiated the transaction.

If the setting is off, the transaction proceeds to execute on the next cron, without any further user input.


### Confirmation link redirects to page

|     |     |
| --- | --- |
| *Option* | `wallets_confirm_redirect_page` |
| *Default* | `''` (none) |
| *Description* | *After a user clicks on a confirmation link from their email, they will be redirected to this page.* |

When a user requests a withdrawal or internal transfer (move), they may have to click on a confirmation link. Users will get this confirmation link in their email.

After the user clicks on the link, the transaction can proceed.

If the admin has selected a page here, the user will then be redirected to that page. This can be any page on the site.

> **NOTICE**: Due to this redirect, the validation link is the one WP-REST API endpoint that does not always return JSON.


## Cron tasks {#cron}

A number of cron tasks need to run for this plugin to function.

These are implemented as concrete implementations of the abstract class `DSWallets\Task`. (More on this in the *Developer reference* chapter of this documentation.)


### Cron interval

|     |     |
| --- | --- |
| *Option* | `wallets_cron_interval` |
| *Default* | `'wallets_one_minute'` |
| *Possible values* | `wallets_never`, `wallets_half_a_minute`, `wallets_one_minute`, `wallets_three_minutes`, `wallets_five_minutes`, `wallets_ten_minutes`, `wallets_twenty_minutes`, `wallets_thirty_minutes`
| *Description* | *How often to run the cron job.* |

The cron jobs are a complex system of tasks that execute periodically.

Cron jobs are executed on the `wallets_cron_tasks` action, which is triggered periocically. Here you can specify how often to trigger this action.

> **TIP**: Of course, when the site has no traffic, the cron jobs cannot run, due to the architecture of WordPress. Running every one minute should be OK for most sites. On sites with low traffic, the cron jobs will run at most this often, but maybe less often than this setting. To ensure that cron jobs always run on time, you can trigger the cron URL manually via a Unix cron task, as explained in the on-screen instructions.


### Verbose logging {#verbose-logging}

|     |     |
| --- | --- |
| *Option* | `wallets_cron_verbose` |
| *Default* | `''` (off) |
| *Description* | *Enable this to write detailed logs to `wp-content/wp-debug.log`. This option is only available if you add `define( 'WP_DEBUG', true );` in your `wp-config.php`* |

This is useful for debugging. If you are running your site with debug logging enabled, the plugin will write some limited information to the logs from time to time. Turning this setting on will cause the plugin to write to the debug log very detailed information on:

- when the cron tasks are running
- how long they run for
- their status, and
- how much memory each task used


### Withdrawals batch size

|     |     |
| --- | --- |
| *Option* | `wallets_withdrawals_max_batch_size` |
| *Default* | `'4'` |
| *Description* | *On each run of the cron jobs, up to this many withdrawals will be processed.* |

When the cron jobs run, a cron task executes withdrawals. This setting specifies how many withdrawals to execute on each run. Do not set this value too high, since placing withdrawals usually takes some time on most wallets.


### Internal transfers (moves) batch size

|     |     |
| --- | --- |
| *Option* | `wallets_moves_max_batch_size` |
| *Default* | `'8'` |
| *Description* | *On each run of the cron jobs, up to this many internal transfers will be processed.* |

When the cron jobs run, a cron task executes pending internal transfers (moves). This setting specifies how many moves to execute on each run. Do not set this value too high, since placing moves usually takes some time. The user balances are being re-checked, transaction posts are being processed on the DB, and email notifications are being rendered from their templates, and enqueued for sending asynchronously. All of this takes time.


### Transaction auto-cancel

|     |     |
| --- | --- |
| *Option* | `wallets_cron_autocancel` |
| *Default* | `'0'` (Never) |
| *Possible values* | `0 days`, `7 days`, `15 days`, `30 days`, `60 days`, `1 year` |
| *Description* | *Pending transactions that have not been executed for this long will be cancelled.* |

When this time interval is set, transactions that remain in a `pending` status for longer than this duration, will be cancelled. This ensures that very old transactions do not suddenly get executed at a distant time in the future without the user expecting it (e.g. if a wallet is offline for a long time).


## HTTP settings {#http}

These settings affect all communication of the plugin to the outside world. They are used by wallet adapters, third-party API calls, etc. Do not touch these unless you understand what they do.


### HTTP timeout

|     |     |
| --- | --- |
| *Option* | `wallets_http_timeout` |
| *Default* | `'10'` |
| *Description* | *When the plugin communicates with external services over HTTP, it will wait for up to this many seconds before timing out. A timeout usually, but not always, indicates a connection is blocked by a firewall, or by another network issue.* |

The plugin uses HTTP to communicate with a number of third party services, including the wallets and exchange rates APIs (since version `6.0.0`, these are only CoinGecko and fixed.io).

This setting tells the plugin how long to wait for an outgoing connection that neither succeeds nor fails. A connection can timeout usually if it is blocked by a firewall. If this setting is too high, the plugin will needlessly wait in case of a firewall or other connectivity issue. If the setting is too low, outgoing connections will fail before they have a chance to succeed. There is usually no need to change the default.


### Max HTTP redirects

|     |     |
| --- | --- |
| *Option* | `wallets_http_redirects` |
| *Default* | `'2'` |
| *Description* | *When the plugin communicates with external services over HTTP, if it receives a 30x redirect, it will follow redirects up to this many times. Usually there shouldn't be any HTTP redirects.* |

The plugin uses HTTP to communicate with a number of third party services, including the wallets and exchange rates APIs (since version `6.0.0`, these are only CoinGecko and fixed.io).

This setting tells the plugin how many 30x redirects to follow when contacting external APIs. Too many redirects are not a good idea, and are usually an indication that something is wrong with the remote service. There is usually no need to change the default.


### Tor enabled

|     |     |
| --- | --- |
| *Option* | `wallets_http_tor_enabled`
| *Default* | `''` (off)
| *Description* | *Force all communication of this plugin with third party services to go through Tor. This includes communication with CoinGecko, fixer.io and other public APIs. You need to set up a Tor proxy first. Only useful if setting up a hidden service. (Requires the [PHP cURL][php-curl] extension to be installed.)*

The plguin can connect to all external APIs (since version `6.0.0`, these are only CoinGecko and fixed.io) only using Tor. This helps with using the plugin anonymously on a hidden site.

> **TIP**: If you are interested in anonymity of your web server, you must review how your WordPress installation connects to the outside world in general. There are articles on how to run WordPress on a Tor hidden site, but doing this securely is beyond the scope of this document.


### Tor proxy IP address

|     |     |
| --- | --- |
| *Option* | `wallets_http_tor_ip` |
| *Default* | `'127.0.0.1'` |
| *Description* | *The IP address of the Tor proxy, if enabled.* |

The plguin can connect to all external APIs (since version `6.0.0`, these are only CoinGecko and fixed.io) only using Tor. This helps with using the plugin anonymously on a hidden site.

Here you would specify the IP address of the Tor proxy. If you are running Tor on the same machine as WordPress, this will be the `localhost` IP.


### Tor proxy TCP port

|     |     |
| --- | --- |
| *Option* | `wallets_http_tor_port` |
| *Default* | `'9050'` |
| *Description* | *The TCP port address of the Tor proxy, if enabled. This is usually 9050 or 9150 on most Tor bundles.* |

The plguin can connect to all external APIs (since version `6.0.0`, these are only CoinGecko and fixed.io) only using Tor. This helps with using the plugin anonymously on a hidden site.

Here you would specify the TCP port of the Tor proxy. The plugin will connect to this port on the proxy. The Tor port is usually 9050 or 9150 on most Tor bundles.


## Capabilities {#caps}

Assign here *[General Capabilities][glossary-general-capabilities]* to your user roles.

There are also *[capabilities][glossary-capabilities]* for viewing/editing the plugin's custom post types. Normally you will not need to change these. If unsure, leave the defaults.

WordPress does access control using the concept of *Roles and Capabilities*. You can learn more in [this WordPress Support article][support-roles].

To assign the plugin's capabilities to user roles, go to your admin screens at: _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Capabilities_.

You can assign *[General capabilities][glossary-general-capabilities]* and *[Post-Type Capabilities][glossary-post-type-capabilities]* to your user roles.

Assign `[manage_wallets]` to the Administrator role only. Gives access to all the settings.

Assign `[has_wallets]` to user roles that are meant to have access to wallets. By default this is assigned to the roles: *Editor*, *Author*, *Contributor*, *Subscriber*.

Assign the remaining *[General capabilities][glossary-general-capabilities]* as needed.

The *[Post-Type Capabilities][glossary-post-type-capabilities]* are the standard WordPress capabilities related to the four Custom Post Types (*[Wallet][glossary-wallet]*, *[Currency][glossary-currency]*, *[Transaction][glossary-transaction]*, *[Address][glossary-address]*).

You probably don't need to touch the *[Post-Type Capabilities][glossary-post-type-capabilities]*: The admins should be the only ones allowed to directly edit *[Wallets][glossary-wallet]*, *[Currencies][glossary-currency]*, *[Transactions][glossary-transaction]*, and *[Addresses][glossary-address]*.

> **NOTE:** For shortcodes with a `user` or `user_id` argument, the capability checked is that of the target user, not the current user.

> **NOTE:** When performing *[Transactions][glossary-transaction]*, *[capabilities][glossary-capabilities]* are checked when a new Transaction is first submitted. Once it is submitted, it is not checked again later, when it gets executed by the cron jobs.

> **NOTE:** If the plugin is network activated on a multisite installation, the capabilities of each user/role are those assigned on the first site on the network. This ensures that users get a uniform experience across the network. For this reason, if you need to check the capabilities from PHP, you must use the delegates `DSWallets\ds_user_can()` and `DSWallets\ds_current_user_can()`.

> **NOTE:** When the plugin is first installed on a site, it initializes all capabilities to sane defaults. On multisite, if the plugin is NOT network activated, then each site on the network will get its capabilities initialized to sane defaults. This happens once when the plugin is first run on each site. After you *uninstall* the plugin, if you install it again, the capabilities are re-initialized to sane defaults.


### General capabilities


The *[General capabilities][glossary-general-capabilities]* determine how your users can interact with the plugin.

Take the time to assign these correctly to your user roles.

| Capability | Description     |
| ---------- | -----------     |
| `manage_wallets`             | Can configure all settings related to Bitcoin and Altcoin Wallets. This is for administrators only. |
| `has_wallets`                | Can have balances and use the wallets API. |
| `list_wallet_transactions`   | Can view a list of past transactions. |
| `generate_wallet_address`    | Can create new deposit addresses. |
| `send_funds_to_user`         | Can send cryptocurrencies to other users on this site. |
| `withdraw_funds_from_wallet` | Can withdraw cryptocurrencies from the site to an external address. |
| `view_wallets_profile`       | Can view the Bitcoin and Altcoin Wallets section in the WordPress user profile admin screen. |

#### `has_wallets` capability

Assign this to all users who should have access to the plugin.

##### Shortcodes affected:
- `[wallets_balance]`
- `[wallets_deposit]`
- `[wallets_account_value]`
- `[wallets_move]`
- `[wallets_withdraw]`
- `[wallets_fiat_withdraw]`
- `[wallets_fiat_deposit]`
- `[wallets_transactions]`

##### WP-REST API endpoints affected:
- GET `/users/{USER_ID}/addresses`
- POST `/users/{USER_ID}/address/{ADDRESS_ID}`
- GET `/users/{USER_ID}/currencies`
- GET `/users/{USER_ID}/currencies/{CURRENCY_ID}/addresses`
- POST `/users/{USER_ID}/currencies/{CURRENCY_ID}/addresses`
- GET `/users/{USER_ID}/transactions`
- GET `/users/{USER_ID}/transactions/category/[deposit|withdraw|move]`
- GET `/users/{USER_ID}/currencies/{CURRENCY_ID}/transactions`
- GET `/users/{USER_ID}/currencies/{CURRENCY_ID}/transactions/category/[deposit|withdraw|move]`
- POST `/users/{USER_ID}/currencies/{CURRENCY_ID}/transactions/category/move`
- POST `/users/{USER_ID}/currencies/{CURRENCY_ID}/transactions/category/withdrawal`

#### `list_wallet_transactions` capability

##### Shortcodes affected:
- `[wallets_transactions]`

#### `generate_wallet_address` capability

##### Shortcodes affected>
- `[wallets_deposit]`

##### WP-REST API endpoints affected:
- GET `/users/{USER_ID}/transactions`
- GET `/users/{USER_ID}/transactions/category/[deposit|withdraw|move]`
- GET `/users/{USER_ID}/currencies/{CURRENCY_ID}/transactions`
- GET `/users/{USER_ID}/currencies/{CURRENCY_ID}/transactions/category/[deposit|withdraw|move]`
- POST `/users/{USER_ID}/currencies/{CURRENCY_ID}/addresses`

#### send_funds_to_user

##### Shortcodes affected:
- `[wallets_move]`

##### WP-REST API endpoints affected:
- POST `/users/{USER_ID}/currencies/{CURRENCY_ID}/transactions/category/move`

#### withdraw_funds_from_wallet

##### Shortcodes affected:
- `[wallets_withdraw]`
- `[wallets_fiat_withdraw]`

##### WP-REST API endpoints affected:
- POST `/users/{USER_ID}/currencies/{CURRENCY_ID}/transactions/category/withdrawal`

#### view_wallets_profile

Allows users to view a *[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]* section in their profile admin screen.

##### Shortcodes affected:
(none)

##### WP-REST API endpoints affected:
(none)

#### access_wallets_api

This capability has been deprecated. Controls token-bearer access to the JSON-API, which predates the WP-REST API.

##### Shortcodes affected:
(none)

##### WP-REST API endpoints affected:
(none)


### Capabilities for wallets_wallet posts

These capabilities apply to custom posts of type: `wallets_wallet`. Only admins should be allowed to modify/delete.

| Capability           | Description |
| ----------           | ----------- |
| `delete_others_wallets_wallets`    | Can delete wallets_wallets which were created by other users
| `delete_wallets_wallets`           | Has basic deletion capability (but may need other capabilities based on *[Wallet][glossary-wallet]* status and ownership)
| `delete_private_wallets_wallets`   | Can delete *[Wallets][glossary-wallet]* which are currently published with private visibility
| `delete_published_wallets_wallets` | Can delete *[Wallets][glossary-wallet]* which are currently published
| `edit_others_wallets_wallets`      | Can edit *[Wallets][glossary-wallet]* which were created by other users
| `edit_wallets_wallets`             | Has basic editing capability (but may need other capabilities based on *[Wallets][glossary-wallet]* status and ownership)
| `edit_private_wallets_wallets`     | Can edit *[Wallets][glossary-wallet]* which are currently published with private visibility
| `edit_published_wallets_wallets`   | Can edit *[Wallets][glossary-wallet]* which are currently published
| `publish_wallets_wallets`          | Can make a *[Wallet][glossary-wallet]* publicly visible
| `read_private_wallets_wallets`     | Can read *[Wallets][glossary-wallet]* which are currently published with private visibility



### Capabilities for wallets_currency posts

These capabilities apply to custom posts of type: `wallets_currency`. Only admins should be allowed to modify/delete.

| Capability           | Description    |
| ----------           | -----------    |
| `delete_others_wallets_currencies`    | Can delete wallets_currencies which were created by other users                                                 |
| `delete_wallets_currencies`           | Has basic deletion capability (but may need other capabilities based on wallets_currency status and ownership)  |
| `delete_private_wallets_currencies`   | Can delete wallets_currencies which are currently published with private visibility                             |
| `delete_published_wallets_currencies` | Can delete wallets_currencies which are currently published                                                     |
| `edit_others_wallets_currencies`      | Can edit wallets_currencies which were created by other users                                                   |
| `edit_wallets_currencies`             | Has basic editing capability (but may need other capabilities based on wallets_currencies status and ownership) |
| `edit_private_wallets_currencies`     | Can edit wallets_currencies which are currently published with private visibility                               |
| `edit_published_wallets_currencies`   | Can edit wallets_currencies which are currently published                                                       |
| `publish_wallets_currencies`          | Can make a wallets_currency publicly visible                                                                    |
| `read_private_wallets_currencies`     | Can read wallets_currencies which are currently published with private visibility                               |


### Capabilities for wallets_tx posts

These capabilities apply to custom posts of type: `wallets_tx`. Only admins should be allowed to modify/delete.

| Capability       | Description |
| ----------       | ----------- |
| `delete_others_wallets_txs`    | Can delete wallets_txs which were created by other users                                                 |
| `delete_wallets_txs`           | Has basic deletion capability (but may need other capabilities based on wallets_tx status and ownership) |
| `delete_private_wallets_txs`   | Can delete wallets_txs which are currently published with private visibility                             |
| `delete_published_wallets_txs` | Can delete wallets_txs which are currently published                                                     |
| `edit_others_wallets_txs`      | Can edit wallets_txs which were created by other users                                                   |
| `edit_wallets_txs`             | Has basic editing capability (but may need other capabilities based on wallets_txs status and ownership) |
| `edit_private_wallets_txs`     | Can edit wallets_txs which are currently published with private visibility                               |
| `edit_published_wallets_txs`   | Can edit wallets_txs which are currently published                                                       |
| `publish_wallets_txs`          | Can make a wallets_tx publicly visible                                                                   |
| `read_private_wallets_txs`     | Can read wallets_txs which are currently published with private visibility                               |


### Capabilities for wallets_address posts

These capabilities apply to custom posts of type: `wallets_address`. Only admins should be allowed to modify/delete.

| Capability          | Description    |
| ----------          | -----------    |
| `delete_others_wallets_addresses`    | Can delete wallets_addresses which were created by other users                                                 |
| `delete_wallets_addresses`           | Has basic deletion capability (but may need other capabilities based on wallets_address status and ownership)  |
| `delete_private_wallets_addresses`   | Can delete wallets_addresses which are currently published with private visibility                             |
| `delete_published_wallets_addresses` | Can delete wallets_addresses which are currently published                                                     |
| `edit_others_wallets_addresses`      | Can edit wallets_addresses which were created by other users                                                   |
| `edit_wallets_addresses`             | Has basic editing capability (but may need other capabilities based on wallets_addresses status and ownership) |
| `edit_private_wallets_addresses`     | Can edit wallets_addresses which are currently published with private visibility                               |
| `edit_published_wallets_addresses`   | Can edit wallets_addresses which are currently published                                                       |
| `publish_wallets_addresses`          | Can make a wallets_address publicly visible                                                                    |
| `read_private_wallets_addresses`     | Can read wallets_addresses which are currently published with private visibility                               |

 Doing so saves all the tabs under *[Capabilities][glossary-capabilities]*.


## Updates {#updates}

The Bitcoin and Altcoin Wallets plugin can be updated via wordpress.org as usual.

Extensions to the plugin can be updated via dashed-slug.net. You must first enter your activation code here.

You can find your activation code when you log in at dashed-slug.net.

The plugin functions normally without the activation code. The code is required only for retrieving updates information.

For more information, see [Extension updates activation][updates].


### Activation code

|     |     |
| --- | --- |
| *Option* | `ds-activation-code`
| *Default* | `''`
| *Description* | *Your personal activation code. Only works for premium members. Enables updates to the plugin's extensions.*



[fixer-io]: https://fixer.io/?fpr=dashed-slug
[json-api]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=developer#json-api
[php-curl]: https://www.php.net/manual/en/book.curl.php
[updates]: https://www.dashed-slug.net/dashed-slug/extension-updates-activation/
[wp-mail-smtp]: https://wordpress.org/plugins/wp-mail-smtp/ "Wordpress.org / WP Mail SMTP plugin by WPForms"
[support-roles]: https://wordpress.org/support/article/roles-and-capabilities/
