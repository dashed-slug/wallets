# Frequently Asked Questions


## The Bitcoin and Altcoin Wallets plugin


### Where is the plugin's documentation?

Since version `6.0.0`, the plugin displays its own documentation in the admin screens. Just go to the Wallets Admin Docs menu, where you'll find the documentation for the plugin, and for any plugin extensions you have installed.

Developers can study the PHPdocumentor pages at: https://wallets-phpdoc.dashed-slug.net/

### How secure is it?

When users issue transactions, these can require verification via an email link by the user. Additionally, you can require that an admin also verifies each transaction. (See "Confirmations" in the documentation).

Of course, the plugin is only as secure as your WordPress installation is.

You should take extra time to secure your WordPress installation, because it will have access to your hot wallets. At a minimum you should do the following:

- Install a security plugin such as [Wordfence](https://infinitewp.com/addons/wordfence/).
- Read the Codex resources on [Hardening WordPress](https://codex.wordpress.org/Hardening_WordPress).
- If you are connecting to an RPC API on a different machine than that of your WordPress server over an untrusted network, tunnel your connection via `ssh` or `stunnel`. [See here](https://en.bitcoin.it/wiki/Enabling_SSL_on_original_client_daemon).

Some more ideas:
- Add a user auditing tool such as [Simple History](https://wordpress.org/plugins/simple-history/).
- Add a CAPTCHA plugin to your login pages.
- Only keep up to a small percentage of the funds in your hot wallet (See *[Cold Storage][glossary-cold-storage]* in the documentation).

### I am installing a bitcoin full node on my server. How can I run it as a service so that it is always running?

This will depend on the Linux distribution on your server.

To setup bitcoin core as a service on [systemd](https://en.wikipedia.org/wiki/Systemd) I used [this guide](https://medium.com/@benmorel/creating-a-linux-service-with-systemd-611b5c8b91d6).

Here is my `/etc/systemd/system/bitcoin.service` file:

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

You will need to edit the user name to match yours, and possibly the path to your bitcoind binary.

Follow the article to set the service to run automatically on system restart. You should never have to enter the bitcoin command directly in the shell. Always let the system start it automatically as a service.

After that, you must check with your hosting provider (the provider who supplies the server for your bitcoin daemon) to see if there are any firewalls, blocking incoming communication with TCP port `8332`. Also check any local firewalls that you may be running, such as `ufw`.

## Functionality questions

### Can I control which users have a wallet?

Yes, simply assign the `has_wallets` capability to the appropriate users or user roles. You should also assign more capabilities, such as `list_wallet_transactions`, `send_funds_to_user`, and `withdraw_funds_from_wallet`.

You can control the capabilities per user role by navigating to: _Settings_ &rarr; _Bitcoin and Altcoin Wallets_ &rarr; _Capabilities_.

### Can I use the plugin to create an investment / interest paying site?

Yes, you can use the premium *Airdrop extension* to perform _recurring airdrops_. These can effectively be paid out in the form of an interest on the user's wallet.

### Can I use the plugin to create a WooCommerce store that accepts cryptocurrencies?

Yes, you can use the premium *WooCommerce Cryptocurrency Payment Gateway extension*. With it, users can use their on-site balance to checkout their shopping carts.

### Can I use the plugin to create a crypto faucet?

Yes, you can use the premium *Faucet extension* to let users earn crypto by solving CAPTCHAs.

### Can I use the plugin to create a crypto exchange?

Yes, you can use the premium *Exchange extension* to create market pairs. However the markets are local only, which means that no liquidity is imported from other exchanges. Read the disclaimers.

### Can I use the plugin to do a token sale?

No, this plugin is not suitable for token sales.

However, you could, of course, setup markets using the *Exchange extension*, and set a large limit sell order with your admin account. This will allow users to buy your shillcoin. You can even disable buying or selling separately.

### Can I use the plugin to accept tips for articles?

Yes, you can use the premium *Tip the author extension*. This lets you attach a tipping UI to posts/articles. You can control where the tipping UI is shown, by post type, category, tags, or even author. Only authors with the `receive_tip` capability can receive tips, and only users with the `tip_the_author` capability can send tips.

### Can I use the plugin to create a paywall?

Not yet. However, a premium extension to let you do just that is currently in development.

## Advanced topics / development

### How can I change the plugin's code?

The plugin and its extensions are yours to edit. You are free to hack them as much as you like. However, you are generally discouraged from doing so, for the following reasons:

- I cannot provide support to modified versions of the plugin. Editing the code can have unintended consequences.

- If you do any modifications to the code, any subsequent update will overwrite your changes. Therefore, it is [not recommended](https://iandunn.name/2014/01/10/the-right-way-to-customize-a-wordpress-plugin/) to simply fire away your favorite editor and hack away themes or plugins.

Whenever possible, use an existing hook ([action](https://developer.wordpress.org/reference/functions/add_action/) or [filter](https://developer.wordpress.org/reference/functions/add_filter/)) to modify the behavior of the plugin. Then, add your code to a child theme, or in separate plugin file. Any PHP file with the [right headers](https://codex.wordpress.org/File_Header) is a valid plugin file.

If you can’t find a hook that allows you to do the modifications you need, contact me to discuss about your need. I may be able to add a hook to the next patch of the plugin.

### Why is my CSS not being applied to the UI elements?

This is usually due to the plugin's CSS rules hiding your own rules. Don't just spam `!important`, instead take the time to study [CSS rule specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/Specificity). If you are unsure how to apply a particular rule on the plugin's UIs, you can contact me.

### How can I change something in the UI elements?

There are several ways to do this, depending on what type of change you want to apply. Please check the documentation under _Frontend and Shortcodes_ &rarr; _Modifying the UI appearance_.

### How can I perform transactions from my PHP code?

There are two ways to do this:

1. You can create `DSWallets\Transaction` objects, populate all the fields, then save the obects to the DB. For details, check the documentation under: _Developer reference_ &rarr; _Working with custom post type objects_, and the PHPDocumentor page for the Transaction class for example code.

2. You can use the Legacy PHP-API. This is compatible with previous versions of the plugin.

### How can I perform transactions from the JavaScript frontend?

There are two ways to do this:

1. You can use the *[WP-REST API][glossary-wp-rest-api]*. Consult the documentation under: _Developer reference_ &rarr; _Wallet APIs_ &rarr; _WP-REST-API_.

### How can I change the wallet backing a particular currency?

It is possible that, for a particular cryptocurrency you may want to replace the wallet backing it with another wallet. For example, you may be offering Bitcoin via the CoinPayments service, and want to start using a Bitcoin core full node wallet. Or you may be using Bitcoin core, and you want to move to a new `wallet.dat` file.

This has become a lot easier with versions `6.0.0` and later, because *[Currencies][glossary-currency]* and *[Wallets][glossary-wallet]* are now decoupled:

1. Create the new *[Wallet][glossary-wallet]* with the built-in Bitcoin adapter for full nodes. Connect to your new full node wallet.
2. Edit your *[Currency][glossary-currency]*, in this case, _Bitcoin_. Set the _Wallet_ to your new full node wallet entry, and _Update_ the Currency.
3. Transfer the hot wallet balance from one wallet to the other. Transfer all the funds to an address generated by new *Hot Wallet*. The address must NOT be a deposit address assigned to a user. For example, you can use the deposit address shown in the *[Cold Storage][glossary-cold-storage]* tool for your new wallet.
4. Delete all the old deposit addresses for that currency. This will force the plugin to generate new deposit addresses from the newly connected wallet.
5. Inform your users that they must no longer use the old deposit addresses, if they have them saved somewhere.

If unsure about this process, please contact me.


### How does the plugin work in multisite installations?

How it works depends on whether the plugin (and its extensions) are network-activated or not. In network-activated setups, users have one balance ber currency, across all sites the network. If the plugin is NOT network-activated, users have a different balance on each site on the network, and each site can have different currencies and wallets.

Note that the plugin and its extensions MUST either all be network activated, OR all must be activated on individual blogs. Do not mix-and-match network-activated and non-network-activated wallets plugins.

Consult the documentation section _Multisite_ for more information.


### How to handle a hack/cyberattack?

While the latest WordPress version is often secure, the same cannot be said about all the WordPress plugins out there. Every day new security vulnerabilities are found involving WordPress plugins. Since WordPress is such a popular software platform, it gets a lot of attention from hackers.

Take an immediate backup of the site, and the server it runs on, if possible. This will preserve any traces that the hackers may have left behind. Funds theft is a crime and you can report it to the police, just like any other hack.

It's best if you are prepared beforehand: Keep the software on the site updated regularly. Take the time to harden your server and WordPress installation. Try to use only reputable plugins on your site and keep them updated.  Use a security plugin.

Finally, only keep a small percentage of the user balances on the hot wallet, utilizing the _Cold storage_ feature to transfer the remaining funds to offline wallets. That way, in case WordPress is compromised, you don't lose all your users' funds! Please take wallet management seriously. There is no software that will automatically do opsec for you. Have a plan on how to act in case of theft.

If you think you have discovered a security vulnerability in this plugin, please let me know over email (not on a public forum).


## Membership questions

### How can I become a premium member and get access to the app extensions?

Paying members can download the available *[App extensions][glossary-app-extension]* and can download updates to those extensions.

Study the available [Membership plans](https://www.dashed-slug.net/dashed-slug/membership-plans/)


### What payment methods are available

The site accepts Bitcoin and Ethereum. Please [deposit the correct amount to your account, then choose a subscription](https://www.dashed-slug.net/dashed-slug/membership-plans/).

[Since 1 November 2022](https://www.dashed-slug.net/important-changes-to-membership/),
membership is implemented using the plugin itself. The downloads are protected behind a paywall
using the [Paywall extension](https://www.dashed-slug.net/bitcoin-altcoin-wallets-wordpress-plugin/paywall-extension/).

Previously the site accepted PayPal recurring payments. If you have already signed up using PayPal, you can continue to use it to pay for membership. New PayPal accounts are no longer available.

If you wish to pay via a different method, [contact me by email](https://www.dashed-slug.net/contact/).

You can send a PayPal payment to my business email address and let me know. I will then activate your membership manually, within 1 business day.

### I cannot download the premium plugins after paying for membership.

If you have paid for an EU business plan, you must provide a valid VATIN. Please enter the VATIN without the country code prefix, and enter the correct _City_ and _Country_ for your business, in your profile details.

If you have paid for a regular plan, and for some reason you still cannot download the premium plugins, please [contact me by email](https://www.dashed-slug.net/contact/).

### How can I cancel my membership?

If you have signed up with a PayPal recurring payment, you can go to your [PayPal dashboard](https://www.paypal.com/cgi-bin/webscr?cmd=_manage-paylist) to cancel the recurring payment.

Additionally, if you wish, you may delete your account from the profile screen on the dashed-slug website. Deleting your account does not automatically cancel your PayPal subscription. Simply visit [your profile](https://www.dashed-slug.net/membership-login/membership-profile/)

If you have paid via cryptocurrencies, there is no need to cancel. You can delete your account if you want, by visiting [your profile](https://www.dashed-slug.net/membership-login/membership-profile/). There is usually no need to do so.

### I am not happy with the plugin. Can I ask for a refund?

You can ask for a refund of any payment within 30 days from the day of payment, no questions asked. Please contact me by email.


## Contacting / feedback


### Are you available for custom development work?

Unfortunately I am not available for custom development work.


### Can you install the plugin for me?
I do not undertake installations. I remain available to assist and answer any questions about the installation and configuration process.

> Regarding plugin installations, please consider this: Unless you know how the plugin works, you will not be able to provide support to your users, or fix issues when these arise. If you are not a developer, you should probably hire a developer to perform the installation and maintenance.


### Can you add XYZ feature?

You can always suggest a feature to me. If it makes sense and I have the time, I might implement it. I do not make promises on this, therefore I do not accept payment for features.


### I am encountering some problem or I have another question.

First check the *Troubleshooting* section of the documentation. The answer to your question may be listed there.

If you cannot find the answer to your question, please consult the documentation under _Contact Support_.


### How can I reach you over IM?

I speak daily with many people, while I also do the development, testing, management, marketing, and everything else.

For this reason, I am NOT reachable over chat apps.

Please state your request on the forums or over email, and I will respond within 24 hours, Monday to Friday. If you are encountering an error, please show me the error message in a screenshot. Try to explain what you did so far and how you arrived at the error.
