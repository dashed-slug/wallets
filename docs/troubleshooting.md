# Troubleshooting common issues

Before contacting support, you can check if your issue is listed here.

## The shortcodes print the message "No currencies", or the UI elements are not shown.

There can be multiple reasons for the UIs not working as expected. Here's an incomplete checklist. If you are unable to find the root cause, contact me.

1. Check that you are logged in. Almost all the UIs require the user to be logged in.
2. Check that you are logged in as a user with the right capabilities. Almost all the UIs require the `has_wallets` capability. To see which capabilities each UI element requires, refer to the *Frontend and Shortcodes* section of the documentation.
3. Check that you have added the shortcodes correctly: Check the correct syntax, and ensure that any quotes used are the `"` character, and not typographic quotes such as `&ldquo;` or `&rdquo;`. If unsure what the problem is, delete the shortcode and type it again.
4. Check that at least one *[Currency][glossary-currency]* is attached to a *[Wallet adapter][glossary-wallet-adapter]* that is currently online. Go to *[Wallets][glossary-wallet]* and check the *Connection status / version* column of each adapter.
5. Check your browser's JavaScript console for any errors (not warnings). With versions of the plugin before `6.0.0` any JavaScript error would cause all the UIs to stop working. Versions `6.0.0` and later feature *[frontend UIs][glossary-frontend-ui]* with isolated JavaScript execution contexts so this is less of a problem. But check the console for any errors! If you decide to contact support, please show me any such errors.
6. Is your server-side cache showing you stale output from before you configured the coin adapter? Clear your server side caching plugins, and reload the page. Caching plugins include *WP Super Cache*, *W3 Total Cache*, *WP Fastest Cache*, *WP-Optimize*, etc.
7. Is the HTML markup being optimized/minified/compressed? The UIs are based on the knockout.js framework, and are using *containerless control flow syntax*. This means that the code relies on semantic HTML comments. Many cache plugins and other optimization plugins (e.g. *WP-Optimize*) offer an option to optimize the HTML output. If you are running any such plugin, please disable the feature that optimizes HTML.
8. Do you see an alert box complaining about invalid JSON? If you see a popup box with a message such as: *“Could not contact server. Status: parsererror Error: SyntaxError: Unexpected token < in JSON at position XYZ”*, then the result you get from WP-REST API endpoints is not valid JSON. This is usually caused by some plugin or theme causing a PHP error, and then the error message gets printed in the API's output, causing the data payload to become invalid. You should instruct WordPress to hide any PHP errors from the front-end. Add `define( 'WP_DEBUG_DISPLAY', false );` in your `wp-config.php`. Ideally you should aim to fix any PHP errors on your site, but this constant will at least hide the errors from the front-end, and from WP-REST API responses.
9. Is the WordPress REST API disabled? The Frontend UIs require that the REST API is available. If you have disabled it, then the UIs will not work.
10. If using the CoinPayments adapter: Check to make sure that your CoinPayments API key has the correct permissions. Refer to the installation instructions for the CoinPayments adapter for details.
11. Does your theme or child override any wallets *[templates][glossary-templates]*? If you have developed custom templates, the problem may be with the templates. Try another theme to see if the problem is theme-related.

## In the admin screens, the following error is shown: "Cron tasks have not run for at least one hour..."

The full message would be:

> Cron tasks have not run for at least one hour. If you see this message once only, you can ignore it. If the message persists, you must trigger cron manually. Consult the documentation under "Troubleshooting" to see how.

The plugin runs some tasks periodically. These are called "cron tasks" or "cron jobs".

Such tasks are: executing transactions, communicating with the wallet adapters, sending emails in batches, cleanup and administration tasks, etc. These tasks are meant to run asynchronously to the main user experience.

Due to the server-client architecture of web servers, WordPress does not normally run when it is not getting traffic. By default, when it does get traffic, it can execute a few of these tasks along with serving the user request. However this is not ideal for two reasons:

- Cron jobs will not run unless the site gets traffic.
- Cron jobs may slow down the user experience, since they have to run in the same request.

Additionally, some web hosts disable cron jobs either to improve performance or to enhance security.

For all the above reasons it is recommended that you setup an external cron trigger as soon as you can. This will improve performance of all plugins using the WordPress cron mechanism. Here's how to do this:

1. Disable the build-in cron running. Edit `wp-config.php` and add the following:

	define( 'DISABLE_WP_CRON', true );

2. Verify that you have edited the config correctly. Go to: _Settings_ &rarr; _Bitcoin & Altcoin Wallets_ &rarr; _⌛ Cron tasks_.

If everything is correct, you will see the following messages:

> ⚠ You have set `DISABLE_WP_CRON` in your `wp-config.php`. Cron jobs will not run on user requests, and transactions will not be processed. You should trigger the following URL manually using a UNIX cron job: http://example.com/wp-cron.php.

...where example.com will be replaced with the actual domain of your site.

You now have several options to choose from:

Option 1: You can setup a `curl` command in another Linux server to hit this URL once per minute. The request will run the cron tasks periodically without affecting user performance for your visitors.

First, determine if curl is installed and the exact path to the curl binary with:

	which curl

Let's say you get a response of `/usr/bin/curl`.

Now type in your shell `crontab -e`, and this will bring up the crontab editor.

Add a line like the following:

	* * * * * /usr/bin/curl -s -o /dev/null https://example.com

Option 2: If you are on a hosting provider that supports it, you can use cPanel or any other software that the hosting provider offers to set up a cron job, like the one above.

Option 3: You can use a service like *[EasyCron](https://www.easycron.com/?ref=124245)* or [cron-job.org](https://cron-job.org).

1. Sign up and login.

2. Click on "+ Cron job".

3. Under "URL & Time" set "URL to call" to `https://example.com/wp-cron.php` (replacing example.com with your domain).

4. Set "When to execute" to "every minute".

5. Click "Create Cron Job".


Finally, check to see if the cron jobs are running OK. You can check by navigating to _Dashboard_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Debug_ &rarr; _Cron jobs last run on_. The time you see here should not be more than a couple of minutes old.


## In the admin screens, the following error is shown: "Sorry, you are not allowed to access this page". Or, alternatively, the admin screen is shown, but the plugin's settings and post types are not shown.

Some times on multisite not all permissions are correctly assigned. Not sure why :-(

If the plugin's cron jobs are running, the issue will be fixed automatically after a few minutes. But if the cron jobs are not running, the issue will not be auto-repaired.

The issue will also resolve itself if the capability `manage_wallets` is assigned to the admin user.

It is possible to do this using the command line tool `wp-cli`, or using another plugin.

For example, if the admin user name is `admin`, type into the shell:

	wp user add-cap admin manage_wallets

Or, using the `wp-console` plugin, load the user and add the capability manually with:

	$user = new WP_User( 'admin' );

	$user->add_cap( 'manage_wallets' );

Running the above code should resolve the issue.


## In the adapters list template, the Hot Wallet Balance is not equal to the Sum of User Balances

TL;DR They shouldn't be. The *[Hot Wallet Balance][glossary-hot-wallet-balance]* and the *Users' Balances* are NOT the same thing.

The difference is explained in the *[Glossary][glossary-glossary]* section of the documentation.

## Testing deposits and withdrawals with the plugin, costs a lot on transaction fees.

You can perform most of your tests using testnets. Bitcoin and Litecoin have robust testnets that people are always mining.

- For RPC adapters, set `testnet=1` and restart your wallet.
- For the *[CoinPayments adapter][glossary-coinpayments-adapter]*, use the `LTCT` symbol for _Litecoin testnet_.

Make sure to delete all addresses and transactions from the DB after finishing the tests. Testnet addresses are different to mainnet addresses.

After configuring your adapter to connect to a testnet, use testnet faucets to perform deposits and withdrawals.

### Bitcoin testnet faucets:
- https://testnet-faucet.mempool.co/
- http://bitcoinfaucet.uo1.net/

### Litecoin testnet faucet:
- http://testnet.litecointools.com/


## Bitcoin core uses too much memory or disk for my server

You must consider the hardware requirements BEFORE beginning installation of the wallet.

To run a full node you must set up the daemon on a machine that you have root access to. This can be a VPS or any other machine that you own.

The full blockchain needs to be downloaded, so you need to make sure that your server can handle the disk and network requirements.

Here's some advice on [how to run a bitcoind wallet in a low memory environment](https://bitcoin.stackexchange.com/questions/50580/how-to-run-bitcoind-in-a-low-memory-environment).

If you are concerned about your available disk space, you can run [a pruned node](https://bitcoin.stackexchange.com/questions/37496/how-can-i-run-bitcoind-in-pruning-mode). The same instructions will apply to many wallets that are Bitcoin forks.

If running a full node is not important for you, or if you want to get multiple currencies out of the box, you can choose to install the [CoinPayments Adapter extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/coinpayments-adapter-extension/).

## I am setting up a Bitcoin full node. In my .conf file I have provided the `rpcuser` and `rpcpassword` parameters. Instead, the plugin recommends the `rpcauth` parameter. What gives?

You should *either* use `rpcuser` and `rpcpassword` to specify login credentials to the RPC API, *or* `rpcauth`, but not both.

The `rpcauth` parameter is simply a way to specify a hashed/salted version of the username and password, rather than the plaintext values. The plugin recommends a hash that contains the username and password you have provided in the coin adapter settings. It uses the algorithm from [`rpcauth.py`](https://github.com/bitcoin/bitcoin/tree/master/share/rpcauth).


## I am testing the plugin in my development environment and I am not getting any email notifications.

This is probably not an issue with the plugin, but with your PHP setup. Unless you have set up sendmail on your system, emails will not work.

Since you are testing in a development environment, it makes sense that sendmail would not be set up. On an actual live server on a web host, emails would work in PHP by default.

An easy workaround is to forward emails through a Gmail account, (although this is not recommended with high traffic). You can setup a plugin such [WP Mail SMTP][wp-mail-smtp].

If you want to check the email queue and see if it's empty, go to *Dashboard* &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Emails on queue_.

You can also check the `wallets_email_queue` option. From the shell, you can do: `wp option get wallets_email_queue`. (This requires an installed `wp-cli`.)


## The plugin is listed as installed and activated, but I can't find it listed in the admin menu.

The menu appears only to uses who have the `manage_wallets` capability. The plugin normally sets this capability to the administrator on activation. You can check if your user has this capability with `wp-cli` or with this plugin: _[Capability Manager][caps]_


## I am trying to change the UIs (by editing shortcodes, templates, CSS rules, etc), but the frontend remains the same, no matter what.

If you have installed a caching plugin such as *WP Super Cache*, try clearing your server cache.

## I cannot connect to a wallet running on my home computer

Running a wallet at home and making it available to your web server 24/7 requires effort on your part.

Your home internet connection must have a static IP AND you must open the correct ports on your router/firewall.

You must think about uptime. If the power supply in your area is unreliable, you may need a UPS. You must also have a reliable connection to the internet.

Ideally it's better to run the wallet on a dedicated server. If your site serves multiple users, you want the wallet to be always available.

Virtual Private Servers (VPSs) should be OK; the wallets do not max-out CPU usage under normal operation, at least after the IBD (Initial Block Download) finishes.

Check with your hosting plan for disk space and memory requirements against the requirements of the wallet you wish to run.

For Bitcoin core, click [here](https://bitcoin.org/en/bitcoin-core/features/requirements).


## Next to my *[Wallet][glossary-wallet]*, the "Connection status / version" column shows an error.

- The error message includes *403 - forbidden*: The port you are trying to connect to is not open for your WordPress machine. This would indicate that your `rpcallowip` setting is not correct. Check your IP and port settings, your firewalls and lastly your host's firewalls. The unix commands `telnet` and `nc` are your friends in debugging this.
- The error message includes *301 - unauthorized*. The username and password that you provided does not match your `rpcuser` and `rpcpassword` settings, or your `rpcauth` setting. (Remember, you must use EITHER `rpcuser` and `rpcpassword`, OR `rpcauth` but NOT both.)
- If there is another error message, it will give you a hint as to what is going wrong. If you are not sure what the error message means, contact me.


## I have setup a Bitcoin-like full node wallet. When I perform a deposit, the deposit never shows up in the plugin, even after waiting for a few minutes.

Deposits are discovered via two ways: polling on cron (slow), and via curl using the wallet's *API notifications* mechanism (fast). It is unlikely that both of these mechanisms don't work. However, it's best to ensure that API notifications work, and that the cron task discovery is used only as a safety backup.

Here's a checklist for debugging incoming deposits to a full node wallet:

1. Check the wallet adapter. Go to _Wallets_ and find the Wallet. It must be of type `DSWallets\Bitcoin_Core_Like_Wallet_Adapter`.

Check the _Connection status / version_ column.

- If you see a message of the form "Version X ready" then communication with the wallet is successful.

- If you see a message such as "❎ Wallet not ready: DSWallets\Bitcoin_Core_Like_Wallet_Adapter: JSON-RPC command getwalletinfo failed with: Failed to connect to a.b.c.d port X: Connection refused", then you have not entered the IP and port correctly, or the RPC port is not reachable from your WordPress machine. Check your `.conf` file, any firewalls between your WordPress server and the wallet server, and any other networking issues preventing you from connecting.

- If you see a message such as "❎ Wallet not ready: DSWallets\Bitcoin_Core_Like_Wallet_Adapter: JSON-RPC command getwalletinfo failed with: HTTP_UNAUTHORIZED", then the JSON-RPC username and password you have entered, does not match the one you gave in your wallet's `.conf` file. Re-enter your credentials, and press _Update_. Then, remove any `rpcuser`, `rpcpassword` and `rpcauth` lines from your `.conf` file, and add the suggested `rpcauth` line shown in the metabox on the side of the screen. Save the `.conf` file and restart your wallet.

- If you get any error message invloving a timeout, the most likely problem is a firewall.

2. Check the _Block height_ column. Your wallet is synced up to this block. Your wallet must be fully synced with its blockchain, or you won't see the most recent transactions. Check your block height against that reported by a third-party block explorer or API service. For Bitcoin, you can view the latest height here: https://blockchain.info/latestblock

3. Click on the wallet, and check to see that you have associated a *[Currency][glossary-currency]* with your wallet. For example, if you have set up a Bitcoin wallet, you would link it with a _Bitcoin_ currency. In this case, you would see, at the bottom-right part of the screen, a metabox titled "Currencies assigned to this wallet". If the wallet does not know what currency it hosts, it has no way of assigning the deposit.

4. Ensure that you are sending funds to a *user deposit address*.

Click on the currency listed under "Currencies assigned to this wallet". You will see a list of addresses only for that currency. Click on "Type: Deposit" on the top of the screen. You will now only see deposit addresses for this currency (NOT withdrawal addresses).

Check to see that the address you are sending funds to, exists here. If a deposit address is not known it cannot be assigned to a user. If a deposit cannot be assigned to a user, it will not show up in the list of transactions.

5. Check with your wallet to see that the transaction is seen by the wallet.

`ssh` to the machine running your wallet.

To get a list of recent transactions on Bitcoin core:

> bitcoin-cli listtransactions

To see information about a particular transaction, issue the following (and replace “TXID” with the actual transaction ID):

> bitcoin-cli gettransaction TXID

To see which transactions have been received on which addresses that your wallet knows about:

> bitcoin-cli listreceivedbyaddress

With the above commands you can verify that the deposit address you sent funds to, and that you verified on the plugin side in step 4, also exists on the wallet.

6. Check to see if cron jobs are running.

Even if the notifications API is not correctly configured, deposits should be *eventually* discovered by polling the wallet RPC API at regular intervals. There is a *[cron job][glossary-cron-job]* that rotates over all wallets and calls their `cron()` method. Every time the cron jobs run, the `cron()` method is run for one active wallet adapter, so it may take several runs for the cron job mechanism to discover your deposits.

Check to see if the cron jobs are running OK. You can check by navigating to _Dashboard_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Debug_ &rarr; _Cron jobs last run on_.

7. Ideally you want to setup the notification mechanism, so that deposits are discovered by the plugin as soon as possible. The Notification API uses the `walletnotify=` and `blocknotify=` entries in your conf file.

These two entries specify commands that the wallet will run whenever a new transaction or block is discovered. Go to your Wallet, and on the right hand side of the screen you will find suggestions for these entries in a metabox. These suggestions are `curl` commands containing values based on the site's URL and the associated currency's post ID.

For example, the entry:

> walletnotify=curl -s 'https://www.example.com/wp-json/dswallets/v1/walletnotify/123/%s' >/dev/null

will notify the plugin about new TXIDs that concern the Currency with post ID `123`.

For this to work, `curl` must be installed on the server. You can check manually if the command works on your server, and if it can reach the plugin. Just find the TXID for your deposit, and enter the command as follows (assume that the TXID here is the string `XYZ`):

> curl -v 'https://www.example.com/wp-json/dswallets/v1/walletnotify/123/XYZ'

Here we have removed the `-s` (silent) parameter and the redirection to null (`/dev/null`), and added the `-v` (verbose) parameter. This way, any errors will become apparent.

See if you get any errors.

If you see any SSL certificate-related errors, you can add the `-k` (insecure) parameter to your command. Even if the communication is eavesdropped, the most an attacker could observe would be the TXID for the deposit. This is actually public anyway, since it exists on the blockchain, so the security implications are minimal.

If the command is successful, then the Wallet plugin is notified about transaction with `XYZ` ID, and will subsequently query the wallet for that transaction. If it exists and it matches a known deposit address, then the plugin will create a new deposit transaction for the user who owns that deposit address.

8. If all goes well, you will see a deposit transaction under _Transactions_.

Check the transaction status. It may be either `pending` or `done`, depending on how many confirmations the transactions had when it was last checked. The plugin will check the transaction's confirmation count against the minimum required, as set at the wallet adapter setting ("Minimum number of confirmations for incoming deposits.").

While a deposit has less confirmations than those required, the plugin will recheck the transaction and will update the status to `done` once the required confirmations are reached.

`pending` transactions are not counted towards the user balance, while `done` transactions do.

## I am unable to setup the transaction notification mechanism on a Bitcoin-like full node wallet. How can I ensure that transactions are eventually scraped from the wallet and appear in the plugin?

This is not optimal. The notification mechanism ensures that transactions appear almost immediately in the plugin.

Scraping runs on cron, and one block is checked on every run.

Go to the wallet editing screen: _Wallets_ &rarr; _(your wallet)_ &rarr; _Edit_ &rarr; _DSWallets\Bitcoin_Core_Like_Adapter_ &rarr; _Scraping wallet for transactions_.

Here you can set a block height to start scanning from. Enter a block height and click _Re-scrape_.

On each cron run, one active wallet adapter performs its periodic tasks. The wallet adapters are rotated so that on each cron run, one active wallet adapter runs.

When the adapter runs, it will check one block for transactions. If there are any unknown transactions that hae outputs to a user deposit address, the deposit transactions are created.

It will also check for the latest transactions using the `listtransactions` RPC command. This will retrieve the latest transactions and if they are deposits, these will also be created.

This mechanism is provided as a fail-safe. The best way to ensure that all deposits are processed is to setup the wallet notification mechanism using curl in the wallet's `.conf` file.


## Withdrawal transactions do not get processed by a full-node wallet.

Normally users can request to withdraw their funds to an external address using the `[wallets_withdraw]` shortcode. Entering a withdrawal transaction adds it to the _Transactions_ list with a status of `pending`.

1. Check to see if cron jobs are running.

Once pending withdrawals exist on the system, a cron job responsible for processing withdrawals will attempt to execute withdrawals using the appropriate wallet adapters. Every time the cron job runs, it will process withdrawals for one active wallet adapter, so it may take several runs for it to get to your withdrawals, if many adapters are active and have pending withdrawals.

The wallet adaper may attempt to send a single transaction in one go, or it may attempt to send multiple withdrawals together using a multi-output transaction, thus saving on miner fees.

Check to see if the cron jobs are running OK. You can check by navigating to _Dashboard_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Debug_ &rarr; _Cron jobs last run on_.

2. Check to see if the wallet adapter is locked. Go to the _Wallets_ list and check the _Withdrawals Lock_ column.

A locked wallet adapter will not process withdrawals. If the attached wallet is encrypted with a passphrase, and the plugin does not know the passphrase, it will show the wallet as locked.

For bitcoin-like wallets, you should have encrypted the wallet file (`wallet.dat`) using a passphrase. A passphrase is different from an RPC password, and is a key that encrypts the wallet file. A passphrase is typically set using the `walletpassphrase` RPC command in Bitcoin core and its forks. Setting a passphrase ensures that noone can initiate transactions (i.e. spend funds) from your wallet, without knowing that passphrase. It is a good idea to set a passphrase. If a hacker steals (copies) your `wallet.dat` file and it is encrypted with a passphrase, they will not be able to steal your funds unless they can brute force the passphrase.

You must go to the wallet adapter settings, and set the wallet passphrase under "Wallet Passphrase". The passphrase is stored in cleartext on the DB, as a post meta value, on the post that describes the wallet. Whenever the plugin needs to check the wallet, it uses the passphrase to unlock it, thus enabling withdrawals.

3. Check to ensure that you have set miner fees correctly in the `.conf` file.

Every full node wallet can accept arguments (conf entries) to specify the amount to be spent on outgoing transactions. For Bitcoin and friends, these include `paytxfee` and `mintxfee`. For a complete reference consult your wallet’s manual, and/or the following page: https://en.bitcoin.it/wiki/Miner_fees#Settings .

If you have set miner fees too low, withdrawal transactions will fail with an error.

Normally your users will be receiving error messages about this, and they will let you know. Fix this by modifying the `.conf` entries to match whatever is currently sensible for your blockchain.

Note that you should set the currency fees to be higher than the typical transaction fee. Navigate to the currency for your wallet, and under _Fees_ &rarr; _Withdrawl fee_, set a vaule that is higher than the typical miner fee for a simple transaction. Any difference between the Currency's withdrawal fee and the actual miner fee paid is profit for your site.

4. There is another way to debug withdrawals, and this is by enabling verbose logging. First, enable debugging on WordPress. Here are instructions on how you enable logs: [Debugging in WordPress][wpdebug]

Add the following to your `wp-config.php`:

	define( 'WP_DEBUG', true );
	define( 'WP_DEBUG_LOG', true );
	define( 'WP_DEBUG_DISPLAY', false );

Wordress will now start writing PHP warnings and errors to `wp-content/debug.log`.

Now go to _Settings_ &rarr; _Bitcoin & Altcoin Wallets_ &rarr; _Cron tasks_ &rarr; _Verbose logging_. Enable the checkbox and hit _Save Changes_.

Now, every time the Withdrawals cron task runs, it will write to the logs. Any errors will appear in the log file.

Do not forget to turn off logging once you are done, because the log file can fill up your disk space.


## I get the following error in the frontend: `Could not contact server. / Status: parseerror / Error: SyntaxError: JSON.parse: unexpected character at line 1 column 1 of the JSON data`

You would normally get this if all of the following is true:

1. you have enabled logging in `wp-config.php` ( `WP_DEBUG` is `true` ), and
2. you have not set `WP_DEBUG_DISPLAY` to `false`, and
3. any plugin or theme is causing a PHP warning or error, for any reason.

The plugin's frontend UIs communicate with your WordPress server over XHR requests. The responses to the XHR requests must be valid JSON. But if any PHP errors are written to the response, these will cause the responses to have invalid JSON syntax. Set `WP_DEBUG_DISPLAY` to `false` in your `wp-config.php`. This way, the errors will be written to the logs and not to the HTTP responses.

Ideally you should identify these errors and correct them.


## I got an email from fixer.io with the message "Please be aware that you have reached at least 75% of your available API Request volume."

The exchange rates subsystem uses the fixer.io service to determine exchange rates to fiat currencies. You would get this message typically if you have connected your website to a fixer account with a free plan.

The free plan for fixer.io gives you 1000 API calls per month. The plugin pulls data from fixer once per hour. For a 30 day month this comes out to be 30×24, or 720 calls per month. The plugin will do these calls whether the site is being used or not, as long as it is online. If you are using the plugin only on one site, the limit will not be exceeded. You can always check your fixer account to see how much of the limit you have been using. Simply login to the service and go to your dashboard to check: https://fixer.io/dashboard

If you are using the same fixer.io API key with multiple sites, you will have to upgrade to a paid plan.


## When attempting a withdrawal via a Full Node wallet (Bitcoin core or other coin's full node wallet), I get a failure with "This transaction requires a transaction fee of at least X".

It is possible that your wallet's default transaction fees are currently not set to a value that is reasonable for the blockchain network that you are using. Please consult your wallet's documentation to set the appropriate transaction fees. For Bitcoin, the relevant settings would include `paytxfee`, `mintxfee`, and `fallbackfee`. Alternatively you can use `txconfirmtarget`. After setting the fees correctly, make sure that the withdrawal fee you have set on your coin adapter's settings is the same or higher than what you set to your wallet.


## I have finished testing the plugin and am now ready to use it in production. I would like to clear any addresses and transactions that I used in testing.

That's a good idea. Simply go to Addresses and to Transactions, and throw everything into the trash!


## When I activate the plugin, my WordPress becomes extremely slow and is unusable, or I get a white screen of death, or I get a 5xx HTTP error.

If WordPress has become unusable right after installing this plugin:

You can easily regain control of your WordPress. Simply delete the plugin from `wp-contents/plugins/wallets` on the server. Note that:

- You will not lose your settings, unless if you run the plugin's uninstall script, in which case the settings will be deleted.
- You will not lose any transaction data or deposit addresses, even if you run the uninstall script. This type of data is saved in your DB, not the filesystem.

You can always try installation again later, after you diagnose the problem.

> **NOTICE**: The plugin contacts a number of third-party endpoints for its normal operation. If these connection attempts timeout (instead of succeeding or failing), this will typically delay about 30 seconds per connection, making the plugin and WordPress unusable.
>
> Connection timeouts are usually caused by **firewalls**!

Here's a few things to check if you suspect that the delays are caused by connection timeouts:

- Only enable Tor settings if your know that your site is a hidden site running on Tor.
- If you are connecting to an RPC wallet situated on a different machine than your WordPress server, make sure that your webhost allows outgoing connections to TCP ports other than `80` and `443`.
- If you are the administrator of the WordPress machine, check your firewalls for any rules that may interfere with outgoing connections. This can include hardware firewalls, software system firewalls and any WordPress security plugins.

## I cannot find how to edit the text for the notification emails. In previous versions before 6.0.0, there were edit boxes in the settings for this.

The emails are now stored as templates. You should copy the email templates into your theme or child theme and edit them:

- Templates were introduced in version 5.0.0 [and are explained here](https://www.dashed-slug.net/wallets-5-0-0/).
- Templates are also explained in the documentation, [here](/wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=frontend#editing-templates).


In short, please copy the following files:

	/PATH_TO_YOUR_WORDPRESS/wp-content/plugins/wallets/templates/email*.php

to a `templates/wallets` directory under your theme or child theme.

For example, to change the withdrawal confirmation email, you would copy the file `email-withdrawal_pending.php` from your plugin directory to:

	/PATH_TO_YOUR_WORDPRESS/wp-content/themes/YOUR_THEME/templates/wallets/email-withdrawal_pending.php

Then, edit the text as appropriate.

You can use basic HTML to format your emails, or even PHP code if you need to.

If you copy the files to your theme, then your changes will be lost if you update your theme. This is why it is recommended that you create a [child theme](https://developer.wordpress.org/themes/advanced-topics/child-themes/) to your theme, if you haven't already.


## Something else is wrong. I would like to check the logs for any errors that might give me clues as to what's going on.

Here are instructions on how you enable logs: [Debugging in WordPress][wpdebug]

Add the following to your `wp-config.php`:

	define( 'WP_DEBUG', true );
	define( 'WP_DEBUG_LOG', true );
	define( 'WP_DEBUG_DISPLAY', false );

Wordress will now start writing PHP warnings and errors to `wp-content/debug.log`.

The plugin always writes to the logs on activation. It would write something like: `Upgrading wallets schema from X to Y.` and `Finished upgrading wallets schema from X to Y.`.

If, while you are activating the plugin, it does not write to that file, this means that logging on your WordPress is not working properly.

Check your `wp-config.php` and the filesystem owner/permissions on the `wp-content` directory. The directory must be writable by the user of your webserver daemon. The user is usually named `www-data`, but it varies from system to system.


[caps]: https://wordpress.org/plugins/capsman/
[wpdebug]: https://codex.wordpress.org/Debugging_in_WordPress
[wp-mail-smtp]: https://wordpress.org/plugins/wp-mail-smtp/
