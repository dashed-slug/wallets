<?php

// Localize the frontend knockout script
$translation_array = array(
	'submit_tx' => __( 'Successfully submitted a transaction request of %s %s', 'wallets-front' ),
	'submit_wd' => __( 'Successfully submitted a withdrawal request of %s %s to %s', 'wallets-front' ),
	'op_failed' => __( 'Wallet operation failed due to an unexpected error', 'wallets-front' ),
	'op_failed_msg' => __( 'Wallet operation failed: %s', 'wallets-front' ),
	'contact_fail' => __( 'Could not contact server. Status: %s Error: %s', 'wallets-front' ),
	'invalid_add' => __( 'Check to see if you have typed the address correctly!', 'wallets-front' ),
);
wp_localize_script( 'wallets_ko', 'wallets_ko_i18n', $translation_array );
