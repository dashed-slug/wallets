# Plugin features

> NOTE: In the following requirements document, *[Admins][glossary-admins]* are WordPress users with the `manage_wallets` capability, and *users* are WordPress users with the `has_wallets` capability.

- Admins can create and edit the following custom post types: *[Wallet][glossary-wallet]*, *[Currency][glossary-currency]*, *[Address][glossary-address]*, *[Transaction][glossary-transaction]*.
    - Can create/edit one or several *[Wallets][glossary-wallet]*:
        - Edit the wallet name.
        - Enable/disable wallet.
        - Assign wallet to a *[wallet adapter][glossary-wallet-adapter]* depending on the wallet type.
        - Edit wallet adapter settings (e.g. Authentication, IP, Port, etc).
        - Quickly view the connection status of each wallet.
        - Quickly view any other relevant data, depending on the wallet adapter.

    - Can create/edit *[Currencies][glossary-currency]*:
        - Set the name, icon, number of decimals, and ticker symbol
        - Set the preferred display of amounts (as an `sprintf` pattern)
        - Set withdrawal fees
        - Set fees for internal transfers between users.
        - Set the exchange rates of a currency, either automatically or manually:
            - With a CoinGecko currency ID, so that the rates are always updated
            - With static exchange rate values, useful for custom currencies.
        - Set links to block explorers. These are used when addresses/transactions are displayed. Some links to common currencies are provided.
        - Set minimum/maximum limits for withdrawals.
            - Can also set limits per WordPress user role.
        - Group currencies into tags using a special Currencies taxonomy.
        - Quickly navigate from a currency to a list of associated Addresses/Transactions
        - The currencies can represent cryptocurrencies, fiat currencies, or other custom currencies that are internal to the site, like debits.
        - Once there exist transactions associated with a currency, its "Decimals" field becomes uneditable. This is done to prevent serious errors in interpreting integer amounts stored on the DB.

    - Can create/edit *[Addresses][glossary-address]*:
        - Can set a short label
        - Are assigned to a user
        - Can group Addresses into tags using a special Addresses taxonomy.
        - For deposits/withdrawals:
            - Can edit the address string
            - Depending on wallet adapter, may display the extra field, appropriately named depending on the wallet adapter (Payment ID, Destination Tag, etc.)
        - For fiat deposits/withdrawals:
            - Can edit user mail address, bank mail address, etc.

    - Can create/edit *[Transactions][glossary-transaction]*:
        - Edit a short title, used as the transaction comment
        - Change the transaction status
        - Assign to a user
        - Assign to a currency
        - Edit the transacted amount and fees paid
        - For deposits/withdrawals
            - Edit the address that this transaction is associated with
        - For internal transfers
            - Assign a counterpart transaction (for credit/debit pairs)
        - Group transactions into tags using a special Transactions taxonomy.

     - Can modify the frontend UIs
         - Using the Customizer.
         - Using WordPress filters to modify the UI texts.
         - Using WordPress actions to add header / footer markup to UIs.
         - By overriding the built-in templates in a theme or child theme.

     - Can use a cold storage solution
         - Monitor percentage of funds on hot wallet vs total user balances
         - Transfer funds to and from cold storage


- Admins can view the Bitcoin and Altcoin Wallets panel in the dashboard
    - Statistics on wallets, and recent transactions and addresses.
    - Tag cloud for transactions taxonomy.
    - Debug information on WordPress, the host system, and the plugin.

- User profiles displays wallet information.
    - Link to all user addresses.
    - Link to all user transactions.
    - Summary of user's total and available balances, per currency.
    - User's API key for the deprecated JSON-API
    - Admins can view this information for all users, not just their self.

- Users can view info about their crypto and perform transactions on the site's frontend:

    - Deposit cryptocurrencies
        - Users can find their deposit addresses using the UI of the `[wallets_deposit]` shortcode, or create a new deposit address.
        - Users are notified by email about their deposit status.
            - These emails are HTML/PHP templates that you can override in your theme or child theme.

    - Withdraw cryptocurrencies
        - Users can request to withdraw their cryptocurrencies using the UI of the `[wallets_withdraw]` shortcode.
            - Subject to capability restriction.
            - Users can approve withdrawal via link in their email if enabled by admin.
                - These emails are HTML/PHP templates that you can override in your theme or child theme.
        - Users are notified by email about their withdrawal status.
            - These emails are HTML/PHP templates that you can override in your theme or child theme.

    - Deposit fiat currencies
        - Users can find their bank deposit details using the UI of the `[wallets_fiat_deposit]` shortcode.
            - Subject to capability restriction.
        - Admins can process bank transactions manually, via _Tools_ &rarr; _Fiat Deposits_.
        - Users are notified by email about their deposit status.
            - These emails are HTML/PHP templates that you can override in your theme or child theme.

    - Withdraw fiat currencies
        - Users can request to withdraw their fiat currencies to a bank account, using the UI of the `[wallets_fiat_withdraw]` shortcode.
            - Subject to capability restriction.
            - Users are notified by email about their withdrawal status.
                - These emails are HTML/PHP templates that you can override in your theme or child theme.
       - Admins can process bank withdrawals manually, via _Tools_ &rarr; _Fiat Withdrawals_.

    - Transfer funds to other users
        - Users can request to transfer funds of any currency they hold to another user.
            - Subject to capability restriction.
            - Users can approve withdrawal via link in their email if enabled by admin
                - These emails are HTML/PHP templates that you can override in your theme or child theme

    - View balances
        - Users can check their balances using the `[wallets_balance]` shortcode.
            - All amounts are also shown in equivalent amounts in one or more well-known currencies, e.g.: USD, EUR, BTC, ETH, etc.
                - User can click on this amount to rotate between well-known currencies
        - Users can check the total account value using the `[wallets_account_value]` shortcode.

    - View transactions
        - Users can view their past or pending transactions for a particular currency.
          - Subject to capability restriction.
          - Transactions can be shown tabulated and paginated using the `[wallets_transactions]` shortcode.
            - The admin can use atttibutes to choose the rows per page.
            - The admin can use atttibutes to choose which columns are shown.
            - The transaction rows are color-coded based on their status.
            - The admin can use attributes to filter the displayed transactions by category (Deposit, Withdraw Move), tags (`wallets_txs_tags` taxonomy), currency, or other user.
          - The admin can choose to have the rows rendered without HTML table using the `[wallets_transactions template="rows"]` shortcode.

    - View exchange rates
        - Users can check the exchange rates of currencies using the `[wallets_rates]` shortcode.
          - Exchange rates are shown for the currencies against any one of the CoinGecko "VS Currencies".
          - The admin can use attributes to specify the number of displayed decimals in exchange rates.
          - The user can click on the exchange rates to rotate the "VS Currencies".

    - View wallet statuses
        - Users can check whether the wallet is online for each of the available currencies using the `[wallets_status]` shortcode.
