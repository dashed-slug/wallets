<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

<form class="dashed-slug-wallets api-key" onsubmit="return false;" data-bind="css: { 'wallets-ready': ! noncesDirty() && ajaxSemaphore() < 1 }">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_wallets_api_key' );
	?>
	<span
		class="wallets-reload-button"
		title="<?php echo apply_filters( 'wallets_ui_text_reload', esc_attr__( 'Reload data from server', 'wallets-front' ) ); ?>"
		data-bind="click: function() { noncesDirty( false ); ko.tasks.runEarly(); noncesDirty( true ); }">
	</span>

	<label class="apikey"><?php echo apply_filters( 'wallets_ui_text_apikey', esc_html__( 'Wallets API key', 'wallets-front' ) ); ?>:
		<span class="wallets-clipboard-copy" onClick="jQuery(this).next()[0].select();document.execCommand('copy');" title="<?php echo apply_filters( 'wallets_ui_text_copy_to_clipboard', esc_html__( 'Copy to clipboard', 'wallets-front' ) ); ?>">&#x1F4CB;</span>
		<input type="text" readonly="readonly" onClick="this.select();" data-bind="value: nonces().api_key" />
	</label>

	<input
		type="button"
		data-bind="click: doResetApikey, disable: noncesDirty() || ajaxSemaphore() > 0"
		value="&#8635; <?php echo apply_filters( 'wallets_ui_text_renew', esc_attr__( 'Renew', 'wallets-front' ) ); ?>" />

	<?php
		do_action( 'wallets_ui_after_wallets_api_key' );
		do_action( 'wallets_ui_after' );
	?>
</form>
