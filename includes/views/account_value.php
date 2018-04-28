<?php defined( 'ABSPATH' ) || die( '-1' ); // don't load directly ?>

<div class="dashed-slug-wallets account-value">
	<?php
		do_action( 'wallets_ui_before' );
		do_action( 'wallets_ui_before_account_value' );
	?>
	<label class="account-value"><?php echo apply_filters( 'wallets_ui_text_account_value', esc_html__( 'Account value', 'wallets-front' ) ); ?>: <span class="base-amount" data-bind="text: accountValue" ></span></label>
	<?php
		do_action( 'wallets_ui_after_account_value' );
		do_action( 'wallets_ui_after' );
	?>
</div>
