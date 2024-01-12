# Installation instructions

- If you are migrating from wallets 5.x, see [Migrating from 5.x](#migrating).

- If you are performing a new installation, continue reading here:

> Before you begin installation, familiarize yourself with the available *[wallet adapter][glossary-wallet-adapter]* *[extensions][glossary-extension]*. Decide which ones you will use.

## Install the plugin

1. Go to _[Plugins][admin-plugins]_.

2. Click on _Add New_.

3. Search for _Bitcoin and Altcoin Wallets_.

4. Click _Install Now_.

5. Click _Activate_.

## Install a Bitcoin core wallet

With the next steps we install a Bitcoin core wallet. This will also work with other similar wallets, e.g. Litecoin core, Dogecoin core, etc. Any wallets with a Bitcoin-like JSON-RPC API will work.

> If you are not interested in installing any Bitcoin or Bitcoin-compatible full node wallets, skip to step 53.

6. Install a Bitcoin core full node on a (Linux) server that your WordPress machine can access. For installation instructions refer to [bitcoin.org][bitcoin-core-install].

7. Optionally, make your wallet run as a service on your server. For example, here's how I do this using `systemd`. First, I create a file `/etc/systemd/system/bitcoin.service`.

8. In that file, I add the following text:

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

9. Change `alexg` to your user name. (Unsure which user you are? On the Linux shell, type `whoami`.)

10. Check to see that the path to the Bitcoin binary is correct for your server. Unsure where the Bitcoin binary is? On the Linux shell, type which `bitcoind`.

11. Save your file.

12. Enter `systemctl start bitcoin` to start the Bitcoin service.

13. Enter `systemctl status bitcoin` to check that your bitcoin service is active. (Press `q` to exit the status view.)

14. Enter `systemctl enable bitcoin` to make the service start when your server reboots.

15. Out of scope for this guide, but you should think how you will keep your server software updated, how to back up the wallets file regularly, how to harden the OS security, etc.

16. Go to _Wallets_.

17. Click _Add New_.

18. Give a title to your new *[Wallet][glossary-wallet]*. Let's call it _Bitcoin core wallet_.

19. At the _Wallet Adapter_ dropdown, pick `DSWallets\Bitcoin_Core_Like_Wallet_Adapter`. Use this adapter when connecting to Bitcoin core or similar wallets, e.g. Litecoin core, Dogecoin core, etc.

> **TIP**: To add more *Wallet Adapers*, install wallet adapter extensions. These are available free of charge on dashed-slug.net.

20. Hit _Publish_ to save the Wallet. The settings that are specific to the Wallet Adapter you chose will appear on your screen.

21. Check the _Wallet Enabled_ box.

22. Enter the IP address of the machine running the wallet. Set to `127.0.0.1` if you are running the wallet on the same machine as WordPress. If you want to enter an IPv6 address, enclose it in square brackets e.g.: `[0:0:0:0:0:0:0:1]`.

23. Enter the port number that you plan on using for the wallet's RPC API. For Bitcoin, the default is `8332` (or `18332` for testnet). Other wallets use different ports by default.

24. Choose a username that you want to use for the wallet's JSON-RPC API. For our example, we'll use `rpcuser`.

25. Choose a password that you want to use for the wallet's JSON-RPC API. For our example, we'll use `rpcpass`. You should use a password that is hard to guess.

26. If your wallet has been encrypted with a passphrase, enter it too. This will let the plugin create transactions. Without it, the Wallet Adapter will not process withdrawals.

27. Hit _Publish_ to save your settings. Note the _Recommended .conf file settings_ on the right hand of the screen.
 
28. Edit the `bitcoin.conf` file. If you are unsure where the file is, [see here][bitcoin-conf-location].

29. Enter the following configuration lines:

		server=1
		rpcallowip=127.0.0.1/8
		rpcbindip=127.0.0.1
		rpcport=8332

30. If the Bitcoin wallet runs on a different machine to your WordPress server, replace the IP address on the `rpcallowip` line. Note that the address is in CIDR notation (You must add a netmask, which is usually `/8`, `/16`, or `/24` depending on whether your server is on a Class A, Class B, or Class C network).

31. Add the `rpcauth` line from the _Recommended .conf file settings_ (see step 20). This value is a salted hash of the JSON-RPC API username and password you provided above. 

32. Save your `bitcoin.conf` file.

33. Restart the bitcoin server, with `systemctl restart bitcoin` for the configuration changes to take effect.

34. Go to _[Wallets][wallets_wallet]_. Check the _Connection status / version_ column next to your _Bitcoin core wallet_. If connection was successful, this will read _Version X ready_ . If connection was NOT successful, consult the [Troubleshooting section][troubleshooting].

35. Check the _Block height_ to see if your wallet is synced. Compare the block height you see with one from a public block explorer. If your wallet is not synced, it will not function properly.

36. Go to _Currencies_.

37. Click _Add New_. We are going to create a *Bitcoin* currency and link it to our wallet.

38. Leave the title blank, as it will be autofilled from CoinGecko. In the CoinGecko ID field below, type `bitcoin`. Notice the autocomplete feature.

39. In the _Wallet_ dropdown, select the _Bitcoin core wallet_ that you just created. This will link this currency with the wallet.

40. No need to enter the ticker symbol `BTC`, as it will be filled in from CoinGecko.

41. Enter `8` for the _Number of decimal places_. This is important as it determines how amounts are saved as integers and interpreted as decimal values.

42. Enter `%01.8f` for _Display pattern_.

43. Hit _Update_. Once the number of decimals is set, you can continue to set the remaining currency settings. The currency title should now be `Bitcoin` and the ticker symbol should be `BTC`. These values are retrieved from CoinGecko.

44. Optionally, you can set a Bitcoin logo icon as the Currency's _Featured Image_. This should be a square logo representing the currency. For Bitcoin, you would enter the Bitcoin logo here. However, since you've set the CoinGecko ID `bitcoin`, the plugin's cron job will soon download and apply the Bitcoin logo to this currency. This may take a few minutes.

45. In _Deposit fee_, enter `0`.

46. In _Internal transfer (move) fee_ enter any value. For example, to charge 10 Satoshis on each internal transfer, you would enter: `0.00000010`.

47. In _Withdrawal fee_ enter a value. This value must be larger than the cost of a simple transaction. For example, to charge 100k Satoshis per withdrawal, you would enter: `0.00100000`.

48. Hit _Update_ once more to save the changes to the Bitcoin currency.

49. Go to _Currencies_ again. Check to see that the currency is correctly linked to the wallet. If the hot wallet has any balance on it, this will be shown next to the currency.

Next we are going to add a `walletnotify` and a `blocknotify` config to our `bitcoin.conf` file. This will cause the wallet to call the plugin's *[WP-REST API][glossary-wp-rest-api]* and notify about any new transactions or blocks. The adapter will try to discover transactions even without this, but if you set up the notify API correctly, incoming deposits will appear faster on the plugin. The commands are `curl` commands that pass the TXID or block hash to the plugin, along with the Currency ID. (This is why you must copy these configs only after you link the Wallet with the Currency.)

> If you are not interested in setting up the notify API, you can skip to step 53.

50. Go back to the _Bitcoin core wallet_ for this currency. You can click on it on from the Currency screen.

51. From _Recommended .conf file settings_, copy the two lines, "walletnotify for Bitcoin" and "blocknotify for Bitcoin", into your `bitcoin.conf`.

52. Restart your Bitcoin wallet with `systemctl restart bitcoin`. Now the plugin will be notified about new transactions and blocks. This will make incoming deposits go faster.

## Define fiat currencies for use with manual bank wire transfers

Next we are going to have the plugin auto-create all the known fiat currencies. The pugin will use the remote service fixer.io, which provides exchange rate data for all fiat currencies. The plugin will first create the currencies, then keep their exchange rates data updated every hour.

> If you are not interested in defining any fiat currencies in the plugin, skip to step 69.

53. Go to [fixer.io][fixer-io] and signup for a free account. You will get an API key for the service.

54. Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _[Fiat currencies][settings-fiat]_.

55. Enter your fixer.io API key and hit _Save Changes_. The plugin will soon load the fiat currency data. Select the currencies that you want to be created, and hit _Save Changes_ again. Now, the plugin will create fiat currency entries asynchronously, on the next cron task runs. This can take several minutes. If your website does not have any traffic, you will need to trigger the cron jobs manually.

56. Go to _Wallets_.

57. Click _Add New_. We will create a virtual wallet that will let admins handle user Fiat deposits/withdrawals manually.

58. Give a title to your new _Wallet_. Let's call it _"Fiat Bank Accounts"_.

59. At the _Wallet Adapter_ dropdown, pick `DSWallets\Bank_Fiat_Adapter`. Use this adapter for Fiat currencies. The adapter will let you enter bank details.

60. Check the _Wallet Enabled_ box.

61. Hit _Publish_ to save your changes.

62. Go to a Fiat currency that you would like to use, e.g. go to _Currencies_ &rarr; _United States Dollar_ &rarr; _Edit_.

63. At the _Wallet_ dropdown, pick the Wallet you just created, i.e. _"Fiat Bank Accounts"_.

64. Hit _Update_ to link the Fiat currency with the _Fiat Bank Accounts_ wallet.

65. Optionally, set a _Featured Image_ for this currency. This should be a square logo representing the currency.

66. Hit _Update_ again to save the remaining changes to your currency.

We will now enter some bank details, so we can process deposits / withdrawals on behalf of users, with bank wire transfers.

For more details on how this works, see _Tools_ &rarr; _[Fiat deposits][fiat-deposits]_ and _Tools_ &rarr; _[Fiat Withdrawals][fiat-withdrawals]_.

> If you are not interested in processing bank wire transfers, skip to step 69.

67. Go back to the _Fiat Bank Accounts_ wallet. You can use the link on the right hand of the screen, in the metabox titled _Wallet_.

68. For each Fiat currency that you have linked to this wallet, you will get a few input fields. Use these fields to specify the details of the bank account that you will use to process deposits.

## Review the most important plugin settings

We will now configure some important plugin settings. You can review the remaining settings later.

69. Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _[Exchange rates][settings-rates]_.

70. Study the informatin on the screen. Choose some *[VS currencies][vs-currencies]* that you think your users are familiar with. The plugin will have to download data repeatedly for each cryptocurrency against all the VS currencies. So, don't select all of them, unless you need them. After you select some currencies, hit _Save Changes_.

71. Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _[Notifications][settings-notify]_. Decide whether you want the user to have to confirm by email any outgoing *[Withdrawals][glossary-withdrawal]*, and *[Internal Transfers][glossary-internal-transfer]*. This is an extra security feature that is enabled by default only for withdrawals.

72. Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _[Capabilities][settings-caps]_, to configure access control for the plugin. Review the *[General Capabilities][glossary-general-capabilities]* set against each of your [User roles][user-roles]. Only *Administrators* should have `manage_wallets`, as this gives complete access to the plugin. User roles who should have access to wallets must have `has_wallets` at a minimum. Give the remaining capabilities to your user roles as needed.

73. Only if you have purchased *Premium membership*: Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _[Updates][settings-updates]_ and enter your *[Activation code][glossary-activation-code]*. This will enable any installed *[extensions][glossary-extension]* to retrieve updates from the dashed-slug.net update server.

## Use the *[Wallet Shortcodes][glossary-wallets-shortcodes]*

74. Create a new page: Go to _Pages_ &rarr; _Add New_.

75. In the page, add a few *[wallet shortcodes][glossary-wallets-shortcodes]*. If unsure which ones to use, enter the following ones for now:
	- `[wallets_balance]`
	- `[wallets_deposit]`
	- `[wallets_withdraw]`
	- `[wallets_move]`
	- `[wallets_transactions]`
	- `[wallets_status]`
	- `[wallets_rates]`

76. _Publish_/_Update_ your page.

77. Visit the page in the *Frontend*. Check that you see the *[Frontend UIs][glossary-frontend-ui]*.

78. Optionally, visit the *[Customizer][glossary-customizer]*. Navigate to _Customizer_ &rarr; _Bitcoin and Altcoin Wallets_. Fiddle with the settings to modify the appearance of the UIs.

## Installing more *[Extensions][glossary-extension]*

That's it! If everything was done correctly, you have a basic installation up and running, with a wallet or two connected and ready to accept deposits.

You should test deposits and withdrawals before publishing your site.

If you encountered any issues during the installation, check the *[Troubleshooting][troubleshooting]* section.

You are now ready to install more *[App extensions][glossary-app-extension]* or *[Wallet adapters][glossary-wallet-adapter]*. Refer to the documentation of these extensions for installation instructions!

> **TIP**: As you install more extensions, the documentation for these extensions will be added to the _Wallets Admin Docs_ pages.

# Migrating from 5.x {#migrating}

Earlier versions of wallets saved transactions and addresses in custom MySQL tables.

With version `6.0.0` of *[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]*, all data is saved on custom post types. This means that the plugin must migrate this data into new posts:

- You must create new Wallets. Wallet settings will not be migrated from 5.x.
- You should create some currencies. The migration tool will help you find out which currencies need to be defined. The migration process will even try to guess Currency settings and create currencies for you, but it does this by guessing the currency from the ticker symbol. This is not ideal and errors can occur. You should create all migrated currencies manually before starting migration.
- Address posts will be created from data in the `wp_wallets_adds` table.
- Transactions posts must be created from data in the `wp_wallets_txs` table.
- You must link Currencies to wallets manually.

> **WARNING**: You must not use any theme that carries its own copy of wallet *[Templates][glossary-templates]*. This is because the old wallet templates are not compatible with the new ones. If your theme or child theme has a `templates/wallets` directory, delete or rename the directory!

What follows is full step-by-step instructions on migrating a *[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]* installation.

The plan is a three step process:
- first enable and configure the *parent plugin*, and assign currencies to Bitcoin-like wallets
- then enable and configure the *[wallet adapters][glossary-wallet-adapter]*, and assign currencies to other wallets
- finally enable and configure the *[app extensions][glossary-app-extension]*

## Upgrade the plugin to version `6.0.0`


0. Backup your website's DB before migration, if possible! You can use `phpMyAdmin` or any other tool you are familiar with.

1. Deactivate all dashed-slug plugins.

2. Update *[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]* to its latest version `6.0.0` or later. Do not activate other plugin extensions yet.

3. Study the chapter _Migrating from 5.x.x_ of this documentation. It will help you understand what is being migrated, why, and how.

4. Activate *[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]*.

5. Go to _Tools_ &rarr; _Migration Tool_.

You will see a list of ticker symbols (e.g. `BTC`, `USDT`, etc), and the total balances held in the old SQL ledger. You will also see for each of these ticker symbols, if there is a Currency defined with that ticker symbol.

Some ticker symbols will not correspond to a Currency yet. Take the time to create all missing Currencies beforehand.

For missing cryptocurrencies, go to _Currencies_ &rarr; _Add New_. Specify 8 decimals for Bitcoin-like coins, 12 decimals for Monero, etc. If you enter the Coingecko ID for a currency and then save the currency, some details for that currency will be filled in for you on the next cron job run (can take a few minutes).

For missing fiat currencies, simply add your free fixer.io API key in the plugin's settings: Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Fiat Currencies_ &rarr; _fixer.io API key_ and enter your [fixer.io][fixer-io] API key. This will create all the known *[Fiat currencies][glossary-fiat-currency]* over a few cron runs (this can take several minutes).

All missing currencies should be created before migration starts. The migration tool tries to infer the *[CoinGecko ID][glossary-coingecko-id]* of an unknown cryptocurrency by its ticker symbol, since previous versions of the plugin only used ticker symbols. If the migration tool is forced to create a Currency on the fly, it may not guess all the parameters correctly. You will then have to revert the migration, create the missing currencies, and try again.

6. Start one type of migration, either "Transactions" or "Balances". Refer to the chapter _Migrating from 5.x.x_ of this documentation to see what this is about.

> **TIP**: The migration task runs as a *[cron task][glossary-cron-tasks]*. If your *[cron jobs][glossary-cron-job]* are not running, fix that issue now. (Refer to Refer to the chapter _Migrating from 5.x.x_ &rarr; _How?_)

> **TIP**: The *[Frontend UIs][glossary-frontend-ui]* will be unavailable until migration finishes.

While migration runs, any newly created *[Currencies][glossary-currency]*, *[Addresses][glossary-address]* and *[Transactions][glossary-transaction]* will be assigned the `migrated` tag. This allows for the migration process to be reverted.

7. If your cron jobs are running, you can monitor progress by refreshing the tool every now and then. The cron jobs should be running once per minute, for a few seconds. When migration finishes, all admins with the `manage_wallets` capability will be notified by email. At this point, you can double-check that migration was successful: Compare the _SQL Sum of User balances_ column with the _CPT Sum of User balances_ column. If migration was successful, the values should be identical.

## Reconnect an existing Bitcoin core or compatible wallet

Let's assume that we are re-connecting a Bitcoin wallet, or a similar wallet, such as Litecoin or Dogecoin. Previously, these altcoins were installed using the _MultiCoin Adapter_ *[extension][glossary-extension]*. The process is now easier, as we will use the built-in `DSWallets\Bitcoin_Like_Wallet_Adapter`. An admin must re-enter the settings into a new *[Wallet][glossary-wallet]* CPT.

If you do not need to connect to such a wallet, skip to step 27.

8. Go to _Wallets_.

9. Click _Add New_.

10. Give a title to your new *[Wallet][glossary-wallet]*. e.g.: _Bitcoin core wallet_ or _Dogecoin core wallet_.

11. At the _Wallet Adapter_ dropdown, pick `DSWallets\Bitcoin_Core_Like_Wallet_Adapter`. Use this adapter when connecting to Bitcoin core or similar wallets, e.g. Litecoin core, Dogecoin core, Bitcoin ABC (cash), etc.

12. Hit _Publish_ to save the Wallet. The settings that are specific to the Wallet Adapter you chose will appear.

13. Check the _Wallet Enabled_ box.

14. Enter the IP address of the machine running the wallet. Set to `127.0.0.1` if you are running the wallet on the same machine as WordPress. If you want to enter an IPv6 address, enclose it in square brackets e.g.: `[0:0:0:0:0:0:0:1]`.

15. Enter the port number for the wallet's RPC API. For Bitcoin, the default is `8332` (or `18332` for testnet). Other wallets use different ports by default.

16. Enter your wallet's JSON-RPC username.

17. Enter your wallet's JSON-RPC password.

18. If your wallet has been encrypted with a passphrase, enter it too. This will let the plugin create transactions. Without it, the Wallet Adapter will not process withdrawals.

19. Hit _Publish_ to save your settings.

20. Go to _[Wallets][wallets_wallet]_. Check the _Connection status / version_ column next to your _Bitcoin core wallet_. If connection was successful, this will read _Version X ready_ . If connection was NOT successful, consult the [Troubleshooting section][troubleshooting].

21. Go to _Currencies_ and find the currency for this wallet. It should have been created by the migration process. If not, you can create it yourself.

22. Assign your new *[Wallet][glossary-wallet]* to the *[Currency][glossary-currency]* you are editing or creating.

23. Set the *[Currency][glossary-currency]* details correctly.

The following details are particularly important:
- Title (Name)
- Ticker symbol
- Decimals
- CoinGecko ID

The remaining Currency details can be set either now or later.

Finally Hit _Update_ to save your *[Currency][glossary-currency]* details.

24. Go back to the wallet, and note the _Recommended .conf file settings_ on the right hand of the screen. The two curl commands silently contact your plugin's *[WP-REST API][glossary-wp-rest-api]* to notify about transactions and blocks. The URI contains the assigned *Currency's* post ID.

25. Go back to step 8 and repeat for each wallet with a JSON-RPC API compatible to that of Bitcoin.

26. Edit the `bitcoin.conf` or other `.conf` file. If you are unsure where the file is, [see here][bitcoin-conf-location]. The `walletnotify=` and `blocknotify=` lines in your config must be updated for version `6.0.0`, to match the ones shown next to the wallet. 

## Redefine fiat currencies for use with manual bank wire transfers

> Now we are going to enable the _Fiat Currencies_. If the migration tool indicates that you have any data on fiat currencies, i.e. if you see ticker symbols such as `USD`, `EUR`, `AUD`, etc. then you need to enable _Fiat Currencies_. If you do not need to do this, skip to step 39.

27. Go to _Wallets_.

28. Click _Add New_. We will create a virtual wallet that will let admins handle user Fiat deposits/withdrawals manually.

29. Give a title to your new _Wallet_. Let's call it _"Fiat Bank Accounts"_.

30. At the _Wallet Adapter_ dropdown, pick `DSWallets\Bank_Fiat_Adapter`. Use this adapter for Fiat currencies. The adapter will let you enter bank details.

31. Check the _Wallet Enabled_ box.

32. Hit _Publish_ to save your changes.

33. Go to a Fiat currency that you would like to use, e.g. go to _Currencies_ &rarr; _United States Dollar_ &rarr; _Edit_.

34. At the _Wallet_ dropdown, pick the Wallet you just created, i.e. _"Fiat Bank Accounts"_.

35. Hit _Update_ to link the Fiat currency with the _Fiat Bank Accounts_ wallet.

36. Go back to the Wallet you just created, i.e. _"Fiat Bank Accounts"_.

37. For each currency that you plan to send and receive bank transfers for, enter the following details:

_Bank name and address_ - Full name and address of bank where you will be receiving fiat deposits.
_Addressing method_ - Select which type of bank transfer to use to receive fiat deposits. Depends on which region your bank is in: Americas, India, Europe.
_Bank branch_ - Enter the details that uniquely specify the bank branch where you will receive fiat deposits. Depending on your choice of addressing method, this will be: SWIFT/BIC or Routing Number or IFSC.
_Bank account_ - Enter the details that uniquely specify your bank account where you will receive fiat deposits. Depending on your choice of addressing method this can be IBAN or Account Number.

Then hit _Update_ to save your bank info. The `[wallets_fiat_deposit]` and `[wallets_fiat_withdraw]` shortcodes will work as expected.

38. Check your frontend for any issues: The shortcodes syntax is backwards-compatible with the old syntax. The *[frontend UIs][glossary-frontend-ui]* are as compatible as possible with the old UIs, including the general markup structure, *[Customizer][glossary-customizer]* settings, HTML classes, etc. In any case, smoke-test your UI to avoid any surprises!

## Review the most important plugin settings

We will now configure some important plugin settings. You can review the remaining settings later.

39. Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _[Exchange rates][settings-rates]_.

40. Study the informatin on the screen. Choose some *[VS currencies][vs-currencies]* that you think your users are familiar with. The plugin will have to download data repeatedly for each cryptocurrency against all the VS currencies. So, don't select all of them, unless you need them. After you select some currencies, hit _Save Changes_.

41. Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _[Notifications][settings-notify]_. Decide whether you want the user to have to confirm by email any outgoing *[Withdrawals][glossary-withdrawal]*, and *[Internal Transfers][glossary-internal-transfer]*. This is an extra security feature that is enabled by default only for withdrawals.

42. Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _[Capabilities][settings-caps]_, to configure access control for the plugin. Review the *[General Capabilities][glossary-general-capabilities]* set against each of your [User roles][user-roles]. Only *Administrators* should have `manage_wallets`, as this gives complete access to the plugin. User roles who should have access to wallets must have `has_wallets` at a minimum. Give the remaining capabilities to your user roles as needed.

43. Only if you have purchased *Premium membership*: Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _[Updates][settings-updates]_ and enter your *[Activation code][glossary-activation-code]*. This will enable any installed *[extensions][glossary-extension]* to retrieve updates from the dashed-slug.net update server.

## Installing more extensions

By now you should have installed a few fiat currencies and/or cryptocurrencies, connected to some Bitcoin-like full node wallets, and assigned *[Currencies][glossary-currency]* to these new *[Wallets][glossary-wallet]*.

Check deposits and withdrawals before proceeding.

44. You can now install any aditional wallet adapters you may need. These are currently:

- _Monero Wallet Adapter_ version `2.0.0`
- _TurtleCoin Wallet Adapter_ version `0.2.0-beta`
- _CoinPayments Wallet Adapter_ version `2.0.0`

Refer to the installation or migration instructions for these *[wallet adapter][glossary-wallet-adapter]* *[extensions][glossary-extension]* for details on how to migrate.

45. Once you have tested deposits and withdrawals for all your *[Wallets][glossary-wallet]* and *[Currencies][glossary-currency]*, you can go on to install some *[App extensions][glossary-app-extension]*.

Refer to the installation or migration instructions for these *[app extensions][glossary-app-extension]* for details on how to migrate.


[fixer-io]: https://fixer.io/?fpr=dashed-slug "Fixer.io"

[bitcoin-core-install]: https://bitcoin.org/en/full-node#other-linux-daemon
[user-roles]: https://wordpress.org/support/article/roles-and-capabilities/ "Roles and Capabilities"

[admin-plugins]: /wp-admin/plugins.php
[migration]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=migration
[bitcoin-conf-location]: https://en.bitcoin.it/wiki/Running_Bitcoin#Bitcoin.conf_Configuration_File
[wallets_wallet]: /wp-admin/edit.php?post_type=wallets_wallet
[troubleshooting]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=troubleshooting
[fixer-io]: https://fixer.io/?fpr=dashed-slug "Fixer.io"
[settings-fiat]: /wp-admin/options-general.php?page=wallets_settings_page&tab=fiat
[settings-rates]: /wp-admin/options-general.php?page=wallets_settings_page&tab=rates
[settings-notify]: /wp-admin/options-general.php?page=wallets_settings_page&tab=notify
[settings-caps]: /wp-admin/options-general.php?page=wallets_settings_page&tab=caps
[settings-updates]: /wp-admin/options-general.php?page=wallets_settings_page&tab=updates
[fiat-deposits]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=tools#fiat-deposits
[fiat-withdrawals]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=tools#fiat-withdrawals
[troubleshooting]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=troubleshooting
