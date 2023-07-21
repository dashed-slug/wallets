# Migrating from 5.x.x

## What?
Moving from *[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]* versions `5.x` to `6.0.0` requires a data migration process.

**If you're not interested in the details, skip to *TL;DR* below.**

## Why?
Older versions of the plugin kept transaction data and address data on custom SQL tables `wp_wallets_txs` and `wp_wallets_adds`.

From version `6.0.0` onwards, such data is stored as custom posts. There are numerous advantages to the new way of storing data:
- Amounts are now stored as integers to avoid rounding errors.
- Currencies are now completely decoupled from Wallets.
- Wallets, Currencies, Addresses and Transactions can be exported and re-imported using standard tools.
- Wallets, Currencies, Addresses and Transactions can be easily created, edited and deleted by admins.
- Currencies, Addresses and Transactions can be organized using custom taxonomies.

The plugin now features the following post types: *[Wallet][glossary-wallet]*, *[Currency][glossary-currency]*, *[Address][glossary-address]*, *[Transaction][glossary-transaction]*. Addresses and transactions need to be copied from the `wp_wallets_txs` and `wp_wallets_adds` tables. Wallets need to be recreated by the admins. Currencies can usually be inferred from the ticker symbols in Addresses and Transactions, but should be reviewed by an admin.

## How?

IF the old SQL tables exist, and as long as they have not been migrated, you will see a notice at the top of the screen when you log in with your admin account (requires the `manage_wallets` capability).

The notice will tell you to go to the *Migration tool* at _Tools_ &rarr; _Wallets Migration_ and initiate the migration process. You can also use the tool to monitor progress, or revert the migration process.

The migration process is a cron task. It will run only as fast as your other cron tasks. If you are on a site with little traffic, you should enable external cron triggering for better performance.

> To enable external cron triggering, you must set `define( 'DISABLE_WP_CRON', true );` in your `wp-config.php`, and trigger the following URL manually, using a UNIX cron job: `http://example.com/wp-cron.php` where example.com is your domain name.

No data is lost during migration - data is only copied from the existing custom SQL tables into new Custom Post Types (CPTs). If something goes wrong, you can always revert the process and try again.

The frontend APIs (WP-REST, JSON-API) will not be available while a migration task is running. This is to keep the ledger consistent. You may wish to notify your users about this. A migration can last a few minutes to a few hours if you have a lot of DB data!

## Which one?

You can choose to migrate all transactions, or only user balances:

### Addresses and Transactions

If you choose to migrate all transactions, then each transaction row in wp_wallets_txs will be transferred to the new ledger as a new post of type `wallets_tx`. In one cron job run, only a few rows can be transferred. Each run can occur no sooner than once per minute. If you have many users with many transactions, this can take hours.

### Addresses and Balances only

If you choose to migrate the balances only, then for each user and each coin with non-zero balance, one transaction will be recorded. The user history will not be transferred, but all the user balances will be migrated correctly, and this can be done a lot faster. On each cron job run, once per minute, the balances of a few coins for one user can be transferred. Unless you have many coins and users, this will run in minutes to hours at most, even with many transactions on the ledger. This is because all transactions per user and coin are summed into one balance transfer.

When an admin initiates a balances-only migration, the setting _Deposit cutoff timestamp_ is set. See the _Settings_ section for details.

### Revert

At any time the migration process creates *[Currencies][glossary-currency]*, *[Addresses][glossary-address]* or *[Transactions][glossary-transaction]*, it attaches the `migrated` tag to them. If you choose to revert a migration, the cron task will start to delete all such entities. It will take a few runs, but once finished, you will be able to initiate a migration process again. This is possible because the custom SQL tables remain unchanged.

## Logs?

If you want detailed logging:
1. [enable logging](https://wordpress.org/support/article/debugging-in-wordpress/) in your WordPress installation
2. go to _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Cron tasks_
3. enable the option _Verbose logging_
4. click _Save Changes_

You can now see monitor the progress in detail in `wp-content/debug.log`.


## TL;DR

- If something goes wrong, you can always install *[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]* `5.x` again. The old data is not deleted.

- Migration takes time. You can choose between migrating all the transactions (slow) or only the balance sums (faster). Please be patient.

- Admins can watch the progress in the admin screens and via email reports.

- Admins can enable WordPress debug logs and the plugin's verbose logging setting to monitor the migration process in full detail.

- WP-Cron must be running. (Need site traffic or external trigger).

- While migration is running, users cannot access their wallets.

- When migration is finished, the site admins will be notified by email.

- When migration is finished, verify that the balance sums have been transferred. To save space, you can drop the two tables `wp_wallets_adds` and `wp_wallets_txs` via your SQL console. (But keep a backup just in case!!!)

# Migration Rollback: if something goes wrong!

If at any time during the migration process you are not happy, you can start a rollback. This will start a cron job that, on every run, will delete or trash (depending on your WordPress settings), a bunch of newly created Currencies, Addresses and Transactions. These are the ones having the `migrated` tag. When the rollback finishes, you can start another migration.

# Plugin downgrade: if something goes even wronger!

If for some reason you can't get *[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]* version `6.0.0` to work for you at all, you can do the following:

1. Deactivate all dashed-slug plugins.
2. Re-install the latest `5.x` version of *[Bitcoin and Altcoin Wallets][glossary-bitcoin-and-altcoin-wallets]*.
3. Re-install the previous versions of all extensions:
   - *Exchange extension* version `1.3.9`
   - *Airdrop extension* version `2.1.3`
   - *Faucet extension* version `1.8.0`
   - *Tip the Author extension* version `2.2.0`
   - *WooCommerce Cryptocurrency Payment Gateway extension* version `2.3.4`
   - *CoinPayments Adapter extension* version `1.2.1`
   - *Full Node Multi Coin Adapter extension* version `1.0.7`
   - *Fiat Coin Adapter extension* version `0.6.3-beta`
   - *Monero Coin Adapter extension* version `1.1.4`
   - *TurtleCoin Adapter extension* version `0.1.5-beta`
4. Activate the parent plugin and its extensions.

At this point your Addresses and Transactions should be same as before.

No SQL table data is ever deleted by the migration process, unless you delete it manually:


# Cleaning old data: if all goes as planned!

Once you have verified that:
- User balances are the same as before,
- All data has been migrated, including Wallets, Currencies, Addresses and Transactions.
- All of your website functionality still works as expected.

You can now (backup and) delete the tables `wp_wallets_txs` and `wp_wallets_adds`. This will save some space on your web server's DB.

## Backup old tables using shell

We'll assume here that your WordPress DB prefix is the default, `wp_`.

> **TIP**: Unsure about what DB table prefix your WordPress uses? Check _Dashboard_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Debug_ &rarr; _MySQL DB prefix_.

To export the old custom MySQL tables, `wp_wallets_txs` and `wp_wallets_adds` into a compressed SQL file:

	mysqldump -u... -p... wordpress_db wp_wallets_txs wp_wallets_adds | gzip -9 >my_wallets_5_tables.sql.gz

(Replace `...` with your credentials and `wordpress_db` with the actual name of your WordPress MySQL database.)

> **TIP**: Unsure about which database your WordPress uses? Check _Dashboard_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Debug_ &rarr; _MySQL DB name_.

You can also use *PHPMyAdmin* to export the two tables.

## Wiping old tables using MySQL console

Once the tables are backed, up you can wipe the old MySQL custom tables.

To wipe the tables, do the following in the MySQL console:

	DROP TABLE wp_wallets_txs;
	DROP TABLE wp_wallets_adds;

> **WARNING: Only drop these tables once you are certain that your new system is running correctly, and after you have backed up the table data (see above).**

You can also use *PHPMyAdmin* to drop the two tables.

## Re-importing old tables using shell

Supposing you have already dropped the two tables, `wp_wallets_txs` and `wp_wallets_adds` and you need to restore them from the backup that you took above:

	zcat my_wallets_5_tables.sql.gz | mysql -u... -p... wordpress_db

(Replace `...` with your credentials and `wordpress_db` with the actual name of your WordPress MySQL database.)


You can also use *PHPMyAdmin* to re-import the two tables.

