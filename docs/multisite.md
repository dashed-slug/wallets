# Multisite

The plugin can operate in two modes:

## Network activated

When network activated, user wallets exist across all sites on a multisite network. To learn more about WordPress Networks, see *[Create a network][wp-create-network]* on WordPress.org.

For this to work, ensure that you have network-activated the parent plugin AND any *[App extensions][glossary-app-extension]* and *[Wallet adapter extensions][glossary-wallet-adapter-extension]*.

> **NOTE**: When the plugin is network-activated, all custom posts, including *[Wallets][glossary-wallet]*, *Tranactions*, *[Addresses][glossary-address]* and *[Currencies][glossary-currency]* are saved on the first blog of the current network. Thus the posts can be found from any blog of the network.

## Not Network-activated

When activated on single site installations, or when activated independently on single sites of a network, user wallets exist only on the site where the plugin was activated. If the user visits other sites on the network that also have the plugin activated, the user will have different wallets there.

For this to work, ensure that you have not network-activated either the parent plugin OR any app extensions and wallet adapter extensions.

[wp-create-network]: https://wordpress.org/support/article/create-a-network/ "Create a Network"


