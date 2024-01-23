# Developer reference

The following topics are of interest to developers who wish to interface with the *[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]* WordPress plugin.

- [Working with custom post types](#post-types) &mdash; Learn how to manipulate the plugin's custom post types from PHP.
- [Wallet APIs](#apis) &mdash; Learn how to communicate with the plugin's various APIs, from over the network, from PHP, or from JavaScript running on the browser.
- [Developing wallet adapters](#wallet-adapters) &mdash; Learn how you can implement new wallet adapters and thus connect new types of wallets to the plugin.
- [Developing cron job tasks](#cron-jobs) &mdash; Learn which cron job tasks are currently implemented into the plugin's cron job scheduler, and how you can extend with your own tasks.

## Working with custom post type objects: *[Wallets][glossary-wallet]*, *[Currencies][glossary-currency]*, *[Addresses][glossary-address]* or *[Transactions][glossary-transaction]* {#post-types}

> The four post types are discussed in the [Post Types][post-types] chapter of this documentation.

To see PHP code examples of how to manipulate *[Wallets][glossary-wallet]*, *[Currencies][glossary-currency]*, *[Transactions][glossary-transaction]* and *[Addresses][glossary-address]*, you can review the PHPdocumentor reference for `DSWallets\Wallet`, `DSWallets\Currency`, `DSWallets\Address` and `DSWallets\Transaction` .

> **TIP**: You can quickly try out PHP commands using the third-party plugin [WP-Console][wp-console]. Just remember to use the `DSWallets` namespace.

### Loading the plugin's objects onto PHP variables

The Post Types are implemented as PHP object classes that are subclasses of the `DSWallets\Post_Type` abstract class.

All post type objects have a `post_id` integer field. When an object is loaded from DB, its post ID will be in there. Do not set the `post_id` field directly. It will be populated when you `->save()` the object (see below).

If you know the post ID of a *[Wallet][glossary-wallet]*, *[Currency][glossary-currency]*, *[Address][glossary-address]* or *[Transaction][glossary-transaction]*, you can load it into a variable and manipulate its fields. For example, to load the Wallet with ID `123`, you might do:

	$wallet = DSWallets\Wallet::load( 123 );

> **TIP**: To see which fields you can modify on each object, as well as more example code snippets, review the PHPdocumentor reference for each post type. The PHPdocumentor includes example code.

You may also use other helper functions to retrieve collections of objects, or individual objects, based on certain conditions/queries/etc.

For example, to get all the wallets available on your system, you would call:

	$wallets = DSWallets\get_wallets();

> **TIP**: To see other functions that will retrieve objects from the DB, see the getters in the following helper files (functions with names beginning with `get_`):

The following files contain getters for post type objects.

- [`wallets/helpers/wallets.php`][wallets-helpers]
- [`wallets/helpers/currencies.php`][currencies-helpers]
- [`wallets/helpers/transactions.php`][transactions-helpers]
- [`wallets/helpers/addresses.php`][addresses-helpers]

### Manipulating the plugin's objects via PHP code

Here is an example of how you would load a *[Wallet][glossary-wallet]* with a post ID of `123`, and do stuff with it.

In the following example code, we do the following:
- We load the wallet, taking care of any errors if necessary.
- We get the first currency associated with this wallet and put it in a variable. Usually this currency is the only one (unless if the wallet adapter is a multi-currency wallet adapter).
- If the wallet is loaded with a *[Wallet Adapter][glossary-wallet-adapter]*, we print out the adapter's settings to the [debug log file][wpdebug]. (The wallet adapter settings are normally set by an admin.) These settings are typically connection settings. Which settings are stored depends on the type of *[Wallet Adapter][glossary-wallet-adapter]* used with the wallet. With these settings, the wallet adapter can connect with a cryptocurrency wallet.
- We then retrieve the *[Hot Wallet Balance][glossary-hot-wallet-balance]* from the wallet. Note that currency amounts are always integers to avoid floating point-errors. We shift the integer amount by the currency's number of decimals to get the correct value. (Multiply the amount by `10` raised to minus the number of decimals for the currency. For example, to display a Bitcoin amount we would multiply by `-10^8`.)
- Finally, we modify the wallet's name and save the change to our DB. We capture any problems that may arise while saving the wallet.

Here's the full code:

	use DSWallets\Wallet;
	use function DSWallets\get_currencies_for_wallet;

	// Here we already know that the post ID of our wallet 123.
	$wallet_id = 123;

	try {
		$wallet123 = Wallet::load( $wallet_id );

	} catch ( \Exception $e ) {
		wp_die( "Could not load wallet, because of exception with message: " . $e->getMessage() );
	}

	error_log( "The wallet title is: $wallet->name" );

	// Get the first currency associated with the wallet. Usually there is only one currency per wallet.
	$currencies = get_currencies_for_wallet( $wallet );
	$currency = null;
	foreach ( $currencies as $c ) {
		$currency = $c;
		break; // we only need the first currency.
	}

	if ( $wallet->adapter ) {
		// Here, if all goes well, the Wallet Adapter is auto-intantiated in $wallet->adapter.
		// The adapter's class is deriving from the abstract DSWallets\Wallet_Adapter.
		// The adapter's connection settings have been injected into the adapter object.
		// The adapter may have already connected to the wallet at this point.

		error_log( "The wallet adapter atteched is of type: " . get_class( $wallet->adapter ) );

		error_log( "The wallet adapter settings for wallet $wallet_id are: " . json_encode( $wallet->adapter_settings ) );

		// Get hot wallet balance for this currency directly from the wallet.
		$hot_wallet_balance = $wallet->adapter->get_hot_balance( $currency_id ) * 10 ** -$currency->decimals;
		error_log(

			sprintf(
				"The wallet '%s' has a hot wallet balance of $currency->pattern %s",
				$wallet->title,
				$hot_wallet_balance,
				$currency->name
			)
		);

	} else {
		// There's two possible reasons why the wallet does not have a wallet adapter instantiated:

		if ( $wallet->is_enabled ) {
			error_log( "No wallet adapter is attached to wallet $wallet_id " );
		} else {
			error_log( "The wallet $wallet_id is not currently enabled and therefore its wallet adapter will not be loaded. " );
		}
	}

	// We set a new name to our wallet
	$wallet->name = "This is the wallet with post ID $wallet_id";

	// We save the wallet, and the new name is written out to the DB.
	try {
		$wallet->save();
	} catch ( \Exception $e ) {
		wp_die( "Could not save the new wallet title, because of exception with message: " . $e->getMessage() );
	}

### Saving changes to the plugin's objects to the MySQL DB

Changes are typically not saved unless you call `->save()` on a post type class. For example, to save a *[Currency][glossary-currency]* after manipulating it:

	try {
		$currency->save();
	} catch ( \Exception $e ) {
		wp_die( "Could not save changes to the currency, because of exception with message: " . $e->getMessage() );
	}

There is one type of change that does not require you to call `->save()`, but is applied immediately: *[Currencies][glossary-currency]*, *[Transactions][glossary-transaction]*, and *[Addresses][glossary-address]* can be organized using [custom taxonomies][custom-taxonomies]. When setting taxonomy tags to these objects, the tags are applied immediately.

For example, the following code does the following:
- Loads an address with post ID `124`.
- Determines the extra field name for the address's currency from the wallet attached to that currency. (e.g. *Monero "Payment ID"*, *Ripple "Destination Tag"*, etc).
- Prints out the main address string, and if there is a payment ID, destination tag, or other extra information, it prints this out too, using the correct name for the extra field.
- Adds a tag `foo` to the address, using the custom taxonomy `wallets_adds_tags`.

Here's the full code:

	use DSWallets\Address;

	// Will load address with post ID 124
	$address_id = 124;

	try {
		$address = Address::load( $address_id );

	} catch ( \Exception $e ) {
		wp_die( "Could not load address $address_id, because of exception with message: " . $e->getMessage() );
	}

	$extra_field = '<unknown address field>';
	if ( $address->currency && $address->currency->wallet ) {
		$extra_field = $wallet->adapter->get_extra_field_name( $wallet->currency->post_id );
	}

	if ( $address->extra ) {
		error_log( "The address with post ID $address_id is: $address->address with $extra_field: $address->extra" );
	} else {
		error_log( "The address with post ID $address_id is: $address->address" );
	}

	$tags = $address->tags;

	if ( $tags ) {
		error_log( "The address $address->address with ID $address_id has the following tags on the wallets_address_tags custom taxonomy: " . json_encode( $tags ) );
	} else {
		$tags = [];
	}

	// Adding a new custom taxonomy tag to the address's existing tags.
	$tags[] = 'foo';

	// The tags will be saved immediately here:
	$address->tags = $tags;

	// No need to call $address->save(); here. The tags are always saved on assignment, and retrieved on access.

### Detecting deposits

You can hook into actions that get you notified whenever a deposit is first encountered by a wallet adapter.

You can get notified when a deposit is first encountered (`pending` state), or when the deposit first becomes confirmed (`done` state).

To see a PHP code snippet for detecting a deposit, edit a deposit address. You will find example code in a metabox.

For example, a hook may look something like this:

	add_action(
	  'wallets_incoming_deposit_done',
	  function( \DSWallets\Transaction $tx ) {
	    if ( $tx->currency && 23616 == $tx->currency->post_id ) {
	      if ( $tx->address && 19803 == $tx->address->post_id ) {
	          error_log( "Detected confirmed incoming deposit: $tx" );
	      }
	    }
	  }
	);

> **NOTICE:** This will only capture actual incoming deposits. If you manually create deposit entries into the plugin's ledger, the action will not be triggered.

## Wallet APIs {#apis}

### WP-REST-API {#wr-rest-api}

[Bitcoin and Altcoin Wallets WordPress plugin][wallets] `6.0.0` and later features a *[WP-REST API][glossary-wp-rest-api]*.

This RESTful API communication with the plugin over the network. The frontend UIs use the WP-REST API to load the wallet data that is displayed to the end user.

> **Note**: Plugin versions before `6.0.0` used the now deprecated [JSON-API](#json-api). The plugin's WP-REST API is better because it build's on the [WordPress REST API][wp-rest-api].

To **authenticate** as the currently logged in user to the *[WP-REST API][glossary-wp-rest-api]*, you must pass a nonce.

Make your AJAX calls to the URI: `dsWallets.rest.url` + endpoint URI, and pass the nonce in an `X-WP-Nonce` request header. Make sure you're always using the correct method, because HTTP verbs matter in REST!

For example, here's how you could call the `/users/USER_ID/currencies` endpoint using [jQuery Ajax][jquery-ajax]:

	jQuery.ajax( {
		url: `${dsWallets.rest.url}dswallets/v1/users/${dsWallets.user.id}/currencies`,
		cache: false,
		method: 'GET',
		headers: {
			'X-WP-Nonce': dsWallets.rest.nonce,
		},
		success: function( response ) {
			console.log( response );
			// TODO do your thing here with the response
		},
	} );


#### /currencies

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/currencies` |
| *Method* | `GET` |
| *URI Parameters* | none |
| *Optional GET Parameters* | `exclude_tags`, a comma-separated list of tags, where currencies with these tags will not be returned |
| *Requires login* | No |
| *Requires capabilities* | No |

Retrieves details about all currencies on the system.

#### /currencies/CURRENCY_ID

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/currencies/CURRENCY_ID` |
| *Method* | `GET` |
| *URI Parameters* | `CURRENCY_ID`, an integer |
| *Optional GET Parameters* | None |
| *Requires login* | No |
| *Requires capabilities* | No |

Retrieves details about the specified on the system.

#### /users/USER_ID/currencies

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/currencies` |
| *Method* | `GET` |
| *URI Parameters* | `USER_ID`, an integer |
| *Optional GET Parameters* | `exclude_tags`, a comma-separated list of tags, where currencies with these tags will not be returned |
| *Requires login* | No |
| *Requires capabilities* | `has_wallets` is required. If data on a user other than the currently logged in user is requested, the endpoit requires the current user to have `manage_wallets`. |

Retrieves details about all currencies on the system, as well as the user's Balance and Available Balance for each currency.

#### /users/USER_ID/currencies/CURRENCY_ID

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/currencies/CURRENCY_ID` |
| *Method* | `GET` |
| *URI Parameters* | `USER_ID` and `CURRENCY_ID`, both integers |
| *Optional GET Parameters* | None |
| *Requires login* | Yes |
| *Requires capabilities* | `has_wallets` is required. If data on a user other than the currently logged in user is requested, the endpoit requires the current user to have `manage_wallets`. |

Retrieves details about the specified currency on the system, as well as the user's Balance and Available Balance for this currency.

#### /users/USER_ID/transactions

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/transactions` |
| *Method* | `GET` |
| *URI Parameters* | `USER_ID`, an integer |
| *Optional GET Parameters* | `page`, `rows`, `categories`, `tags` |
| *Requires login* | Yes |
| *Requires capabilities* | `list_wallet_transactions`. If data on a user other than the currently logged in user is requested, also requires `manage_wallets`. |

Retrieves details about a user's transactions on the system.

To retrieve paginated data, you can use the GET params: `page` (default `1`, i.e. first page) and `rows` (default `10`, i.e. ten transactions per page).

To retrieve transactions of only one category, set the GET param `categories`. Values can be: `deposit`, `withdrawal`, `move`, and `all` (default). For example adding `&categories=deposit` will retrieve only deposits.

To retrieve transactions with at least one of the desired transaction tags (`wallets_tx_tags` custom taxonomy), specify the `tags` GET parameter. You can set `tags` to a list of comma-separated tag slugs. For example adding `&tags=foo,bar,baz` will retrieve only transactions with at least one of the tags "foo", "bar", or "baz".


#### /users/USER_ID/transactions/category/CATEGORY

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/transactions/category/CATEGORY` |
| *Method* | `GET` |
| *URI Parameters* | `USER_ID`, an integer |
| *Optional GET Parameters* | `page`, `rows`, `tags` |
| *Requires login* | Yes |
| *Requires capabilities* | `list_wallet_transactions`. If data on a user other than the currently logged in user is requested, also requires `manage_wallets`. |

Retrieves details about a user's transactions on the system. The transactions are all of the specified category, where `CATEGORY` can be one of: `deposit`, `withdrawal`, `move`.

To retrieve paginated data, you can use the GET params: `page` (default `1`, i.e. first page) and `rows` (default `10`, i.e. ten transactions per page).

To retrieve transactions with at least one of the desired transaction tags (`wallets_tx_tags` custom taxonomy), specify the `tags` GET parameter. You can set `tags` to a list of comma-separated tag slugs. For example adding `&tags=foo,bar,baz` will retrieve only transactions with at least one of the tags "foo", "bar", or "baz".

#### /transactions/validate/NONCE

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/transactions/validate/NONCE` |
| *Method* | `GET` |
| *URI Parameters* | `NONCE`, a string |
| *Optional GET Parameters* | None |
| *Requires login* | No |
| *Requires capabilities* | No |

The URI to this endpoint is the confirmation link that users receive in their email, when a transaction that they initiated needs to be confirmed.

> **NOTE**: The endpoint modifies transaction state, but has the `GET` verb.
> This isn't very restful, but this way users can click on the link from theil email client.

#### /users/USER_ID/currencies/CURRENCY_ID/transactions

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/currencies/CURRENCY_ID/transactions` |
| *Method* | `GET` |
| *URI Parameters* | `USER_ID` and `CURRENCY_ID`, both integers |
| *Optional GET Parameters* | `page`, `rows`, `categories`, `tags` |
| *Requires login* | Yes |
| *Requires capabilities* | `list_wallet_transactions`. If data on a user other than the currently logged in user is requested, also requires `manage_wallets`. |

Retrieves details about a user's transactions of a particluar currency on the system.

To retrieve paginated data, you can use the GET params: `page` (default `1`, i.e. first page) and `rows` (default `10`, i.e. ten transactions per page).

To retrieve transactions of only one category, set the GET param `categories`. Values can be: `deposit`, `withdrawal`, `move`, and `all` (default). For example adding `&categories=deposit` will retrieve only deposits.

To retrieve transactions with at least one of the desired transaction tags (`wallets_tx_tags` custom taxonomy), specify the `tags` GET parameter. You can set `tags` to a list of comma-separated tag slugs. For example adding `&tags=foo,bar,baz` will retrieve only transactions with at least one of the tags "foo", "bar", or "baz".

#### /users/USER_ID/currencies/CURRENCY_ID/transactions/category/CATEGORY

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/currencies/CURRENCY_ID/transactions/category/CATEGORY` |
| *Method* | `GET` |
| *URI Parameters* | `USER_ID` and `CURRENCY_ID`, both integers. `CATEGORY`, one of: `deposit`, `withdrawal`, `move` |
| *Optional GET Parameters* | `page`, `rows`, `tags` |
| *Requires login* | Yes |
| *Requires capabilities* | `list_wallet_transactions`. If data on a user other than the currently logged in user is requested, also requires `manage_wallets`. |

Retrieves details about a user's transactions of a particluar currency and category on the system.

To retrieve paginated data, you can use the GET params: `page` (default `1`, i.e. first page) and `rows` (default `10`, i.e. ten transactions per page).

To retrieve transactions with at least one of the desired transaction tags (`wallets_tx_tags` custom taxonomy), specify the `tags` GET parameter. You can set `tags` to a list of comma-separated tag slugs. For example adding `&tags=foo,bar,baz` will retrieve only transactions with at least one of the tags "foo", "bar", or "baz".

#### /users/USER_ID/currencies/CURRENCY_ID/transactions/category/move


|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/currencies/CURRENCY_ID/transactions/category/move` |
| *Method* | `POST` |
| *URI Parameters* | `USER_ID` and `CURRENCY_ID`, both integers. |
| *Optional GET Parameters* | No |
| *POST Parameters* | `amount` numeric, `recipient` string, `comment` string |
| *Requires login* | Yes |
| *Requires capabilities* | `send_funds_to_user`. If data on a user other than the currently logged in user is requested, also requires `manage_wallets`. |

Creates a new internal transfer (`category=move`). This is an off-chain transaction on the MySQL DB. The transaction consists of two rows, a debit row (amount<0) and a credit row (amount>0). The transaction is created in a pending state, and will be picked up by the cron job that executes internal transfers.

The transfer is from the `USER_ID` user to the user specified by the `recipient` string, which can match a user's name, login, email, etc. `comment` is a free-form text that is attached to the transaction.


#### /users/USER_ID/currencies/CURRENCY_ID/transactions/category/withdrawal

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/currencies/CURRENCY_ID/transactions/category/withdrawal` |
| *Method* | `POST` |
| *URI Parameters* | `USER_ID` and `CURRENCY_ID`, both integers. |
| *Optional GET Parameters* | No |
| *POST Parameters* | `amount` numeric, `address` string, `addressExtra` string, `comment` string |
| *Requires login* | Yes |
| *Requires capabilities* | `send_funds_to_user`. If data on a user other than the currently logged in user is requested, also requires `manage_wallets`. |

Creates a new withdrawal (`category=withdrawal`). This is a request to perform an on-chain transaction. The transaction is created in a pending state, and will be picked up by the cron job that executes internal transfers.

The transfer is from the `USER_ID` user to the address specified by the `address` string, and the optional `addressExtra` string, if it is specified. `comment` is a free-form text that is attached to the transaction.

#### /users/USER_ID/addresses

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/addresses` |
| *Method* | `GET` |
| *URI Parameters* | `USER_ID`, an integer |
| *Optional GET Parameters* | `latest` |
| *Requires login* | Yes |
| *Requires capabilities* | If data on a user other than the currently logged in user is requested, requires `manage_wallets`. |

Retrieves details about a user's deposit/withdrawal adresses on the system.

If `latest` is set to a truthy value, only the latest deposit address will be retrieved for each currency.


#### /users/USER_ID/addresses/ADDRESS_ID

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/addresses/ADDRESS_ID` |
| *Method* | `PUT` |
| *URI Parameters* | `USER_ID`, an integer and `ADDRESS_ID`, an integer |
| *Optional GET Parameters* | No |
| *PUT Parameters* | `label` a string |
| *Requires login* | Yes |
| *Requires capabilities* | If data on a user other than the currently logged in user is requested, requires `manage_wallets`. |

Allows a user to set a `label` text string to an existing address. The address is specified by its post ID.

#### /users/USER_ID/addresses/ADDRESS_ID

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/addresses/ADDRESS_ID` |
| *Method* | `GET` |
| *URI Parameters* | `USER_ID`, an integer and `ADDRESS_ID`, an integer |
| *Optional GET Parameters* | No |
| *Requires login* | Yes |
| *Requires capabilities* | If data on a user other than the currently logged in user is requested, requires `manage_wallets`. |

Retrieves an existing address. The address is specified by its post ID and must correspond to the specified user.


#### /users/USER_ID/currencies/CURRENCY_ID/addresses

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/currencies/CURRENCY_ID/addresses` |
| *Method* | `GET` |
| *URI Parameters* | `USER_ID`, an integer and `CURRENCY_ID`, an integer |
| *Optional GET Parameters* | No |
| *Requires login* | Yes |
| *Requires capabilities* | If data on a user other than the currently logged in user is requested, requires `manage_wallets`. |

Retrieves details about a user's deposit/withdrawal adresses of a particular currency on the system.


#### /users/USER_ID/currencies/CURRENCY_ID/addresses

|     |     |
| --- | --- |
| *Endpoint* | `/dswallets/v1/users/USER_ID/currencies/CURRENCY_ID/addresses` |
| *Method* | `PUT` |
| *URI Parameters* | `USER_ID`, an integer and `CURRENCY_ID`, an integer |
| *Optional GET Parameters* | No |
| *Requires login* | Yes |
| *Requires capabilities* | If data on a user other than the currently logged in user is requested, requires `manage_wallets`. |

Creates a new deposit address for a user and currency. This will typically cause a request to the wallet for a new address. The new address is generated by the wallet, sent back to the plugin via the wallet adapter, and saved to the DB along with the new deposit address. Subsequent transactions to this address will be credited to the user's balance when they are detected.


### Frontend JavaScript API

#### the `dsWallets` object

The JavaScript code on the front exposes a bunch of data. Some of this data is necessary to call the REST API with proper authentication. The data is in the `window.dsWallets` object.

| JavaScript object | type | Description |
| --- | --- | --- |
| `dsWallets.rest.url` | string | The base URI for the WP-REST API of this site. Will typically end in `/wp-json/`. |
| `dsWallets.rest.nonce` | string | To authenticate WP-REST requests, pass this nonce using n `X-WP-Nonce` request header. |
| `dsWallets.rest.polling` | numeric | Number of seconds to wait between auto-refreshing the UI data. This is the value of _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Frontend UI Setting_ &rarr; _Polling interval_ (option `wallets_polling_interval`). |
| `dsWallets.user.id` | numeric | The user ID of the current user. Can be used in WP-REST requests that ask for a `USER_ID` URI parameter. |
| `` | | |
| `dsWallets.vs_currencies` | array | Whenever currency amounts are shown on the frontend, the values are also displayed below, expressed in another, more stable/familiar currency, called the *[VS Currency][glossary-vs-currencies]*. These values are the equivalent amounts, and are calculated based on the latest known exchange rate data. Equivalent amounts are shown with the HTML class `vs-amount`. The `dsWallets.vs_currencies` field is an array of lowercase *[VS Currency][glossary-vs-currencies]* ticker symbols. For example, it could be: `['btc','usd','eur']`. |
| `dsWallets.vsCurrencyRotate()` | method | Call this method without arguments, and all the wallet UIs will display any equivalent amounts using the next available *[VS Currency][glossary-vs-currencies]*. When the user has clicked through all the available currencies, the method rotates back to the first one. The current available currency is stored in the browser's [local storage][web-storage] value `dswallets-vs-currency`. All the user's open tabs on your site are synced to display the same *[VS Currency][glossary-vs-currencies]*. |

#### the `wallets_ready` event

If you need to access the `dsWallets` object, it is safer if you call it after the `wallets_ready` event. This is a bubbling DOM event that signifies that the plugin's common mechanism for displaying *[VS Currencies][glossary-vs-currencies]* is ready, and that the data required to call the WP-REST API are available.

> **NOTE**: Frontend UIs run their own execution threads, but communicate with the `dsWallets` object, and also need to wait for `wallets_ready` for some operations.

Here's how to hook to the event and do stuff with the object:

	$('html').on( 'wallets_ready', function( event, dsWalletsRest ) {
		console.log( dsWalletsRest); // yay a callback object!

		dsWalletsRest.vsCurrencyRotate(); // rotate all UIs to next VS Currency

		// TODO do your thing here
	} );

### Legacy PHP-API {#php-api}

Versions of the [Bitcoin and Altcoin Wallets WordPress plugin][wallets] before `6.0.0` featured a so-called *[PHP-API][glossary-php-api]*. This API allowed manipulating transactions via PHP code.

With version `6.0.0`, all operations can be done by directly manipulating the [custom post type objects](#post-types). The *Legacy PHP API* is introduced for compatibility. It is a rebuild of the *[PHP-API][glossary-php-api]* to work with the new objects, rather than the old custom MySQL tables. It aims at maximum compatibility with the old API. However, there are some small differences with the original, because of the shift from *[coin adapters][glossary-coin-adapter]* to *[wallet adapters][glossary-wallet-adapter]*, and because of other changes. Smoke test your legacy code!

> The *Legacy PHP API* is NOT deprecated: there are no plans to remove it in the future. It is a bridge for existing PHP code but can also be used for new applications. A few functions have been marked as deprecated, because it's now preferable to use simpler helper functions.

| Hook Type | Slug |  Description |
| ---- | ---- | ----------- |
| Filter | [`wallets_api_balance`][wallets-api-balance] | Accesses the balance of a user. |
| Filter | [`wallets_api_available_balance`][wallets-api-available-balance] | Accesses the available balance of a user. |
| Filter | [`wallets_api_transactions`][wallets-api-transactions] | Accesses user transactions. |
| Action | [`wallets_api_withdraw`][wallets-api-withdraw] | Request to perform a withdrawal transaction. |
| Action | [`wallets_api_move`][wallets-api-move] | Request to perform an internal transfer transaction (aka "move") between two users. |
| Filter | [`wallets_api_deposit-address`][wallets-api-deposit-address] | Accesses a deposit address of a user. |
| Action | [`wallets_api_cancel`][wallets-api-cancel] | Allows a transaction to be cancelled. Requires `manage_wallets` capability. |
| Action | [`wallets_api_retry`][wallets-api-retry] | Allows a transaction to be retried. Requires `manage_wallets` capability. |
| Filter | <s>`wallets_api_adapters`</s> | This filter has been removed, because the coin adapters have been replaced with wallet adapters, which is a different thing altogether. If your code uses this filter, you will see a `_doing_it_wrong()` message in your logs. |
Click on the links above to review the *[Legacy PHP-API][glossary-legacy-php-api]* endpoints in PHPDocumentor.


### Legacy JSON-API {#json-api}

#### What it is

Versions of the [Bitcoin and Altcoin Wallets WordPress plugin][wallets] before `6.0.0` featured a so-called [JSON-API][json-api]. This API allowed communication with the plugin over the network. The frontend UIs use this API to load data that is displayed to the user.

> **NOTE**: With `6.0.0`, the JSON-API has being superceded by the *[WP-REST API](#wp-rest-api)*.

The *[Legacy JSON-API][glossary-legacy-json-api]* is compatible with the old *[JSON-API v3][glossary-json-api-v3]*. Wherever there are minor differences in implementation with *[JSON-API v3][glossary-json-api-v3]*, these are clearly marked with a "&#x26A0;" symbol below:

#### Who should use it

Users who have already built apps that communicate with the JSON-API v3 can now use the *[Legacy JSON-API][glossary-legacy-json-api]*. Do not develop new applications using this API, as it is deprecated and may be removed in a future version. If you are developing an application from scratch, use the WP-REST API to communicate to the plugin via the network.

> &#x26A0; The legacy API is disabled by default. If you need it, enable it in the admin settings.

#### Endpoint summary

All endpoints use the `GET` HTTP verb.

| auth | endpoint | description |
| ---- | -------- | ----------- |
| public | [`?__wallets_action=notify`](#notify) | Public API that notifies the plugin to check a wallet for a new transaction by TXID. |
| special cron nonce | [`?__wallets_action=do_cron`](#do_cron) | &#x26A0; This used to trigger the cron API externally using site-wide secret nonce. This endpoint has been removed. |
| bearer token or cookie | [`?__wallets_action=do_reset_apikey`](#do_reset_apikey) | Forces a reset of the user's API key. The new key is returned and the previous key is invalidated. |
| bearer token or cookie | [`?__wallets_action=get_nonces`](#get_nonces) | This returns the currently logged in user's API bearer token. &#x26A0; No longer returns valid nonces, as they are removed. |
| bearer token or cookie | [`?__wallets_action=get_coins_info`](#get_coins_info) | Returns info about all cryptos, user balances, deposit addresses. |
| bearer token or cookie | [`?__wallets_action=get_transactions`](#get_transactions) | Retrieve past transaction info (deposits, withdrawals and transfers to other users) of the current user. |
| bearer token or cookie | [`?__wallets_action=do_new_address`](#do_new_address) | Forces the plugin to generate a new deposit address for this user and specified currency. &#x26A0; |
| bearer token or cookie | [`?__wallets_action=do_move`](#do_move) | Requests a funds transfer to another user. This is an internal, off-chain transaction on your DB's ledger. |
| bearer token or cookie | [`?__wallets_action=do_withdraw`](#do_withdraw) | Requests a user funds withdrawal to an external address on the blockchain. |

#### Authentication

Authentication with the JSON-API is complex, and this is one of the reasons that it is being superseded by the WP-REST API.

To use the JSON-API, users are authenticated using a bearer token. Users can reset their token via [`do_reset_apikey`](#do_reset_apikey) and retrieve the token via [`get_nonces`](#get_nonces).

> &#x26A0; The nonces themselves have been removed and replaced with dummy values for compatibility. Only use the [`get_nonces`](#get_nonces) endpoint to retrieve the API key.

Once the user has an `<APIKEY>`, they can authenticate by appending a GET parameter to the request:

	?__wallets_apikey=<APIKEY>

For example, here's how to call [`get_coins_info`](#get_coins_info) via curl:

	curl 'http://www.example.com/?__wallets_action=get_coins_info&__wallets_apiversion=3&__wallets_api_key=06f9a901bc95cfbacb5e41b30d51b764f0ea5d8e5d9ced0ed5664078ddf4e65d'

or via an HTTP header

	Authorization: Bearer <APIKEY>

For example, here's how to call [`get_coins_info`](#get_coins_info) via curl:

	curl -H 'Authorization: Bearer 06f9a901bc95cfbacb5e41b30d51b764f0ea5d8e5d9ced0ed5664078ddf4e65d' 'http://www.example.com/?__wallets_action=get_coins_info&__wallets_apiversion=3'

 **When accessing the JSON-API endpoints from a browser, if the user is logged in, the API key (`__wallets_apikey` argument or Auth header)) can be omitted.**

> &#x26A0; Previously, only users with the `access_wallets_api` capability could use the JSON API with key-based authentication. This capability is now removed. All users can access the Legacy JSON-API if it is enabled.

##### Getting your key via the admin profile

A user's JSON-API key is available at the admin user profile screen.

A user can see their own API key at _Users_ &rarr; _Profile_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _API key for legacy JSON-API v3 (deprecated!)_.

An admin with `manage_wallets` can see the API keys of all users by visiting their profile page.

##### Getting your key via the frontend UIs

> &#x26A0; Previously the API key could be displayed to the currently logged in user via the `[wallets_api_key]` shortcode. This has now been removed.

##### Getting your key via your browser

While logged in, you can visit the following API endpoint via your browser:

	https://www.example.com/?__wallets_action=get_nonces&__wallets_apiversion=3

Replace `www.example.com` with the domain for your site.

The response may look something like:

	{
		"nonces": {
			"do_withdraw": "0000000000",
			"do_move": "0000000000",
			"do_new_address": "0000000000",
			"api_key": "06f9a901bc95cfbacb5e41b30d51b764f0ea5d8e5d9ced0ed5664078ddf4e65d"
		},
		"result": "success"
	}

The api_key is the hex string. The zeroes are there to provide compatibility with the action nonces which are now removed.

##### Getting your key via curl

To obtain your API key **without logging in via a browser**, you can use curl to simulate a login, then use the [`get_nonces`](#get_nonces) endpoint to retrieve your key:

	$USER=your wordpress user name
	$PASSWORD=your wordpress password

	curl -b cookies.txt -c cookies.txt -F log=$USER -F pwd=$PASSWORD -F testcookie=1 \
      -F wp-submit="Log In" -F redirect_to=https://www.example.com/wp-admin \
      -F submit=login -F rememberme=forever https://www.example.com/wp-login.php

The above commands perform a login to your site and store your session cookie in `cookies.txt`. Any subsequent JSON endpoints that you contact using this cookie file will be executed as if you were logged in via your browser:

	curl \
		-b cookie.txt \
		-c cookie.txt \
		'http://www.example.com/?__wallets_apiversion=3&__wallets_action=get_nonces'

The response will contain the `"api_key"` field, a hex string. You can use this key in any subsequent calls to perform authenticated calls to the JSON API, without the need for the cookie file. Use the extra GET parameters. For example, you can do this to get your coin balances and deposit addresses using your key:

	curl "https://www.example.com/\
	?__wallets_apiversion=3\
	&__wallets_action=get_coins_info\
	&__wallets_api_key=5d3919d169f2049b6c2fbd5e2d886abcc1ebd5a12b23bd6dd4eebcbf084f55b1"

It is best not to pass the API key as a GET parameter, since the URLs of requests are often logged.

Instead, you can pass the API key as an HTTP Auth header:

	curl \
	-H "Authorization: Bearer 5d3919d169f2049b6c2fbd5e2d886abcc1ebd5a12b23bd6dd4eebcbf084f55b1" \
	'https://www.example.com/
	?__wallets_apiversion=3
	&__wallets_action=get_coins_info

### API endpoints

Below is the API documentation.

First, the API endpoints are presented. Parameters, example responses, and required capablities are listed.

Some API responses can be modified using WordPress filters. These filters are also documented here.

#### notify {#notify}

Public API that notifies the plugin to check a wallet for a new transaction by TXID.

For the bitcoin daemon, the `-walletnotify` parameter must be made to call `/wallets/notify/BTC/wallet/TXID` where `TXID` is a transaction ID, and `-blocknotify` must be made to call `/wallets/api3/notify/BTC/block/BLOCKHASH` where `BLOCKHASH` is the hash of the latest block announced on the blockchain. Allows the plugin to receive deposits.

**Pretty URI path:** `/wallets/api3/notify/SYMBOL/TYPE/MESSAGE`
**Ugly URI path:** `?__wallets_action=notify&__wallets_symbol=SYMBOL&__wallets_notify_type=TYPE&__wallets_notify_message=MESSAGE`

**Parameters:**

- **[SYMBOL][glossary-symbol]**: The symbol of the coin that this notification is about, e.g. BTC, LTC, etc.
- **TYPE**: The notification type, can be one of `wallet`, `block`, `alert`.
- **MESSAGE**: A string that is the payload of the notification, usually a TXID.

**Example response:**

	{
	   "result":"success"
	}

#### do_cron {#do_cron}

Public api that used to trigger the cron jobs. Has been removed.

> &#x26A0; This endpoint no longer triggers the cron jobs and remains here for compatibility. To ensure that cron jobs are triggered, you can setup a Linux cron that hits your site's `wp-cron.php`. However, the jobs will now run **only** if the cron interval has elapsed since the last cron run. Set the cron interval at _Settings_ &rarr; _Bitcoin & Altcoin Wallets_ &rarr; _⌛ Cron tasks_ &rarr; _⏲  Cron interval_.

**URI path:** `?__wallets_action=do_cron&__wallets_apiversion=3&__wallets_cron_nonce=NONCE`

**Paramters:**

- **NONCE**: A random hex string intended to prevent users from DDoSing this endpoint. You can find this nonce in the admin cron settings page.

**Required capabilities:** none

**Example response:**

	{
	   "result":"success"
	}

#### do_reset_apikey {#do_reset_apikey}

Resets the user's API key to a new random value, and invalidates the previous key. The new key is returned in the response.

**URI path:** `/?__wallets_action=do_reset_apikey&__wallets_apiversion=3&__wallets_apikey=APIKEY`

**Parameters:**

- **APIKEY**: The auth bearer token. Can also be passed with an HTTP header. If this key is omitted, the user must be logged in via cookies.

**Required capabilities:** `has_wallets`

**Example response:**

	{
		"new_key":"c067801dec64449cb76a9e53dce06d502e78087bd2b3d91538404af95c8324b5",
		"result":"success"
	}

#### get_nonces {#get_nonces}

Retrieves the API key (bearer token). This endpoint requires that you are logged in. It cannot be accessed using an API key.

Once you get the API key, append it to other endpoints in order to authenticate.

> &#x26A0; This endpoint used to retrieve WordPress nonces for performing the `do_move`, `do_withdraw` and `do_new_address` actions. These have now been removed. The API returns dummy values for compatibility.

**Pretty URI path:** `/wallets/api3/get_nonces`
**Ugly URI path:** `?__wallets_action=get_nonces&__wallets_apiversion=3&__wallets_apikey=APIKEY`

**Parameters:**

- **APIKEY**: The auth bearer token. Can also be passed with an HTTP header. If this key is omitted, the user must be logged in via cookies.

**Required capabilities:** `has_wallets`

**Example response:**

	{
		"nonces":{
			"do_withdraw":"893a9f9dad",
			"do_move":"6d5ebff18a",
			"do_new_address": "27cda821b5",
			"api_key":"5d3919d169f2049b6c2fbd5e2d886abcc1ebd5a12b23bd6dd4eebcbf084f55b1"
		},
		"result":"success"
	}

#### get_coins_info {#get_coins_info}

Returns info about all enabled cryptocurrencies, such as fees, icon, deposit address, etc. Also returns user balances for those currencies.

**Pretty URI path:** `/wallets/api3/get_coins_info`
**Ugly URI path:** `?__wallets_action=get_coins_info&__wallets_apiversion=3&__wallets_apikey=APIKEY`

**Parameters:**

- **APIKEY**: The auth bearer token. Can also be passed with an HTTP header. If this key is omitted, the user must be logged in via cookies.

**Required capabilities:** `has_wallets`

**Example response:**

	{
		"coins":{
			"LTC":{
				"name":"Litecoin",
				"symbol":"LTC",
				"is_fiat":true,
				"is_crypto":false,
				"icon_url":"http:\/\/example.com\/wp-content\/plugins\/wallets-litecoin\/assets\/sprites\/litecoin-logo.png",
				"sprintf":"\u0141%01.8f",
				"extra_desc":"Destination address label (optional)",
				"explorer_uri_address": "https://live.blockcypher.com/ltc/address/%s/",
				"explorer_uri_tx": "https://live.blockcypher.com/ltc/tx/%s/",
				"balance":0.00659988,
				"move_fee":0,
				"move_fee_proportional":0,
				"withdraw_fee":0.0002,
				"withdraw_fee_proportional":0,
				"deposit_address":"LPsj8nDiuBjbvRFR8CDoYhSbd4nDekbBQV",
			},
			"BTC":{
				"name":"Bitcoin",
				"symbol":"BTC",
				"is_fiat":true,
				"is_crypto":false,
				"icon_url":"http:\/\/example.com\/wp-content\/plugins\/wallets\/includes\/..\/assets\/sprites\/bitcoin-logo.png",
				"sprintf":"\u0e3f%01.8f",
				"extra_desc":"Destination address label (optional)",
				"explorer_uri_address": "https://blockchain.info/address/%s",
				"explorer_uri_tx": "https://blockchain.info/tx/%s",
				"balance":0.00091565,
				"move_fee":0,
				"move_fee_proportional":0,
				"withdraw_fee":0.0002,
				"withdraw_fee_proportional":0,
				"deposit_address":"mrXEs8Kbj7mcMU1ZAq84Kdm85Vdd2Xg2b2",
			}
		},
		"result":"success"
	}

#### get_transactions {#get_transactions}

Retrieve past transaction info (deposits, withdrawals and transfers to other users) of the currently logged in user.

Used by the `[wallets_transactions]` shortcode UI to display a paginated table of transactions.

**Pretty URI path:** `/wallets/api3/get_transactions/SYMBOL/COUNT/FROM`
**Ugly URI path:** `?__wallets_action=get_transactions&__wallets_apiversion=3&__wallets_symbol=SYMBOL&__wallets_tx_from=FROM&__wallets_tx_count=COUNT&__wallets_apikey=APIKEY`

**Parameters:**

- **[SYMBOL][glossary-symbol]**: The coin’s symbol, e.g. BTC, LTC, etc. Only transactions regarding this coin will be retrieved.
- **FROM**: Start retrieving transactions from this offset (useful for pagination).
- **COUNT**: Retrieve this many transactions.
- **APIKEY**: The auth bearer token. Can also be passed with an HTTP header. If this key is omitted, the user must be logged in via cookies.

**Required capabilities:** `has_wallets`, `list_wallet_transactions`

**Example response:**

	{
	   "transactions":[
	      {
	         "category":"deposit",
	         "tags": "",
	         "account":"1",
	         "other_account":null,
	         "address":"1rXEs8Kbj7mcMU1ZAq84Kdm85Vdd2Xg2b2",
	         "txid":"c9c30612ea6ec2509c4505463b6f965ac25e8e2cff6451e480aa3b307377df97",
	         "symbol":"BTC",
	         "amount":"0.0000100000",
	         "fee":"0.0000000000",
	         "comment":null,
	         "created_time":"2016-12-17 15:22:14",
	         "updated_time":"2016-12-30 11:33:14",
	         "confirmations":"3249",
	         "status":"done",
	         "retries":0,
	         "admin_confirm":0,
	         "user_confirm":0,
	         "extra":null,
	         "other_account_name":null
	      },
	      {
	         "category":"move",
	         "tags": "send move",
	         "account":"1",
	         "other_account":"2",
	         "address":"",
	         "txid":"move-58629b173cb8f0.44669543-send",
	         "symbol":"BTC",
	         "amount":"-0.0000133400",
	         "fee":"0.0000010000",
	         "comment":"comment test",
	         "created_time":"2016-12-27 16:47:19",
	         "updated_time":"2016-12-27 16:47:19",
	         "confirmations":"0",
	         "status":"done",
	         "retries":0,
	         "admin_confirm":0,
	         "user_confirm":1,
	         "extra":null,
	         "other_account_name":"luser"
	      },
	      {
	         "category":"withdraw",
	         "tags": "",
	         "account":"1",
	         "other_account":null,
	         "address":"1i1B4pkLQ2VmLZwhuEGto3NAJdeh4xJr1W",
	         "txid":"fed08f9a90c526f2bb791059a8718d422b8fdcb55f719bd36b6e3d9717e815e0",
	         "symbol":"BTC",
	         "amount":"-0.0000600000",
	         "fee":"0.0000500000",
	         "comment":"withdrawing bitcoins",
	         "created_time":"2016-12-30 11:44:37",
	         "updated_time":"2016-12-30 12:46:41",
	         "confirmations":"12",
	         "status":"done",
	         "retries":2,
	         "admin_confirm":1,
	         "user_confirm":1,
	         "extra":null,
	         "other_account_name":null
	      }
	   ],
	   "result":"success"
	}

*All times are GMT.*

#### do_new_address {#do_new_address}

Assigns a new deposit address for the specified coin to the user.

Any previous addresses for the same user and coin are retained. The user can continue to receive deposits via these other addresses.

> &#x26A0; Previously there was no limit to how many addresses a user could generate. This has now been limited.

**URI path:** `/?__wallets_action=do_new_address&__wallets_apiversion=3&__wallets_symbol=SYMBOL&__wallets_apikey=APIKEY`

**Parameters:**

- **[SYMBOL][glossary-symbol]**: The symbol of the coins to move, e.g. BTC, LTC, etc.
- **APIKEY**: The auth bearer token. Can also be passed with an HTTP header. If this key is omitted, the user must be logged in via cookies.

**Required capabilities:** `has_wallets`

**Example response:**

	{
	   "result":"success",
	   "new_address": "mpwokEmcuUmvp5TpM83TuPLQyfXHpvgGmF"
	}

#### do_move {#do_move}

Transfers funds to another user.
The transfer fee that the administrator has set in the coin adapter is charged to the sender.

**URI path:** `/?__wallets_action=do_move&__wallets_apiversion=3&__wallets_symbol=SYMBOL&__wallets_move_toaccount=TOACCOUNT&__wallets_move_amount=AMOUNT&__wallets_move_comment=COMMENT&__wallets_apikey=APIKEY`

**Parameters:**

- **[SYMBOL][glossary-symbol]**: The symbol of the coins to move, e.g. BTC, LTC, etc.
- **TOACCOUNT**: User ID of the recipient. The IDs are accessible via `get_user_info`.
- **AMOUNT**: The amount of coins to transfer, excluding any transaction fees.
  This is the amount that the recipient is to receive.
- **COMMENT**: A descriptive string that the sender attaches to the transaction.
- **APIKEY**: The auth bearer token. Can also be passed with an HTTP header. If this key is omitted, the user must be logged in via cookies.

**Required capabilities:** `has_wallets`, `send_funds_to_user`

**Example response:**

	{
	   "result":"success"
	}

#### do_withdraw {#do_withdraw}

Transfers funds to another address on the coin’s network.

The withdrawal fee that the administrator has set in the Currency settings is charged to the sender.

**URI path:** `/?__wallets_action=do_withdraw&__wallets_apiversion=3&__wallets_symbol=SYMBOL&__wallets_withdraw_address=ADDRESS&__wallets_withdraw_extra=EXTRA&__wallets_withdraw_amount=AMOUNT&__wallets_withdraw_comment=COMMENT&__wallets_withdraw_comment_to=COMMENT_TO&__wallets_apikey=APIKEY`

**Parameters:**

- **[SYMBOL][glossary-symbol]**: The symbol of the coins to move, e.g. BTC, LTC, etc.
- **[ADDRESS][glossary-address]**: The external address to send coins to.
- **EXTRA**: Optional extra destination tag supported by some coins (e.g. Monero Payment ID, Ripple Destination Tag, etc). Can be omitted.
- **AMOUNT**: The amount of coins to transfer, excluding any transaction fees.
  This is the amount that the recipient address is to receive.
- **COMMENT**: A descriptive string that the sender attaches to the withdrawal. Can be omitted.
- **COMMENT_TO**: A descriptive string that the sender attaches to the address. Can be omitted.
- **APIKEY**: The auth bearer token. Can also be passed with an HTTP header. If this key is omitted, the user must be logged in via cookies.

**Required capabilities:** `has_wallets`, `widthdraw_funds_from_wallet`

**Example response:**

	{
	   "result":"success"
	}

### JSON API filters

The following set of filters modifies coin information sent via the JSON API. Use these to control how some coin information is shown in the frontend.

#### Coin name

- `wallets_coin_name_XYZ` - Name for coin with symbol `XYZ`.

To override the name for Bitcoin:

	public function filter_wallets_coin_name_BTC( $pattern ) {
		return 'Bitcoin Core'; // LOL :p
	}

	add_filter( 'wallets_coin_name_BTC', 'filter_wallets_coin_name_BTC' );

#### Coin icon

- `wallets_coin_icon_url_XYZ` - Image URL for coin with symbol `XYZ`.

To override the URI for the Bitcoin icon:

	public function filter_wallets_coin_icon_url_BTC( $pattern ) {
		return 'https://upload.wikimedia.org/wikipedia/commons/thumb/' .
			'4/46/Bitcoin.svg/2000px-Bitcoin.svg.png';
	}

	add_filter( 'wallets_coin_icon_url_BTC', 'filter_wallets_coin_icon_url_BTC' );

#### Amounts pattern

Cryptocurrency amounts in the frontend are passed through an `sprintf()` function. The JavaScript implementation used is [https://github.com/alexei/sprintf.js](https://github.com/alexei/sprintf.js).

- `wallets_sprintf_pattern_XYZ` - Display pattern for amounts of coin with symbol `XYZ`.

To override the frontend display format for Bitcoin amounts:

	public function filter_wallets_sprintf_pattern_BTC( $pattern ) {
		return 'BTC %01.8f';
	}

	add_filter( 'wallets_sprintf_pattern_BTC', 'filter_wallets_sprintf_pattern_BTC' );

Change the `8` digit to how many decimal digits you want displayed.

#### Blockexplorer Transaction URI pattern

`wallets_explorer_uri_tx_XYZ` - Use this to select a different blockexplorer for viewing transactions on the blockchain. The string `%s` will be replaced with the transaction ID.

To use chain.so for Bitcoin transactions:

	public function filter_wallets_explorer_uri_tx_BTC( $pattern ) {
		return 'https://chain.so/tx/BTC/%s';
	}

	add_filter( 'wallets_explorer_uri_tx_BTC', 'filter_wallets_explorer_uri_tx_BTC' );

#### Blockexplorer Address URI pattern

`wallets_explorer_uri_add_XYZ` - Use this to select a different blockexplorer for viewing addresses on the blockchain. The string `%s` will be replaced with the address.

To use chain.so for Bitcoin addresses:

	public function filter_wallets_explorer_uri_add_BTC( $pattern ) {
		return 'https://chain.so/address/BTC/%s';
	}

	add_filter( 'wallets_explorer_uri_add_BTC', 'filter_wallets_explorer_uri_add_BTC' );

## Developing wallet adapters {#wallet-adapters}

A wallet adapter is the middleware between the plugin and the hot wallets.

It is an abstraction of the various technical details of various types of wallets.

To implement an adapter, you must:
- Derive your class from the abstract `DSWallets\Wallet_Adapter`
- Include your class code when the `wallets_declare_wallet_adapters` action fires.

### Example:

The following Wallet Adapter example is also available at:

https://github.com/dashed-slug/my-wallet-adapter

#### `my-wallet-adapter-plugin.php`

Create a plugin file that will load your adapter.

You must use a valid [plugin header][plugin-header].

Then, on the `wallets_declare_wallet_adapters` action, include or require the other file which will contain the adapter:

	<?php
	/*
	 * Plugin Name: My Wallet Adapter
	 * Description: Connects Bitcoin and Altcoin Wallets 6.0.0 or later with some type of wallet. This is sample code for wallet adapter developers. See the plugin's documentation for details.
	 * Version: 1.0
	 * Plugin URI: http://example.com
	 * Author: author@example.com
	 * Author URI: http://example.com/author
	 * Text Domain: my-wallet-adapter
	 * Domain Path: /languages/
	 * License: GPLv2 or later
	 *
	 * @license GNU General Public License, version 2
	 */

	// don't load directly
	defined( 'ABSPATH' ) || die( '-1' );

	add_action(
		'wallets_declare_wallet_adapters',
		function() {
			require_once __DIR__ . '/my-wallet-adapter.php';
		}
	);

#### `my-wallet-adapter.php`

Implementing the wallet adapter requires you to extend the abstract class `DSWallets\Wallet_Adapter`. Create one such class for each different type of wallet. One wallet adapter may be associated with multiple currencies, if the wallet supports this.

##### Defining the wallet adapter class

Here we create such a class after some guard clauses. We'll name it `My_Wallet_Adapter`. We also import some classes from the base plugin that we'll need later.

	<?php

	use DSWallets\Wallet_Adapter;
	use DSWallets\Address;
	use DSWallets\Currency;
	use DSWallets\Transaction;
	use DSWallets\Wallet;

	defined( 'ABSPATH' ) || die( -1 ); // don't load directly

	if (
		class_exists( '\DSWallets\Wallet_Adapter' ) &&
		! class_exists( 'My_Wallet_Adapter' )
	) {

		class My_Wallet_Adapter extends Wallet_Adapter {
			// TODO
		}
	}


##### Implementing the constructor

You must provide a constructor that takes a single argument, of type `DSWallets\Wallet`. The plugin will call this constructor to instantiate your Wallet Adapter, once the adapter is assigned to a Wallet via the admin interface.

For details about the `Wallet` custom post type object, see:
- _Developer Reference_ &rarr; _[Working with custom post type objects](#post-types)_
- _The post types_ &rarr; _[Wallets][post-types-wallets]_
- _[The PHPDocumentor page for `Wallet`][phpdoc-wallet]_
- _[The PHPDocumentor page for wallet-related helper functions][phpdoc-wallet]_

The constructor should define the wallet adapter's settings in the `settings_schema` field. These are the settings that a wallet adapter needs to connect to a wallet. Things like IP addresses, port numbers, usernames, passwords, and other credentials will go here. What follows is a simple constructor to illustrate how this works:

	public function __construct( Wallet $wallet ) {

		$this->settings_schema = [
				[
					'id'            => 'ip',
					'name'          => __( 'IP address for the wallet', 'my-wallet-adapter' ),
					'type'          => 'string',
					'description'   => __( 'The IP of the machine running your wallet daemon. Set to 127.0.0.1 if you are running the daemon on the same machine as WordPress. If you want to enter an IPv6 address, enclose it in square brackets e.g.: [0:0:0:0:0:0:0:1].', 'my-wallet-adapter' ),
					'default'       => '127.0.0.1',
					// note how you can optionally add a validator callback for your settings.
					// Any PHP callable will do, as long as it takes one argument and returns a boolean.
					'validation_cb' => [ $this, 'validate_tcp_ip_address' ],
				],
				[
					'id'            => 'port',
					'name'          => __( 'TCP port for the wallet', 'my-wallet-adapter' ),
					'type'          => 'number',
					'description'   => __( 'The TCP port where the wallet listens for connections.', 'my-wallet-adapter' ),
					'min'           => 0,
					'max'           => 65535,
					'step'          => 1,
					'default'       => 1234,
				],
				[
					'id'            => 'username',
					'name'          => __( 'Username', 'my-wallet-adapter' ),
					'type'          => 'string',
					'description'   => __( 'A username to use when connecting to the wallet\'s API.', 'my-wallet-adapter' ),
					'default'       => '',
				],
				[
					'id'            => 'password',
					'name'          => __( 'Password', 'my-wallet-adapter' ),
					'type'          => 'secret',
					'description'   => __( 'A password to connect to the wallet's API.', 'my-wallet-adapter' ),
					'default'       => '',
				],
				[
					'id'            => 'some_choice',
					'name'          => __( 'Some choice', 'my-wallet-adapter' ),
					'type'          => 'select',
					'description'   => __(
						'You can also create settings that are dropdown choices',
						'my-wallet-adapter'
					),
					'default'       => 'second',
					'options'       => [
						'first'  => __( 'First choice', 'my-wallet-adapter' ),
						'second' => __( 'Second choice', 'my-wallet-adapter' ),
					],
				],
		]; // end settings_schema value

	} // end constructor

With this code, we have now defined the following wallet adapter settings:

| Setting ID    | Type     | Commentary |
| ------------  | -------- | ----------- |
| `ip`          | `string` | You can define arbitrary string settings, and you can provide a default value and a validator callback. |
| `port`        | `number` | You can define numeric settings. These render as `<input type="number"` and you can optionally provide the `min`, `max`, and `step` HTML attributes. |
| `username`    | `string` | Another plain old string. This time the default value is empty and there is no validator.
| `password`    | `secret` | Any secret credentials such as passwords, private API keys, PINs, etc should be marked as "secret". This turns the input field to `type="password"`. Already existing passwords are never rendered into the HTML form. If the admin enters no password in this field, the old password is retained. Note that this password will be stored on the `wp_postmeta` table of your MySQL DB. |
| `some_choice` | `select` | Here we have a setting that requires the admin to select a value from a dropdown. The second value is selected by default. |

> **TTP**: Do not use hyphens `-` in the setting IDs, because this will make your settings harder to access later. Underscores `_` are ok.

##### Implementing methods that interact with the wallet

To interact with the wallet, you must use the connection settings to connect with the wallet and provide implementations for the following important methods:

- `do_withdrawals( array $withdrawals ): void` &mdash; Takes an array of pending withdrawals of type `DSWallets\Transaction` and executes them. The adapter must set some information on each transaction. You do not need to call `->save()` on the withdrawals: The plugin will ensure that this state is saved for each transaction on the DB. After attempting one or more withdrawals, the following fields must be modified accordingly:
  - `$withdrawal->status` &mdash; One of: `pending`, `done`, `cancelled`, `failed`, depending on whether the transaction succeeded or not.
  - `$withdrawal->txid` &mdash; Assign the blockchain transaction ID. This can be an ID unique to this transaction, or it could be a TXID of a multi-output transaction. This is useful in cases where the wallet is able to process all the withdrawals in one blockchain transaction.
  - `$withdrawal->chain_fee` &mdash; Assign the fees paid *in integer form*. If the blockchain transaction represents multiple withdrawals, divide by the number of withdrawals to get an approximate fee paid for each withdrawal. This number is only displayed to the user, and is not used elsewhere.
  - `$withdrawal->error` &mdash; If there is an error that caused you to set the transaction to `failed`, then set this error string to a meaningful message, that would let an admin know why the transaction failed. Provide as much information as you think is useful for debugging, but avoid showing sensitive user information, as this error message may be logged. After the adapter fails, it may throw an Exception which will be logged, but this is not necessary, as long as `status` and `error` are set correctly for each transaction.
- `get_new_address( Currency $currency = null ): Address` &mdash; Create a wallet address that can be assigned to a user as a deposit address. The plugin will indicate which currency the address is for via the `$currency` argument. Wallets for one currency only may discard this value.
- `get_hot_balance( Currency $currency = null ): int` &mdash; Retrieve the total balance held on the wallet, as an integer. The plugin will indicate which currency the address is for via the `$currency` argument. Wallets for one currency only may discard this value.
- `get_hot_locked_balance( Currency $currency = null ): int` &mdash; Retrieve the portion of the total balance on the wallet, minus any amounts that are currently considered locked, but which will later become available for withdrawal. Reasons for coins being locked include: coins from transactions that have not yet been fully confirmed, coins locked due to staking, etc. If the wallet does not have such a concept, return `0`.

> **NOTE**: To avoid floating point errors, all currency amounts are stored as integers internally. This is true for transaction amounts, fees, balances, etc. To display an amount, the plugin will take the number of decimals for a currency, and the display format, from the [Currency][post-types-currencies] definition.
>
> If your adapter needs to display amounts directly to the user (e.g. in the logs, via an email, in `do_description_text()`, etc), they must be converted to decimal, and passed to the `sprintf()` pattern associated with the currency. Multiply your amounts by ten to the plus or minus (number of decimals) to convert between integer and decimal:
>
>     error_log(
>         sprintf(
>             "The following amount of the %s currency was encountered: $currency->pattern",
>             $currency->name,
>             $amount * 10 ** -$currency->decimals
>         )
>     );


##### Implementing the remaining methods

You must also implement the following methods, which provide additional information to the plugin. These are much easier.

- `do_description_text(): void` &mdash; Use this to output HTML for the admin. This could be instructions, or details about the state of the wallet. They are shown to the admin in a metabox on the *[Wallet][glossary-wallet]* editor screen.
- `get_extra_field_name( $currency = null ): ?string` &mdash; If the specified `$currency`, or the underlying blockchain, requires more destination information to send a transaction than just an address string, then return here the name of that information. For example, Monero has *Payment ID*, and Ripple (XRP) has *Destination tag*.
- `get_wallet_version(): string` &mdash; Provide here the version of the wallet (not your adapter) to display to the admin. If not available, you can return an empty string or any other value. If it is not possible to connect to your wallet using the provided settings, you can throw an Exception here. The error message of the Exception will be shown to the admin next to the wallet status. This lets the plugin and the admin know that this wallet is offline.
- `get_block_height( Currency $currency = null ): int` &mdash; Provide here the block height up to which the wallet is synced, to display to the admin. If not available, you can return zero. This is displayed only so the admin knows if a wallet has fallen out of sync.
- `is_locked(): bool` &mdash; This should return whether the wallet is ready to accept withdrawal requests. When this returns `true`, the plugin will not attempt withdrawals that require it to use this wallet adapter.
- `do_cron(): void` &mdash; Any other maintenance tasks that your wallet may require can go here. This is called regularly by the `DSWallets\AdaptersTask` [cron job](#cron-jobs).



## Developing cron job tasks {#cron-jobs}

Cron jobs are classes that enclose a task that is to be repeated by the plugin.

The plugin includes a rudimentary task "runner".

If you need to create a cron job task, you must:

1. Derive a new class from the abstract `DSWallets\Task`. Only define the class after `plugins_loaded`.
2. On the class's constructor, set the task's priority, do any other initializing, then call the parent constructor.
3. Implement the public method `run()`. Use `$this->log()` to write to the debug log. Keep the method short. If it needs to process a lot of data, do it in small batches.
4. Instantiate your `Task`. The plugin will run your task whenever it runs other cron job tasks.

For a sample implementation, see the following repository:

https://github.com/dashed-slug/my-wallet-task

Install the repository as a new plugin and activate it.
Go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Cron tasks_ &rarr; _Verbose logging_ and enable logging.
As the cron task runs, you will see output such as the following in your `wp-content/debug.log`:

	[09-Dec-2021 19:23:41 UTC] [1639077821] Bitcoin and Altcoin Wallets: My_Wallet_Task: Task started.
	[09-Dec-2021 19:23:41 UTC] [1639077821] Bitcoin and Altcoin Wallets: My_Wallet_Task: Hello cron world!
	[09-Dec-2021 19:23:41 UTC] [1639077821] Bitcoin and Altcoin Wallets: My_Wallet_Task: Task finished. Elapsed: 2 sec, Mem delta: 11 bytes, Mem peak: 26 bytes, PHP / WP mem limits: 128 MB / 40 MB



[addresses-helpers]: https://wallets-phpdoc.dashed-slug.net/wallets/files/build-helpers-addresses.html
[currencies-helpers]: https://wallets-phpdoc.dashed-slug.net/wallets/files/build-helpers-currencies.html
[custom-taxonomies]: https://developer.wordpress.org/plugins/taxonomies/working-with-custom-taxonomies/
[jquery-ajax]: https://api.jquery.com/jQuery.ajax/
[json-api]: https://www.dashed-slug.net/bitcoin-and-altcoin-wallets-wordpress-plugin/json-api/
[plugin-header]: https://developer.wordpress.org/plugins/plugin-basics/header-requirements/
[post-types-currencies]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#currencies
[post-types-wallets]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types#wallets
[post-types]: /wp-admin/admin.php?page=wallets_docs&wallets-component=wallets&wallets-doc=post-types
[transactions-helpers]: https://wallets-phpdoc.dashed-slug.net/wallets/files/build-helpers-transactions.html
[wallets-api-available-balance]: https://wallets-phpdoc.dashed-slug.net/wallets/namespaces/dswallets.html#function_api_available_balance_filter
[wallets-api-balance]: https://wallets-phpdoc.dashed-slug.net/wallets/namespaces/dswallets.html#function_api_balance_filter
[wallets-api-cancel]: https://wallets-phpdoc.dashed-slug.net/wallets/namespaces/dswallets.html#function_api_cancel_transaction_action
[wallets-api-deposit-address]: https://wallets-phpdoc.dashed-slug.net/wallets/namespaces/dswallets.html#function_api_deposit_address_filter
[wallets-api-move]: https://wallets-phpdoc.dashed-slug.net/wallets/namespaces/dswallets.html#function_api_move_action
[wallets-api-retry]: https://wallets-phpdoc.dashed-slug.net/wallets/namespaces/dswallets.html#function_api_retry_transaction_action
[wallets-api-transactions]: https://wallets-phpdoc.dashed-slug.net/wallets/namespaces/dswallets.html#function_api_transactions_filter
[wallets-api-withdraw]: https://wallets-phpdoc.dashed-slug.net/wallets/namespaces/dswallets.html#function_api_withdraw_action
[wallets-helpers]: https://wallets-phpdoc.dashed-slug.net/wallets/files/build-helpers-wallets.html
[wallets]: https://www.dashed-slug.net/bitcoin-and-altcoin-wallets-wordpress-plugin/
[web-storage]: https://developer.mozilla.org/en-US/docs/Web/API/Storage
[wp-console]: https://wordpress.org/plugins/wp-console/
[wpdebug]: https://codex.wordpress.org/Debugging_in_WordPress
[wp-rest-api]: https://developer.wordpress.org/rest-api/
