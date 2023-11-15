# The post types

The plugin is based on the following four post types:

| Post type | Description |
| ---- | ----------- |
| `wallets_wallet` | Abstract representation of a connected wallet. Holds a wallet adapter and its settings. Can be associated with one or many currencies. |
| `wallets_currency` | Details about a crypto or fiat currency. Name, ticker symbol, display format, exchange rates, etc. |
| `wallets_address` | A deposit or withdrawal address. Associated with a Currency and user. |
| `wallets_tx` | Deposits, withdrawals, internal transfers. The user balances are calculated as sums over the amounts and fees of these posts. |

Relations between these entities is as follows: You should first define your wallets. Each wallet is set to use one of the available wallet adapters. Think of wallet adapters like "drivers" that let you connect to your wallets. The wallet post includes the connection settings that make the wallet adapter connect to your wallet.

Then, define the currencies. Every currency corresponds to one wallet, and every wallet corresponds to one or more currencies. This allows for wallets that support multiple currencies, such as the CoinPayments wallet. You can define all the fiat currencies automatically using an API key to fixer.io. Additionally, the CoinPayments extension will auto-create its currencies if they do not exist.

Once you have assigned wallet adapters to your wallet posts, and have linked your currency posts to your wallet posts, your users can now create addresses and transactions.

Transactions that are deposits / withdrawals are linked to addresses. Internal transfers on the other hand, are not linked to addresses.

Finally, addresses and transactions are linked to their respective currencies.

This modular architecture allows you to migrate a currency to a different wallet more easily than before.

## Wallets {#wallets}

The wallets plugin does not actually contain any wallets. The plugin must connect to your cryptocurrency wallets. The `wallets_wallet` post type represents the connection information to a wallet.

### Fields
To create a wallet:

1. Go to _Wallets_ and hit the _Add new_ button.

2. Give a descriptive title to your wallet, e.g. _"Bitcoin core wallet"_, or _"CoinPayments wallet"_.

3. Then, choose a *[wallet adapter][glossary-wallet-adapter]*. Depending on what type of wallet you want to connect to, you can choose one of:

| Wallet Adapter PHP class | Description |
| ------------------------ | ----------- |
| `DSWallets\Bitcoin_Core_Like_Wallet_Adapter` | Built-in adapter, allows you to connect to Bitcoin core and similar wallets. These include Litecoin core, Dogecoin core, etc. |
| `DSWallets\Bank_Fiat_Adapter` | This is a special built-in dummy adapter that lets you do manual deposits/withdrawals. Assign this to your fiat currencies if you want to manually process deposits / withdrawals to and from a bank account. |
| `DSWallets\Monero_Like_Wallet_Adapter` | Lets you connect to a Monero full node wallet, or to a wallet that is a fork of Monero. This adapter is packaged into a plugin extension. |
| `DSWallets\TurtleCoin_Like_Wallet_Adapter` | Lets you connect to a TurtleCoin full node wallet, or to a wallet that is a fork of TurtleCoin. This adapter is packaged into a plugin extension. |
| `DSWallets\CoinPayments_Wallet_Adapter` | Lets you connect to a CoinPayments.net online wallet. This adapter is packaged into a plugin extension. This is a multi-coin wallet adapter, and multiple currencies can be assigned to it. |

4. Once you have chosen a wallet adapter, hit _Publish_ to create your wallet post.

5. After the post is saved, you will see additional fields. These fields are specific to the Wallet Adapter that you have chosen. Fill in the additional fields as needed, then hit _Update_ to save the values you entered.

6. You can toggle whether the wallet is enabled with the "Wallet enabled" checkbox. If you uncheck and hit _Update_, the post will become a draft. Use this to quickly disable a wallet. Disabling a wallet does not disable the currencies associated with it.

Use the "Currencies assigned to this wallet" meta box to quickly navigate to the currencies that are associated with the wallet.

#### Bitcoin core-like wallet adapter

This built-in wallet adapter allows you to connect to Bitcoin core, or similar wallets. These include Litecoin core, Dogecoin core, etc.

This wallet adapter will let you connect to any wallet with a JSON-RPC API that is compatible with that of Bitcoin core.

Create one wallet post for each wallet that you plan to connect to. Only assign one currency to this adapter.

#### Bank Fiat Adapter

This is a special built-in dummy adapter that lets you do manual deposits/withdrawals. Assign your fiat currencies to a wallet with this wallet adapter, if you want to manually process deposits / withdrawals to and from a bank account.

This special wallet adapter does not actually connect to a wallet. The adapter is useful if you are going to be processing bank deposits/withdrawals manually on behalf of your users.

The settings to the wallet adapter are the necessary bank details for an international transfer to and from the account. You can set bank details separately for each fiat currency assigned.

You can later use the special shortcodes `[wallets_fiat_deposit]` and `[wallets_fiat_withdraw]` to allow users to deposit/withdraw to and from such bank accounts.

#### Monero-like Wallet Adapter

Lets you connect to a Monero full node wallet, or to a wallet that is a fork of Monero. This adapter is packaged into a plugin extension. You can download the adapter here: https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/monero-coin-adapter-extension/

Create one wallet post for each wallet that you plan to connect to. Only assign one currency to this adapter.

Refer to the documentation for this wallet adapter for more details. The documentation becomes available after you install the adapter extension.

#### TurtleCoin Wallet Adapter

Lets you connect to a TurtleCoin full node wallet, or to a wallet that is a fork of TurtleCoin. This adapter is packaged into a plugin extension. You can download the adapter here: https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/turtlecoin-adapter-extension/

Create one wallet post for each wallet that you plan to connect to. Only assign one currency to this adapter.

Refer to the documentation for this wallet adapter for more details. The documentation becomes available after you install the adapter extension.

#### CoinPayments Wallet Adapter

> **This is a multi-coin wallet adapter, and multiple currencies can be assigned to it.**

Lets you connect to a CoinPayments.net online wallet.

This adapter is packaged into a plugin extension. You can download the adapter here: https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/coinpayments-adapter-extension/

Refer to the documentation for this wallet adapter for more details. The documentation becomes available after you install the adapter extension.

### Navigating away from Wallet

#### Currencies

You will see a link to the associated currency or currencies for the wallet you are editing.


## Currencies {#currencies}

Currencies are central to the wallets plugin, and are modelled using the `wallets_currency` post type.

Once you have defined one or more wallets, you must create currencies. Currencies are associated to wallets, and transactions and addresses are associated to currencies.

### Fields

A currency post defines a currency's details, such as its name, display pattern (how amounts are rendered), how many decimal digits an amount of this currency has, how the exchange rate of that currency is loaded, etc.

For example, to create a Dogecoin currency entry:

1. Go to _Currencies_ and hit the _Add new_ button.

#### Currency's title/name

2. Set the name of the currency, in this case *"Dogecoin"*. You can leave this empty to be filled in automatically, if you set the CoinGecko ID (see below).

#### Currency's CoinGecko ID

3. CoinGecko maintains a database of cryptocurrencies and uses a unique ID for each currency. Start typing the name of your currency here, and the autocomplete feature will help you locate your currency's ID. Setting the CoinGecko ID is not strictly required, but has many benefits.

#### Currency's ticker symbol

4. As ticker symbol, set `DOGE`. You can leave this empty to be filled in automatically, if you set the CoinGecko ID (see above).

#### Currency Wallet

5. Assign the currency to an existing wallet. For Dogecoin, you would assign a Wallet with an adapter of type `Bitcoin_Core_Like_Wallet_Adapter` that connects to your Dogecoin full node wallet. In this case, you may also use a wallet with a CoinPayments Wallet Adapter, since Dogecoin is a currency available on CoinPayments.net.

#### Decimal places

6. Set the number of decimal places, which is `8` in the case of Dogecoin. You must save your currency after setting the decimals. This is because the integer encoding of amounts depend on this value. Hit _Publish_ to save the Currency.

> **NOTE**: Once one or more transactions are associated with your currency, you will no longer be allowed to change this value, so enter the correct value!

#### Currency's display pattern

7. Enter a display pattern. This is a [PHP sprintf() pattern][php-sprintf]. For example, to display a doge amount with 8 decimals, preceded by the Ď character, you would enter: `Ď %01.8f`.

#### Contract address or asset ID

8. If this currency is a token on a blockchain that supports multiple tokens, enter here the contract's address string (the hex string starting with `0x` that uniquely identifies this token).

If this is a Taproot Asset on the Lightning network, enter here the Asset ID hex string.

If you have set the CoingeckoID correctly above (step 3), then the contract address will be filled in automatically for you from CoinGecko, if it exists.

Typically tokens have contract addresses, while coins do not. Tokens are different from coins. Coins are assets that are native to their blockchain; examples are Bitcoin, Ethereum, Dogecoin, etc. Tokens are assets that adhere to contract APIs such as ERC-20, BEP2, BEP-20, TRC-20, Waves, etc. and examples of tokens include Tether, DAI, Shiba Inu, etc.

Leave blank for coins and other currencies.

#### Fees

##### Currency's Deposit fee

9. Enter a deposit fee. Usually this will be `0`.

##### Currency's Internal transfer (move) fee

10. Enter an internal transfer fee. You can set this to `0` or to a fee that you charge for internal transfers. Internal transfers are store on your DB, so it makes sense to charge a small fee here.


##### Currency's Withdrawal fee

11. Enter a withdrawal fee. This is important. You must set the withdrawal fee to be larger than what your wallet pays for a transaction. This fee must cover any miner fees that you pay to perform a withdrawal on behalf of your user. If, for example, Dogecoin fees for a simple transaction are on average `1.25` DOGE, you might set the withdrawal fee to be `2` DOGE. In this case, you earn `0.75` (`2 minus 1.25`) DOGE per withdrawal. If you have connected your currency to the CoinPayments Wallet adapter, the wallet adapter will set this to a value dictated by the CoinPayments platform. You can choose to increase this value and thus earn a cut of the total fees, but you may not decrease it below what CoinPayments charges.

#### Currency's exchange rates {#currency-exchange-rates}

12. Set the exchange rates data for this currency against *[VS Currencies][glossary-vs-currencies]*.

Normally you would not do this manually. Instead, set the CoinGecko ID and the plugin will keep the exchange rates updated for this currency. You will no longer be allowed to edit the exchange rates directly.

If you do not use a CoinGecko ID to define this currency, then you can edit the exchange rates manually here.

#### Block explorer

13. Optionally set the block explorer links. These are links used to link Transactions and Addresses to a block explorer. Use the `%s` placeholder in your link where you want the TXID or Address string. For example, if you wanted to use the chain.so explorer with Dogecoin, you would set _Block explorer URI pattern for addresses_ to `https://chain.so/address/DOGE/%s` and _Block explorer URI pattern for transactions_ to `https://chain.so/tx/DOGE/%s`.

#### Currency's withdrawal limits

14. Optionally set withdrawal limits for this currency. _Minimum withdrawal amount_ is the minimum allowed withdrawal, and it must be an amount larger than the withdrawal fees. _Hard daily withdrawal amount_ is the maximum amount of this currency that a user can withdraw in one day. A value of 0 means no limit. You can also restrict these daily withdrawal limits per User Role.

#### Currency tags

15. You can optionally organize your currencies using tags. These tags are part of the `wallets_currency_tags` custom taxonomy. You can then list currencies with a particular tag in the admin screens. Some special tags are used by the plugin:
- `CoinPayments` for currencies that were auto-generated by CoinPayments.
- `fiat` for currencies that are considered fiat currencies (i.e. not cryptocurrencies).
- `fixer` for fiat currencies that are auto-generated from fixer.io data.

#### Currency Icon

16. You can optionally set a featured image for this post from your media gallery. This image is used in the frontend as the logo for this currency.

> **TIP**: If you have set the CoinGecko ID above, you do not need to set the icon manually. The plugin will eventually (after a few minutes) retrieve the currency's icon, place it in your media gallery, and associate it with the currency.

> **TIP**: After you configure the currency settings, hit the _Update_ button to save changes.

### Navigating away from Currency

#### Wallet, Transactions, Addresses

In the same screen you will find meta boxes to help you navigate to the *[Wallet][glossary-wallet]*, *[Transactions][glossary-transaction]*, and *[Addresses][glossary-address]* associated with this currency.

## Addresses {#addresses}

Addresses are strings of characters that denote blockchain addresses. Addresses are modelled using the `wallets_address` post type.

An address is associated with a currency, a user, and possibly one or more transactions.

An address is either a *[Deposit][glossary-deposit]* address, or a *[Withdrawal][glossary-withdrawal]* address.

You normally would not create an address manually. A user can request to create a new deposit address via the `[wallets_deposit]` UI. A user can create as many deposit addresses as specified in: _Settings_ &rarr; _Bitcoin and Altcoin Wallets settings_ &rarr; _General settings_ &rarr; _Max deposit address count_. All past deposit addresses are monitored for deposits.

The plugin listens for incoming transactions to deposit addresses associated with users. When a deposit is observed, a deposit transaction is created. The user's balance is therefore updated. How the plugin monitors the wallet for incoming deposits is specified in the *[wallet adapter][glossary-wallet-adapter]* associated with each currency.

A withdrawal address is created when the user requests to withdraw using the `[wallets_withdraw]` UI. 

When a withdrawal is requested, the withdrawal address is saved as a new address post of type withdrawal. If an address with the same address string and same currency is found, that address is reused. Every withdrawal transaction is associated with its withdrawal address.


### Address's fields


#### Address's Title
An address's _Title_ can serve as a reminder to the user, but is otherwise unused by the plugin, and it can be left empty.

#### Address Type

Every address is either a deposit address or a withdrawal address.

#### Address string(s)

The contents of an address are different depending on whether the address is associated with a *[Fiat currency][glossary-fiat-currency]* or a *[Cryptocurrency][glossary-cryptocurrency]*.

Currencies are considered *[Fiat][glossary-fiat]*:
1. if their wallets are assigned the *[Bank Fiat adapter][glossary-bank-fiat-adapter]*, or
2. if they have the `fiat` tag assigned.
All other currencies are considered to be *[Cryptocurrencies][glossary-cryptocurrency]*.

If you have provided a fixer.io API key, the plugin will populate your system with the *[Fiat][glossary-fiat]* currencies and assign the `fiat` tag to them.


If the currency associated with an address is a *[Cryptocurrency][glossary-cryptocurrency]*, then the address post has an *[Address string][glossary-address-string]*.

In this case, depending on the wallet adapter associated with the currency, it may also have an *[Extra field][glossary-extra-field]*, which is wallet-specific. What this argument is, if present, is determined by the currency and the blockchain it is running on. For example, a Monero-like wallet adapter would instruct the plugin that any Monero addresses also have a *Payment ID*, while *Ripple (XRP)* addresses have a *Destination Tag*, etc.

If the currency with an address is a *[Fiat currency][glossary-fiat-currency]*, then the *[Address string][glossary-address-string]* and *[Extra field][glossary-extra-field]* are replaced with *Recipient name and home address* and *Bank name and address*. This is true for all fiat currencies. You would normally associate these with the *[Bank Fiat adapter][glossary-bank-fiat-adapter]*. i.e.: For bank deposits/withdrawals, you must provide the full name and address of the user, and the user's bank. This is typically the basic information required to perform bank transfers. Other information is stored in the associated transaction (see below).

Therefore, the currency associated with the address must always be specified. This is set via a dropdown. Under normal circumstances, you should never have to change the currency that an address is associated to.

#### Address tags

You can optionally organize your addresses using tags. These tags are part of the `wallets_address_tags` custom taxonomy. You can then list addresses with a particular tag in the admin screens.

Addresses with the special tag slug `archived` will not be shown in the frontend. Deposit addresses marked `archived` will continue to be usable as deposit addresses.

### Navigating away from Address

If the associated currency has block explorer links assigned, then you will get a navigation link from the address editor to the block explorer.

### Address and Transactions

You will also see a link to the associated currency for the address you are editing, and a link to the list of transactions associated with this address, if any.

## Transactions {#transactions}

Transactions are modeled in the plugin using the `wallets_tx` custom post type.

### Transaction Fields

#### Transaction Type: Deposit, Withdrawal, Move

Transactions can be:

- *[deposits][glossary-deposit]*, in which case they are associated with an address of type *[Deposit][glossary-deposit]*.
  - Deposit transactions always have positive amounts.
  - Typically there is no deposit fee, but there can be. The deposit fee does not affect the user balance, as it is paid by the sender. The deposit fee, if any, is there simply for information purposes. In the case of the CoinPayments wallet adapter, this can capture the 0.5% deposit fee incurred by the platform.
- *[withdrawals][glossary-withdrawal]*, in which case they are associated with an address of type *[Withdrawal][glossary-withdrawal]*. The withdrawal's target is the address.
  - Withdrawal transactions always have negative amounts (and fees), since these are subtracted from user balances.
- *internal transfers (move)*. Internal transfers are transfers between users.
  - They are typically created when a user uses the `[wallets_move]` shortcode.
  - These do not correspond to any blockchain transaction, but are internal to the plugin.
  - They live on your MySQL DB only, and are meant to transfer funds among users.
  - An internal transfer is typically represented by two posts, one for debiting the sending user, and one for crediting the receiving user. However, you are allowed to create as stand-alone credit or debit transaction to manually affect a user's balance.
  - A *Credit* transaction has a positive *Amount* and a *Debit* transaction has a negative *Amount*.
  - A *Credit* transaction that is associated with a *Debit* transaction, must be set to have its *Debit* counterpart as a *Parent post*. This is done automatically when users use the `[wallets_move]` shortcode, but must be done manually by the admin if it is needed for custom transfers.
  - Typically the sender pays fees, so these are specified on the *Debit* transaction.
  - Fees are always negative, since they are always subtracted from user balances.

#### Transaction Status

The status of a transaction can be one of:

- *[Pending][glossary-pending]*: The transaction has not been finalized but is about to. If it is a debiting transaction (i.e. withdrawal, or debit part of internal transfer) then it affects the *[Available Balance][glossary-available-balance]* of the user, but not the *[Balance][glossary-balance]*. This ensures that the funds are reserved and will not be used for other purposes. However, if the transaction fails or is cancelled, the funds will become available again.
- *[Done][glossary-done]*: The transaction has been finalized. It affects the user's *[Balance][glossary-balance]*.
- *[Cancelled][glossary-cancelled]*: The transaction has been manually cancelled. It no longer affects the user's balance.
  - Be careful when cancelling deposits/withdrawals: Cancelling a transaction on your DB cannot cancel a transaction on the blockchain.
  - Cancelling a *Credit*/*Debit* pair will also cancel the transaction's counterpart. The *Debit* for a *Credit* transaction post is its parent. The *Credit* for a *Debit* transaction post is its child.

#### Transaction Currency

The currency associated with the transaction. If this is a deposit/withdrawal, then the associated address must be of the same currency.

#### Transaction Amount

The transacted amount. *Credit* transactions and *[Deposits][glossary-deposit]* have positive amounts. *Debit* transactions and *[Withdrawals][glossary-withdrawal]* have negative amounts. The amount sign denotes the fact that these amounts are added to, and subtracted from, user balances, respectively.

#### Transaction fee paid to site

This fee is subtracted from the sender's balance. In the case of deposits, it is ignored (but it is displayed to the user, if it exists).


#### Transaction: Blockchain-specific transaction attributes

##### Blockchain transaction address string

The address string, excluding any additional information or metadata about the address.

##### Blockchain transaction address extra field

Monero Payment ID, Ripple Destination Tag, etc.

##### Blockchain Transaction ID (TXID)

The TXID associated with this blockchain transaction. Can be empty. For example, failed or cancelled withdrawals, or pending withdrawals before they are executed by cron, will not have a TXID.

#### Transaction: Internal-transfer-specific attributes

##### Internal transfer transaction: Counterpart transaction

For internal transfers (moves), *Credits* have their corresponding *Debit* transaction assigned as parent.

Allows linking a *Credit* transaction with its *Debit* counterpart. Create the *Debit* transaction first. Then, when creating the *Credit* transaction, set its parent to be the *Debit* transaction you previously created.


#### Transaction: Bank Fiat-specific transaction attributes

##### Bank Fiat transaction: Recipient name and home address

User's name and full home address. Multi-line field.

##### Bank Fiat transaction: Bank name and address

Name and address of user's bank. Multi-line field.

#### Transaction: Pending withdrawal checks

Pending withdrawals can only proceed if certain checks pass.

The withdrawals cron sends withdrawals of each coin in batches to the wallet adapter. This allows transactions with multi-UTXO outputs which save on miner fees. For this reason, some checks apply to batches of pending withdrawals, while other checks apply to individual pending withdrawals.

##### Individual checks performed

The transaction object is passed to the `wallets_withdrawal_pre_check` action. If the action throws an exception, this means that the withdrawal is not eligible for execution. You can attach your own checks to this action.

- Amount and fee must be negative
- Currency must be specified in withdrawal
- Address must be specified in withdrawal
- Address string must be specified
- Must be in pending state before execution
- Amount must be at least the minimum allowed by the currency
- If the withdrawal must be verified by email link, the user must have clicked on the link

##### Batch checks performed

An array of transaction objects is passed to the `wallets_withdrawals_pre_check` filter (note the plural in the hook name). All of the transactions must be pending withdrawals for one currency, but can originate from multiple users. The filter will write output to the log and will return an array of transactions that can proceeed to be executed. If not all transactions can be executed, then a subset of the withdrawals will be returned. This subset is ok to be sent to the adapter.

- The wallet adapter for the currencies must be enabled.
- The wallet adapter for the currencies must be unlocked for withdrawals.
- The user must hold enough balance to perform all the withdrawals that will pass this filter.
- If the currency specifies a maximum withdrawal limit per day, this must not be exceeded.
- If the currency specifies a maximum withdrawal limit per day for a user role, this must not be exceeded for any of the user's roles.
- The hot wallet must hold enough balance to perform all the withdrawals that will pass this filter. If this filter fails for any withdrawal, the filter has a side effect: All the users with the `manage_wallets` capability (admins) will be notified by email that the hot wallet balance is low. This email will not be sent more often than once per day (24 hours).


### Transaction tags

You can optionally organize your transactions using tags. These tags are part of the `wallets_txs_tags` custom taxonomy. You can then list transactions with a particular tag in the admin screens.

The plugin and its extensions also assign various tags. This helps in grouping transactions that are somehow related. For example, all deposits from the same airdrop or airdrop run, or all transactions associated with a woocommerce purchase.


### Navigating away from Transaction

#### Block explorer
For *[Deposits][glossary-deposit]* and *[Withdrawals][glossary-withdrawal]*: If the associated currency has block explorer links assigned, then you will get a navigation link from the transaction editor to the block explorer at the specified TXID.

#### Currency and address

You will also see a link to the associated currency for the transaction you are editing, and a link to the associated address.


[php-sprintf]: https://www.php.net/manual/en/function.sprintf.php
