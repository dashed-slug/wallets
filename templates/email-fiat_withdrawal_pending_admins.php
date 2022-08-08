<?php namespace DSWallets;

/**
 * Email sent to the admins, when a fiat withdrawal to a bank account is requested.
 *
 * The withdrawal is as of yet not confirmed (pending state/pending post status).
 * Explains that an admin must go to the tools section to find the bank transfer that need to be performed.
 *
 * @var DSWallets\Transaction $tx The transaction that this email message is about.
 * @phan-file-suppress PhanUndeclaredVariable
 *
 * @author dashed-slug <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */
defined( 'ABSPATH' ) || die( -1 );

$payment_details = json_decode( $tx->address->label );
if ( $payment_details && isset( $payment_details->iban ) && isset( $payment_details->swiftBic ) ) {
	$addressing_method = 'iban';
} elseif ( $payment_details && isset( $payment_details->routingNumber )&& isset( $payment_details->accountNumber ) ) {
	$addressing_method = 'routing';
} elseif ( $payment_details && isset( $payment_details->ifsc ) && isset( $payment_details->indianAccNum ) ) {
	$addressing_method = 'ifsc';
}

$url = admin_url( 'tools.php?page=wallets-fiat-withdrawals' );

?>
<p>To all admins of <?php bloginfo(); ?>,</p>

<p>
<?php
	esc_html_e(
		sprintf(
			// translators: %1$s is replaced with the currency name. %2$s is replaced with the amount.
			__(
				'User %1$s has requested a %2$s withdrawal of %3$s to their bank account.',
				'wallets'
			),
			$tx->user->display_name,
			$tx->currency->name,
			$tx->get_amount_as_string( 'amount', false, true )
		)
	);
?>
</p>

<?php if ( $tx->nonce ): ?>
<p><?php esc_html_e( 'The withdrawal is now marked "pending", and the user must click on a confirmation link.', 'wallets' ); ?></p>
<p><?php esc_html_e( 'Afterwards, an admin must perform the bank withdrawal to fulfill the user\'s request. Please visit:', 'wallets' ); ?></p>

<?php else: ?>
<p><?php esc_html_e( 'The withdrawal is now marked "pending" and is ready to be processed.', 'wallets' ); ?></p>
<p><?php esc_html_e( 'An admin must now perform the bank withdrawal to fulfill the user\'s request. Please visit:', 'wallets' ); ?></p>

<?php endif; ?>

<p style="font-size:xx-large;"><a href="<?php esc_attr_e( $url ); ?>" target="_blank"><?php esc_html_e( $url ); ?></a></p>

<p><?php esc_html_e( 'When the bank transfer is submitted to the bank, the withdrawal must be marked as "done" by an admin, and a TXID must be assigned.', 'wallets' ); ?></p>

<p><?php esc_html_e( 'Transaction details follow:', 'wallets' ); ?></p>

<table>
	<tbody>
		<tr>
			<th style="align: right;"><?php esc_html_e( 'Currency:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->currency->name ); ?> (<?php esc_html_e( $tx->currency->symbol ); ?>)</td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Amount:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->get_amount_as_string( 'amount', true ) ); ?></td>
		</tr>

		<tr>
			<th style="align: right;">><?php esc_html_e( 'Fees to be paid by the user:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->get_amount_as_string( 'fee', true ) ); ?></td>
		</tr>

		<tr>
			<th style="align: right;">><?php esc_html_e( 'User\'s full name and address:', 'wallets' ); ?></th>
			<td><pre><?php esc_html_e( $tx->address ? $tx->address->address : __( 'undisclosed address', 'wallets' ) ); ?></pre></td>
		</tr>

		<tr>
			<th style="align: right;">><?php esc_html_e( 'Name and address of the user\'s bank:', 'wallets' ); ?></th>
			<td><pre><?php esc_html_e( $tx->address->extra ); ?></pre></td>
		</tr>

		<?php if ( 'iban' == $addressing_method ): ?>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'SWIFT/BIC:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->swiftBic ); ?></code></td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'IBAN:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->iban ); ?></code></td>
		</tr>

		<?php elseif ( 'routing' == $addressing_method ): ?>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Routing number:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->routingNumber ); ?></code></td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Account number:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->accountNumber ); ?></code></td>
		</tr>

		<?php elseif ( 'ifsc' == $addressing_method ): ?>

		<tr>
			<th style="align: right;"><?php
				echo apply_filters(
					'wallets_fiat_ui_text_ifsc',
					__(
						'<abbr title="Indian Financial System Code">IFSC</abbr>',
						'wallets'
					)
				);
			?>:</th>
			<td><code><?php esc_html_e( $payment_details->ifsc ); ?></code></td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Account number:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->indianAccNum ); ?></code></td>
		</tr>

		<?php endif; ?>

		<?php if ( $tx->comment ): ?>
		<tr>
			<th style="align: right;"><?php esc_html_e( 'Comment/Notes:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->comment ); ?></td>
		</tr>
		<?php endif; ?>

	</tbody>
</table>
