<?php defined( 'ABSPATH' ) || die( -1 ); // don't load directly ?>

<div class="dashed-slug-wallets account-value" data-bind="css: { 'wallets-ready': !coinsDirty() }">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_account_value' );
	?>
	<span class="wallets-reload-button" title="<?php echo apply_filters( 'wallets_ui_text_reload', esc_attr__( 'Reload data from server', 'wallets-front' ) ); ?>" data-bind="click: function() { coinsDirty( false ); if ( 'object' == typeof ko.tasks ) ko.tasks.runEarly(); coinsDirty( true ); }"></span>
	<label class="account-value"><?php echo apply_filters( 'wallets_ui_text_account_value', esc_html__( 'Account value', 'wallets-front' ) ); ?>: <span class="fiat-amount" data-bind="text: accountValue" ></span></label>
	<?php
		do_action( 'wallets_ui_after_account_value' );
		do_action( 'wallets_ui_after' );
	?>
</div>
