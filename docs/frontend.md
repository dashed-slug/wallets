# Frontend and Shortcodes


## UIs overview

You can display wallets UI forms to the front-end. These let the user do basic operations with their accounts, such as depositing, withdrawing, and transacting currencies.

Display the UI forms using WordPress [shortcodes][shortcodes].

The UIs use the WP-REST API to communicate with the plugin.

This chapter discusses both the available shortcodes, and other more advanced ways to control/modify the frontend UIs.


### Public shortcodes

Some shortcodes do not require login or capabilities. These are:

- `[wallets_rates]`          - Displays exchange rates for all site currencies.
- `[wallets_status]`         - Displays wallet status for all site currencies.
- `[wallets_total_balances]` - Displays the sums of user balances for each site currency.


### Private shortcodes

The shortcodes that do require login and the right capabilities, are:

- `[wallets_balance]`       - Displays user balances.
- `[wallets_deposit]`       - Displays a UI for the user to see their deposit addresses and create new ones.
- `[wallets_withdraw]`      - Displays a UI for submitting cryptocurrency withdrawals.
- `[wallets_account_value]` - Displays the total value of a user's account.
- `[wallets_move]`          - Displays a UI for transferring currencies to other users on the site. (off-chain transactions)
- `[wallets_fiat_withdraw]` - Display a UI for submitting fiat bank withdrawal requests.
- `[wallets_fiat_deposit]`  - Display a UI with the correct fiat bank deposit details for each currency.
- `[wallets_transactions]`  - Displays a table UI of the default user's past and pending transactions.

The private shortcodes will only function if:

1. the user is logged in,
2. there is at least one wallet online, and
3. the user has the necessary capabilities, including `has_wallets`.

To review the currently assigned capabilities on user roles, visit _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Capabilities_ &rarr; _General Capabilities_.


### Which template to display?

All UIs have a default template and possibly a number of additional templates. You can specify the template you want to use via the `template` attribute.

Think of templates as variants to the original UI that display the same information in some different way. For example, `[wallets_balance]` displays a box with a single balance, but `[wallets_balance template="list"]` displays all balances for the entire list of currencies on the site.

For example, `[wallets_balance template="list"]` would correspond to a template file of `balance-list.php`.

When rendering the shortcode, the plugin will look for this template file:

1. first under the child theme (if one is active),
2. then under the parent theme, and
3. finally under the plugin's directory.

The plugin will use the first template it encounters.


### Which user to display?

UIs display the current user's data by default.

To display data for a specific user, use one of the following attributes:

- `user`    - Must match one of a user's `login`, `slug`, or `email`.
- `user_id` - Integer WordPress user ID.


### Which currency to display?

For UIs that display data for a specific currency, use one of the following attributes:

- `currency_id`  - The post ID for the currency's post. Unambiguous.
- `symbol`       - The currency's ticker symbol. If many currencies share the same ticker symbol, the result is unpredictable.
- `coingecko_id` - The unique id for this currency on CoinGecko. Unambiguous, but will only work if the CoinGecko ID has been set on the currency post.



## The wallets shortcodes {#wallets-shortcodes}


### Wallet balance `balance.php`

> Accepted attributes: `user_id`, `user`, `symbol`, `currency_id`, `coingecko_id`, `template`

> Text filters: `wallets_ui_text_reload`, `wallets_ui_text_no_coins`, `wallets_ui_text_currency`, `wallets_ui_text_balance`, `wallets_ui_text_available_balance`

To display user balances:

	[wallets_balance]

A box will be shown with all the site currencies in a dropdown. The box displays the balance of the selected currency, in its native amount and priced in another "VS currency". The user can rotate over the available options for the "VS currency" by clicking on the VS currency amount.


### Wallet balances: List `balance-list.php`

> Accepted attributes: `user_id`, `user`, `symbol`, `currency_id`, `coingecko_id`, `template`, `show_zero_balances`

> Text filters: `wallets_ui_text_reload`, `wallets_ui_text_no_coins`, `wallets_ui_text_show_zero_balances`, `wallets_ui_text_currency`, `wallets_ui_text_balance`, `wallets_ui_text_available_balance`

To display the user's balances over all the site's currenies in a list:

	[wallets_balance template="list"]

To do the same, but do not list zero balances:

	[wallets_balance template="list" show_zero_balances="on"]


### Wallet balance: Text only `balance-textonly.php`

> Accepted attributes: `user_id`, `user`, `symbol`, `currency_id`, `coingecko_id`, `template`

To display the user's balances over a site curreny as plain text, where the currency is specified by its post ID:

	[wallets_balance template="textonly" currency_id="123"]

To do the same, but with the currency specified by its ticker symbol:

	[wallets_balance template="textonly" symbol="DOGE"]

To do the same, but with the currency specified by its CoinGecko ID:

	[wallets_balance template="textonly" coingecko_id="doge"]


### Wallets deposit `deposit.php`

> Accepted attributes: `user_id`, `user`, `symbol`, `currency_id`, `coingecko_id`, `template`, `qrsize`

> Text filters: `wallets_ui_text_reload`, `wallets_ui_text_no_coins`, `wallets_ui_text_currency`, `wallets_ui_text_address`, `wallets_ui_text_depositaddress`, `wallets_ui_text_copy_to_clipboard`, `wallets_ui_text_copy_to_clipboard`, `wallets_ui_text_no_addresses`, `wallets_ui_text_get_new_address`, `wallets_ui_alert_address_created_with_label`, `wallets_ui_alert_address_created`, `wallets_ui_alert_address_creation_fail`

To display a UI for the user to see their deposit addresses and create new ones:

	[wallets_deposit]

To do the same but for a user specified by email, and select by default the DOGE deposit addresses:

	[wallets_deposit user="some.user@example-mail.com" coingecko_id="doge"]

To do the same but with a specifc pixel size for the QR code:

	[wallets_deposit qrsize="32"]

### Wallet deposit: List `deposit-list.php`

To display the latest deposit address for each currency in a list:

> Accepted attributes: `user_id`, `user`, `template`

> Text filters: `wallets_ui_after`, `wallets_ui_after_deposit_list`, `wallets_ui_alert_address_created`, `wallets_ui_alert_address_created_with_label`, `wallets_ui_alert_address_creation_fail`, `wallets_ui_before`, `wallets_ui_before_deposit_list`, `wallets_ui_text_copy_to_clipboard`, `wallets_ui_text_currency`, `wallets_ui_text_depositaddress`, `wallets_ui_text_new`, `wallets_ui_text_no_coins`, `wallets_ui_text_reload`


### Wallets move `move.php`

> Accepted attributes: `symbol`, `currency_id`, `coingecko_id`, `template`

> Text filters: `wallets_ui_text_no_coins`, `wallets_ui_text_currency`, `wallets_ui_text_recipientuser`, `wallets_ui_text_enterusernameoremail`, `wallets_ui_text_amount`, `wallets_ui_text_max`, `wallets_ui_text_fee`, `wallets_ui_text_amountplusfee`, `wallets_ui_text_comment`, `wallets_ui_text_send`, `wallets_ui_text_resetform`, `wallets_ui_alert_move_created`, `wallets_ui_alert_address_created`, `wallets_ui_alert_move_creation_fail`

To display a UI for transferring currencies to other users on the site. (off-chain transactions):

	[wallets_move]

To do the same, but also pre-select the DOGE currency, specified by ticker symbol:

	[wallets_move symbol="DOGE]


### Wallets withdrawal `withdraw.php`

> Accepted attributes: `symbol`, `currency_id`, `coingecko_id`, `template`

> Text filters: `wallets_ui_text_no_coins`, `wallets_ui_text_currency`, `wallets_ui_text_withdrawtoaddress`, `wallets_ui_text_amount`, `wallets_ui_text_max`, `wallets_ui_text_fee`, `wallets_ui_text_amountplusfee`, `wallets_ui_text_comment`, `wallets_ui_text_send`, `wallets_ui_text_resetform`, `wallets_ui_alert_withdrawal_created`, `wallets_ui_alert_address_created`, `wallets_ui_alert_withdrawal_creation_fail`, `wallets_ui_alert_withdrawal_qr_scan_failed`

To display a UI for submitting cryptocurrency withdrawals:

	[wallets_withdraw]

To do the same, but also pre-select the DOGE currency, specified by CoinGecko ID:

	[wallets_withdraw coingecko_id="doge]


### Wallets transactions `transactions.php`, `fragments/transactions.php`

> Accepted attributes: `user_id`, `user`, `symbol`, `currency_id`, `coingecko_id`, `template`, `columns`, `rowcount`, `categories`, `tags`

> Text filters: `wallets_ui_text_reload`, `wallets_ui_text_no_coins`, `wallets_ui_text_coin`, `wallets_ui_text_page`, `wallets_ui_text_rowsperpage`, `wallets_ui_text_type`, `wallets_ui_text_type_withdrawal`, `wallets_ui_text_type`, `wallets_ui_text_type_deposit`, `wallets_ui_text_type`, `wallets_ui_text_type_move`, `wallets_ui_text_time`, `wallets_ui_text_status`, `wallets_ui_text_tags`, `wallets_ui_text_time`, `wallets_ui_text_fee`, `wallets_ui_text_address`, `wallets_ui_text_txid`, `wallets_ui_text_comment`, `wallets_ui_text_type`, `wallets_ui_text_tags`, `wallets_ui_text_time`, `wallets_ui_text_amount`, `wallets_ui_text_fee`, `wallets_ui_text_address`, `wallets_ui_text_txid`, `wallets_ui_text_comment`, `wallets_ui_text_status`, `wallets_ui_text_userconfirm`

To display a table UI of the default user's past and pending transactions:

	[wallets_transactions]

To display a table UI of the default user's past and pending transactions for a specific currency (also allows user to change the currency via a dropdown):

	[wallets_transactions currency_id="123"]

To display the transactions of a user specified by user ID:

	[wallets_transaction user_id="123"]

To display the transactions of a user specified by user ID only for a currency specified by ticker symbol:

	[wallets_transaction user_id="123" symbol="DOGE"]

To display a table UI of the default user's past and pending transactions, with 20 transactions per page:

	[wallets_transactions rowcount="20"]


To display a table UI of the default user's past and pending withdrawal transactions:

	[wallets_transactions categories="withdrawal"]

And to display a table UI of the default user's past and pending internal transfers and deposits:

	[wallets_transactions categories="deposit,move"]


To display a table UI of the default user's past and pending transactions that have both the tags `foo` and `bar` (where the tags are slugs for terms in the `wallets_tx_tags` taxonomy):

	[wallets_transactions tags="foo,bar"]

To display a UI with the correct fiat bank deposit details for each currency, and also specify which columns to show, and in what order, explicitly:

	[wallets_transactions columns="type,tags,time,currency,amount,fee,address,txid,comment,status,user_confirm"]


### Wallets transactions: Rows `transactions-rows.php`, `fragments/transactions-rows.php`

> Accepted attributes: `user_id`, `user`, `symbol`, `currency_id`, `coingecko_id`, `template`, `columns`, `rowcount`, `categories`, `tags`

> Text filters: `wallets_ui_text_reload`, `wallets_ui_text_no_coins`, `wallets_ui_text_coin`, `wallets_ui_text_page`, `wallets_ui_text_rowsperpage`, `wallets_ui_text_type`, `wallets_ui_text_type_withdrawal`, `wallets_ui_text_type`, `wallets_ui_text_type_deposit`, `wallets_ui_text_type`, `wallets_ui_text_type_move`, `wallets_ui_text_time`, `wallets_ui_text_status`, `wallets_ui_text_tags`, `wallets_ui_text_time`, `wallets_ui_text_fee`, `wallets_ui_text_address`, `wallets_ui_text_txid`, `wallets_ui_text_comment`, `wallets_ui_text_type`, `wallets_ui_text_tags`, `wallets_ui_text_time`, `wallets_ui_text_amount`, `wallets_ui_text_fee`, `wallets_ui_text_address`, `wallets_ui_text_txid`, `wallets_ui_text_comment`, `wallets_ui_text_status`, `wallets_ui_text_userconfirm`

To display a rows UI of the default user's past and pending transactions in rows rather than table form:

	[wallets_transactions template="rows"]

To display a rows UI of the default user's past and pending transactions for a specific currency (also allows user to change the currency via a dropdown):

	[wallets_transactions template="rows" currency_id="123"]

> All the same attributes apply as for `[wallets_transaction]`, except for the `columns` attribute, which is not used for rows.


### Wallets fiat withdrawal to bank `fiat_withdraw.php`

> Accepted attributes: `user_id`, `user`, `symbol`, `currency_id`, `template`, `validation`

> Text filters: `wallets_ui_text_no_coins`, `wallets_fiat_ui_text_fiat_currency`, `wallets_fiat_ui_text_amount`, `wallets_fiat_ui_text_max`, `wallets_fiat_ui_text_fee`, `wallets_fiat_ui_text_amountplusfee`, `wallets_fiat_ui_text_nameaddress`, `wallets_fiat_ui_text_banknameaddress`, `wallets_fiat_ui_text_swiftbiciban`, `wallets_fiat_ui_text_routingaccnum`, `wallets_fiat_ui_text_ifscaccnum`, `wallets_fiat_ui_text_swiftbic`, `wallets_fiat_ui_text_iban`, `wallets_fiat_ui_text_routingnum`, `wallets_fiat_ui_text_accountnum`, `wallets_fiat_ui_text_ifsc`, `wallets_fiat_ui_text_indianaccountnum`, `wallets_fiat_ui_text_comment`, `wallets_fiat_ui_text_requestbanktransfer`, `wallets_ui_text_resetform`, `wallets_fiat_ui_text_no_swift_bic_entered`, `wallets_fiat_ui_text_invalid_swift_bic`, `wallets_fiat_ui_text_no_routing_number_entered`, `wallets_fiat_ui_text_routing_number_must_be_a_number`, `wallets_fiat_ui_text_routing_number_must_have_nine_digits`, `wallets_fiat_ui_text_no_acc_num_entered`, `wallets_fiat_ui_text_acc_num_must_be_num`, `wallets_fiat_ui_text_acc_num_six_to_fourteen_digits`, `wallets_fiat_ui_text_no_ifsc_entered`, `wallets_fiat_ui_text_invalid_ifsc_entered`, `wallets_fiat_ui_text_no_acc_num_entered`, `wallets_fiat_ui_text_acc_num_nine_to_eighteen_digits`, `wallets_fiat_ui_alert_bank_withdrawal_created`, `wallets_fiat_ui_alert_bank_withdrawal_creation_failed`

To display a UI for submitting fiat bank withdrawal requests:

	[wallets_fiat_withdraw]

To do the same but for a user specified by email:

	[wallets_fiat_withdraw user="some.user@example-mail.com"]

To display a UI that accepts any data without validation:

	[wallets_fiat_withdraw validation="off"]

### Wallets fiat deposit from bank `fiat_deposit.php`

> Accepted attributes: `symbol`, `currency_id`, `coingecko_id`, `template`

> Text filters: `wallets_ui_text_no_coins`, `wallets_fiat_ui_text_fiat_currency`, `wallets_fiat_ui_text_fiat_deposit_instructions`, `wallets_fiat_ui_text_fiat_deposit_bank_name_address`, `wallets_fiat_ui_text_fiat_deposit_bank_bic`, `wallets_fiat_ui_text_fiat_deposit_bank_acc_routing`, `wallets_fiat_ui_text_fiat_deposit_bank_ifsc`, `wallets_fiat_ui_text_fiat_deposit_bank_acc_iban`, `wallets_fiat_ui_text_fiat_deposit_bank_acc_accnum`, `wallets_fiat_ui_text_fiat_deposit_bank_acc_indianaccnum`, `wallets_fiat_ui_text_fiat_deposit_message_instructions`

To display a UI with the correct fiat bank deposit details for each currency.

	[wallets_fiat_deposit]

To do the same but for a user specified by email:

	[wallets_fiat_deposit user="some.user@example-mail.com"]


### Wallets rates `rates.php`

> Accepted attributes: `decimals`, `template`

> Text filters: `wallets_ui_text_reload`, `wallets_ui_text_no_coins`, `wallets_ui_text_currency`, `wallets_ui_text_exchangerate`

To display exchange rates for the site's currencies:

	[wallets_rates]

To do the same, but force all the exchange rates to be displayed at 2 decimals:

	[wallets_rates decimals="2"]


### Wallets status `status.php`

> Accepted attributes: `template`

> Text filters: `wallets_ui_text_reload`, `wallets_ui_text_no_coins`, `wallets_ui_text_currency`, `wallets_ui_text_walletstatus`, `wallets_ui_text_blockheight`

To display the wallet status (online/offline) for the site's currencies:

	[wallets_status]


### Wallets total balances `total_balances.php`

> Text filters: `wallets_ui_text_no_coins`, `wallets_ui_text_show_zero_balances`, `wallets_ui_text_currency`, `wallets_ui_text_balance`, `wallets_ui_text_available_balance`

> Accepted attributes: `template`, `show_zero_balances`

To display total balances over all users for each coin:

	[wallets_total_balances]

To do the same, but do not list currencies with zero balances by default:

	[wallets_total_balances show_zero_balances="on"]



### Wallets account value `account_value.php`

> Accepted attributes: `user_id`, `user`, `template`

> Text filters: `wallets_ui_text_reload`, `wallets_ui_text_no_coins`, `wallets_ui_text_account_value`

To display the total value of a user's account:

	[wallets_account_value]

To do the same but for a user specified by email:

	[wallets_account_value user="some.user@example-mail.com"]



### Wallets accont value: Text only `account_value-textonly.php`

> Accepted attributes: `user_id`, `user`, `template`

To display the total value of a user's account as text:

	[wallets_account_value template="textonly"]



## Displaying user balances as a menu

You can display the user balances for all enabled coins, as part of a WordPress menu.

1. Go to _Appearance_ &rarr; _Menus_.
2. At the top right side of the screen, click _Screen Options_.
3. Under _Boxes_, make sure that _Bitcoin and Altcoin Wallets balances_ is selected.
4. Now you are free to create a menu that includes the user balances.
5. Assign your menu to one of the menu areas of your theme.

Alternatively you can display this list of balances with a shortcode anywhere in your pages, using the [Menu Shortcode][menu-shortcode] plugin.


## Frontend UI JavaScript event bindings


### Add WordPress dependency on `wallets-front` script

To bind JavaScript code to the plugin's frontend using a `wp_enqueue_script()` call, make sure to list `wallets-front` as a dependency. This ensures that your script is loaded after the knockout scripts of the plugin. For example:

	wp_enqueue_script(
		'mywalletsscript',
		'https://url/to/mywalletsscript.js',
		array( 'wallets-front' ),
		'1.0.0'
	);


### Bind to `wallets_ready` event on frontend

After the plugin's main JavaScript code is run, the plugin emits a bubbling [DOM event][mdn-event]. Any code that depends on the main plugin code, can run on this event:

		jQuery( 'body' ).on( 'wallets_ready', function( event, dsWalletsRest ) {
			alert( json_encode( dsWalletsRest ) );
		});


## Modifying the UI appearance

There are multiple ways to modify the appearance of the frontend UIs.

Depending on what you want to modify, some ways are easier/preferable than others:

- To **add extra text** around the start and end of the UI, see *Output additional markup before/after the UIs using actions* below.
- To **modify the text** used in the labels and message boxes, see *Modifying text in the UIs using filters* below.
- If you only want to modify the texts to **provide a translation**, do not use the text filters. Instead, see *Translating the text in the UIs to other languages* below.
- If you want to **modify the markup** in some significant way, see *Editing the template files* below.
- To **modify visual aspects** such as text attributes, margins, colors, etc, see *Modifying styles with Customizer* below.
- To **modify visual aspects** freely using custom CSS rules, see *Modifying styles with CSS rules* below.


### Translating the text in the UIs to other languages

If you wish to provide translations to these templates, see the *[Localization][glossary-localization]* section of this document.

If you wish to modify the text of the labels in some other way, you may use WordPress filters (below):


### Modifying text in the UIs using filters

The templates that correspond to the various frontend UIs contain text that can be modified using [WordPress filters][wordpress-filters].

For example here is how you would bind to the filter for the *"No currencies."* message:

	add_filter(
		'wallets_ui_text_no_coins',
		function( $text ) {
			return "There's no currencies to be found!";
		}
	);


> **WARNING**: Any values you produce in these filters will not be passed to `esc_html()` or `esc_attr()`. This means that you can use these filters to modify the HTML markup.


### Output additional markup before/after the UIs using actions

All UIs when displayed activate the following two [WordPress actions][wordpress-actions]:

- `wallets_ui_before`
- `wallets_ui_after`

Additionally the following actions exist to differentiate between UIs:

- `wallets_ui_before_move`
- `wallets_ui_after_move`
- `wallets_ui_before_withdraw`
- `wallets_ui_after_withdraw`
- `wallets_ui_before_balance`
- `wallets_ui_after_balance`
- `wallets_ui_before_deposit`
- `wallets_ui_after_deposit`
- `wallets_ui_before_transactions`
- `wallets_ui_after_transactions`
- `wallets_ui_before_account_value`
- `wallets_ui_after_account_value`
- `wallets_ui_before_total_balances`
- `wallets_ui_after_total_balances`
- `wallets_ui_before_rates`
- `wallets_ui_after_rates`
- `wallets_ui_before_wallets_api_key`
- `wallets_ui_after_wallets_api_key`


### Shortcode rendering error messages

If a shortcode cannot be rendered due to a problem, an error message is displayed. The HTML container gets the class `error`.

For example, an error can be caused if:
- The user is not logged in.
- The user does not have the necessary capabilities.
- The template throws a PHP exception.

To modify the error message shown when a shortcode cannot be rendered, use the `wallets_ui_text_shortcode_error` filter:

	add_filter(
		'wallets_ui_text_shortcode_error',
		function( $error_message ) {
			return '<p>UI cannot be shown!</p>';
		}
	);

The following strings are substituted in the error message pattern:

- `%1$s` Template *generic* part (e.g. `deposit`, `move`, `withdraw`, etc.)
- `%2$s` Template *specialized* part (e.g. `default`, `textonly`, etc.)
- `%3$s` View file (e.g. `/wp-content/plugins/wallets/templates/deposit/default.php`)
- `%4$s` The text of the error message.

For example, you can format this error data as follows:

	add_filter(
		'wallets_ui_text_shortcode_error',
		function( $error_message ) {
			return '<p>Error while rendering the <code>%1$s</code> shortcode with its <code>%2$s</code> template in <code>%3$s</code>: %4$s</p>';
		}
	);


### Editing the template files {#editing-templates}

> **TIP:** You should not try to edit the files in place, because any changes you make will be overwritten whenever the plugin updates.

If you need to modify the HTML markup for the UI elements (i.e. not just the text), then you should copy the files into your theme or child theme. To learn how to create a child theme from your theme, see [this article from wpbeginner][wpbeginner-child-theme].

Starting from version `5.0.0`, the templates for the UIs are in the `wp-content/plugins/wallets/templates` directory.

The template files available for the UI elements are:

| shortcode                                          | template file
| ---                                                | ---
| `[wallets_account_value]`                          | `account_value.php`
| `[wallets_account_value template="textonly"]`      | `account_value-textonly.php`
| `[wallets_balance template="list"]`                | `balance-list.php`
| `[wallets_balance]`                                | `balance.php`
| `[wallets_balance template="textonly"]`            | `balance-textonly.php`
| `[wallets_deposit template="list"]`                | `deposit-list.php`
| `[wallets_deposit]`                                | `deposit.php`
| `[wallets_deposit template="textonly"]`            | `deposit-textonly.php`
| `[wallets_depositextra template="textonly"]`       | `depositextra-textonly.php`
| `[wallets_move]`                                   | `move.php`
| `[wallets_rates]`                                  | `rates.php`
| `[wallets_status]`                                 | `status.php`
| `[wallets_total_balances]`                         | `total_balances.php`
| `[wallets_transactions]`                           | `transactions.php`
| `[wallets_transactions template="rows"]`           | `transactions-rows.php`
| `[wallets_withdraw]`                               | `withdraw.php`
| `[wallets_fiat_deposit]`                           | `fiat_deposit.php`
| `[wallets_fiat_withdraw]`                          | `fiat_withdraw.php`


Since version `6.0.0`, each template file encapsulates:
- HTML markup (including knockout bindings)
- Inline JavaScript (the code that controls the UI)
- Inline CSS rules (any styles that are to be applied to the UI)

Because the templates are now self-contained files, they are easier to study and modify. Also, each template has its own JavaScript execution context. This eliminates a known issue with versions before `6.0.0`, where any errors from one UI would halt execution of all the other UIs.

Copy any of these template files in `wp-content/themes/YOUR_THEME/templates/wallets`, or `wp-content/themes/YOUR_CHILD_THEME/templates/wallets`. You can edit the copies under your theme or child theme directory, and these copies will take precedence.

> **TIP**:When you modify the markup, make sure to preserve the bindings to knockout observables (the `data-bind` HTML attributes). Child theme templates take precedence, then parent theme templates, then plugin templates.


## Modifying styles with Customizer {#customizer}

Starting with version `4.1.0`, you can now use the [Customizer][customizer] to control UI styling. Visit _Customizer_ &rarr; _Bitcoin and Altcoin Wallets_.

The following sections are available:

### General

Color the opacity of UIs while data is communicated with the server's API (i.e. while loading data or performing transactions). By default, when a UI is not ready for user interaction, its opacity fades to `0.5`.

### Borders

Control many aspects of how the borders are displayed: Color, Line Style, Width, Radius, Padding width, Shadow offset X, Shadow offset Y, Shadow color, Shadow blur radius

### Text

Control color and font size for regular text and for labels.

### Transaction colors

Change the colors associated with transaction states (*[Pending][glossary-pending]*, *[Done][glossary-done]*, *[Failed][glossary-failed]*, *[Cancelled][glossary-cancelled]*).

### Currency icons

Control how currency icons are displayed: Icon size, Shadow offset X, Shadow offset Y, Shadow color, Shadow blur radius


## Modifying styles with CSS rules

All the UI elements have the `dashed-slug-wallets` class to help you with CSS styling. Here's some selectors you can use:

- `.dashed-slug-wallets.account-value    {` &hellip; `}`
- `.dashed-slug-wallets.balance          {` &hellip; `}`
- `.dashed-slug-wallets.balance-list     {` &hellip; `}`
- `.dashed-slug-wallets.balance.textonly {` &hellip; `}`
- `.dashed-slug-wallets.deposit          {` &hellip; `}`
- `.dashed-slug-wallets.withdraw         {` &hellip; `}`
- `.dashed-slug-wallets.move             {` &hellip; `}`
- `.dashed-slug-wallets.transactions     {` &hellip; `}`
- `.dashed-slug-wallets.fiat-deposit     {` &hellip; `}`
- `.dashed-slug-wallets.fiat-withdraw    {` &hellip; `}`
- `.dashed-slug-wallets.rates            {` &hellip; `}`
- `.dashed-slug-wallets.status           {` &hellip; `}`
- `.dashed-slug-wallets.total-balances   {` &hellip; `}`

> **TIP**:If you do NOT want to edit the template files, an eazy way to add your CSS rules is to go to: _Customize_ &rarr; _Additional CSS_.

> **TIP**:If you are going to edit the template files, you will find the rules specific to each UI, in the template file. The CSS code is enclosed in an inline `<style>` tag. The inline styles are applied using a [Scoped CSS polyfill][scoped-styling].

> **TIP**:If you are trying to modify the style of an element, but your changes are not applied, a CSS rule with higher [specificity][mdn-specificity] may apply. Use your browser's inspector to determine the most specific rules that apply to the element you are styling.


[customizer]:             https://codex.wordpress.org/Theme_Customization_API                               "Codex / Theme Customization API"
[mdn-event]:              https://developer.mozilla.org/en-US/docs/Web/API/Event                            "MDN Web Docs / Event"
[mdn-specificity]:        https://developer.mozilla.org/en-US/docs/Web/CSS/Specificity                      "MDN Web Docs / Specificity"
[menu-shortcode]:         https://wordpress.org/plugins/menu-shortcode/                                     "wordpress.org / Menu Shortcode plugin"
[scoped-styling]:         https://github.com/samthor/scoped                                                 "Scoped CSS polyfill"
[shortcodes]:             https://codex.wordpress.org/Shortcode                                             "Codex / Shortcode"
[wordpress-actions]:      https://developer.wordpress.org/plugins/hooks/actions/                            "Wordpress.org / Plugin Handbook / Hooks / Actions"
[wordpress-filters]:      https://developer.wordpress.org/plugins/hooks/filters/                            "Wordpress.org / Plugin Handbook / Hooks / Filters"
[wpbeginner-child-theme]: https://www.wpbeginner.com/wp-themes/how-to-create-a-wordpress-child-theme-video/ "How to Create a WordPress Child Theme (Beginner’s Guide)"
