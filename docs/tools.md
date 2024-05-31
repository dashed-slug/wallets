# Tools

*[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]* adds some tools to the WordPress _Tools_ menu.

All users should familiarize themselves with *[Cold Storage][glossary-cold-storage]*: Use the cold storage tool to keep a percentage of user balances online, and store the rest safely in an offline wallet for added security.

If you plan to carry out bank transfers on behalf of your users, check out the *[Fiat Withdrawals][glossary-fiat-withdrawals]* and *[Fiat Deposits][glossary-fiat-deposits]* tools.

## Cold Storage {#cold-storage}

To display the cold storage tool, go to: _Tools_ &rarr; _Cold Storage_.

### What is Cold Storage?

When users hold cryptocurrencies on your site, these funds are stored on the hot wallets. The plugin uses these hot wallets to communicate with the blockchains: to detect user deposits, and to perform user withdrawals.

If a malicious hacker gains access to your WordPress server, they can steal all your user funds.

This is why you must:

- keep your server secure (hardened OS, hardened WordPress, updated plugins, etc.), and
- keep part of user funds in an offline wallet or wallets. This is your Cold storage.

> What percentage of user funds should be kept online will vary depending on your application.

The tool helps you perform admin withdrawals to cold storage devices. These do not show as transactions, as they affect the hot wallet balance (i.e. available to the site for withdrawals), not any user balances.

For more information on the concept of Cold Storage see [Investopedia][investopedia-cs].

> **TIP**: You should get a hardware wallet to use for cold storage. A [Trezor][trezor] or [Ledger Wallet][ledger] will do nicely!


### Using Cold Storage to secure your site

For every currency you have installed, the table will show you the following information:

- *[Adapter type][glossary-adapter-type]* - The type of wallet adapter used for this currency, such as Bitcoin-like, Monero-like, etc.
- *[Currency][glossary-currency]* - The currency, duh!
- *[Hot Wallet balance][glossary-hot-wallet-balance]* - How many coins you have in your hot wallet.
- *[User balances][glossary-user-balance]* - The total of all your users' balances.
- *% of user balances backed by hot wallet* - Horizontal bar showing the percentage of the *[Sum of User Balances][glossary-sum-of-user-balances]* that is currently backed by the hot wallet balance. 100% means that all the user funds are in the hot wallet.

For each currency, the tool will let you *[Deposit][glossary-deposit]* and *Withdraw* funds to and from your cold storage wallet.

You can now target to keep a certain percent in the hot wallet at all times. Click the _Withdraw_ button, and send the rest of the coins to an offline wallet. All the admins will be notified about the outcome of this transaction by email.

Later, you can deposit the funds again to the site's hot wallet. Just click on the _Deposit_ button and you will get an address from your hot wallet that is suitable for cold storage deposits. Do not just deposit the funds into any address on your hot wallet, because user deposit addresses will count towards balances. The deposit address that you see in the Cold Storage tool is one you can be sure that it's not a user deposit address.

> **TIP**: These deposits/withdrawal are not recorded in the plugin's ledger. They do not affect any user balances. They only affect the hot wallet balance. **If your site gets hacked, all of the coins in your hot wallets are at risk of theft**. All coins in cold storage are safe.

You should keep in the hot wallet more money than you expect your users to withdraw all at once. If there are not enough funds online, user-requested withdrawals will fail and you will likely need to explain this to your users!

Any administrator with the `manage_wallets` capability has access to that page. Keep in mind that you need to wait for transactions to confirm before they affect your balance.

> **TIP**: If you are using the CoinPayments adapter, withdrawals might take a few minutes to execute. For most full node wallets they execute instantly.


## Fiat Deposits {#fiat-deposits}

You may display deposit information for your site using the `[wallets_fiat_deposit]` shortcode. The UI lets the users pick the fiat currency they want to deposit. The UI displays the following information for each fiat currency:

- Bank name and address
- Bank deposit details (for banks in USA):
  - Bank name and address
  - ABA Routing Number
  - Account Number
- Bank deposit details (for banks in India):
  - IFSC
  - Account number
- Bank deposit details (for banks in Africa):
  - Bank name and address
  - SWIFT-BIC
  - Account Number
- Bank deposit details (for banks in Europe):
  - Bank name and address
  - SWIFT-BIC
  - Account IBAN
- A *[deposit code][glossary-deposit-code]* unique to the user

The user uses this information to transfer funds to the site's bank account. The user must attach the deposit code as a note to the receiver.

When an admin notices the incoming transaction to the bank account, this tool help to enter the deposit record to the plugin.

1. Go to _Tools_ &rarr; _Fiat Deposits_.
2. Hit _Create_.
3. Enter the following details about the transaction:
	1. User ID (must be supplied in bank transfer comment)
	2. Bank TXID (as supplied by the bank)
	3. Fiat Currency
	4. Amount
	5. Sender name and address
	6. Bank name and address
	7. Bank addressing method (Can be one of: *[SWIFT-BIC and IBAN][glossary-swift-bic-and-iban]*, *[ABA Routing number and Account number][glossary-aba]*, *[IFSC and Account number][glossary-ifsc-and-account-number]*.
	8. Account number, where there are extra fields to specify the account. The naming of the fields depends on which bank addressing method is selected.
4. Comment (A free text that is attached to the transaction.)
5. Status (Can be set to: *[Done][glossary-done]*, *[Pending][glossary-pending]*, *[Failed][glossary-failed]*, *[Cancelled][glossary-cancelled]*.)

After entering all the details, hit the _Create_ button and a deposit transaction will be created for that user and currency.

Once you create the deposit, the user will receive an *email notification* about the transaction.

To modify the content of this email notification, override the template files: `wp-content/plugins/wallets/templates/email-fiat_deposit_*_sender.php`. Remember than in emails you can only use very simple HTML. See the *Frontend* chapter of this documentation on information on how to safely override Templates.

If the admin has set the deposit transaction to the *[Done][glossary-done]* status, then the user balance will increase by the transaction's amount. If the transaction was set to any other status (*[Failed][glossary-failed]*, *[Cancelled][glossary-cancelled]*, *[Pending][glossary-pending]*), it does not affect the user balance. For example, you can create a deposit where some information is still missing, and set it to *[Pending][glossary-pending]*. Later, you can edit the transaction, add the missing information, and set it to *[Done][glossary-done]*. The user is notified when the transaction is first saved or changes status.

Once you have created deposit transactions, these are listed in the *[Fiat Deposits][glossary-fiat-deposits]* tool. Use the tool to quickly see the status of the latest Bank Fiat Deposits, or to edit transaction details in case of errors.


## Fiat Withdrawals {#fiat-withdrawals}

A user may initiate a fiat withdrawal via the UI of the `[wallets_fiat_withdraw]` shortcode.

If the user has performed other Fiat Withdrawals, the details of the last such Withdrawal for each currency is filled in.

The user first creates the fiat withdrawal, which is in a *[Pending][glossary-pending]* state. The user receives an email notification about the pending withdrawal. If confirmations are required, the user gets a confirmation link in the email.

All the admins with a `manage_wallets` capability are notified by email about the fiat withdrawal request. This way, an admin can visit this tool and process the withdrawal request.

1. Go to _Tools_ &rarr; _Fiat Withdrawals_. You will see a list of all withdrawals for fiat currencies.
2. From the list of *[Fiat Withdrawals][glossary-fiat-withdrawals]*, select a withdrawal that is in a Pending state. Click *Modify* on the withdrawal's row.
3. You will see the user-supplied details of this withdrawal request. You can use the displayed information to perform the transaction with your bank.
4. You can edit the transaction in the following ways:
   1. Add/edit a TXID. (This can correspond with the unique ID that your bank assigned to the transaction.)
   2. Status (Can be set to: *[Done][glossary-done]*, *[Pending][glossary-pending]*, *[Failed][glossary-failed]*, *[Cancelled][glossary-cancelled]*.)
   3. Error message (If setting the withdrawal to "failed", optionally enter an error message for the user.)
5. Once you enter your changes, hit the _Update_ button.

If the status of the transaction was changed, the user will be notified about any changes by email.


[trezor]: https://shop.trezor.io?a=dashed-slug.net
[ledger]: https://www.ledgerwallet.com/r/fd5d
[investopedia-cs]: https://www.investopedia.com/terms/c/cold-storage.asp
